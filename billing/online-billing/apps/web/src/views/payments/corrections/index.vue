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
          Submit a posted document for Administrator review. Approval unlocks the next controlled
          step: a linked unpaid-invoice correction draft, or separate receipt-reversal execution.
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
      title="Linked replacement for unpaid invoices"
      type="info"
      :closable="false"
      show-icon
      description="After Correct bill is approved, start a linked draft, edit date/shipment/lines, then post a replacement SI. The original number and PDF stay immutable and are linked as REPLACEMENT. Paid invoices stay blocked. Fiscal cancellation vs credit-note labeling remains accountant-open."
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
            :title="isRequester ? 'Deciding your own request' : 'Decision required'"
            :description="
              isRequester
                ? 'You submitted this request. As an Administrator you can still decide it; the history and audit log record it as a self-review. Approval alone does not change the posted document.'
                : 'You can record an approval or rejection. Approval alone does not change the posted document; the teller starts a linked draft or executes receipt reversal separately.'
            "
          />
          <ElAlert
            v-else-if="canExecuteInvoiceCorrection"
            type="warning"
            :closable="false"
            show-icon
            title="Linked correction draft"
            description="Edit a replacement draft (date, shipment, lines, surcharge). Posting creates a new SI and marks the original SUPERSEDED (kept for audit, not payable)."
          />
          <ElAlert
            v-else-if="canExecuteReversal"
            type="warning"
            :closable="false"
            show-icon
            title="Settlement-only receipt reversal"
            description="Execution restores invoice collectible balances and withholding capacity once. The original receipt number and PDF remain. No fiscal replacement document is issued."
          />
          <ElAlert
            v-else-if="selected.status === 'EXECUTED' && selected.replacement_invoice"
            type="success"
            :closable="false"
            show-icon
            title="Replacement posted"
            :description="`Replacement ${selected.replacement_invoice.invoice_number || '#' + selected.replacement_invoice.id} is posted. Original ${selected.target?.document_number || 'invoice'} remains unchanged.`"
          />
          <ElAlert
            v-else-if="selected.status === 'PENDING'"
            type="info"
            :closable="false"
            show-icon
            title="Awaiting Administrator review"
            description="Only an Administrator with the server-granted approval permission can decide this request."
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

          <div v-else-if="canExecuteInvoiceCorrection" class="space-y-3">
            <p v-if="selected.correction_draft" class="text-sm text-g-700">
              Draft #{{ selected.correction_draft.id }}
              <span class="text-g-500">· lock {{ selected.correction_draft.lock_version }}</span>
              <span v-if="selected.has_posted_settlement" class="ml-2 text-error"
                >Settlement detected — start/post blocked</span
              >
            </p>
            <ElForm label-position="top" @submit.prevent>
              <ElFormItem label="Execution notes" required>
                <ElInput
                  v-model="executionNotes"
                  type="textarea"
                  :rows="3"
                  maxlength="2000"
                  show-word-limit
                  placeholder="Why this replacement should post now (min. 5 characters)."
                />
              </ElFormItem>
            </ElForm>
            <div class="flex flex-wrap justify-end gap-2">
              <ElButton :loading="startingDraft" @click="startOrOpenInvoiceDraft">
                {{
                  selected.correction_draft_invoice_id
                    ? 'Open correction draft'
                    : 'Start correction draft'
                }}
              </ElButton>
              <ElButton
                type="primary"
                :loading="executing"
                :disabled="!selected.correction_draft_invoice_id || selected.has_posted_settlement"
                @click="executeInvoiceReplacement"
              >
                Post replacement invoice
              </ElButton>
            </div>
          </div>

          <ElForm v-else-if="canExecuteReversal" label-position="top" @submit.prevent>
            <ElFormItem label="Execution notes" required>
              <ElInput
                v-model="executionNotes"
                type="textarea"
                :rows="4"
                maxlength="2000"
                show-word-limit
                placeholder="Confirm why settlement restoration should run now. At least five characters are required."
              />
            </ElFormItem>
            <div class="flex justify-end">
              <ElButton type="danger" :loading="executing" @click="executeReversal"
                >Execute settlement reversal</ElButton
              >
            </div>
          </ElForm>
        </div>
      </template>
    </ElDrawer>

    <ElDialog
      v-model="editDraftVisible"
      title="Edit correction draft"
      width="min(960px, 96vw)"
      destroy-on-close
      @closed="resetEditDraft"
    >
      <p class="mb-3 text-sm text-g-600">
        Original {{ selected?.target?.document_number || 'invoice' }} stays posted. Changes apply to
        the replacement draft only.
      </p>
      <InvoiceShipmentFields
        v-if="editDraft"
        v-model:vessel-id="editVesselId"
        v-model:vessel-name="editVesselName"
        v-model:voyage="editVoyage"
        v-model:notes="editNotes"
        v-model:movement-type="editMovementType"
        v-model:route-type="editRouteType"
      />
      <div class="mt-4">
        <ElFormItem label="Business date">
          <ElDatePicker
            v-model="editBusinessDate"
            type="date"
            value-format="YYYY-MM-DD"
            class="w-full"
          />
        </ElFormItem>
      </div>
      <InvoiceBillingItemsGrid
        v-if="editDraft"
        v-model:lines="editLines"
        v-model:surcharge-mode="editSurchargeMode"
        v-model:dangerous-cargo-percent="editDangerPercent"
        class="mt-4"
        :tariffs="editTariffs"
        :route-type="editRouteType"
        :draft="editDraft"
        :customer-id="editDraft.customer_id"
        :business-date="editBusinessDate || undefined"
      />
      <template #footer>
        <ElButton @click="editDraftVisible = false">Close</ElButton>
        <ElButton type="primary" :loading="savingDraft" @click="saveCorrectionDraft"
          >Save draft</ElButton
        >
      </template>
    </ElDialog>

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
          >Submit for review</ElButton
        >
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import type { FormInstance, FormRules } from 'element-plus'
  import { ElMessage } from 'element-plus'
  import { Plus, Refresh } from '@element-plus/icons-vue'
  import { fetchGetUserInfo } from '@/api/auth'
  import {
    approveDocumentCorrectionRequest,
    createDocumentCorrectionRequest,
    executeDocumentCorrectionRequest,
    fetchDocumentCorrectionRequests,
    rejectDocumentCorrectionRequest,
    startDocumentCorrectionDraft,
    type DocumentCorrectionAction,
    type DocumentCorrectionRequest,
    type DocumentCorrectionStatus,
    type DocumentCorrectionType
  } from '@/api/documentCorrections'
  import {
    billingLineFromDraftItem,
    emptyBillingLine,
    fetchEffectiveTariffs,
    fetchInvoiceDraft,
    invoiceShipmentPayload,
    updateInvoiceDraft,
    type BillingDraftLine,
    type InvoiceDraft,
    type MovementType,
    type RouteType,
    type SurchargeMode
  } from '@/api/invoices'
  import type { Tariff } from '@/api/pricing'
  import InvoiceBillingItemsGrid from '@/components/business/InvoiceBillingItemsGrid.vue'
  import InvoiceShipmentFields from '@/components/business/InvoiceShipmentFields.vue'
  import { useAuthoritativeRealtimeRefresh } from '@/composables/useAuthoritativeRealtimeRefresh'
  import { useUserStore } from '@/store/modules/user'
  import { manilaBusinessDate } from '@/utils/date/manilaBusinessDate'

  const route = useRoute()
  const userStore = useUserStore()
  const loading = ref(false)
  const creating = ref(false)
  const deciding = ref(false)
  const executing = ref(false)
  const startingDraft = ref(false)
  const savingDraft = ref(false)
  const createVisible = ref(false)
  const detailVisible = ref(false)
  const editDraftVisible = ref(false)
  const selected = ref<DocumentCorrectionRequest | null>(null)
  const requests = ref<DocumentCorrectionRequest[]>([])
  const statusFilter = ref<DocumentCorrectionStatus | ''>('')
  const decisionNotes = ref('')
  const executionNotes = ref('')
  const editDraft = ref<InvoiceDraft | null>(null)
  const editTariffs = ref<Tariff[]>([])
  const editLines = ref<BillingDraftLine[]>([emptyBillingLine()])
  const editVesselId = ref<number | null>(null)
  const editVesselName = ref('')
  const editVoyage = ref('')
  const editNotes = ref('')
  const editMovementType = ref<MovementType | ''>('')
  const editRouteType = ref<RouteType | ''>('')
  const editBusinessDate = ref(manilaBusinessDate())
  const editSurchargeMode = ref<SurchargeMode>('FUEL')
  const editDangerPercent = ref<string | null>(null)
  const realtime = useAuthoritativeRealtimeRefresh({
    scope: 'corrections',
    refresh: () => loadRequests(),
    isBusy: () =>
      creating.value ||
      deciding.value ||
      executing.value ||
      startingDraft.value ||
      savingDraft.value
  })
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
  const canDecide = computed(() => isAdministrator.value && selected.value?.status === 'PENDING')
  const canExecuteInvoiceCorrection = computed(
    () =>
      selected.value?.status === 'APPROVED' &&
      selected.value?.requested_action === 'INVOICE_CORRECTION'
  )
  const canExecuteReversal = computed(
    () =>
      isAdministrator.value &&
      selected.value?.status === 'APPROVED' &&
      selected.value?.requested_action === 'RECEIPT_REVERSAL'
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

  function applyCreateQueryPrefill() {
    const number = String(route.query.document_number || '').trim()
    const type = String(route.query.document_type || 'INVOICE').toUpperCase()
    const openCreate = String(route.query.create || '') === '1'
    const openApproved = String(route.query.open_approved || '') === '1'
    if (!number && !openCreate && !openApproved) return

    if (openApproved && number) {
      void openApprovedInvoiceCorrection(number)
      return
    }

    resetCreateForm()
    if (type === 'RECEIPT') {
      createForm.document_type = 'RECEIPT'
      createForm.requested_action = 'RECEIPT_REVERSAL'
    } else {
      createForm.document_type = 'INVOICE'
      createForm.requested_action = 'INVOICE_CORRECTION'
    }
    if (number) {
      createForm.document_number = number
    }
    createVisible.value = true
  }

  async function openApprovedInvoiceCorrection(documentNumber: string) {
    const match =
      requests.value.find(
        (row) =>
          row.requested_action === 'INVOICE_CORRECTION' &&
          row.status === 'APPROVED' &&
          row.target?.document_number === documentNumber
      ) ||
      (
        await fetchDocumentCorrectionRequests('APPROVED').catch(() => ({
          data: [] as DocumentCorrectionRequest[]
        }))
      ).data.find(
        (row) =>
          row.requested_action === 'INVOICE_CORRECTION' &&
          row.target?.document_number === documentNumber
      )

    if (!match) {
      ElMessage.info(
        'No approved Correct bill request for this invoice yet. Submit Correct bill first.'
      )
      resetCreateForm()
      createForm.document_type = 'INVOICE'
      createForm.requested_action = 'INVOICE_CORRECTION'
      createForm.document_number = documentNumber
      createVisible.value = true
      return
    }
    openDetails(match)
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
      ElMessage.success('Correction request submitted for review.')
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
    executionNotes.value = ''
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
          ? selected.value.requested_action === 'INVOICE_CORRECTION'
            ? 'Review approved. Start a linked correction draft, then post the replacement.'
            : 'Review approved. Settlement execution remains a separate action for receipt reversals.'
          : 'Review request rejected.'
      )
      decisionNotes.value = ''
      await loadRequests()
    } finally {
      deciding.value = false
    }
  }

  function applyEditFromDraft(draft: InvoiceDraft) {
    editDraft.value = draft
    editBusinessDate.value = draft.business_date || manilaBusinessDate()
    editVesselId.value = draft.vessel_id ?? null
    editVesselName.value = draft.vessel_name || ''
    editVoyage.value = draft.voyage || ''
    editNotes.value = draft.notes || ''
    editMovementType.value = draft.movement_type || ''
    editRouteType.value = draft.route_type || ''
    const mode = String(draft.surcharge_mode || 'FUEL').toUpperCase()
    editSurchargeMode.value =
      mode === 'NONE' || mode === 'DANGEROUS_CARGO' || mode === 'FUEL' ? mode : 'FUEL'
    editDangerPercent.value =
      editSurchargeMode.value === 'DANGEROUS_CARGO' ? draft.dangerous_cargo_percent || null : null
    editLines.value = draft.items?.length
      ? draft.items.map(billingLineFromDraftItem)
      : [emptyBillingLine()]
  }

  function resetEditDraft() {
    editDraft.value = null
    editLines.value = [emptyBillingLine()]
    editVesselId.value = null
    editVesselName.value = ''
    editVoyage.value = ''
    editNotes.value = ''
    editMovementType.value = ''
    editRouteType.value = ''
    editBusinessDate.value = manilaBusinessDate()
    editSurchargeMode.value = 'FUEL'
    editDangerPercent.value = null
  }

  async function ensureEditTariffs() {
    if (editTariffs.value.length) return
    try {
      editTariffs.value = await fetchEffectiveTariffs()
    } catch {
      editTariffs.value = []
    }
  }

  async function startOrOpenInvoiceDraft() {
    if (!selected.value) return
    startingDraft.value = true
    try {
      if (!selected.value.correction_draft_invoice_id) {
        selected.value = await startDocumentCorrectionDraft(selected.value.id)
        ElMessage.success('Correction draft started from the original unpaid invoice.')
        await loadRequests()
      }
      const draftId = selected.value.correction_draft_invoice_id
      if (!draftId) {
        ElMessage.error('Correction draft id missing after start.')
        return
      }
      await ensureEditTariffs()
      const draft = await fetchInvoiceDraft(draftId)
      applyEditFromDraft(draft)
      editDraftVisible.value = true
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to open correction draft.')
    } finally {
      startingDraft.value = false
    }
  }

  async function saveCorrectionDraft() {
    if (!editDraft.value) return
    const shipment = invoiceShipmentPayload({
      vessel_id: editVesselId.value,
      voyage: editVoyage.value,
      notes: editNotes.value,
      movement_type: editMovementType.value,
      route_type: editRouteType.value
    })
    if (!shipment) {
      ElMessage.warning('Complete vessel, voyage, type, route, and notes before saving.')
      return
    }
    const items = editLines.value
      .filter((line) => line.tariff_version_id && line.quantity)
      .map((line) => ({
        tariff_version_id: line.tariff_version_id,
        tariff_code: line.tariff_code || undefined,
        service_type: line.service_type || undefined,
        quantity: line.quantity
      }))
    if (!items.length) {
      ElMessage.warning('Add at least one tariff line.')
      return
    }
    savingDraft.value = true
    try {
      const updated = await updateInvoiceDraft(editDraft.value.id, {
        expected_version: editDraft.value.lock_version,
        business_date: editBusinessDate.value || manilaBusinessDate(),
        ...shipment,
        surcharge_mode: editSurchargeMode.value,
        dangerous_cargo_percent:
          editSurchargeMode.value === 'DANGEROUS_CARGO' ? editDangerPercent.value : null,
        items,
        reason: 'Correction draft edit'
      })
      applyEditFromDraft(updated)
      ElMessage.success('Correction draft saved.')
      if (selected.value) {
        selected.value = {
          ...selected.value,
          correction_draft: {
            id: updated.id,
            invoice_number: updated.invoice_number,
            status: updated.status,
            business_date: updated.business_date,
            currency: updated.currency,
            total_charge_amount: updated.total_charge_amount,
            lock_version: updated.lock_version
          }
        }
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to save correction draft.')
    } finally {
      savingDraft.value = false
    }
  }

  async function executeInvoiceReplacement() {
    if (!selected.value || executionNotes.value.trim().length < 5) {
      ElMessage.warning('Enter at least five characters of execution notes.')
      return
    }
    if (!selected.value.correction_draft_invoice_id) {
      ElMessage.warning('Start and save a correction draft before posting the replacement.')
      return
    }
    executing.value = true
    try {
      const result = await executeDocumentCorrectionRequest(
        selected.value.id,
        executionNotes.value.trim(),
        selected.value.correction_draft?.lock_version
      )
      const payload = (result as any)?.data ?? result
      selected.value = payload.correction_request ?? selected.value
      editDraftVisible.value = false
      ElMessage.success(
        `Replacement ${payload.replacement_invoice?.invoice_number || 'invoice'} posted. Original preserved.`
      )
      executionNotes.value = ''
      await loadRequests()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to post replacement invoice.')
    } finally {
      executing.value = false
    }
  }

  async function executeReversal() {
    if (!selected.value || executionNotes.value.trim().length < 5) {
      ElMessage.warning('Enter at least five characters of execution notes.')
      return
    }
    executing.value = true
    try {
      const result = await executeDocumentCorrectionRequest(
        selected.value.id,
        executionNotes.value.trim()
      )
      const payload = (result as any)?.data ?? result
      selected.value = payload.correction_request ?? selected.value
      ElMessage.success(
        `Settlement reversal executed for ${payload.receipt?.receipt_number || 'receipt'}. Original PDF preserved.`
      )
      executionNotes.value = ''
      await loadRequests()
    } finally {
      executing.value = false
    }
  }

  const editTariffOptions = computed(() =>
    editTariffs.value
      .filter((tariff) => !editRouteType.value || tariff.route_type === editRouteType.value)
      .flatMap((tariff) =>
        (tariff.versions || [])
          .filter((v) => v.status === 'effective')
          .map((v) => ({ versionId: v.id }))
      )
  )

  watch(editRouteType, (route, previous) => {
    if (!route || route === previous) return
    if (!editTariffOptions.value.length) return
    const allowed = new Set(editTariffOptions.value.map((opt) => opt.versionId))
    const dropped = editLines.value.some(
      (line) => line.tariff_version_id && !allowed.has(line.tariff_version_id)
    )
    if (!dropped) return
    editLines.value = editLines.value.map((line) =>
      !line.tariff_version_id || allowed.has(line.tariff_version_id) ? line : emptyBillingLine()
    )
    ElMessage.info('Tariff lines were cleared because they do not match the selected route.')
  })

  onMounted(async () => {
    const me = await fetchGetUserInfo()
    realtime.startForOrganization(
      Number((me as any).organization?.id || (me as any).organization_id)
    )
    await loadRequests()
    applyCreateQueryPrefill()
  })
</script>
