<template>
  <div class="page-content space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:bank-card-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">VIP Credit Accounts</h1>
          <p class="mt-1 max-w-2xl text-sm text-g-500">
            Staff view of configured VIP credit accounts, latest profile status, and exposure
            summary. Profile changes are published from Customer Accounts.
          </p>
        </div>
      </div>
      <ElButton :loading="loading" @click="load">
        <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
        Refresh
      </ElButton>
    </header>

    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="Hold or disable blocks new charges only. Existing credit debt remains repayable."
    />

    <section v-loading="loading" class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>Credit accounts</h4>
          <p>Select a row to open the staff summary</p>
        </div>
      </div>
      <ElEmpty
        v-if="accounts.length === 0"
        description="No VIP credit accounts are configured yet."
      />
      <ElTable v-else :data="accounts" highlight-current-row @current-change="selectAccount">
        <ElTableColumn label="Customer" min-width="220">
          <template #default="{ row }">
            {{ row.customer?.name || '—' }}
            <span class="block text-xs text-g-500">{{ row.customer?.account_number || '—' }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Profile status" width="140">
          <template #default="{ row }">
            <ElTag size="small" :type="statusTag(latestVersion(row)?.status)">
              {{ latestVersion(row)?.status || '—' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Version" width="100">
          <template #default="{ row }">
            {{
              latestVersion(row)?.version_number ? `v${latestVersion(row)?.version_number}` : '—'
            }}
          </template>
        </ElTableColumn>
        <ElTableColumn label="Effective from" min-width="170">
          <template #default="{ row }">
            {{ formatDateTimeManila(latestVersion(row)?.effective_from) }}
          </template>
        </ElTableColumn>
      </ElTable>
      <div v-if="pagination.total > pagination.per_page" class="mt-4 flex justify-end">
        <ElPagination
          v-model:current-page="pagination.page"
          :page-size="pagination.per_page"
          :total="pagination.total"
          layout="total, prev, pager, next"
          @current-change="load"
        />
      </div>
    </section>

    <section v-if="summary" v-loading="summaryLoading" class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>{{ summary.account?.customer_name || 'Credit account' }} — summary</h4>
          <p>
            Profile
            {{ summary.profile?.status || '—' }}
            <template v-if="summary.profile?.version"> · v{{ summary.profile.version }} </template>
          </p>
        </div>
      </div>

      <ElAlert
        v-if="!summary.eligible && summary.reason"
        class="mb-4"
        type="warning"
        :closable="false"
        show-icon
        :title="summary.reason"
      />

      <div class="mb-5 grid grid-cols-2 gap-3 md:grid-cols-4">
        <div class="art-card-xs p-3">
          <p class="text-xs text-g-500">Exposure</p>
          <p class="mt-1 font-medium text-g-900">
            {{ money(summary.exposure_amount, summary.terms?.currency) }}
          </p>
        </div>
        <div class="art-card-xs p-3">
          <p class="text-xs text-g-500">Overdue</p>
          <p class="mt-1 font-medium text-g-900">
            {{ money(summary.overdue_amount, summary.terms?.currency) }}
          </p>
        </div>
        <div class="art-card-xs p-3">
          <p class="text-xs text-g-500">Available credit</p>
          <p class="mt-1 font-medium text-g-900">
            {{
              summary.available_credit_amount == null
                ? 'Unlimited / n/a'
                : money(summary.available_credit_amount, summary.terms?.currency)
            }}
          </p>
        </div>
        <div class="art-card-xs p-3">
          <p class="text-xs text-g-500">Terms</p>
          <p class="mt-1 font-medium text-g-900">
            {{ summary.terms?.payment_terms_days ?? '—' }} days
          </p>
        </div>
      </div>

      <ElTable :data="summary.charges || []" size="small" empty-text="No open credit charges.">
        <ElTableColumn prop="invoice_number" label="Invoice" min-width="140" />
        <ElTableColumn label="Due date" min-width="140">
          <template #default="{ row }">{{ row.due_date || 'Needs terms review' }}</template>
        </ElTableColumn>
        <ElTableColumn label="Charged" align="right" min-width="120">
          <template #default="{ row }">{{ money(row.charged_amount, row.currency) }}</template>
        </ElTableColumn>
        <ElTableColumn label="Outstanding" align="right" min-width="130">
          <template #default="{ row }">{{ money(row.outstanding_amount, row.currency) }}</template>
        </ElTableColumn>
        <ElTableColumn label="Overdue" width="100">
          <template #default="{ row }">
            <ElTag v-if="row.is_overdue" size="small" type="danger">Yes</ElTag>
            <span v-else class="text-xs text-g-500">No</span>
          </template>
        </ElTableColumn>
      </ElTable>
    </section>
  </div>
</template>

<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import {
    fetchAdminCreditAccounts,
    fetchStaffCreditAccountSummary,
    type AdminCreditAccount,
    type AdminCreditAccountVersion,
    type VipCreditSummary
  } from '@/api/vipCredit'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'

  defineOptions({ name: 'VipCreditAccounts' })

  const loading = ref(false)
  const summaryLoading = ref(false)
  const accounts = ref<AdminCreditAccount[]>([])
  const summary = ref<VipCreditSummary | null>(null)
  const selectedId = ref<number | null>(null)
  const pagination = reactive({
    page: 1,
    per_page: 25,
    total: 0
  })

  const latestVersion = (row: AdminCreditAccount): AdminCreditAccountVersion | undefined =>
    row.versions?.[0]

  const statusTag = (status?: string) => {
    if (status === 'ACTIVE') return 'success'
    if (status === 'HELD') return 'warning'
    if (status === 'DISABLED') return 'danger'
    return 'info'
  }

  const money = (amount?: string | null, currency = 'PHP') =>
    amount === null || amount === undefined
      ? '—'
      : new Intl.NumberFormat('en-PH', {
          style: 'currency',
          currency: currency || 'PHP'
        }).format(Number(amount))

  async function load() {
    loading.value = true
    try {
      const res: any = await fetchAdminCreditAccounts(pagination.page)
      accounts.value = Array.isArray(res?.data) ? res.data : Array.isArray(res) ? res : []
      pagination.total = Number(res?.total || accounts.value.length)
      pagination.per_page = Number(res?.per_page || 25)
      if (selectedId.value && !accounts.value.some((row) => row.id === selectedId.value)) {
        summary.value = null
        selectedId.value = null
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load VIP credit accounts.')
    } finally {
      loading.value = false
    }
  }

  async function selectAccount(row: AdminCreditAccount | null) {
    if (!row?.id) {
      summary.value = null
      selectedId.value = null
      return
    }
    selectedId.value = row.id
    summaryLoading.value = true
    try {
      summary.value = await fetchStaffCreditAccountSummary(row.id)
    } catch (error: any) {
      summary.value = null
      ElMessage.error(error?.message || 'Unable to load credit account summary.')
    } finally {
      summaryLoading.value = false
    }
  }

  onMounted(load)
</script>
