<template>
  <div class="teller-review-container max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <!-- Header Banner -->
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-sm"
    >
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Payment Proof Review</h1>
          <span
            class="bg-amber-500/20 text-amber-200 border border-amber-400/30 text-xs px-2.5 py-0.5 rounded-full font-medium"
            >Teller Workstation</span
          >
        </div>
        <p class="text-slate-300 text-sm max-w-2xl">
          Oldest-first fair teller queue. Inspect bank deposit slips, verify credited bank amounts,
          and issue official collection receipts.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <ElButton
          :loading="loading"
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="loadQueue"
        >
          <ElIcon class="mr-1"><Refresh /></ElIcon> Refresh
        </ElButton>
        <ElButton
          type="primary"
          size="default"
          :loading="claiming"
          class="!bg-amber-500 hover:!bg-amber-400 !border-none !text-slate-950 font-semibold shadow-lg shadow-amber-500/25"
          @click="claimNext"
        >
          <ElIcon class="mr-1"><Select /></ElIcon> Claim Oldest Proof
        </ElButton>
      </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div
        class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between"
      >
        <div>
          <span class="text-xs font-medium text-slate-500 uppercase tracking-wider"
            >Awaiting Claim</span
          >
          <div class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">
            {{ queue.filter((q) => q.status === 'SUBMITTED').length }}
          </div>
          <span class="text-xs text-amber-600 dark:text-amber-400 mt-1 block font-medium"
            >Fair queue priority</span
          >
        </div>
        <div
          class="h-10 w-10 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400"
        >
          <ElIcon :size="20"><Clock /></ElIcon>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between"
      >
        <div>
          <span class="text-xs font-medium text-slate-500 uppercase tracking-wider">In Review</span>
          <div class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">
            {{ queue.filter((q) => q.status === 'IN_REVIEW').length }}
          </div>
          <span class="text-xs text-sky-600 dark:text-sky-400 mt-1 block"
            >Active claims across tellers</span
          >
        </div>
        <div
          class="h-10 w-10 rounded-xl bg-sky-50 dark:bg-sky-950/50 flex items-center justify-center text-sky-600 dark:text-sky-400"
        >
          <ElIcon :size="20"><User /></ElIcon>
        </div>
      </div>

      <div
        class="bg-white dark:bg-slate-900 p-4 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between"
      >
        <div>
          <span class="text-xs font-medium text-slate-500 uppercase tracking-wider"
            >My Active Claim</span
          >
          <div class="mt-1 text-2xl font-bold text-slate-800 dark:text-slate-100">
            {{ myClaimedSubmissions.length }}
          </div>
          <span class="text-xs text-emerald-600 dark:text-emerald-400 mt-1 block"
            >Assigned to you</span
          >
        </div>
        <div
          class="h-10 w-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400"
        >
          <ElIcon :size="20"><DocumentChecked /></ElIcon>
        </div>
      </div>
    </div>

    <!-- Split Workstation: Queue List (Left) + Detailed Review (Right) -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- Queue Panel (5 cols) -->
      <ElCard
        shadow="never"
        class="lg:col-span-5 !rounded-xl !border-slate-200/80 dark:!border-slate-800"
        v-loading="loading"
      >
        <template #header>
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100">Queue List</h2>
              <ElTag size="small" effect="plain">{{ queue.length }} items</ElTag>
            </div>
            <ElRadioGroup v-model="queueFilter" size="small">
              <ElRadioButton label="all">All</ElRadioButton>
              <ElRadioButton label="mine">My Claim</ElRadioButton>
            </ElRadioGroup>
          </div>
        </template>

        <ElEmpty
          v-if="filteredQueue.length === 0"
          description="No payment proofs in this queue view."
        />
        <div v-else class="space-y-2.5 max-h-[720px] overflow-y-auto pr-1">
          <div
            v-for="item in filteredQueue"
            :key="item.id"
            :class="[
              'p-3.5 rounded-xl border transition-all cursor-pointer select-none',
              current?.id === item.id
                ? 'bg-blue-50/60 dark:bg-blue-950/30 border-blue-500 shadow-sm ring-1 ring-blue-500/30'
                : 'bg-white dark:bg-slate-900 border-slate-200/80 dark:border-slate-800 hover:border-slate-300 dark:hover:border-slate-700'
            ]"
            @click="openSubmission(item)"
          >
            <div class="flex items-start justify-between gap-2">
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <span class="font-mono text-xs font-bold text-slate-500">#{{ item.id }}</span>
                  <span class="font-semibold text-slate-800 dark:text-slate-100 truncate text-sm">
                    {{ item.customer?.name || `Customer #${item.customer_id}` }}
                  </span>
                </div>
                <div class="text-xs text-slate-400 mt-1 flex items-center gap-1.5">
                  <ElIcon><Clock /></ElIcon>
                  <span>{{ item.initial_submitted_at }}</span>
                  <span
                    v-if="item.resubmission_rounds > 0"
                    class="text-amber-600 dark:text-amber-400 font-medium"
                  >
                    (Rev. {{ item.resubmission_rounds }})
                  </span>
                </div>
              </div>
              <StatusTag :status="item.status" size="small" />
            </div>

            <div
              class="mt-3 pt-2.5 border-t border-slate-100 dark:border-slate-800/80 flex items-center justify-between text-xs"
            >
              <span class="text-slate-500">
                Assigned:
                <strong class="text-slate-700 dark:text-slate-300">{{
                  item.assigned_teller?.name || 'Unclaimed'
                }}</strong>
              </span>
              <MoneyDisplay
                :value="item.requested_amount"
                :currency="item.currency"
                size="sm"
                weight="bold"
              />
            </div>
          </div>
        </div>
      </ElCard>

      <!-- Review Decision Workstation (7 cols) -->
      <ElCard
        shadow="never"
        class="lg:col-span-7 !rounded-xl !border-slate-200/80 dark:!border-slate-800"
        v-loading="loadingDetail"
      >
        <template #header>
          <div class="flex items-center justify-between">
            <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100"
              >Review Decision &amp; Verification</h2
            >
            <StatusTag v-if="current" :status="current.status" />
          </div>
        </template>

        <ElEmpty
          v-if="!current"
          description="Select a submission from the queue or click 'Claim Oldest Proof' to begin review."
        />
        <div v-else class="space-y-5">
          <!-- Submission Meta Banner -->
          <div
            class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700 grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs"
          >
            <div>
              <span class="text-slate-400 block font-medium uppercase tracking-wider text-[10px]"
                >Customer</span
              >
              <span
                class="font-semibold text-slate-800 dark:text-slate-200 text-sm mt-0.5 block truncate"
              >
                {{ current.customer?.name || `Customer #${current.customer_id}` }}
              </span>
            </div>
            <div>
              <span class="text-slate-400 block font-medium uppercase tracking-wider text-[10px]"
                >Customer Reference</span
              >
              <span
                class="font-mono font-medium text-slate-700 dark:text-slate-300 text-sm mt-0.5 block truncate"
              >
                {{ current.declared_reference || 'None provided' }}
              </span>
            </div>
            <div>
              <span class="text-slate-400 block font-medium uppercase tracking-wider text-[10px]"
                >Requested Amount</span
              >
              <div class="mt-0.5">
                <MoneyDisplay
                  :value="current.requested_amount"
                  :currency="current.currency"
                  size="base"
                  weight="bold"
                />
              </div>
            </div>
            <div>
              <span class="text-slate-400 block font-medium uppercase tracking-wider text-[10px]"
                >Proof File</span
              >
              <div class="mt-0.5 flex items-center gap-1">
                <ElButton
                  size="small"
                  text
                  type="primary"
                  class="!p-0 font-medium"
                  @click="proofModalVisible = true"
                >
                  <ElIcon class="mr-0.5"><View /></ElIcon> Preview File
                </ElButton>
              </div>
            </div>
            <div
              v-if="current.payment_group"
              class="col-span-2 sm:col-span-4 border-t border-slate-200/80 dark:border-slate-700 pt-2"
            >
              <span class="text-slate-400 block font-medium uppercase tracking-wider text-[10px]"
                >Frozen Payment Instruction</span
              >
              <div
                class="mt-1 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600 dark:text-slate-300"
              >
                <span
                  >#{{ current.payment_group.id }} · Deadline
                  {{ current.payment_group.payment_deadline_at }}</span
                >
                <span v-if="current.payment_group.review_due_at"
                  >Review target {{ current.payment_group.review_due_at }}</span
                >
                <ElTag size="small" effect="plain">
                  {{
                    current.payment_group.payment_method === 'CHECK_DEPOSIT'
                      ? 'Check deposit'
                      : 'Bank transfer'
                  }}
                </ElTag>
                <ElTag
                  v-if="current.payment_group.payment_method === 'CHECK_DEPOSIT'"
                  size="small"
                  :type="
                    current.payment_group.check_clearance_status === 'CLEARED'
                      ? 'success'
                      : 'warning'
                  "
                  effect="plain"
                  >Check {{ current.payment_group.check_clearance_status.toLowerCase() }}</ElTag
                >
                <span
                  v-if="
                    current.payment_group.payment_method === 'CHECK_DEPOSIT' &&
                    current.payment_group.clearance_due_at
                  "
                  >Clearance target {{ current.payment_group.clearance_due_at }}</span
                >
                <ElTag
                  v-if="current.payment_group.first_proof_was_timely === false"
                  size="small"
                  type="danger"
                  effect="plain"
                  >Late proof — reconcile, do not discard</ElTag
                >
                <span v-else>Initial proof was submitted within the frozen window.</span>
              </div>
            </div>
          </div>

          <!-- Allocation & Confirmed Amounts Table -->
          <div>
            <div class="flex items-center justify-between mb-2">
              <div>
                <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-200"
                  >Invoice Payment Allocation</h3
                >
                <p class="text-xs text-slate-400"
                  >Specify actual cleared cash amount and optional withholding tax certificate.</p
                >
              </div>
            </div>

            <ElTable
              :data="decisionRows"
              size="small"
              border
              class="w-full text-xs rounded-lg overflow-hidden"
            >
              <ElTableColumn label="Invoice" min-width="120">
                <template #default="{ row }">
                  <span class="font-mono font-semibold">{{ row.invoice_number }}</span>
                </template>
              </ElTableColumn>
              <ElTableColumn label="Billed Balance" min-width="120" align="right">
                <template #default="{ row }">
                  <MoneyDisplay
                    :value="row.requested_amount"
                    :currency="current.currency"
                    size="xs"
                  />
                </template>
              </ElTableColumn>
              <ElTableColumn label="Confirmed Cash (₱)" min-width="150">
                <template #default="{ row }">
                  <ElInput
                    v-model="row.cash_amount"
                    size="small"
                    :disabled="!isAssignedToMe || current.status !== 'IN_REVIEW'"
                    placeholder="0.00"
                  />
                </template>
              </ElTableColumn>
              <ElTableColumn label="Withholding Cert ID / Amount" min-width="190">
                <template #default="{ row }">
                  <div class="flex items-center gap-1.5">
                    <ElInput
                      v-model="row.certificate_id"
                      size="small"
                      placeholder="Cert ID"
                      :disabled="!isAssignedToMe || current.status !== 'IN_REVIEW'"
                      class="!w-24"
                    />
                    <ElInput
                      v-model="row.withholding_amount"
                      size="small"
                      placeholder="Amount"
                      :disabled="!isAssignedToMe || current.status !== 'IN_REVIEW'"
                    />
                  </div>
                </template>
              </ElTableColumn>
            </ElTable>
          </div>

          <!-- Verified Reference Input -->
          <div>
            <label
              class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1"
            >
              Verified Bank Reference / Official Remarks
            </label>
            <ElInput
              v-model="confirmedReference"
              maxlength="128"
              size="default"
              placeholder="e.g. Bank deposit tracking no., confirmed online transaction reference"
              :disabled="!isAssignedToMe || current.status !== 'IN_REVIEW'"
              clearable
            />
          </div>

          <!-- Total Calculation Summary Bar -->
          <div class="bg-slate-900 text-white p-4 rounded-xl flex items-center justify-between">
            <div>
              <span class="text-xs text-slate-400 uppercase tracking-wider block"
                >Collection Receipt Total</span
              >
              <span class="text-xs text-slate-400">Cash + Withholding applied</span>
            </div>
            <MoneyDisplay
              :value="totalAllocatedAmount"
              size="xl"
              class="text-emerald-400 font-bold"
            />
          </div>

          <!-- Action Controls -->
          <div
            v-if="current.status === 'IN_REVIEW' && isAssignedToMe"
            class="pt-3 border-t border-slate-100 dark:border-slate-800 flex flex-wrap items-center justify-between gap-3"
          >
            <div class="flex flex-wrap items-center gap-2">
              <ElButton type="danger" plain @click="openRejectModal">
                <ElIcon class="mr-1"><Close /></ElIcon> Reject with Reason
              </ElButton>
              <ElButton
                v-if="requiresCheckClearance"
                type="warning"
                plain
                :loading="deciding"
                @click="recordCheckClearance('CLEARED')"
                >Record Check Cleared</ElButton
              >
              <ElButton
                v-if="canMarkCheckDishonored"
                type="danger"
                plain
                :loading="deciding"
                @click="recordCheckClearance('DISHONORED')"
                >Mark Check Dishonored</ElButton
              >
            </div>
            <div class="flex items-center gap-2">
              <span v-if="requiresCheckClearance" class="text-xs text-amber-600 dark:text-amber-400"
                >A deposited check must be cleared before posting.</span
              >
              <ElButton
                type="success"
                size="large"
                :loading="deciding"
                :disabled="requiresCheckClearance"
                class="!px-6 !font-semibold shadow-lg shadow-emerald-500/20"
                @click="approveCurrent"
              >
                <ElIcon class="mr-1"><Check /></ElIcon> Approve &amp; Post Receipt
              </ElButton>
            </div>
          </div>
          <ElAlert
            v-else
            type="info"
            :closable="false"
            show-icon
            :title="
              current.status === 'IN_REVIEW'
                ? 'Claimed by another teller. Only the assigned teller may approve or reject.'
                : 'This proof submission has already been resolved or closed.'
            "
          />
        </div>
      </ElCard>
    </div>

    <!-- Reject Dialog with Quick Reason Chips -->
    <ElDialog
      v-model="rejectDialogVisible"
      title="Reject Payment Proof"
      width="540px"
      destroy-on-close
    >
      <div class="space-y-4">
        <ElAlert
          type="error"
          :closable="false"
          show-icon
          title="Return for Customer Correction"
          description="The customer will be notified and given the opportunity to resubmit corrected documentation with their original queue priority."
        />

        <div>
          <label
            class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-2"
          >
            Quick Reason Templates
          </label>
          <div class="flex flex-wrap gap-2">
            <ElTag
              v-for="chip in quickRejectChips"
              :key="chip"
              class="cursor-pointer hover:opacity-80 select-none"
              effect="plain"
              type="danger"
              @click="rejectionReason = chip"
            >
              {{ chip }}
            </ElTag>
          </div>
        </div>

        <div>
          <label
            class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1"
          >
            Detailed Reason <span class="text-rose-500">*</span>
          </label>
          <ElInput
            v-model="rejectionReason"
            type="textarea"
            :rows="3"
            placeholder="Explain specifically what is missing, unreadable, or incorrect..."
          />
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <ElButton @click="rejectDialogVisible = false">Cancel</ElButton>
          <ElButton
            type="danger"
            :loading="deciding"
            :disabled="!rejectionReason.trim()"
            @click="confirmReject"
          >
            Confirm Rejection
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <!-- Proof Document Viewer Modal -->
    <ProofViewerModal
      v-model="proofModalVisible"
      :title="`Payment Proof #${current?.id || ''} · ${current?.customer?.name || ''}`"
      :file-id="current?.proof_file_id"
      :fetcher="proofFetcher"
    />
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    Refresh,
    Select,
    Clock,
    User,
    DocumentChecked,
    View,
    Check,
    Close
  } from '@element-plus/icons-vue'
  import MoneyDisplay from '@/components/business/MoneyDisplay.vue'
  import StatusTag from '@/components/business/StatusTag.vue'
  import ProofViewerModal from '@/components/business/ProofViewerModal.vue'
  import {
    approvePaymentSubmission,
    claimNextPaymentSubmission,
    fetchTellerPaymentSubmission,
    fetchTellerPaymentSubmissions,
    recordPaymentCheckClearance,
    rejectPaymentSubmission,
    type ManualPaymentSubmission
  } from '@/api/payments'
  import { downloadPrivateFile } from '@/api/documentRequirements'
  import { useUserStore } from '@/store/modules/user'

  defineOptions({ name: 'TellerPaymentProofReview' })

  type DecisionRow = {
    invoice_id: number
    invoice_number: string
    requested_amount: string
    cash_amount: string
    certificate_id: string
    withholding_amount: string
  }

  const userStore = useUserStore()
  const loading = ref(false)
  const loadingDetail = ref(false)
  const claiming = ref(false)
  const deciding = ref(false)
  const queue = ref<ManualPaymentSubmission[]>([])
  const current = ref<any>(null)
  const decisionRows = ref<DecisionRow[]>([])
  const confirmedReference = ref('')
  const queueFilter = ref<'all' | 'mine'>('all')

  // Modals
  const proofModalVisible = ref(false)
  const rejectDialogVisible = ref(false)
  const rejectionReason = ref('')

  const quickRejectChips = [
    'Deposit slip image is blurry or cut off',
    'Deposited amount does not match invoice balance',
    'Bank transaction reference not found in bank statement',
    'Payment made to incorrect bank account number',
    'Withholding Certificate (BIR 2307) missing or expired'
  ]

  const isAssignedToMe = computed(
    () => Number(current.value?.assigned_to_user_id) === Number(userStore.info?.userId)
  )

  const requiresCheckClearance = computed(
    () =>
      current.value?.payment_group?.payment_method === 'CHECK_DEPOSIT' &&
      current.value?.payment_group?.check_clearance_status !== 'CLEARED'
  )

  const canMarkCheckDishonored = computed(
    () =>
      current.value?.payment_group?.payment_method === 'CHECK_DEPOSIT' &&
      current.value?.payment_group?.check_clearance_status !== 'DISHONORED'
  )

  const myClaimedSubmissions = computed(() =>
    queue.value.filter(
      (item) => Number(item.assigned_to_user_id) === Number(userStore.info?.userId)
    )
  )

  const filteredQueue = computed(() => {
    if (queueFilter.value === 'mine') {
      return myClaimedSubmissions.value
    }
    return queue.value
  })

  const totalAllocatedAmount = computed(() => {
    return decisionRows.value.reduce((total, row) => {
      const cash = parseFloat(row.cash_amount) || 0
      const wht = parseFloat(row.withholding_amount) || 0
      return total + cash + wht
    }, 0)
  })

  async function loadQueue() {
    loading.value = true
    try {
      const response = await fetchTellerPaymentSubmissions()
      queue.value = response.data || []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load payment proof queue.')
    } finally {
      loading.value = false
    }
  }

  async function openSubmission(row: ManualPaymentSubmission | null) {
    if (!row) return
    loadingDetail.value = true
    try {
      current.value = await fetchTellerPaymentSubmission(row.id)
      confirmedReference.value =
        current.value.confirmed_reference || current.value.declared_reference || ''
      decisionRows.value = current.value.items.map((item: any) => ({
        invoice_id: item.invoice_id,
        invoice_number: item.invoice?.invoice_number || `Bill #${item.invoice_id}`,
        requested_amount: item.requested_amount,
        cash_amount: item.requested_amount,
        certificate_id: '',
        withholding_amount: ''
      }))
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load payment proof detail.')
    } finally {
      loadingDetail.value = false
    }
  }

  async function claimNext() {
    claiming.value = true
    try {
      const submission = await claimNextPaymentSubmission()
      if (!submission) {
        ElMessage.info('No proof is waiting in the queue.')
        return
      }
      ElMessage.success('Oldest payment proof claimed.')
      await loadQueue()
      await openSubmission(submission)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to claim payment proof.')
    } finally {
      claiming.value = false
    }
  }

  function reviewAllocations() {
    return decisionRows.value.map((row) => ({
      invoice_id: row.invoice_id,
      cash_amount: row.cash_amount || '0.00',
      withholding_applications:
        row.certificate_id && row.withholding_amount
          ? [{ certificate_id: Number(row.certificate_id), amount: row.withholding_amount }]
          : []
    }))
  }

  const proofFetcher = async () => {
    if (!current.value?.proof_file_id) {
      throw new Error('No proof file available.')
    }
    const blob = await downloadPrivateFile(
      current.value.proof_file_id,
      current.value.proof_file?.current_version
    )
    return { blob, filename: `proof-${current.value.id}` }
  }

  async function approveCurrent() {
    if (!current.value) return
    try {
      await ElMessageBox.confirm(
        'This will atomically post an official collection receipt using the confirmed amounts. Continue only after bank verification.',
        'Approve Payment Proof',
        { type: 'warning', confirmButtonText: 'Confirm & Post Receipt' }
      )
      deciding.value = true
      const updated = await approvePaymentSubmission(current.value.id, {
        expected_version: current.value.lock_version,
        confirmed_reference: confirmedReference.value || undefined,
        allocations: reviewAllocations()
      })
      ElMessage.success(
        `Proof approved. Official Collection Receipt #${updated.receipt?.receipt_number || ''} was posted.`
      )
      await loadQueue()
      current.value = updated
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to approve payment proof.')
    } finally {
      deciding.value = false
    }
  }

  async function recordCheckClearance(clearanceStatus: 'CLEARED' | 'DISHONORED') {
    if (!current.value?.payment_group) return
    try {
      const { value } = await ElMessageBox.prompt(
        clearanceStatus === 'CLEARED'
          ? 'Record the bank reconciliation detail that confirms this deposited check has cleared.'
          : 'Record the bank reconciliation detail that confirms this deposited check was dishonored.',
        clearanceStatus === 'CLEARED' ? 'Record Check Clearance' : 'Mark Check Dishonored',
        {
          inputPlaceholder: 'Reconciliation note or bank reference',
          inputValidator: (value) =>
            value?.trim().length >= 3 ? true : 'Enter at least 3 characters.',
          confirmButtonText: clearanceStatus === 'CLEARED' ? 'Record Cleared' : 'Record Dishonored',
          type: clearanceStatus === 'CLEARED' ? 'success' : 'warning'
        }
      )
      deciding.value = true
      const updated = await recordPaymentCheckClearance(current.value.id, {
        expected_submission_version: current.value.lock_version,
        expected_payment_group_version: current.value.payment_group.lock_version,
        clearance_status: clearanceStatus,
        notes: value.trim()
      })
      ElMessage.success(
        clearanceStatus === 'CLEARED'
          ? 'Check clearance recorded. You may now approve the proof if all other checks pass.'
          : 'Dishonored check recorded. Do not post a receipt; return the proof for correction when appropriate.'
      )
      await loadQueue()
      current.value = updated
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close') {
        ElMessage.error(error?.message || 'Unable to record the check-clearance decision.')
      }
    } finally {
      deciding.value = false
    }
  }

  function openRejectModal() {
    rejectionReason.value = ''
    rejectDialogVisible.value = true
  }

  async function confirmReject() {
    if (!current.value || !rejectionReason.value.trim()) return
    deciding.value = true
    try {
      const updated = await rejectPaymentSubmission(
        current.value.id,
        current.value.lock_version,
        rejectionReason.value.trim()
      )
      ElMessage.success('Proof returned to the customer for correction.')
      rejectDialogVisible.value = false
      await loadQueue()
      current.value = updated
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to reject payment proof.')
    } finally {
      deciding.value = false
    }
  }

  onMounted(loadQueue)
</script>

<style scoped>
  .teller-review-container {
    animation: fadeIn 0.25s ease-out;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(4px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>
