<template>
  <section class="art-card-sm mb-5 p-4 sm:p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <div>
        <h2 class="font-medium text-g-900">Connect directly to SQL Server</h2>
        <p class="mt-1 text-sm text-g-500"
          >Read legacy transactions from the database. No backup or export upload needed.</p
        >
      </div>
      <ElTag :type="driverAvailable ? 'success' : 'warning'" effect="plain">{{
        driverAvailable ? 'SQL Server driver ready' : 'SQL Server driver unavailable'
      }}</ElTag>
    </div>
    <ElAlert v-if="loadError" type="error" :closable="false" :title="loadError" class="mb-4" />
    <ElForm
      ref="connectionForm"
      :model="connection"
      :rules="rules"
      label-position="top"
      @submit.prevent="start"
    >
      <div class="grid grid-cols-1 gap-x-4 sm:grid-cols-2 lg:grid-cols-3">
        <ElFormItem label="Server hostname or IP" prop="host"
          ><ElInput
            v-model="connection.host"
            placeholder="sqlserver.company.local"
            autocomplete="off"
        /></ElFormItem>
        <ElFormItem label="TCP port" prop="port"
          ><ElInputNumber
            v-model="connection.port"
            :min="1"
            :max="65535"
            controls-position="right"
            class="!w-full"
        /></ElFormItem>
        <ElFormItem label="Database name" prop="database"
          ><ElInput v-model="connection.database" placeholder="billing_restore" autocomplete="off"
        /></ElFormItem>
        <ElFormItem label="SQL Server username" prop="username"
          ><ElInput
            v-model="connection.username"
            placeholder="Read-only SQL login"
            autocomplete="off"
        /></ElFormItem>
        <ElFormItem label="SQL Server password" prop="password"
          ><ElInput
            v-model="connection.password"
            type="password"
            show-password
            autocomplete="new-password"
        /></ElFormItem>
      </div>
      <p class="mb-3 text-xs leading-5 text-g-500"
        >Use a read-only SQL login. For SQLEXPRESS, enter the host and its configured TCP port
        separately. The app server must be able to reach it; in local Docker, host.docker.internal
        points to the Windows host. Repeat imports of the same database stay together automatically.
        The session is encrypted. SQL Express typically uses a self-signed certificate, which this
        importer accepts the same way the ZIP exporter's Trust server certificate option does. Do
        not expose SQL Server to the public internet.</p
      >
      <ElCheckbox v-model="connection.restored_database" class="!h-auto whitespace-normal"
        >This is a verified restored database (allow consistent read locks).</ElCheckbox
      >
      <p class="mt-1 text-xs text-g-500"
        >Leave unchecked for a live source: it must be read-only or already support snapshot
        isolation. The importer never enables database settings.</p
      >
      <div class="mt-4 flex flex-wrap gap-2">
        <ElButton
          :loading="testing"
          :disabled="starting || !driverAvailable"
          @click="testConnection"
          >Test connection</ElButton
        >
        <ElButton
          type="primary"
          native-type="submit"
          :loading="starting"
          :disabled="testing || !driverAvailable || busy"
          >Start read-only import</ElButton
        >
      </div>
    </ElForm>
    <ElAlert
      v-if="connectionResult"
      type="success"
      :closable="false"
      :title="connectionResult"
      class="mt-4"
    />
    <p class="mt-3 text-xs leading-5 text-g-500"
      >Credentials are encrypted temporarily for the background job, never returned or saved in
      browser storage, and cleared on completion, failure or cancellation. Unstarted credentials
      expire after one hour. Review the extracted transactions before adding them to searchable
      history.</p
    >
    <div v-if="reads.length" class="mt-5 border-t border-g-200 pt-4" aria-live="polite">
      <div class="mb-3 flex items-center justify-between"
        ><h3 class="text-sm font-medium text-g-900">Database reads</h3
        ><ElButton link type="primary" @click="refresh">Refresh</ElButton></div
      >
      <div v-for="read in reads" :key="read.id" class="mb-3 rounded-lg border border-g-200 p-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
          <div
            ><span class="font-medium text-g-800">{{ read.source_key }}</span
            ><span class="ml-2 text-xs text-g-500">{{
              formatDateTimeManila(read.created_at)
            }}</span></div
          >
          <ElTag
            :type="
              read.status === 'FAILED' ? 'danger' : read.status === 'EXTRACTED' ? 'success' : 'info'
            "
            size="small"
            >{{ statusLabel(read.status) }}</ElTag
          >
        </div>
        <div class="mt-2 flex flex-wrap items-center justify-between gap-2">
          <span class="text-xs text-g-500"
            >{{ read.read_rows.toLocaleString()
            }}{{
              read.expected_rows !== null ? ` / ${read.expected_rows.toLocaleString()}` : ''
            }}
            records read</span
          >
          <ElButton
            v-if="read.batch_id"
            link
            type="primary"
            :disabled="busy"
            @click="emit('ready', read.batch_id)"
            >Open preview</ElButton
          >
          <ElButton
            v-else-if="read.status === 'QUEUED' || read.status === 'READING'"
            link
            type="danger"
            :loading="cancelling === read.id"
            @click="cancel(read)"
            >Cancel read</ElButton
          >
        </div>
        <p v-if="read.status === 'QUEUED'" class="mt-2 text-xs text-g-500"
          >Waiting for the dedicated import worker. You may leave this page.</p
        >
        <p v-if="read.error_message" class="mt-2 text-sm text-error">{{ read.error_message }}</p>
      </div>
    </div>
  </section>
