<template>
  <div class="audit-page p-4">
    <ElCard shadow="never" class="mb-4">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Audit Logs & Security Trail</h2>
          <p class="text-sm text-gray-500">Immutable chronological record of administrative actions, authentication events, and data changes.</p>
        </div>
        <div>
          <ElButton @click="loadLogs">Refresh</ElButton>
        </div>
      </div>
      <div class="mt-4 flex flex-wrap gap-3">
        <ElInput
          v-model="filters.action"
          placeholder="Filter by action (e.g. user.create, setting.update)"
          class="!w-72"
          clearable
          @clear="handleFilter"
          @keyup.enter="handleFilter"
        />
        <ElInput
          v-model="filters.auditable_type"
          placeholder="Filter by target type (e.g. User, Setting)"
          class="!w-64"
          clearable
          @clear="handleFilter"
          @keyup.enter="handleFilter"
        />
        <ElButton type="primary" @click="handleFilter">Filter</ElButton>
        <ElButton @click="resetFilters">Reset</ElButton>
      </div>
    </ElCard>

    <ElCard shadow="never">
      <ElTable :data="logList" v-loading="loading" stripe style="width: 100%">
        <ElTableColumn prop="id" label="ID" width="80" />
        <ElTableColumn prop="created_at" label="Timestamp" width="180">
          <template #default="{ row }">
            <span class="font-mono text-xs">{{ formatTimestamp(row.created_at) }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="action" label="Action" min-width="160">
          <template #default="{ row }">
            <ElTag size="small" :type="getActionTagType(row.action)">
              {{ row.action }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Performed By" min-width="180">
          <template #default="{ row }">
            <div v-if="row.user">
              <div class="font-medium text-xs text-gray-800">{{ row.user.name }}</div>
              <div class="text-xs text-gray-400">{{ row.user.email }}</div>
            </div>
            <span v-else class="text-xs text-gray-400 italic">System / Anonymous</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Target Entity" min-width="180">
          <template #default="{ row }">
            <span v-if="row.auditable_type" class="text-xs">
              <span class="font-semibold text-gray-700">{{ formatTargetType(row.auditable_type) }}</span>
              <span v-if="row.auditable_id" class="text-gray-400 font-mono ml-1">#{{ row.auditable_id }}</span>
            </span>
            <span v-else class="text-xs text-gray-400">—</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="ip_address" label="IP Address" width="140">
          <template #default="{ row }">
            <span class="font-mono text-xs text-gray-600">{{ row.ip_address || '—' }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Actions" width="120" fixed="right">
          <template #default="{ row }">
            <ElButton size="small" type="primary" link @click="inspectLog(row)">
              Inspect Diff
            </ElButton>
          </template>
        </ElTableColumn>
      </ElTable>

      <div class="mt-4 flex justify-end">
        <ElPagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[15, 30, 50, 100]"
          layout="total, sizes, prev, pager, next, jumper"
          @size-change="handleSizeChange"
          @current-change="handlePageChange"
        />
      </div>
    </ElCard>

    <!-- Inspect Log / Diff Dialog -->
    <ElDialog
      v-model="diffDialogVisible"
      :title="`Audit Log Entry #${selectedLog?.id || ''}`"
      width="780px"
      destroy-on-close
    >
      <div v-if="selectedLog" class="space-y-4">
        <ElDescriptions :column="2" border size="small">
          <ElDescriptionsItem label="Action">
            <ElTag size="small" :type="getActionTagType(selectedLog.action)">
              {{ selectedLog.action }}
            </ElTag>
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Timestamp">
            {{ formatTimestamp(selectedLog.created_at) }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Performed By">
            {{ selectedLog.user ? `${selectedLog.user.name} (${selectedLog.user.email})` : 'System' }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Target">
            {{ formatTargetType(selectedLog.auditable_type) }} {{ selectedLog.auditable_id ? `#${selectedLog.auditable_id}` : '' }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="IP Address">
            <span class="font-mono">{{ selectedLog.ip_address || '—' }}</span>
          </ElDescriptionsItem>
          <ElDescriptionsItem label="User Agent">
            <span class="text-xs text-gray-500 truncate block max-w-xs" :title="selectedLog.user_agent || ''">
              {{ selectedLog.user_agent || '—' }}
            </span>
          </ElDescriptionsItem>
        </ElDescriptions>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
          <div class="border rounded-md p-3 bg-gray-50">
            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Previous State (Old Values)</h4>
            <pre v-if="selectedLog.old_values && Object.keys(selectedLog.old_values).length > 0" class="text-xs font-mono bg-white p-2 rounded border max-h-64 overflow-auto">{{ JSON.stringify(selectedLog.old_values, null, 2) }}</pre>
            <div v-else class="text-xs text-gray-400 italic py-4 text-center">No previous values recorded</div>
          </div>

          <div class="border rounded-md p-3 bg-gray-50">
            <h4 class="text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Modified State (New Values)</h4>
            <pre v-if="selectedLog.new_values && Object.keys(selectedLog.new_values).length > 0" class="text-xs font-mono bg-white p-2 rounded border max-h-64 overflow-auto">{{ JSON.stringify(selectedLog.new_values, null, 2) }}</pre>
            <div v-else class="text-xs text-gray-400 italic py-4 text-center">No new values recorded</div>
          </div>
        </div>
      </div>

      <template #footer>
        <ElButton @click="diffDialogVisible = false">Close</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { fetchAuditLogs, AuditLogItem } from '@/api/audit'
import { ElMessage } from 'element-plus'

const loading = ref(false)
const logList = ref<AuditLogItem[]>([])
const selectedLog = ref<AuditLogItem | null>(null)
const diffDialogVisible = ref(false)

const filters = reactive({
  action: '',
  auditable_type: ''
})

const pagination = reactive({
  page: 1,
  per_page: 15,
  total: 0
})

const loadLogs = async () => {
  loading.value = true
  try {
    const res: any = await fetchAuditLogs({
      page: pagination.page,
      per_page: pagination.per_page,
      action: filters.action || undefined,
      auditable_type: filters.auditable_type || undefined
    })

    const payload =
      res && Array.isArray(res.data) &&
      ('current_page' in res || 'last_page' in res || 'meta' in res)
        ? res
        : res?.data || res
    if (payload && Array.isArray(payload.data)) {
      logList.value = payload.data
      pagination.total = payload.total ?? payload.meta?.total ?? 0
      pagination.page = payload.current_page ?? payload.meta?.current_page ?? 1
    } else if (Array.isArray(payload)) {
      logList.value = payload
      pagination.total = payload.length
    }
  } catch (error: any) {
    ElMessage.error(error.message || 'Failed to load audit logs')
  } finally {
    loading.value = false
  }
}

const handleFilter = () => {
  pagination.page = 1
  loadLogs()
}

const resetFilters = () => {
  filters.action = ''
  filters.auditable_type = ''
  pagination.page = 1
  loadLogs()
}

const handlePageChange = (page: number) => {
  pagination.page = page
  loadLogs()
}

const handleSizeChange = (size: number) => {
  pagination.per_page = size
  pagination.page = 1
  loadLogs()
}

const inspectLog = (log: AuditLogItem) => {
  selectedLog.value = log
  diffDialogVisible.value = true
}

const formatTimestamp = (ts: string) => {
  if (!ts) return '—'
  const date = new Date(ts)
  return isNaN(date.getTime()) ? ts : date.toLocaleString()
}

const formatTargetType = (type?: string | null) => {
  if (!type) return '—'
  const parts = type.split('\\')
  return parts[parts.length - 1] || type
}

const getActionTagType = (action: string): 'success' | 'warning' | 'danger' | 'info' => {
  const act = action.toLowerCase()
  if (act.includes('create') || act.includes('activate') || act.includes('login')) {
    return 'success'
  }
  if (act.includes('update') || act.includes('change')) {
    return 'warning'
  }
  if (act.includes('delete') || act.includes('suspend') || act.includes('revoke')) {
    return 'danger'
  }
  return 'info'
}

onMounted(() => {
  loadLogs()
})
</script>

<style scoped>
.audit-page {
  width: 100%;
}
</style>
