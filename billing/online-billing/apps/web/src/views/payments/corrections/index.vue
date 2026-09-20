<template>
  <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <section
      class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 p-6 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl font-bold tracking-tight sm:text-2xl">Document Corrections</h1>
          <ElTag type="warning" effect="dark" size="small">Controlled review</ElTag>
        </div>
        <p class="mt-1 max-w-2xl text-sm text-slate-300">
          Submit a posted document for independent review. Approval records a protected decision
          only; it does not edit, replace, void, reverse, or reprint a financial document.
        </p>
      </div>
      <div class="flex flex-wrap gap-2">
        <ElButton
          :loading="loading"
          plain
          class="!border-white/20 !bg-white/10 !text-white hover:!bg-white/20"
          @click="loadRequests"
        >
          <ElIcon class="mr-1"><Refresh /></ElIcon> Refresh
        </ElButton>
        <ElButton type="primary" class="font-semibold" @click="openCreateDialog">
          <ElIcon class="mr-1"><Plus /></ElIcon> New Review Request
        </ElButton>
      </div>
    </section>

    <ElAlert
      title="Current boundary"
      type="info"
      :closable="false"
      show-icon
      description="The accountant-defined correction/reversal execution matrix is not configured. An approved request preserves the original invoice, receipt, number, allocation, and canonical PDF while the decision remains auditable."
    />

    <ElCard shadow="never" class="!rounded-xl !border-slate-200/80 dark:!border-slate-800">
      <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2">
          <ElSelect v-model="statusFilter" class="!w-44" @change="loadRequests">
            <ElOption label="All statuses" value="" />
            <ElOption label="Pending review" value="PENDING" />
            <ElOption label="Approved review" value="APPROVED" />
            <ElOption label="Rejected review" value="REJECTED" />
            <ElOption label="Cancelled" value="CANCELLED" />
            <ElOption label="Executed" value="EXECUTED" />
          </ElSelect>
          <ElButton @click="loadRequests">Apply</ElButton>
        </div>
        <span class="text-xs text-slate-500">{{ pagination.total }} request(s)</span>
      </div>

      <ElTable
        v-loading="loading"
        :data="requests"
        stripe
        class="w-full"
        empty-text="No correction requests match this view."
      >
        <ElTableColumn label="Request" width="105">
          <template #default="{ row }"
            ><span class="font-mono text-xs">#{{ row.id }}</span></template
          >
        </ElTableColumn>
        <ElTableColumn label="Target" min-width="180">
          <template #default="{ row }">
            <div class="flex flex-col gap-1">
              <span class="font-mono text-xs font-semibold text-slate-700 dark:text-slate-200">
                {{ row.target?.document_number || 'Unavailable target' }}
              </span>
              <span class="text-xs text-slate-500">{{
                labelForDocumentType(row.document_type)
              }}</span>
            </div>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Requested action" min-width="175">
          <template #default="{ row }">
            <ElTag
              effect="plain"
              size="small"
              :type="row.requested_action === 'RECEIPT_REVERSAL' ? 'danger' : 'warning'"
            >
              {{ labelForAction(row.requested_action) }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Status" width="140">
          <template #default="{ row }"
            ><ElTag :type="statusType(row.status)" size="small">{{ row.status }}</ElTag></template
          >
        </ElTableColumn>
        <ElTableColumn label="Requested by" min-width="150">
          <template #default="{ row }">{{ row.requested_by?.name || 'Unknown user' }}</template>
        </ElTableColumn>
        <ElTableColumn label="Requested at" min-width="175">
          <template #default="{ row }"
            ><span class="text-xs text-slate-500">{{
              formatDate(row.requested_at)
            }}</span></template
          >
        </ElTableColumn>
        <ElTableColumn label="" width="110" fixed="right">
          <template #default="{ row }"
            ><ElButton link type="primary" @click="openDetails(row)">Review</ElButton></template
          >
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDrawer
      v-model="detailVisible"
      title="Correction Request Review"
      size="min(640px, 100%)"
      destroy-on-close
    >
      <template v-if="selected">
        <div class="space-y-5 px-1">
          <div
            class="grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm sm:grid-cols-2 dark:border-slate-700 dark:bg-slate-800/50"
          >
            <div>
              <span class="block text-xs font-medium uppercase tracking-wide text-slate-400"
                >Target</span
              >
              <span class="mt-1 block font-mono font-semibold">{{
                selected.target?.document_number || 'Unavailable target'
              }}</span>
              <span class="text-xs text-slate-500">{{
                labelForDocumentType(selected.document_type)
              }}</span>
            </div>
            <div>
              <span class="block text-xs font-medium uppercase tracking-wide text-slate-400"
                >Request status</span
              >
              <ElTag class="mt-1" :type="statusType(selected.status)">{{ selected.status }}</ElTag>
            </div>
            <div>
              <span class="block text-xs font-medium uppercase tracking-wide text-slate-400"
                >Frozen revision</span
              >
              <span class="mt-1 block"
                >v{{ selected.target_revision?.revision_number ?? '—' }} · target lock
                {{ selected.target_lock_version }}</span
              >
            </div>
            <div>
              <span class="block text-xs font-medium uppercase tracking-wide text-slate-400"
                >Current target</span
              >
              <span class="mt-1 block"
                >{{ selected.target?.status || 'Unavailable' }} ·
                {{ selected.target?.business_date || '—' }}</span
              >
            </div>
          </div>

          <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100"
              >Reason for review</h3
            >
            <p
              class="mt-2 whitespace-pre-wrap rounded-lg border border-slate-200 p-3 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200"
              >{{ selected.reason }}</p
            >
          </div>

          <div v-if="selected.decision_notes">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">Decision notes</h3>
            <p
              class="mt-2 whitespace-pre-wrap rounded-lg border border-slate-200 p-3 text-sm text-slate-700 dark:border-slate-700 dark:text-slate-200"
              >{{ selected.decision_notes }}</p
            >
          </div>

          <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100"
              >Immutable review history</h3
            >
            <ElTimeline class="mt-4">
              <ElTimelineItem
                v-for="event in selected.events"
                :key="event.id"
                :timestamp="formatDate(event.created_at)"
                :type="eventType(event.event_type)"
              >
                <div class="text-sm font-medium text-slate-700 dark:text-slate-200">{{
                  event.event_type
                }}</div>
                <div class="mt-0.5 text-xs text-slate-500"
                  >{{ event.actor?.name || 'System' }} · {{ event.to_status }}</div
                >
                <p
                  v-if="event.notes"
                  class="mt-1 whitespace-pre-wrap text-sm text-slate-600 dark:text-slate-300"
                  >{{ event.notes }}</p
                >
              </ElTimelineItem>
            </ElTimeline>
          </div>

          <ElAlert
            v-if="canDecide"
            type="warning"
            :closable="false"
            show-icon
            title="Independent decision required"
            description="You can record an approval or rejection. This action does not execute a correction or reversal; fiscal and settlement execution remains unavailable."
          />
          <ElAlert
            v-else-if="selected.status === 'PENDING'"
            type="info"
            :closable="false"
            show-icon
            title="Awaiting independent Administrator review"
            :description="
              isRequester
                ? 'You submitted this request and cannot decide it yourself.'
                : 'Only an Administrator with the server-granted approval permission can decide this request.'
            "
          />

          <ElForm v-if="canDecide" label-position="top" @submit.prevent>
            <ElFormItem label="Decision notes" required>
              <ElInput
                v-model="decisionNotes"
                type="textarea"
                :rows="4"
                maxlength="2000"
                show-word-limit
                placeholder="Record the review basis. At least five characters are required."
              />
            </ElFormItem>
            <div class="flex justify-end gap-2">
              <ElButton :loading="deciding" @click="decide('reject')">Reject request</ElButton>
              <ElButton type="primary" :loading="deciding" @click="decide('approve')"
                >Approve review only</ElButton
              >
            </div>
          </ElForm>
        </div>
      </template>
    </ElDrawer>

    <ElDialog
      v-model="createVisible"
      title="New Correction or Reversal Review"
      width="min(560px, 94vw)"
      destroy-on-close
      @closed="resetCreateForm"
    >
      <ElAlert
        type="warning"
        :closable="false"
        show-icon
        title="A request does not change a document"
        description="Use the visible invoice or collection-receipt number. The service locks and validates the current document state before it records the request."
      />
      <ElForm
        ref="createFormRef"
        :model="createForm"
        :rules="createRules"
        label-position="top"
        class="mt-5"
      >
        <ElFormItem label="Document type" prop="document_type">
          <ElRadioGroup v-model="createForm.document_type" @change="syncRequestedAction">
            <ElRadioButton value="INVOICE">Issued invoice</ElRadioButton>
            <ElRadioButton value="RECEIPT">Collection receipt</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem
          :label="
            createForm.document_type === 'INVOICE' ? 'Invoice number' : 'Collection receipt number'
          "
          prop="document_number"
        >
          <ElInput
            v-model="createForm.document_number"
            placeholder="Enter the printed document number exactly, including leading zeroes"
            maxlength="64"
          />
        </ElFormItem>
        <ElFormItem label="Requested action">
          <ElInput :model-value="labelForAction(createForm.requested_action)" disabled />
        </ElFormItem>
        <ElFormItem label="Reason for review" prop="reason">
          <ElInput
            v-model="createForm.reason"
            type="textarea"
            :rows="4"
            maxlength="2000"
            show-word-limit
            placeholder="Explain why this document needs controlled review."
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="createVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="creating" @click="submitCreate"
          >Submit for independent review</ElButton
        >
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref } from 'vue'
  import type { FormInstance, FormRules } from 'element-plus'
  import { ElMessage } from 'element-plus'
  import { Plus, Refresh } from '@element-plus/icons-vue'
  import { useUserStore } from '@/store/modules/user'
  import {
    approveDocumentCorrectionRequest,
    createDocumentCorrectionRequest,
    fetchDocumentCorrectionRequests,
    rejectDocumentCorrectionRequest,
    type DocumentCorrectionAction,
    type DocumentCorrectionRequest,
    type DocumentCorrectionStatus,
    type DocumentCorrectionType
  } from '@/api/documentCorrections'

  const userStore = useUserStore()
  const loading = ref(false)
  const creating = ref(false)
  const deciding = ref(false)
  const createVisible = ref(false)
  const detailVisible = ref(false)
  const selected = ref<DocumentCorrectionRequest | null>(null)
  const requests = ref<DocumentCorrectionRequest[]>([])
  const statusFilter = ref<DocumentCorrectionStatus | ''>('')
  const decisionNotes = ref('')
  const createFormRef = ref<FormInstance>()
  const pagination = reactive({ current_page: 1, last_page: 1, per_page: 25, total: 0 })
  const createForm = reactive<{
    document_type: DocumentCorrectionType
    document_number: string
    requested_action: DocumentCorrectionAction
    reason: string
  }>({
    document_type: 'INVOICE',
    document_number: '',
    requested_action: 'INVOICE_CORRECTION',
    reason: ''
  })
  const createRules: FormRules<typeof createForm> = {
    document_type: [{ required: true, message: 'Choose the document type.', trigger: 'change' }],
    document_number: [
      { required: true, message: 'Enter the printed document number.', trigger: 'blur' }
    ],
    reason: [
      {
        required: true,
        min: 5,
        message: 'Give a review reason of at least five characters.',
        trigger: 'blur'
      }
    ]
  }

  const isAdministrator = computed(() => userStore.info?.roles?.includes('Administrator') === true)
  const isRequester = computed(
    () => Number(selected.value?.requested_by?.id) === Number(userStore.info?.userId)
  )
  const canDecide = computed(
    () => isAdministrator.value && selected.value?.status === 'PENDING' && !isRequester.value
  )

  function labelForDocumentType(type: DocumentCorrectionType) {
    return type === 'INVOICE' ? 'Issued invoice' : 'Collection receipt'
  }

  function labelForAction(action: DocumentCorrectionAction) {
    return action === 'INVOICE_CORRECTION'
      ? 'Correct issued unpaid invoice'
      : 'Review receipt reversal'
  }

  function statusType(status: DocumentCorrectionStatus) {
    return {
      PENDING: 'warning',
      APPROVED: 'success',
      REJECTED: 'danger',
      CANCELLED: 'info',
      EXECUTED: 'primary'
    }[status] as 'success' | 'warning' | 'danger' | 'info' | 'primary'
  }

  function eventType(event: string) {
    return event === 'APPROVED' ? 'success' : event === 'REJECTED' ? 'danger' : 'primary'
  }

  function formatDate(value?: string | null) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('en-PH', {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: 'Asia/Manila'
    }).format(new Date(value))
  }

  async function loadRequests() {
    loading.value = true
    try {
      const response = await fetchDocumentCorrectionRequests(statusFilter.value || undefined)
      requests.value = response.data
      Object.assign(pagination, {
        current_page: response.current_page,
        last_page: response.last_page,
        per_page: response.per_page,
        total: response.total
      })
      if (selected.value) {
        selected.value =
          response.data.find((row) => row.id === selected.value?.id) || selected.value
      }
    } finally {
      loading.value = false
    }
  }

  function openCreateDialog() {
    createVisible.value = true
  }

  function syncRequestedAction() {
    createForm.requested_action =
      createForm.document_type === 'INVOICE' ? 'INVOICE_CORRECTION' : 'RECEIPT_REVERSAL'
  }

  function resetCreateForm() {
    createForm.document_type = 'INVOICE'
    createForm.document_number = ''
    createForm.requested_action = 'INVOICE_CORRECTION'
    createForm.reason = ''
    createFormRef.value?.clearValidate()
  }

  async function submitCreate() {
    const valid = await createFormRef.value?.validate().catch(() => false)
    if (!valid) return
    creating.value = true
    try {
      const created = await createDocumentCorrectionRequest({
        ...createForm,
        document_number: createForm.document_number.trim()
      })
      ElMessage.success('Correction request submitted for independent review.')
      createVisible.value = false
      selected.value = created
      detailVisible.value = true
      await loadRequests()
    } finally {
      creating.value = false
    }
  }

  function openDetails(request: DocumentCorrectionRequest) {
    selected.value = request
    decisionNotes.value = ''
    detailVisible.value = true
  }

  async function decide(action: 'approve' | 'reject') {
    if (!selected.value || decisionNotes.value.trim().length < 5) {
      ElMessage.warning('Enter at least five characters of decision notes.')
      return
    }
    deciding.value = true
    try {
      selected.value =
        action === 'approve'
          ? await approveDocumentCorrectionRequest(selected.value.id, decisionNotes.value.trim())
          : await rejectDocumentCorrectionRequest(selected.value.id, decisionNotes.value.trim())
      ElMessage.success(
        action === 'approve'
          ? 'Review approved. No fiscal execution was performed.'
          : 'Review request rejected.'
      )
      decisionNotes.value = ''
      await loadRequests()
    } finally {
      deciding.value = false
    }
  }

  onMounted(loadRequests)
</script>