</template>

<script setup lang="ts">
  import { onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
  import { type FormInstance, type FormRules, ElMessage } from 'element-plus'
  import {
    cancelLegacySqlRead,
    fetchLegacySourceReads,
    startLegacySqlRead,
    testLegacySqlConnection,
    type LegacySourceRead,
    type LegacySqlConnection
  } from '@/api/legacyImports'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'

  defineProps<{ busy: boolean }>()
  const emit = defineEmits<{ ready: [batchId: number] }>()
  const connection = reactive<LegacySqlConnection>({
    host: '',
    port: 1433,
    database: '',
    username: '',
    password: '',
    restored_database: false
  })
  const connectionForm = ref<FormInstance>()
  const rules: FormRules<LegacySqlConnection> = {
    host: [
      { required: true, message: 'Enter the SQL Server host.', trigger: 'blur' },
      {
        pattern: /^[a-zA-Z0-9][a-zA-Z0-9.-]*$/,
        message: 'Use a hostname or IPv4 address; enter the port separately.',
        trigger: 'blur'
      }
    ],
    port: [{ required: true, message: 'Enter the SQL Server TCP port.', trigger: 'blur' }],
    database: [{ required: true, message: 'Enter the legacy database name.', trigger: 'blur' }],
    username: [{ required: true, message: 'Enter a SQL login.', trigger: 'blur' }],
    password: [{ required: true, message: 'Enter the SQL login password.', trigger: 'blur' }]
  }
  const driverAvailable = ref(false)
  const loadError = ref('')
  const reads = ref<LegacySourceRead[]>([])
  const testing = ref(false)
  const starting = ref(false)
  const cancelling = ref<number>()
  const connectionResult = ref('')
  let requestKey = crypto.randomUUID()
  let trackedId: number | undefined
  let timer: ReturnType<typeof setTimeout> | undefined
  let disposed = false
  let refreshing = false
  watch(connection, () => {
    connectionResult.value = ''
  })
  function statusLabel(status: string) {
    return (
      (
        {
          QUEUED: 'Queued',
          READING: 'Reading SQL Server',
          EXTRACTED: 'Ready to preview',
          FAILED: 'Read failed',
          CANCELLED: 'Cancelled'
        } as Record<string, string>
      )[status] ?? status
    )
  }
  async function refresh() {
    if (refreshing || disposed) return
    refreshing = true
    if (timer) clearTimeout(timer)
    try {
      const result = await fetchLegacySourceReads()
      driverAvailable.value = result.driver_available
      reads.value = result.reads
      loadError.value = ''
      const completed = result.reads.find(
        (item) => item.id === trackedId && item.status === 'EXTRACTED' && item.batch_id
      )
      if (completed?.batch_id) {
        trackedId = undefined
        emit('ready', completed.batch_id)
      }
    } catch {
      loadError.value = 'Could not load database reads. Refresh to try again.'
    } finally {
      refreshing = false
      if (
        !disposed &&
        reads.value.some((item) => item.status === 'QUEUED' || item.status === 'READING')
      )
        timer = setTimeout(refresh, 3000)
    }
  }
  async function testConnection() {
    if (!(await connectionForm.value?.validate().catch(() => false))) return
    testing.value = true
    try {
      const result = await testLegacySqlConnection({ ...connection })
      connectionResult.value = result.message
    } finally {
      testing.value = false
    }
  }
  async function start() {
    if (!(await connectionForm.value?.validate().catch(() => false))) return
    starting.value = true
    try {
      const result = await startLegacySqlRead({ ...connection }, requestKey)
      trackedId = result.id
      requestKey = crypto.randomUUID()
      connection.password = ''
      ElMessage.success(
        'Read-only import queued. Review the extracted records before finalizing history.'
      )
      await refresh()
    } finally {
      starting.value = false
    }
  }
  async function cancel(read: LegacySourceRead) {
    cancelling.value = read.id
    try {
      await cancelLegacySqlRead(read.id)
      await refresh()
    } finally {
      cancelling.value = undefined
    }
  }
  onMounted(refresh)
  onBeforeUnmount(() => {
    disposed = true
    connection.password = ''
    if (timer) clearTimeout(timer)
  })
</script>

<style scoped>
  :deep(.el-checkbox__label) {
    white-space: normal;
  }
</style>
