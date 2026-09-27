<template>
  <div class="customer-portal-container max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <!-- Header Banner -->
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-sm"
    >
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight">My Bills &amp; Payments</h1>
          <span
            class="bg-blue-500/20 text-blue-200 border border-blue-400/30 text-xs px-2.5 py-0.5 rounded-full font-medium"
            >Customer Portal</span
          >
        </div>
        <p class="text-slate-300 text-sm max-w-2xl">
          Review your posted port service invoices, select bills to pay, and upload bank deposit
          proofs for teller verification.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <ElButton
          :loading="loading"
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="loadWorkspace"
        >
          <ElIcon class="mr-1"><Refresh /></ElIcon> Refresh
        </ElButton>
      </div>
    </div>

    <!-- Summary Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- Total Outstanding Due -->
      <div
        class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between"
      >
        <div>
          <span class="text-xs font-medium text-slate-500 uppercase tracking-wider"
            >Total Outstanding</span
          >
          <div class="mt-1">
            <MoneyDisplay :value="totalOutstanding" size="2xl" highlight="due" />
          </div>
          <span class="text-xs text-slate-400 mt-1 block"
            >{{ payableBillsCount }} payable bill{{ payableBillsCount === 1 ? '' : 's' }}</span
          >
        </div>
        <div
          class="h-12 w-12 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400"
        >
          <ElIcon :size="24"><Wallet /></ElIcon>
        </div>
      </div>

      <!-- Pending Verification -->
      <div
        class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between"
      >
        <div>
          <span class="text-xs font-medium text-slate-500 uppercase tracking-wider"
            >In Review with Teller</span
          >
          <div class="mt-1 flex items-baseline gap-2">
            <span class="text-2xl font-bold text-slate-800 dark:text-slate-100">{{
              pendingSubmissionsCount
            }}</span>
            <span class="text-xs text-slate-400"
              >submission{{ pendingSubmissionsCount === 1 ? '' : 's' }}</span
            >
          </div>
          <span class="text-xs text-amber-600 dark:text-amber-400 mt-1 block"
            >Awaiting teller clearance</span
          >
        </div>
        <div
          class="h-12 w-12 rounded-xl bg-sky-50 dark:bg-sky-950/50 flex items-center justify-center text-sky-600 dark:text-sky-400"
        >
          <ElIcon :size="24"><Clock /></ElIcon>
        </div>
      </div>

      <!-- Account Details -->
      <div
        class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex items-center justify-between"
      >
        <div class="min-w-0">
          <span class="text-xs font-medium text-slate-500 uppercase tracking-wider"
            >Linked Account</span
          >
          <div class="mt-1 font-semibold text-slate-800 dark:text-slate-100 truncate text-base">
            {{ profileName || (activeCustomerId ? `Customer #${activeCustomerId}` : 'Not linked') }}
          </div>
          <div class="mt-1 flex items-center gap-2">
            <StatusTag :status="activeCustomerId ? 'ACTIVE' : 'PENDING'" size="small" />
            <span v-if="accountTin" class="text-xs text-slate-400 font-mono"
              >TIN: {{ accountTin }}</span
            >
          </div>
        </div>
        <div
          class="h-12 w-12 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400"
        >
          <ElIcon :size="24"><User /></ElIcon>
        </div>
      </div>
    </div>

    <!-- Bills Table Card -->
    <ElCard
      shadow="never"
      class="!rounded-xl !border-slate-200/80 dark:!border-slate-800"
      v-loading="loading"
    >
      <template #header>
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
          <div>
            <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100"
              >Payable Port Invoices</h2
            >
            <p class="text-xs text-slate-500 mt-0.5"
              >Select full bill balances, receive frozen bank-transfer or check-deposit
              instructions, then upload proof for teller verification.</p
            >
          </div>
          <div class="flex items-center gap-3">
            <ElInput
              v-model="searchQuery"
              placeholder="Search by Bill No..."
              clearable
              size="default"
              class="!w-64"
            >
              <template #prefix
                ><ElIcon><Search /></ElIcon
              ></template>
            </ElInput>
          </div>
        </div>
      </template>

      <ElEmpty
        v-if="!activeCustomerId"
        description="No active customer account is linked to your login. Contact administrator."
      />
      <ElEmpty
        v-else-if="filteredBills.length === 0 && !loading"
        description="No open payable bills found for your account."
      />
      <ElTable
        v-else
        :data="filteredBills"
        @selection-change="selectedBills = $event"
        class="w-full text-sm"
        stripe
      >
        <ElTableColumn type="selection" width="48" :selectable="isPayable" />
        <ElTableColumn prop="invoice_number" label="Bill Number" min-width="170">
          <template #default="{ row }">
            <span class="font-semibold text-slate-800 dark:text-slate-200 font-mono">{{
              row.invoice_number
            }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="business_date" label="Billing Date" min-width="130">
          <template #default="{ row }">
            <span class="text-slate-600 dark:text-slate-300">{{ row.business_date }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Total Billed" min-width="140" align="right">
          <template #default="{ row }">
            <MoneyDisplay :value="row.total_charge_amount" :currency="row.currency" size="sm" />
          </template>
        </ElTableColumn>
        <ElTableColumn label="Applied / Paid" min-width="140" align="right">
          <template #default="{ row }">
            <MoneyDisplay
              :value="row.applied_amount"
              :currency="row.currency"
              size="sm"
              highlight="paid"
            />
          </template>
        </ElTableColumn>
        <ElTableColumn label="Current Balance" min-width="150" align="right">
          <template #default="{ row }">
            <MoneyDisplay
              :value="row.outstanding_amount"
              :currency="row.currency"
              size="base"
              highlight="due"
              weight="bold"
            />
          </template>
        </ElTableColumn>
        <ElTableColumn label="Receipt History" min-width="170">
          <template #default="{ row }">
            <span v-if="!row.receipt_history?.length" class="text-xs text-slate-400">None</span>
            <div v-else class="flex flex-wrap gap-1">
              <ElTag
                v-for="receipt in row.receipt_history"
                :key="receipt.receipt_id"
                size="small"
                type="success"
                effect="plain"
                class="font-mono"
              >
                {{ receipt.receipt_number }}
              </ElTag>
            </div>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <!-- Floating Sticky Payment Action Bar -->
    <Transition name="fade-up">
      <div
        v-if="selectedBills.length > 0"
        class="fixed bottom-6 inset-x-0 mx-auto max-w-4xl z-40 bg-slate-900/95 backdrop-blur text-white px-6 py-4 rounded-2xl shadow-2xl border border-slate-700/80 flex flex-col sm:flex-row items-center justify-between gap-4 animate-in"
      >
        <div class="flex items-center gap-4">
          <div
            class="h-10 w-10 rounded-full bg-amber-500/20 text-amber-400 flex items-center justify-center font-bold"
          >
            {{ selectedBills.length }}
          </div>
          <div>
            <div class="text-xs text-slate-400 uppercase tracking-wider font-medium"
              >Selected bills</div
            >
            <div class="flex items-baseline gap-2">
              <MoneyDisplay :value="selectedBillTotal" size="xl" class="text-white" />
              <span class="text-xs text-slate-400">Invoice total</span>
            </div>
            <p v-if="selectedWithholdingCents > 0" class="mt-1 text-xs text-slate-300">
              Approved 2307 {{ formatAmount(selectedWithholding) }} · Cash to deposit
              {{ formatAmount(selectedCashDue) }}
            </p>
            <p v-else class="mt-1 text-xs text-slate-400">
              No approved BIR 2307 applies to these bill dates. Deposit the invoice total.
            </p>
          </div>
        </div>

        <div class="flex flex-wrap items-center gap-3 w-full sm:w-auto justify-end">
          <ElButton text class="!text-slate-300 hover:!text-white" @click="selectedBills = []"
            >Clear</ElButton
          >
          <ElSelect v-model="paymentMethod" size="large" class="!w-44" aria-label="Payment method">
            <ElOption label="Bank transfer" value="BANK_TRANSFER" />
            <ElOption label="Check deposit" value="CHECK_DEPOSIT" />
          </ElSelect>
          <ElButton
            type="primary"
            size="large"
            class="!px-6 !font-semibold shadow-lg shadow-blue-500/25"
            :loading="issuingInstruction"
            @click="issueInstruction"
          >
            Get Payment Instructions <ElIcon class="ml-1"><ArrowRight /></ElIcon>
          </ElButton>
        </div>
      </div>
    </Transition>

    <ElCard
      v-if="activeInstruction"
      shadow="never"
      class="!rounded-xl !border-blue-200/80 dark:!border-blue-900/70 bg-blue-50/40 dark:bg-blue-950/15"
    >
      <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100"
              >Manual Payment Instruction #{{ activeInstruction.id }}</h2
            >
            <ElTag type="warning" effect="plain">{{
              activeInstruction.status.replaceAll('_', ' ')
            }}</ElTag>
            <ElTag
              v-if="activeInstruction.first_proof_was_timely === false"
              type="danger"
              effect="plain"
              >Proof was submitted after the deadline</ElTag
            >
            <ElTag effect="plain">{{ paymentMethodLabel(activeInstruction.payment_method) }}</ElTag>
            <ElTag
              v-if="activeInstruction.payment_method === 'CHECK_DEPOSIT'"
              :type="activeInstruction.check_clearance_status === 'CLEARED' ? 'success' : 'warning'"
              effect="plain"
              >Check {{ activeInstruction.check_clearance_status.toLowerCase() }}</ElTag
            >
          </div>
          <p class="mt-2 whitespace-pre-line text-sm text-slate-700 dark:text-slate-300">{{
            activeInstruction.manual_instructions_snapshot
          }}</p>
          <div class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-xs text-slate-500">
            <span
              >Bill total:
              <strong>{{
                formatAmount(activeInstruction.gross_selected_amount, activeInstruction.currency)
              }}</strong></span
            >
            <span v-if="instructionWithholdingCents > 0">
              Approved 2307:
              <strong>{{
                formatAmount(instructionWithholding, activeInstruction.currency)
              }}</strong>
              · Cash to deposit:
              <strong>{{ formatAmount(instructionCashDue, activeInstruction.currency) }}</strong>
            </span>
            <span>Issued: {{ activeInstruction.instruction_issued_at }}</span>
            <span
              >Deadline: <strong>{{ activeInstruction.payment_deadline_at }}</strong></span
            >
            <span v-if="activeInstruction.review_due_at"
              >Review target: <strong>{{ activeInstruction.review_due_at }}</strong></span
            >
            <span v-if="activeInstruction.correction_due_at"
              >Correction deadline: <strong>{{ activeInstruction.correction_due_at }}</strong></span
            >
          </div>
          <p class="mt-2 text-xs text-slate-500"
            >Deposit the cash amount. The invoice total stays on the bill. When an approved BIR
            2307 covers these dates, the teller applies the unused certificate after confirming the
            cash. The deadline is fixed when the instruction is issued. A late proof remains
            available for reconciliation but is marked for teller review; it never erases the bill
            debt.</p
          >
        </div>
        <ElButton
          v-if="activeInstruction.status === 'MANUAL_INSTRUCTION_ISSUED'"
          type="primary"
          class="shrink-0"
          @click="openSubmitDrawer = true"
          >Upload proof</ElButton
        >
      </div>
    </ElCard>

    <!-- Payment Submissions History Card -->
    <ElCard shadow="never" class="!rounded-xl !border-slate-200/80 dark:!border-slate-800">
      <template #header>
        <div class="flex items-center justify-between">
          <div>
            <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100"
              >Payment Proof Submissions</h2
            >
            <p class="text-xs text-slate-500 mt-0.5"
              >Track teller review progress and official collection receipts.</p
            >
          </div>
          <ElTag v-if="submissions.length > 0" size="small" effect="plain"
            >{{ submissions.length }} total</ElTag
          >
        </div>
      </template>

      <ElEmpty v-if="submissions.length === 0" description="No payment submissions recorded yet." />
      <ElTable v-else :data="submissions" class="w-full text-sm" stripe>
        <ElTableColumn prop="id" label="ID" width="90">
          <template #default="{ row }">
            <span class="font-mono text-xs font-semibold text-slate-600 dark:text-slate-400"
              >#{{ row.id }}</span
            >
          </template>
        </ElTableColumn>
        <ElTableColumn prop="initial_submitted_at" label="Submitted Date" min-width="160">
          <template #default="{ row }">
            <span class="text-xs text-slate-600 dark:text-slate-300">{{
              row.initial_submitted_at
            }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Allocated Bills" min-width="190">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <ElTag
                v-for="item in row.items"
                :key="item.invoice_id"
                size="small"
                effect="plain"
                class="font-mono text-xs"
              >
                {{ item.invoice?.invoice_number || `Bill #${item.invoice_id}` }}
              </ElTag>
            </div>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Requested Amount" min-width="140" align="right">
          <template #default="{ row }">
            <MoneyDisplay :value="row.requested_amount" :currency="row.currency" size="sm" />
          </template>
        </ElTableColumn>
        <ElTableColumn label="Status" width="140">
          <template #default="{ row }">
            <StatusTag :status="row.status" />
          </template>
        </ElTableColumn>
        <ElTableColumn label="Teller Review / Receipt" min-width="220">
          <template #default="{ row }">
            <div
              v-if="row.status === 'REJECTED'"
              class="text-rose-600 text-xs font-medium flex items-start gap-1"
            >
              <ElIcon class="mt-0.5"><Warning /></ElIcon>
              <span>{{ row.rejection_reason || 'Correction needed by teller' }}</span>
            </div>
            <div
              v-else-if="row.receipt"
              class="flex items-center gap-1.5 text-emerald-600 text-xs font-semibold"
            >
              <ElIcon><CircleCheckFilled /></ElIcon>
              <span>OR #{{ row.receipt.receipt_number }} Issued</span>
            </div>
            <span v-else class="text-xs text-slate-400 flex items-center gap-1">
              <ElIcon><Clock /></ElIcon> Waiting for teller claim
            </span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Actions" width="160" align="right" fixed="right">
          <template #default="{ row }">
            <div class="flex items-center justify-end gap-1.5">
              <ElButton size="small" text type="primary" @click="viewProof(row)">
                <ElIcon class="mr-1"><View /></ElIcon> Proof
              </ElButton>
              <ElButton
                v-if="row.status === 'REJECTED'"
                size="small"
                type="warning"
                plain
                @click="openResubmitModal(row)"
              >
                Resubmit
              </ElButton>
            </div>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <!-- Upload Payment Proof Drawer -->
    <ElDrawer
      v-model="openSubmitDrawer"
      title="Upload Bank Payment Proof"
      size="520px"
      destroy-on-close
      class="rounded-l-2xl"
    >
      <div class="space-y-5">
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="Important Information"
          description="The selected bills and payment deadline were frozen in your instruction. Uploading proof is not payment; a teller verifies funds before the official collection receipt is issued."
        />

        <div
          class="bg-slate-50 dark:bg-slate-800/50 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700 space-y-2"
        >
          <div class="flex justify-between text-xs text-slate-500">
            <span>Instruction #{{ activeInstruction?.id }}</span>
            <span class="font-semibold text-slate-700 dark:text-slate-300"
              >{{ activeInstruction?.items.length || 0 }} bills</span
            >
          </div>
          <div class="flex justify-between text-sm font-semibold">
            <span>Selected Balance</span>
            <MoneyDisplay
              :value="activeInstruction?.gross_selected_amount || '0.00'"
              :currency="activeInstruction?.currency || 'PHP'"
              size="lg"
              highlight="due"
            />
          </div>
        </div>

        <div class="space-y-4">
          <div>
            <label
              class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1"
            >
              Bank / Deposit Reference (Optional)
            </label>
            <ElInput
              v-model="declaredReference"
              maxlength="128"
              placeholder="e.g. Bank Ref, Check No., or Branch Transaction Code"
              clearable
            />
          </div>

          <div>
            <label
              class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1"
            >
              Deposit Slip / Payment Confirmation File <span class="text-rose-500">*</span>
            </label>
            <ElUpload
              drag
              :auto-upload="false"
              :limit="1"
              :on-change="onProofSelected"
              :on-remove="clearProof"
              accept="application/pdf,image/*"
              class="w-full"
            >
              <ElIcon class="el-icon--upload"><UploadFilled /></ElIcon>
              <div class="el-upload__text">
                Drop bank receipt here or <em>click to browse</em>
              </div>
              <template #tip>
                <div class="text-xs text-slate-400 mt-2">
                  Accepts PDF, JPG, PNG up to maximum configured size.
                </div>
              </template>
            </ElUpload>
          </div>
        </div>
      </div>

      <template #footer>
        <div
          class="flex items-center justify-end gap-3 pt-4 border-t border-slate-100 dark:border-slate-800"
        >
          <ElButton @click="openSubmitDrawer = false">Cancel</ElButton>
          <ElButton
            type="primary"
            :loading="submitting"
            :disabled="!proofFile || !activeInstruction"
            @click="submitProof"
          >
            Submit for Verification
          </ElButton>
        </div>
      </template>
    </ElDrawer>

    <!-- Resubmit Modal -->
    <ElDialog
      v-model="resubmitModalVisible"
      title="Resubmit Corrected Payment Proof"
      width="480px"
      destroy-on-close
    >
      <div v-if="targetResubmission" class="space-y-4">
        <ElAlert
          type="warning"
          :closable="false"
          show-icon
          :title="`Prior Rejection Reason: ${targetResubmission.rejection_reason || 'Document correction needed'}`"
        />
        <p class="text-xs text-slate-500">
          Your submission will retain its original position in the teller priority queue.
        </p>
        <div>
          <label
            class="block text-xs font-semibold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1"
          >
            New Proof File <span class="text-rose-500">*</span>
          </label>
          <ElUpload
            :auto-upload="false"
            :limit="1"
            :on-change="onProofSelected"
            :on-remove="clearProof"
            accept="application/pdf,image/*"
          >
            <ElButton plain
              ><ElIcon class="mr-1"><UploadFilled /></ElIcon> Choose New File</ElButton
            >
          </ElUpload>
        </div>
      </div>

      <template #footer>
        <div class="flex justify-end gap-2">
          <ElButton @click="resubmitModalVisible = false">Cancel</ElButton>
          <ElButton
            type="primary"
            :loading="submitting"
            :disabled="!proofFile"
            @click="confirmResubmit"
          >
            Confirm Resubmission
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <!-- Proof Viewer Modal -->
    <ProofViewerModal
      v-model="proofModalVisible"
      :title="`Payment Proof #${activeViewingSubmission?.id || ''}`"
      :file-id="activeViewingSubmission?.proof_file_id"
      :fetcher="proofFetcher"
    />
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage, type UploadFile } from 'element-plus'
  import {
    Refresh,
    Wallet,
    Clock,
    User,
    Search,
    ArrowRight,
    Warning,
    CircleCheckFilled,
    View,
    UploadFilled
  } from '@element-plus/icons-vue'
  import MoneyDisplay from '@/components/business/MoneyDisplay.vue'
  import StatusTag from '@/components/business/StatusTag.vue'
  import ProofViewerModal from '@/components/business/ProofViewerModal.vue'
  import {
    fetchDocumentTypes,
    uploadPrivateFile,
    downloadPrivateFile,
    type DocumentTypeItem
  } from '@/api/documentRequirements'
  import { fetchPortalProfile } from '@/api/registration'
  import {
    fetchPaymentSubmissions,
    fetchPortalPaymentGroups,
    fetchPortalBills,
    issueManualPaymentInstruction,
    resubmitPaymentProof,
    submitPaymentProof,
    type ManualPaymentSubmission,
    type PaymentGroup,
    type PortalBill
  } from '@/api/payments'
  import { fetchCustomerWithholding, type WithholdingCertificate } from '@/api/taxEvidence'
  import {
    fromCents,
    planWithholding,
    toCents,
    usableWithholdingCertificates
  } from '@/utils/billing/withholdingPlan'

  defineOptions({ name: 'CustomerPaymentPortal' })

  const loading = ref(false)
  const submitting = ref(false)
  const activeCustomerId = ref<number | null>(null)
  const profileName = ref('')
  const accountTin = ref('')
  const paymentProofType = ref<DocumentTypeItem | null>(null)
  const bills = ref<PortalBill[]>([])
  const submissions = ref<ManualPaymentSubmission[]>([])
  const paymentGroups = ref<PaymentGroup[]>([])
  const selectedBills = ref<PortalBill[]>([])
  const approvedCertificates = ref<WithholdingCertificate[]>([])
  const searchQuery = ref('')
  const declaredReference = ref('')
  const proofFile = ref<File | null>(null)
  const issuingInstruction = ref(false)
  const paymentMethod = ref<'BANK_TRANSFER' | 'CHECK_DEPOSIT'>('BANK_TRANSFER')

  // Drawers & Modals
  const openSubmitDrawer = ref(false)
  const resubmitModalVisible = ref(false)
  const targetResubmission = ref<ManualPaymentSubmission | null>(null)
  const proofModalVisible = ref(false)
  const activeViewingSubmission = ref<ManualPaymentSubmission | null>(null)

  const payableBillsCount = computed(
    () => bills.value.filter((b) => Number(b.outstanding_amount) > 0).length
  )

  const totalOutstanding = computed(() =>
    bills.value.reduce((total, bill) => total + Number(bill.outstanding_amount || 0), 0)
  )

  const pendingSubmissionsCount = computed(
    () =>
      submissions.value.filter((s) => s.status === 'SUBMITTED' || s.status === 'IN_REVIEW').length
  )

  const selectedTotal = computed(() =>
    selectedBills.value.reduce((total, bill) => total + Number(bill.outstanding_amount || 0), 0)
  )

  const usableCertificates = computed(() =>
    usableWithholdingCertificates(approvedCertificates.value)
  )

  const selectedPlan = computed(() =>
    planWithholding(
      selectedBills.value.map((bill) => ({
        invoiceId: bill.id,
        businessDate: bill.business_date,
        outstanding: bill.outstanding_amount
      })),
      usableCertificates.value
    )
  )

  const selectedBillTotal = computed(() =>
    fromCents(
      selectedBills.value.reduce((total, bill) => total + toCents(bill.outstanding_amount), 0)
    )
  )

  const selectedWithholding = computed(() =>
    fromCents(
      selectedPlan.value.reduce((total, line) => total + toCents(line.withholdingAmount), 0)
    )
  )

  const selectedWithholdingCents = computed(() => toCents(selectedWithholding.value))

  const selectedCashDue = computed(() =>
    fromCents(selectedPlan.value.reduce((total, line) => total + toCents(line.cashAmount), 0))
  )

  const instructionPlan = computed(() => {
    const group = activeInstruction.value
    if (!group) return []
    return planWithholding(
      (group.items || []).map((item) => ({
        invoiceId: item.invoice_id,
        businessDate:
          item.invoice?.business_date ||
          bills.value.find((bill) => bill.id === item.invoice_id)?.business_date ||
          '',
        outstanding: item.requested_amount
      })),
      usableCertificates.value
    )
  })

  const instructionWithholding = computed(() =>
    fromCents(
      instructionPlan.value.reduce((total, line) => total + toCents(line.withholdingAmount), 0)
    )
  )

  const instructionWithholdingCents = computed(() => toCents(instructionWithholding.value))

  const instructionCashDue = computed(() =>
    fromCents(instructionPlan.value.reduce((total, line) => total + toCents(line.cashAmount), 0))
  )

  const activeInstruction = computed(
    () =>
      paymentGroups.value.find((group) =>
        ['MANUAL_INSTRUCTION_ISSUED', 'PROOF_SUBMITTED', 'IN_REVIEW', 'PROOF_REJECTED'].includes(
          group.status
        )
      ) || null
  )

  const filteredBills = computed(() => {
    if (!searchQuery.value.trim()) return bills.value
    const q = searchQuery.value.toLowerCase().trim()
    return bills.value.filter(
      (b) => b.invoice_number.toLowerCase().includes(q) || b.business_date.includes(q)
    )
  })

  function isPayable(bill: PortalBill) {
    return !activeInstruction.value && Number(bill.outstanding_amount) > 0
  }

  function onProofSelected(uploadFile: UploadFile) {
    proofFile.value = uploadFile.raw || null
  }

  function clearProof() {
    proofFile.value = null
  }

  function formatAmount(amount: string | number, currency = 'PHP') {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(
      Number(amount || 0)
    )
  }

  function paymentMethodLabel(method: PaymentGroup['payment_method']) {
    return method === 'CHECK_DEPOSIT' ? 'Check deposit' : 'Bank transfer'
  }

  async function loadWorkspace() {
    loading.value = true
    try {
      const profile = await fetchPortalProfile()
      profileName.value = profile.user?.name || ''
      const link = profile.customer_links?.find((item: any) => item.is_active)
      activeCustomerId.value = link?.customer_id || null
      accountTin.value = link?.customer?.tin || ''
      if (!activeCustomerId.value) return

      const [billList, history, groups, documentTypes, withholdingPage] = await Promise.all([
        fetchPortalBills(activeCustomerId.value),
        fetchPaymentSubmissions(activeCustomerId.value),
        fetchPortalPaymentGroups(activeCustomerId.value),
        fetchDocumentTypes({ purpose: 'PAYMENT_PROOF', is_active: true }),
        fetchCustomerWithholding()
      ])
      bills.value = billList
      submissions.value = history.data || []
      paymentGroups.value = groups
      paymentProofType.value = documentTypes[0] || null
      approvedCertificates.value = withholdingPage.data || []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load payment information.')
    } finally {
      loading.value = false
    }
  }

  async function issueInstruction() {
    if (!activeCustomerId.value || selectedBills.value.length === 0) return
    if (activeInstruction.value) {
      ElMessage.info(
        'Use or resolve your existing payment instruction before creating another one.'
      )
      return
    }
    issuingInstruction.value = true
    try {
      const group = await issueManualPaymentInstruction({
        customer_id: activeCustomerId.value,
        payment_method: paymentMethod.value,
        allocations: selectedBills.value.map((bill) => ({
          invoice_id: bill.id,
          expected_invoice_lock_version: bill.lock_version,
          requested_amount: bill.outstanding_amount
        }))
      })
      paymentGroups.value = [group, ...paymentGroups.value]
      selectedBills.value = []
      openSubmitDrawer.value = true
      ElMessage.success('Manual payment instructions and deadline are ready.')
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to issue payment instructions.')
    } finally {
      issuingInstruction.value = false
    }
  }

  async function uploadSelectedProof(): Promise<number> {
    if (!proofFile.value || !paymentProofType.value)
      throw new Error(
        'Select a payment-proof file and ensure an active Payment Proof document type is configured.'
      )
    const data = new FormData()
    data.append('file', proofFile.value)
    data.append('document_type_id', String(paymentProofType.value.id))
    const uploaded = await uploadPrivateFile(data)
    return uploaded.id
  }

  async function submitProof() {
    if (!activeInstruction.value) return
    submitting.value = true
    try {
      const proofFileId = await uploadSelectedProof()
      await submitPaymentProof({
        payment_group_id: activeInstruction.value.id,
        proof_file_id: proofFileId,
        declared_reference: declaredReference.value || undefined
      })
      ElMessage.success('Payment proof submitted for teller verification.')
      selectedBills.value = []
      declaredReference.value = ''
      clearProof()
      openSubmitDrawer.value = false
      await loadWorkspace()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to submit payment proof.')
    } finally {
      submitting.value = false
    }
  }

  function openResubmitModal(submission: ManualPaymentSubmission) {
    targetResubmission.value = submission
    clearProof()
    resubmitModalVisible.value = true
  }

  async function confirmResubmit() {
    if (!targetResubmission.value) return
    submitting.value = true
    try {
      const proofFileId = await uploadSelectedProof()
      await resubmitPaymentProof(targetResubmission.value.id, proofFileId)
      ElMessage.success('Corrected proof returned to the teller queue with its original priority.')
      clearProof()
      resubmitModalVisible.value = false
      targetResubmission.value = null
      await loadWorkspace()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to resubmit payment proof.')
    } finally {
      submitting.value = false
    }
  }

  function viewProof(submission: ManualPaymentSubmission) {
    activeViewingSubmission.value = submission
    proofModalVisible.value = true
  }

  const proofFetcher = async () => {
    if (!activeViewingSubmission.value?.proof_file_id) {
      throw new Error('No proof file available.')
    }
    const blob = await downloadPrivateFile(activeViewingSubmission.value.proof_file_id)
    return { blob, filename: `proof-${activeViewingSubmission.value.id}` }
  }

  onMounted(loadWorkspace)
</script>

<style scoped>
  .customer-portal-container {
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

  .fade-up-enter-active,
  .fade-up-leave-active {
    transition: all 0.3s cubic-bezier(0.16, 1, 0.3, 1);
  }

  .fade-up-enter-from,
  .fade-up-leave-to {
    opacity: 0;
    transform: translateY(20px);
  }
</style>
