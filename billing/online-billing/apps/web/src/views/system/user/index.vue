<template>
  <div class="user-page p-4">
    <ElCard shadow="never" class="mb-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">User Administration</h2>
          <p class="text-sm text-gray-500">Manage user accounts, location assignments, status, and role-based permissions.</p>
        </div>
        <div class="space-x-2">
          <ElButton type="primary" @click="openCreateDialog">
            Add User
          </ElButton>
          <ElButton @click="loadUsers">
            Refresh
          </ElButton>
        </div>
      </div>
      <div class="mt-4 flex flex-wrap gap-3">
        <ElInput
          v-model="searchQuery"
          placeholder="Search by name or email..."
          class="!w-72"
          clearable
          @clear="loadUsers"
          @keyup.enter="loadUsers"
        />
        <ElSelect v-model="statusFilter" placeholder="Status" clearable class="!w-40" @change="loadUsers">
          <ElOption label="Active" value="active" />
          <ElOption label="Suspended" value="suspended" />
          <ElOption label="Pending" value="pending_activation" />
        </ElSelect>
        <ElButton @click="loadUsers">Filter</ElButton>
      </div>
    </ElCard>

    <ElCard shadow="never">
      <ElTable :data="userList" v-loading="loading" stripe style="width: 100%">
        <ElTableColumn prop="id" label="ID" width="70" />
        <ElTableColumn prop="name" label="Full Name" min-width="150" />
        <ElTableColumn prop="email" label="Email Address" min-width="180" />
        <ElTableColumn prop="phone" label="Phone" width="140">
          <template #default="{ row }">
            {{ row.phone || '—' }}
          </template>
        </ElTableColumn>
        <ElTableColumn label="Roles" min-width="160">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <ElTag
                v-for="role in (row.roles || [])"
                :key="typeof role === 'string' ? role : role.id"
                size="small"
                type="info"
              >
                {{ typeof role === 'string' ? role : role.name }}
              </ElTag>
            </div>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="status" label="Status" width="120">
          <template #default="{ row }">
            <ElTag :type="row.status === 'active' ? 'success' : (row.status === 'suspended' ? 'danger' : 'warning')">
              {{ row.status }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="lock_version" label="Ver." width="70" align="center" />
        <ElTableColumn label="Actions" width="280" fixed="right">
          <template #default="{ row }">
            <ElButton size="small" @click="openEditDialog(row)">Edit</ElButton>
            <ElButton
              v-if="row.status === 'active'"
              size="small"
              type="warning"
              @click="handleSuspend(row)"
            >
              Suspend
            </ElButton>
            <ElButton
              v-else
              size="small"
              type="success"
              @click="handleActivate(row)"
            >
              Activate
            </ElButton>
            <ElButton
              size="small"
              type="danger"
              plain
              @click="handleRevokeSessions(row)"
            >
              Revoke Sessions
            </ElButton>
          </template>
        </ElTableColumn>
      </ElTable>

      <div class="mt-4 flex justify-end">
        <ElPagination
          background
          layout="total, prev, pager, next"
          :total="totalUsers"
          :page-size="pageSize"
          :current-page="currentPage"
          @current-change="handlePageChange"
        />
      </div>
    </ElCard>

    <!-- Create / Edit Dialog -->
    <ElDialog
      v-model="dialogVisible"
      :title="isEditing ? 'Edit User Account' : 'Create New User Account'"
      width="500px"
      destroy-on-close
    >
      <ElForm ref="formRef" :model="form" :rules="rules" label-position="top">
        <ElFormItem label="Full Name" prop="name">
          <ElInput v-model="form.name" placeholder="e.g. Maria Santos" />
        </ElFormItem>
        <ElFormItem label="Email Address" prop="email">
          <ElInput v-model="form.email" placeholder="user@scipsi.com" />
        </ElFormItem>
        <ElFormItem label="Phone" prop="phone">
          <ElInput v-model="form.phone" placeholder="+639170000000" />
        </ElFormItem>
        <ElFormItem :label="isEditing ? 'New Password (leave blank to keep current)' : 'Password'" prop="password">
          <ElInput v-model="form.password" type="password" show-password placeholder="Minimum 8 characters" />
        </ElFormItem>
        <ElFormItem label="Assigned Roles" prop="role_ids">
          <ElSelect v-model="form.role_ids" multiple placeholder="Select roles" class="w-full">
            <ElOption
              v-for="role in availableRoles"
              :key="role.id"
              :label="role.label || role.name"
              :value="role.id"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem v-if="isEditing" label="Current Optimistic Version">
          <ElInput :model-value="`v${form.lock_version}`" disabled />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <span class="dialog-footer">
          <ElButton @click="dialogVisible = false">Cancel</ElButton>
          <ElButton type="primary" :loading="saving" @click="handleSave">
            {{ isEditing ? 'Save Changes' : 'Create User' }}
          </ElButton>
        </span>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import {
  fetchUserList,
  fetchCreateUser,
  fetchUpdateUser,
  fetchSuspendUser,
  fetchActivateUser,
  UserItem
} from '@/api/users'
import { fetchRoleList, RoleItem } from '@/api/roles'
import { fetchRevokeSessions } from '@/api/auth'
import { ElMessage, ElMessageBox, FormInstance, FormRules } from 'element-plus'

defineOptions({ name: 'UserAdministration' })

const userList = ref<UserItem[]>([])
const loading = ref(false)
const saving = ref(false)
const totalUsers = ref(0)
const currentPage = ref(1)
const pageSize = ref(25)
const searchQuery = ref('')
const statusFilter = ref('')
const availableRoles = ref<RoleItem[]>([])

const dialogVisible = ref(false)
const isEditing = ref(false)
const selectedUserId = ref<number | null>(null)
const formRef = ref<FormInstance>()

const form = reactive({
  name: '',
  email: '',
  phone: '',
  password: '',
  role_ids: [] as number[],
  lock_version: 1,
})

const rules: FormRules = {
  name: [{ required: true, message: 'Full name is required', trigger: 'blur' }],
  email: [
    { required: true, message: 'Email address is required', trigger: 'blur' },
    { type: 'email', message: 'Please enter a valid email', trigger: 'blur' }
  ],
  password: [
    {
      validator: (_rule, value, callback) => {
        if (!isEditing.value && (!value || value.length < 8)) {
          callback(new Error('Password must be at least 8 characters'))
        } else if (value && value.length < 8) {
          callback(new Error('Password must be at least 8 characters'))
        } else {
          callback()
        }
      },
      trigger: 'blur'
    }
  ]
}

const loadUsers = async () => {
  loading.value = true
  try {
    const res: any = await fetchUserList({
      page: currentPage.value,
      per_page: pageSize.value,
      search: searchQuery.value || undefined,
      status: statusFilter.value || undefined
    })
    const data = Array.isArray(res)
      ? { data: res, total: res.length }
      : res?.data && !Array.isArray(res.data)
        ? res.data
        : res
    userList.value = Array.isArray(data?.data) ? data.data : []
    totalUsers.value = data?.total ?? data?.meta?.total ?? userList.value.length
  } catch (err: any) {
    ElMessage.error(err.message || 'Failed to load users')
  } finally {
    loading.value = false
  }
}

const loadRoles = async () => {
  try {
    const res: any = await fetchRoleList()
    availableRoles.value = (res?.data || res) || []
  } catch (err: any) {
    console.warn('Failed to load roles list:', err)
  }
}

const handlePageChange = (page: number) => {
  currentPage.value = page
  loadUsers()
}

const openCreateDialog = () => {
  isEditing.value = false
  selectedUserId.value = null
  form.name = ''
  form.email = ''
  form.phone = ''
  form.password = ''
  form.role_ids = []
  form.lock_version = 1
  dialogVisible.value = true
}

const openEditDialog = (user: UserItem) => {
  isEditing.value = true
  selectedUserId.value = user.id
  form.name = user.name
  form.email = user.email
  form.phone = user.phone || ''
  form.password = ''
  form.lock_version = user.lock_version
  form.role_ids = (user.roles || []).map((r) => (typeof r === 'string' ? (availableRoles.value.find(ar => ar.name === r)?.id || 0) : r.id)).filter(id => id > 0)
  dialogVisible.value = true
}

const handleSave = async () => {
  if (!formRef.value) return
  await formRef.value.validate(async (valid) => {
    if (!valid) return
    saving.value = true
    try {
      if (isEditing.value && selectedUserId.value) {
        await fetchUpdateUser(selectedUserId.value, {
          name: form.name,
          email: form.email,
          phone: form.phone || undefined,
          password: form.password || undefined,
          role_ids: form.role_ids,
          lock_version: form.lock_version
        })
        ElMessage.success('User updated successfully')
      } else {
        await fetchCreateUser({
          name: form.name,
          email: form.email,
          phone: form.phone || undefined,
          password: form.password,
          role_ids: form.role_ids,
          status: 'active'
        })
        ElMessage.success('User created successfully')
      }
      dialogVisible.value = false
      loadUsers()
    } catch (err: any) {
      ElMessage.error(err.message || 'Operation failed')
    } finally {
      saving.value = false
    }
  })
}

const handleSuspend = (user: UserItem) => {
  ElMessageBox.confirm(
    `Are you sure you want to suspend user ${user.name}? Their active sessions and tokens will be revoked immediately.`,
    'Suspend User',
    { confirmButtonText: 'Suspend', cancelButtonText: 'Cancel', type: 'warning' }
  ).then(async () => {
    try {
      await fetchSuspendUser(user.id, user.lock_version)
      ElMessage.success('User suspended and sessions revoked')
      loadUsers()
    } catch (err: any) {
      ElMessage.error(err.message || 'Suspend failed')
    }
  })
}

const handleActivate = async (user: UserItem) => {
  try {
    await fetchActivateUser(user.id, user.lock_version)
    ElMessage.success('User activated successfully')
    loadUsers()
  } catch (err: any) {
    ElMessage.error(err.message || 'Activation failed')
  }
}

const handleRevokeSessions = (user: UserItem) => {
  ElMessageBox.confirm(
    `Revoke all active sessions and API tokens for ${user.name}?`,
    'Revoke Sessions',
    { confirmButtonText: 'Revoke', cancelButtonText: 'Cancel', type: 'warning' }
  ).then(async () => {
    try {
      await fetchRevokeSessions(user.id)
      ElMessage.success('Active sessions revoked')
    } catch (err: any) {
      ElMessage.error(err.message || 'Revocation failed')
    }
  })
}

onMounted(() => {
  loadUsers()
  loadRoles()
})
</script>
