<template>
  <div class="customer-accounts-page p-4">
    <!-- Header Card -->
    <ElCard shadow="never" class="mb-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-800 dark:text-gray-100">
              Customer Accounts & Buyer Profiles
            </h2>
            <ElTag type="success" size="small" effect="plain">Decision W32</ElTag>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Administer customer business accounts, explicit portal user links, verified contact channels, and reviewed buyer profile versions.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <ElButton @click="fetchCustomers" :loading="loading">Refresh</ElButton>
        </div>
      </div>

      <ElAlert
        title="W32 Identity & Authority Safeguard: Matching company name or phone never auto-links an account. Mobile OTP proves possession only, not company authority. Buyer profile edits affect future billing drafts only and never rewrite issued fiscal artifacts."
        type="info"
        :closable="false"
        show-icon
        class="mt-3"
      />
    </ElCard>

    <!-- Filters & Table Card -->
    <ElCard shadow="never">
      <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <div class="flex flex-wrap items-center gap-3">
          <ElInput
            v-model="filters.search"
            placeholder="Search account # or company name..."
            clearable
            class="w-64"
            @keyup.enter="fetchCustomers"
            @clear="fetchCustomers"
          >
            <template #prefix>
              <span class="text-gray-400">🔍</span>
            </template>
          </ElInput>

          <ElSelect
            v-model="filters.status"
            placeholder="Status"
            clearable
            class="w-36"
            @change="fetchCustomers"
          >
            <ElOption label="All Statuses" value="" />
            <ElOption label="Active" value="active" />
            <ElOption label="Pending" value="pending" />
            <ElOption label="Suspended" value="suspended" />
          </ElSelect>

          <ElSelect
            v-model="filters.customer_type"
            placeholder="Type"
            clearable
            class="w-36"
            @change="fetchCustomers"
          >
            <ElOption label="All Types" value="" />
            <ElOption label="Business" value="business" />
            <ElOption label="Walk-in" value="walk_in" />
          </ElSelect>

          <ElButton type="primary" @click="fetchCustomers">Filter</ElButton>
        </div>

        <div class="text-xs text-gray-500">
          Showing {{ customers.length }} of {{ pagination.total }} customer accounts
        </div>
      </div>

      <!-- Customers Table -->
      <ElTable :data="customers" v-loading="loading" stripe style="width: 100%">
        <ElTableColumn label="Account Number" prop="account_number" width="180">
          <template #default="{ row }">
            <span class="font-mono font-medium text-blue-600 dark:text-blue-400">
              {{ row.account_number }}
            </span>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Company / Buyer Name" prop="name" min-width="220">
          <template #default="{ row }">
            <div class="font-medium text-gray-900 dark:text-gray-100">{{ row.name }}</div>
            <div class="text-xs text-gray-400">
              Active Profile v{{ row.buyer_profile?.current_version || 1 }}
            </div>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Type" prop="customer_type" width="120">
          <template #default="{ row }">
            <ElTag size="small" :type="row.customer_type === 'business' ? 'primary' : 'info'">
              {{ row.customer_type }}
            </ElTag>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Status" prop="status" width="120">
          <template #default="{ row }">
            <ElTag
              size="small"
              :type="row.status === 'active' ? 'success' : row.status === 'pending' ? 'warning' : 'danger'"
            >
              {{ row.status }}
            </ElTag>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Linked Users" width="130" align="center">
          <template #default="{ row }">
            <span class="font-medium">{{ row.links?.length || 0 }}</span>
            <span class="text-xs text-gray-400 ml-1">user(s)</span>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Verified Contacts" width="150" align="center">
          <template #default="{ row }">
            <ElTag
              size="small"
              :type="getVerifiedCount(row) > 0 ? 'success' : 'info'"
              effect="plain"
            >
              {{ getVerifiedCount(row) }} / {{ row.contact_points?.length || 0 }} Verified
            </ElTag>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Created" width="160">
          <template #default="{ row }">
            <span class="text-xs text-gray-500">{{ formatDate(row.created_at) }}</span>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Actions" width="200" fixed="right">
          <template #default="{ row }">
            <div class="flex items-center gap-2">
              <ElButton size="small" type="primary" link @click="openDetail(row)">
                View Detail
              </ElButton>
              <ElButton size="small" link @click="promptStatusChange(row)">
                Status
              </ElButton>
            </div>
          </template>
        </ElTableColumn>
      </ElTable>

      <!-- Pagination -->
      <div class="flex justify-end mt-4">
        <ElPagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="fetchCustomers"
          @current-change="fetchCustomers"
        />
      </div>
    </ElCard>

    <!-- Detail Drawer -->
    <ElDrawer
      v-model="drawerVisible"
      title="Customer Business Account Details"
      size="650px"
      direction="rtl"
    >
      <div v-if="selectedCustomer" class="space-y-6">
        <!-- Summary Box -->
        <div class="bg-gray-50 dark:bg-gray-800/50 p-4 rounded-xl border border-gray-200 dark:border-gray-700">
          <div class="flex justify-between items-start">
            <div>
              <div class="text-xs uppercase font-semibold text-gray-400">Account Number</div>
              <div class="font-mono font-bold text-lg text-blue-600 dark:text-blue-400">
                {{ selectedCustomer.account_number }}
              </div>
              <div class="text-base font-semibold text-gray-900 dark:text-gray-100 mt-1">
                {{ selectedCustomer.name }}
              </div>
            </div>
            <ElTag
              :type="selectedCustomer.status === 'active' ? 'success' : 'warning'"
              size="small"
            >
              {{ selectedCustomer.status }}
            </ElTag>
          </div>
        </div>

        <!-- Section 1: Explicit Customer-User Links -->
        <div>
          <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2 flex items-center justify-between">
            <span>Explicit Portal User Links</span>
            <ElTag size="small" effect="plain">{{ selectedCustomer.links?.length || 0 }} Associated</ElTag>
          </h4>
          <ElTable :data="selectedCustomer.links || []" size="small" border>
            <ElTableColumn label="User Name" prop="user.name" min-width="150" />
            <ElTableColumn label="Email" prop="user.email" min-width="160" />
            <ElTableColumn label="Authority Role" prop="authority_role" width="130">
              <template #default="{ row }">
                <ElTag size="small" type="info">{{ row.authority_role }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Link Active" width="100" align="center">
              <template #default="{ row }">
                <ElTag size="small" :type="row.is_active ? 'success' : 'danger'">
                  {{ row.is_active ? 'Active' : 'Inactive' }}
                </ElTag>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>

        <!-- Section 2: Contact Points & Verification Status -->
        <div>
          <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2">
            Verified Communication Channels
          </h4>
          <ElTable :data="selectedCustomer.contact_points || []" size="small" border>
            <ElTableColumn label="Channel" prop="type" width="90">
              <template #default="{ row }">
                <ElTag size="small" :type="row.type === 'mobile' ? 'success' : 'primary'">
                  {{ row.type }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Value / Number" prop="value" min-width="150" />
            <ElTableColumn label="Verification" width="120" align="center">
              <template #default="{ row }">
                <ElTag size="small" :type="row.is_verified ? 'success' : 'warning'">
                  {{ row.is_verified ? 'Verified' : 'Unverified' }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Verified At" width="140">
              <template #default="{ row }">
                <span class="text-xs text-gray-500">{{ formatDate(row.verified_at) }}</span>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>

        <!-- Section 3: Buyer Profile Versions & Review -->
        <div>
          <h4 class="text-sm font-bold text-gray-800 dark:text-gray-200 mb-2 flex items-center justify-between">
            <span>Buyer Profile Version History</span>
            <span class="text-xs text-gray-400">Current: v{{ selectedCustomer.buyer_profile?.current_version || 1 }}</span>
          </h4>
          <ElTable :data="selectedCustomer.buyer_profile?.versions || []" size="small" border>
            <ElTableColumn label="Ver" prop="version" width="60" align="center">
              <template #default="{ row }">
                <span class="font-bold">v{{ row.version }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Registered Name" prop="registered_name" min-width="160" />
            <ElTableColumn label="TIN" prop="tin" width="130">
              <template #default="{ row }">
                <span class="font-mono text-xs">{{ row.tin || 'Unset' }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Status" prop="status" width="110" align="center">
              <template #default="{ row }">
                <ElTag
                  size="small"
                  :type="row.status === 'active' ? 'success' : row.status === 'pending_review' ? 'warning' : 'info'"
                >
                  {{ row.status }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Action" width="120" fixed="right">
              <template #default="{ row }">
                <div v-if="row.status === 'pending_review'" class="flex gap-1">
                  <ElButton
                    size="small"
                    type="success"
                    link
                    @click="handleReviewVersion(row, 'approve')"
                  >
                    Approve
                  </ElButton>
                  <ElButton
                    size="small"
                    type="danger"
                    link
                    @click="handleReviewVersion(row, 'reject')"
                  >
                    Reject
                  </ElButton>
                </div>
                <span v-else class="text-xs text-gray-400">Immutable</span>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, onMounted } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    fetchAdminCustomers,
    fetchAdminCustomerDetail,
    updateAdminCustomerStatus,
    reviewAdminBuyerProfile
  } from '@/api/registration'

  defineOptions({ name: 'CustomerAccounts' })

  const loading = ref(false)
  const customers = ref<any[]>([])
  const selectedCustomer = ref<any>(null)
  const drawerVisible = ref(false)

  const filters = reactive({
    search: '',
    status: '',
    customer_type: ''
  })

  const pagination = reactive({
    page: 1,
    per_page: 20,
    total: 0
  })

  const getVerifiedCount = (customer: any) => {
    return (customer.contact_points || []).filter((c: any) => c.is_verified).length
  }

  const formatDate = (dateStr: string | null) => {
    if (!dateStr) return '-'
    return new Date(dateStr).toLocaleString()
  }

  const fetchCustomers = async () => {
    try {
      loading.value = true
      const res: any = await fetchAdminCustomers({
        page: pagination.page,
        per_page: pagination.per_page,
        search: filters.search,
        status: filters.status,
        customer_type: filters.customer_type
      })

      const raw = res?.data || res
      customers.value = Array.isArray(raw) ? raw : (raw?.data || [])
      pagination.total = res?.meta?.total || raw?.total || customers.value.length
      loading.value = false
    } catch (error: any) {
      loading.value = false
      ElMessage.error(error?.message || 'Failed to fetch customer accounts.')
    }
  }

  const openDetail = async (row: any) => {
    try {
      const res: any = await fetchAdminCustomerDetail(row.id)
      selectedCustomer.value = res?.customer || res?.data?.customer || res || row
      drawerVisible.value = true
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load customer details.')
    }
  }

  const promptStatusChange = (customer: any) => {
    ElMessageBox.prompt('Enter new status (active, pending, suspended, archived):', 'Update Customer Status', {
      confirmButtonText: 'Update',
      cancelButtonText: 'Cancel',
      inputValue: customer.status,
      inputPattern: /^(active|pending|suspended|archived)$/,
      inputErrorMessage: 'Status must be active, pending, suspended, or archived'
    }).then(async ({ value }) => {
      try {
        await updateAdminCustomerStatus(customer.id, value, 'Status updated via admin console')
        ElMessage.success(`Status updated to ${value}`)
        fetchCustomers()
        if (selectedCustomer.value?.id === customer.id) {
          selectedCustomer.value.status = value
        }
      } catch (error: any) {
        ElMessage.error(error?.message || 'Failed to update status.')
      }
    }).catch(() => {})
  }

  const handleReviewVersion = (version: any, action: 'approve' | 'reject') => {
    ElMessageBox.prompt(`Provide notes for ${action} action:`, `${action === 'approve' ? 'Approve' : 'Reject'} Version ${version.version}`, {
      confirmButtonText: action === 'approve' ? 'Approve Version' : 'Reject Version',
      cancelButtonText: 'Cancel',
      inputPlaceholder: 'e.g. Verified with BIR Certificate of Registration'
    }).then(async ({ value }) => {
      try {
        await reviewAdminBuyerProfile(selectedCustomer.value.id, version.id, action, value)
        ElMessage.success(`Buyer profile version ${version.version} ${action}d.`)
        openDetail(selectedCustomer.value)
        fetchCustomers()
      } catch (error: any) {
        ElMessage.error(error?.message || `Failed to ${action} version.`)
      }
    }).catch(() => {})
  }

  onMounted(() => {
    fetchCustomers()
  })
</script>

<style scoped>
  .customer-accounts-page {
    width: 100%;
  }
</style>
