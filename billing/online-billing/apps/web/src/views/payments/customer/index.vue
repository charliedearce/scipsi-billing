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

    <!-- Bills: unpaid vs paid -->
    <ElCard shadow="never" class="!rounded-xl !border-g-200 dark:!border-g-300" v-loading="loading">
      <template #header>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-base font-semibold text-g-900">Port invoices</h2>
            <p class="mt-0.5 text-xs text-g-500">
              Unpaid bills can be selected for payment instructions. Paid bills stay view-only with
              receipt history.
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <ElRadioGroup v-model="billsTab" size="small" @change="onBillsTabChange">
              <ElRadioButton value="unpaid">Unpaid ({{ unpaidBills.length }})</ElRadioButton>
              <ElRadioButton value="paid">Paid ({{ paidBills.length }})</ElRadioButton>
            </ElRadioGroup>
            <ElInput
              v-model="billsSearchQuery"
              :placeholder="
                billsTab === 'unpaid' ? 'Search unpaid bill no…' : 'Search paid bill no…'
              "
              clearable
              class="!w-56"
              @input="billsPage = 1"
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
        v-else-if="pagedBills.length === 0 && !loading"
        :description="
          billsTab === 'unpaid'
            ? 'No unpaid bills for your account.'
            : 'No paid bills for your account yet.'
        "
      />
      <template v-else>
        <ElTable
          :data="pagedBills"
          class="w-full text-sm"
          stripe
          @selection-change="onUnpaidSelectionChange"
        >
          <ElTableColumn
            v-if="billsTab === 'unpaid'"
            type="selection"
            width="48"
            :selectable="isPayable"
          />
          <ElTableColumn prop="invoice_number" label="Bill Number" min-width="170">
            <template #default="{ row }">
              <button
                type="button"
                class="text-left font-mono font-semibold text-theme hover:underline"
                @click="openBillDetail(row)"
              >
                {{ row.invoice_number }}
              </button>
            </template>
          </ElTableColumn>
          <ElTableColumn label="" width="88" align="right">
            <template #default="{ row }">
              <ElButton size="small" text type="primary" @click="openBillDetail(row)">
                <ElIcon class="mr-1"><View /></ElIcon> Details
              </ElButton>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="business_date" label="Billing Date" min-width="130">
            <template #default="{ row }">
              <span class="text-g-700">{{ row.business_date }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Requested" min-width="145">
            <template #default="{ row }">
              <span class="text-xs text-g-600">{{
                formatDateTimeManila(row.timeline?.requested_at)
              }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Bill approved" min-width="145">
            <template #default="{ row }">
              <span class="text-xs text-g-600">{{
                formatDateTimeManila(row.timeline?.bill_approved_at)
              }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn v-if="billsTab === 'paid'" label="Paid" min-width="145">
            <template #default="{ row }">
              <span class="text-xs text-g-600">{{
                formatDateTimeManila(row.timeline?.paid_at)
              }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Total Billed" min-width="140" align="right">
            <template #default="{ row }">
              <MoneyDisplay :value="row.total_charge_amount" :currency="row.currency" size="sm" />
            </template>
          </ElTableColumn>
          <ElTableColumn
            v-if="billsTab === 'unpaid'"
            label="Applied / Paid"
            min-width="140"
            align="right"
          >
            <template #default="{ row }">
              <MoneyDisplay
                :value="row.applied_amount"
                :currency="row.currency"
                size="sm"
                highlight="paid"
              />
            </template>
          </ElTableColumn>
          <ElTableColumn
            :label="billsTab === 'unpaid' ? 'Current Balance' : 'Amount Paid'"
            min-width="150"
            align="right"
          >
            <template #default="{ row }">
              <MoneyDisplay
                v-if="billsTab === 'unpaid'"
                :value="row.outstanding_amount"
                :currency="row.currency"
                size="base"
                highlight="due"
                weight="bold"
              />
              <MoneyDisplay
                v-else
                :value="row.applied_amount"
                :currency="row.currency"
                size="base"
                highlight="paid"
                weight="bold"
              />
            </template>
          </ElTableColumn>
          <ElTableColumn label="Receipt History" min-width="220">
            <template #default="{ row }">
              <span v-if="!row.receipt_history?.length" class="text-xs text-g-500">None</span>
              <div v-else class="flex flex-col gap-1">
                <div
                  v-for="receipt in row.receipt_history"
                  :key="receipt.receipt_id"
                  class="flex flex-wrap items-center gap-1"
                >
                  <ElButton
                    size="small"
                    :type="
                      receipt.receipt_kind === 'ACKNOWLEDGEMENT'
                        ? 'warning'
                        : receipt.pdf_available
                          ? 'success'
                          : 'info'
                    "
                    plain
                    class="!font-mono"
                    :disabled="!receipt.pdf_available"
                    @click="viewReceiptPdf(receipt)"
                  >
                    {{ receipt.receipt_kind === 'ACKNOWLEDGEMENT' ? 'ACK' : 'OR' }}
                    {{ receipt.receipt_number }}
                  </ElButton>
                  <ElButton size="small" text type="primary" @click="openReceiptChat(receipt)">
                    Chat
                  </ElButton>
                </div>
              </div>
            </template>
          </ElTableColumn>
        </ElTable>
        <div class="mt-4 flex justify-end">
          <ElPagination
            v-model:current-page="billsPage"
            v-model:page-size="billsPageSize"
            :total="filteredTabBills.length"
            :page-sizes="[10, 20, 50]"
            layout="total, sizes, prev, pager, next"
            background
            small
          />
        </div>
      </template>
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
            <span>Issued: {{ formatDateTimeManila(activeInstruction.instruction_issued_at) }}</span>
            <span
              >Deadline:
              <strong>{{
                formatDateTimeManila(activeInstruction.payment_deadline_at)
              }}</strong></span
            >
            <span v-if="activeInstruction.review_due_at"
              >Review target:
              <strong>{{ formatDateTimeManila(activeInstruction.review_due_at) }}</strong></span
            >
            <span v-if="activeInstruction.correction_due_at"
              >Correction deadline:
              <strong>{{ formatDateTimeManila(activeInstruction.correction_due_at) }}</strong></span
            >
          </div>
          <p class="mt-2 text-xs text-slate-500"
            >Deposit the cash amount. The invoice total stays on the bill. When an approved BIR 2307
            covers these dates, the teller applies the unused certificate after confirming the cash.
            The deadline is fixed when the instruction is issued. A late proof remains available for
            reconciliation but is marked for teller review; it never erases the bill debt.</p
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
    <ElCard shadow="never" class="!rounded-xl !border-g-200 dark:!border-g-300">
      <template #header>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="text-base font-semibold text-g-900">Payment Proof Submissions</h2>
            <p class="mt-0.5 text-xs text-g-500"
              >Track teller review progress and official collection receipts.</p
            >
          </div>
          <div class="flex flex-wrap items-center gap-2">
            <ElInput
              v-model="proofSearchQuery"
              placeholder="Search proof ID, bill, OR, status…"
              clearable
              class="!w-64"
              @input="proofPage = 1"
            >
              <template #prefix
                ><ElIcon><Search /></ElIcon
              ></template>
            </ElInput>
            <ElTag v-if="filteredSubmissions.length > 0" size="small" effect="plain"
              >{{ filteredSubmissions.length }} shown</ElTag
            >
          </div>
        </div>
      </template>

      <ElEmpty v-if="submissions.length === 0" description="No payment submissions recorded yet." />
      <ElEmpty
        v-else-if="filteredSubmissions.length === 0"
        description="No payment proofs match your search."
      />
      <template v-else>
        <ElTable :data="pagedSubmissions" class="w-full text-sm" stripe>
          <ElTableColumn prop="id" label="ID" width="90">
            <template #default="{ row }">
              <span class="font-mono text-xs font-semibold text-g-700">#{{ row.id }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="initial_submitted_at" label="Submitted Date" min-width="160">
            <template #default="{ row }">
              <span class="text-xs text-g-600">{{ row.initial_submitted_at }}</span>
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
                class="flex items-start gap-1 text-xs font-medium text-error"
              >
                <ElIcon class="mt-0.5"><Warning /></ElIcon>
                <span>{{ row.rejection_reason || 'Correction needed by teller' }}</span>
              </div>
              <div
                v-else-if="row.receipt"
                class="flex flex-col gap-1 text-xs font-semibold text-success"
              >
                <div class="flex items-center gap-1.5">
                  <ElIcon><CircleCheckFilled /></ElIcon>
                  <span
                    >{{ row.receipt.receipt_kind === 'ACKNOWLEDGEMENT' ? 'ACK' : 'OR' }} #{{
                      row.receipt.receipt_number
                    }}
                    Issued</span
                  >
                </div>
                <ElButton
                  v-if="receiptPdfReady(row.receipt)"
                  size="small"
                  :type="row.receipt.receipt_kind === 'ACKNOWLEDGEMENT' ? 'warning' : 'success'"
                  plain
                  class="!w-fit"
                  @click="
                    viewReceiptPdf({
                      receipt_id: row.receipt.id,
                      receipt_number: row.receipt.receipt_number,
                      receipt_kind: row.receipt.receipt_kind,
                      pdf_available: true
                    })
                  "
                >
                  {{
                    row.receipt.receipt_kind === 'ACKNOWLEDGEMENT'
                      ? 'View / Download ACK'
                      : 'View / Download OR'
                  }}
                </ElButton>
              </div>
              <span v-else class="flex items-center gap-1 text-xs text-g-500">
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
        <div class="mt-4 flex justify-end">
          <ElPagination
            v-model:current-page="proofPage"
            v-model:page-size="proofPageSize"
            :total="filteredSubmissions.length"
            :page-sizes="[10, 20, 50]"
            layout="total, sizes, prev, pager, next"
            background
            small
          />
        </div>
      </template>
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

    <!-- Bill detail drawer -->
    <ElDrawer
      v-model="billDetailVisible"
      :title="billDetail?.invoice_number || 'Bill details'"
      size="720px"
      destroy-on-close
      class="rounded-l-2xl"
    >
      <div v-loading="billDetailLoading" class="space-y-5 min-h-[240px]">
        <template v-if="billDetail">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="text-xs uppercase tracking-wider text-slate-500">Bill number</div>
              <div class="font-mono text-lg font-semibold text-slate-900 dark:text-slate-100">{{
                billDetail.invoice_number
              }}</div>
              <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                <span>Billing date: {{ billDetail.business_date }}</span>
                <StatusTag :status="billDetail.status" size="small" />
                <span v-if="billDetail.catering_teller?.name">
                  Catered by {{ billDetail.catering_teller.name }}
                </span>
              </div>
            </div>
            <div class="flex flex-wrap gap-2">
              <ElButton type="success" plain @click="openBillTellerChat">
                {{
                  billDetail.catering_teller?.name
                    ? `Chat with ${billDetail.catering_teller.name}`
                    : 'Chat with teller'
                }}
              </ElButton>
              <ElButton
                v-if="billDetail.pdf_available"
                type="primary"
                plain
                :loading="pdfLoading"
                @click="viewBillPdf"
              >
                View PDF
              </ElButton>
              <ElTag v-else type="info" effect="plain">PDF not ready yet</ElTag>
            </div>
          </div>

          <BillingRequestProgress
            v-if="billDetail.request_progress"
            :progress="billDetail.request_progress"
          />
          <div
            v-else
            class="grid grid-cols-1 sm:grid-cols-3 gap-2 text-xs rounded-xl border border-slate-200/80 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/40 p-3"
          >
            <div>
              <div class="uppercase tracking-wider text-slate-500 mb-0.5">Requested</div>
              <div class="font-medium text-slate-800 dark:text-slate-100">{{
                formatDateTimeManila(billDetail.timeline?.requested_at)
              }}</div>
            </div>
            <div>
              <div class="uppercase tracking-wider text-slate-500 mb-0.5">Bill approved</div>
              <div class="font-medium text-slate-800 dark:text-slate-100">{{
                formatDateTimeManila(billDetail.timeline?.bill_approved_at || billDetail.posted_at)
              }}</div>
            </div>
            <div>
              <div class="uppercase tracking-wider text-slate-500 mb-0.5">Paid</div>
              <div class="font-medium text-slate-800 dark:text-slate-100">{{
                formatDateTimeManila(billDetail.timeline?.paid_at)
              }}</div>
            </div>
          </div>

          <div
            class="grid grid-cols-2 gap-3 rounded-xl border border-g-200 bg-g-100 p-4 sm:grid-cols-4"
          >
            <div>
              <div class="mb-0.5 text-xs font-semibold uppercase tracking-wider text-g-500"
                >Vessel</div
              >
              <div class="text-sm font-medium text-g-900">{{
                billDetail.shipment?.vessel_name || '—'
              }}</div>
            </div>
            <div>
              <div class="mb-0.5 text-xs font-semibold uppercase tracking-wider text-g-500"
                >Voyage</div
              >
              <div class="text-sm font-medium text-g-900">{{
                billDetail.shipment?.voyage || '—'
              }}</div>
            </div>
            <div>
              <div class="mb-0.5 text-xs font-semibold uppercase tracking-wider text-g-500"
                >Type</div
              >
              <div class="text-sm font-medium text-g-900">{{
                billDetail.shipment?.movement_type || '—'
              }}</div>
            </div>
            <div>
              <div class="mb-0.5 text-xs font-semibold uppercase tracking-wider text-g-500"
                >Route</div
              >
              <div class="text-sm font-medium text-g-900">{{
                formatShipmentRoute(billDetail.shipment?.route_type)
              }}</div>
            </div>
          </div>

          <div
            class="grid grid-cols-1 sm:grid-cols-2 gap-3 bg-slate-50 dark:bg-slate-800/40 p-4 rounded-xl border border-slate-200/80 dark:border-slate-700"
          >
            <div>
              <div class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1"
                >Buyer</div
              >
              <div class="text-sm font-medium text-slate-800 dark:text-slate-100">{{
                billDetail.buyer?.name || '—'
              }}</div>
              <div v-if="billDetail.buyer?.trade_name" class="text-xs text-slate-500">{{
                billDetail.buyer.trade_name
              }}</div>
              <div class="mt-2 space-y-0.5 text-xs text-slate-600 dark:text-slate-300">
                <div v-if="billDetail.buyer?.tin"
                  >TIN: {{ billDetail.buyer.tin
                  }}{{
                    billDetail.buyer.branch_code ? ` / ${billDetail.buyer.branch_code}` : ''
                  }}</div
                >
                <div v-if="billDetail.buyer?.email">{{ billDetail.buyer.email }}</div>
                <div v-if="billDetail.buyer?.phone">{{ billDetail.buyer.phone }}</div>
                <div
                  v-if="formatBuyerAddress(billDetail.buyer?.address)"
                  class="whitespace-pre-line"
                  >{{ formatBuyerAddress(billDetail.buyer?.address) }}</div
                >
              </div>
            </div>
            <div class="space-y-1.5 text-sm">
              <div class="flex justify-between gap-3"
                ><span class="text-slate-500">Gross</span
                ><MoneyDisplay
                  :value="billDetail.amounts.gross_amount"
                  :currency="billDetail.currency"
                  size="sm"
              /></div>
              <div class="flex justify-between gap-3"
                ><span class="text-slate-500">Fuel</span
                ><MoneyDisplay
                  :value="billDetail.amounts.fuel_surcharge_amount"
                  :currency="billDetail.currency"
                  size="sm"
              /></div>
              <div class="flex justify-between gap-3"
                ><span class="text-slate-500">PPA</span
                ><MoneyDisplay
                  :value="billDetail.amounts.ppa_amount"
                  :currency="billDetail.currency"
                  size="sm"
              /></div>
              <div class="flex justify-between gap-3"
                ><span class="text-slate-500">Discount</span
                ><MoneyDisplay
                  :value="billDetail.amounts.discount_amount"
                  :currency="billDetail.currency"
                  size="sm"
              /></div>
              <div class="flex justify-between gap-3"
                ><span class="text-slate-500">Tax</span
                ><MoneyDisplay
                  :value="billDetail.amounts.tax_amount"
                  :currency="billDetail.currency"
                  size="sm"
              /></div>
              <div
                class="flex justify-between gap-3 pt-1 border-t border-slate-200 dark:border-slate-700 font-semibold"
              >
                <span>Total billed</span>
                <MoneyDisplay
                  :value="billDetail.amounts.total_charge_amount"
                  :currency="billDetail.currency"
                  size="base"
                  weight="bold"
                />
              </div>
              <div class="flex justify-between gap-3"
                ><span class="text-slate-500">Applied</span
                ><MoneyDisplay
                  :value="billDetail.amounts.applied_amount"
                  :currency="billDetail.currency"
                  size="sm"
                  highlight="paid"
              /></div>
              <div class="flex justify-between gap-3"
                ><span class="text-slate-500">Outstanding</span
                ><MoneyDisplay
                  :value="billDetail.amounts.outstanding_amount"
                  :currency="billDetail.currency"
                  size="sm"
                  highlight="due"
                  weight="bold"
              /></div>
            </div>
          </div>

          <div>
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-2"
              >Line items</h3
            >
            <ElTable :data="billDetail.items" size="small" stripe class="w-full text-xs">
              <ElTableColumn prop="line_number" label="#" width="48" />
              <ElTableColumn label="Description" min-width="180">
                <template #default="{ row }">
                  <div class="font-medium">{{ row.description || '—' }}</div>
                  <div v-if="row.tariff_code" class="text-[11px] text-slate-400 font-mono">{{
                    row.tariff_code
                  }}</div>
                </template>
              </ElTableColumn>
              <ElTableColumn prop="quantity" label="Qty" width="72" align="right" />
              <ElTableColumn label="Rate" width="100" align="right">
                <template #default="{ row }">
                  <MoneyDisplay :value="row.unit_rate" :currency="billDetail.currency" size="sm" />
                </template>
              </ElTableColumn>
              <ElTableColumn label="Line total" width="110" align="right">
                <template #default="{ row }">
                  <MoneyDisplay
                    :value="row.total_charge_amount"
                    :currency="billDetail.currency"
                    size="sm"
                    weight="bold"
                  />
                </template>
              </ElTableColumn>
            </ElTable>
          </div>

          <div v-if="billDetail.receipt_history?.length">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-2"
              >Receipt history</h3
            >
            <div class="flex flex-col gap-2">
              <div
                v-for="receipt in billDetail.receipt_history"
                :key="receipt.receipt_id"
                class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-g-200 px-3 py-2"
              >
                <div class="text-sm">
                  <span class="font-mono font-medium"
                    >{{ receipt.receipt_kind === 'ACKNOWLEDGEMENT' ? 'ACK' : 'OR' }}
                    {{ receipt.receipt_number }}</span
                  >
                  <span class="text-g-500 ml-2 text-xs"
                    >{{ formatAmount(receipt.applied_amount, billDetail.currency) }}
                    <template v-if="receipt.posted_at"
                      >· {{ formatDateTimeManila(receipt.posted_at) }}</template
                    ></span
                  >
                </div>
                <div class="flex flex-wrap gap-2">
                  <ElButton
                    v-if="receipt.pdf_available"
                    size="small"
                    :type="receipt.receipt_kind === 'ACKNOWLEDGEMENT' ? 'warning' : 'primary'"
                    plain
                    @click="viewReceiptPdf(receipt)"
                  >
                    {{
                      receipt.receipt_kind === 'ACKNOWLEDGEMENT'
                        ? 'View / Download ACK'
                        : 'View / Download OR'
                    }}
                  </ElButton>
                  <ElTag v-else type="info" effect="plain" size="small">PDF not ready</ElTag>
                  <ElButton size="small" plain @click="openReceiptChat(receipt)">Chat</ElButton>
                </div>
              </div>
            </div>
          </div>

          <div v-if="billDetail.source_attachments?.length">
            <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100 mb-2"
              >Your uploaded attachments</h3
            >
            <p class="text-xs text-slate-500 mb-2"
              >Files you submitted with the billing request for this bill. View them anytime to
              track what the teller reviewed.</p
            >
            <ElTable
              :data="billDetail.source_attachments"
              size="small"
              stripe
              class="w-full text-xs"
            >
              <ElTableColumn label="Type" min-width="140">
                <template #default="{ row }">{{ row.document_type_name || '—' }}</template>
              </ElTableColumn>
              <ElTableColumn label="File" min-width="160">
                <template #default="{ row }">{{
                  row.original_name || `File #${row.private_file_id}`
                }}</template>
              </ElTableColumn>
              <ElTableColumn label="Scan" width="100">
                <template #default="{ row }">{{ row.scan_status || '—' }}</template>
              </ElTableColumn>
              <ElTableColumn label="" width="56" align="right">
                <template #default="{ row }">
                  <ElTooltip content="View file" placement="top">
                    <ElButton
                      size="small"
                      text
                      type="primary"
                      :icon="View"
                      :disabled="!row.private_file_id"
                      aria-label="View file"
                      @click="viewAttachment(row.private_file_id, row.original_name)"
                    />
                  </ElTooltip>
                </template>
              </ElTableColumn>
            </ElTable>
          </div>

          <p v-if="billDetail.notes" class="text-xs text-slate-500 whitespace-pre-line"
            >Notes: {{ billDetail.notes }}</p
          >
        </template>
      </div>
    </ElDrawer>

    <ProofViewerModal
      v-model="billPdfVisible"
      :title="billDetail?.invoice_number ? `PDF · ${billDetail.invoice_number}` : 'Bill PDF'"
      :fetcher="billPdfFetcher"
    />

    <ProofViewerModal
      v-model="receiptPdfVisible"
      :title="activeReceiptTitle"
      :fetcher="receiptPdfFetcher"
    />

    <ProofViewerModal
      v-model="attachmentVisible"
      :title="attachmentTitle"
      :file-id="attachmentFileId"
      :fetcher="attachmentFetcher"
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
  import BillingRequestProgress from '@/components/business/BillingRequestProgress.vue'
  import MoneyDisplay from '@/components/business/MoneyDisplay.vue'
  import StatusTag from '@/components/business/StatusTag.vue'
  import ProofViewerModal from '@/components/business/ProofViewerModal.vue'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'
  import { mittBus } from '@/utils/sys'
  import { fetchGetUserInfo } from '@/api/auth'
  import { useAuthoritativeRealtimeRefresh } from '@/composables/useAuthoritativeRealtimeRefresh'
  import {
    fetchDocumentTypes,
    uploadPrivateFile,
    downloadPrivateFile,
    type DocumentTypeItem
  } from '@/api/documentRequirements'
  import { fetchPortalProfile } from '@/api/registration'
  import { fetchCustomerWithholding, type WithholdingCertificate } from '@/api/taxEvidence'
  import {
    fetchPaymentSubmissions,
    fetchPortalPaymentGroups,
    fetchPortalBills,
    fetchPortalBillDetail,
    downloadPortalBillPdf,
    downloadPortalReceiptPdf,
    issueManualPaymentInstruction,
    resubmitPaymentProof,
    submitPaymentProof,
    type ManualPaymentSubmission,
    type PaymentGroup,
    type PortalBill,
    type PortalBillDetail
  } from '@/api/payments'
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
  const billsTab = ref<'unpaid' | 'paid'>('unpaid')
  const billsSearchQuery = ref('')
  const billsPage = ref(1)
  const billsPageSize = ref(10)
  const proofSearchQuery = ref('')
  const proofPage = ref(1)
  const proofPageSize = ref(10)
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
  const billDetailVisible = ref(false)
  const billDetailLoading = ref(false)
  const billDetail = ref<PortalBillDetail | null>(null)
  const billPdfVisible = ref(false)
  const pdfLoading = ref(false)
  const receiptPdfVisible = ref(false)
  const activeReceiptId = ref<number | null>(null)
  const activeReceiptNumber = ref('')
  const activeReceiptKind = ref<'OFFICIAL' | 'ACKNOWLEDGEMENT'>('OFFICIAL')
  const attachmentVisible = ref(false)
  const attachmentFileId = ref<number | null>(null)
  const attachmentTitle = ref('Attachment')
  const realtime = useAuthoritativeRealtimeRefresh({
    scope: 'payments',
    refresh: () => loadWorkspace(),
    isBusy: () => submitting.value || issuingInstruction.value
  })

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

  const unpaidBills = computed(() => bills.value.filter((b) => Number(b.outstanding_amount) > 0))
  const paidBills = computed(() => bills.value.filter((b) => Number(b.outstanding_amount) <= 0))

  const filteredTabBills = computed(() => {
    const source = billsTab.value === 'unpaid' ? unpaidBills.value : paidBills.value
    if (!billsSearchQuery.value.trim()) return source
    const q = billsSearchQuery.value.toLowerCase().trim()
    return source.filter(
      (b) =>
        b.invoice_number.toLowerCase().includes(q) ||
        b.business_date.includes(q) ||
        (b.receipt_history || []).some((r) =>
          String(r.receipt_number || '')
            .toLowerCase()
            .includes(q)
        )
    )
  })

  const pagedBills = computed(() => {
    const start = (billsPage.value - 1) * billsPageSize.value
    return filteredTabBills.value.slice(start, start + billsPageSize.value)
  })

  const filteredSubmissions = computed(() => {
    if (!proofSearchQuery.value.trim()) return submissions.value
    const q = proofSearchQuery.value.toLowerCase().trim()
    return submissions.value.filter((s) => {
      const billNos = (s.items || [])
        .map((item) => String(item.invoice?.invoice_number || item.invoice_id || ''))
        .join(' ')
        .toLowerCase()
      const receiptNo = String(s.receipt?.receipt_number || '').toLowerCase()
      const ref = String(s.declared_reference || '').toLowerCase()
      return (
        String(s.id).includes(q) ||
        String(s.status || '')
          .toLowerCase()
          .includes(q) ||
        String(s.initial_submitted_at || '')
          .toLowerCase()
          .includes(q) ||
        billNos.includes(q) ||
        receiptNo.includes(q) ||
        ref.includes(q) ||
        String(s.rejection_reason || '')
          .toLowerCase()
          .includes(q)
      )
    })
  })

  const pagedSubmissions = computed(() => {
    const start = (proofPage.value - 1) * proofPageSize.value
    return filteredSubmissions.value.slice(start, start + proofPageSize.value)
  })

  function onBillsTabChange() {
    billsPage.value = 1
    billsSearchQuery.value = ''
    selectedBills.value = []
  }

  function onUnpaidSelectionChange(rows: PortalBill[]) {
    if (billsTab.value !== 'unpaid') {
      selectedBills.value = []
      return
    }
    selectedBills.value = rows
  }

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
      const [profile, me] = await Promise.all([fetchPortalProfile(), fetchGetUserInfo()])
      realtime.startForUser(Number((me as any).id || (me as any).userId))
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

  async function openBillDetail(bill: PortalBill) {
    if (!activeCustomerId.value) return
    billDetailVisible.value = true
    billDetailLoading.value = true
    billDetail.value = null
    try {
      billDetail.value = await fetchPortalBillDetail(activeCustomerId.value, bill.id)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load bill details.')
      billDetailVisible.value = false
    } finally {
      billDetailLoading.value = false
    }
  }

  function openBillTellerChat() {
    const billingRequestId = billDetail.value?.billing_request_id
    if (billingRequestId) {
      mittBus.emit('openChat', { billingRequestId })
      return
    }
    if (billDetail.value?.id) {
      mittBus.emit('openChat', { invoiceId: billDetail.value.id })
    }
  }

  function openReceiptChat(receipt: { receipt_id: number }) {
    mittBus.emit('openChat', { receiptId: receipt.receipt_id })
  }

  function formatShipmentRoute(route?: string | null): string {
    if (route === 'DOMESTIC') return 'Domestic'
    if (route === 'FOREIGN') return 'Foreign'
    return '—'
  }

  function formatBuyerAddress(address: PortalBillDetail['buyer']['address']): string {
    if (!address) return ''
    if (typeof address === 'string') return address
    const record = address as Record<string, unknown>
    const parts = [
      record.line1 || record.address_line1,
      record.line2 || record.address_line2,
      record.barangay,
      record.city || record.municipality,
      record.province,
      record.zip || record.postal_code
    ]
      .map((part) => (typeof part === 'string' ? part.trim() : ''))
      .filter(Boolean)
    return parts.join(', ')
  }

  const activeReceiptTitle = computed(() => {
    const label =
      activeReceiptKind.value === 'ACKNOWLEDGEMENT' ? 'Acknowledgement Receipt' : 'Official Receipt'
    return activeReceiptNumber.value ? `${label} · ${activeReceiptNumber.value}` : `${label} PDF`
  })

  function receiptPdfReady(
    receipt?: {
      canonical_artifact?: { id?: number; status?: string } | null
    } | null
  ): boolean {
    const status = receipt?.canonical_artifact?.status
    return Boolean(
      receipt?.canonical_artifact?.id && (!status || status === 'RENDERED' || status === 'FAILED')
    )
  }

  function viewReceiptPdf(receipt: {
    receipt_id: number
    receipt_number?: string | null
    receipt_kind?: string | null
    pdf_available?: boolean
  }) {
    if (!receipt?.receipt_id) return
    const isAck = receipt.receipt_kind === 'ACKNOWLEDGEMENT'
    if (receipt.pdf_available === false) {
      ElMessage.info(isAck ? 'Acknowledgement PDF is not ready yet.' : 'OR PDF is not ready yet.')
      return
    }
    activeReceiptId.value = receipt.receipt_id
    activeReceiptNumber.value = receipt.receipt_number || String(receipt.receipt_id)
    activeReceiptKind.value = isAck ? 'ACKNOWLEDGEMENT' : 'OFFICIAL'
    receiptPdfVisible.value = true
  }

  async function viewBillPdf() {
    if (!billDetail.value?.pdf_available) return
    pdfLoading.value = true
    try {
      billPdfVisible.value = true
    } finally {
      pdfLoading.value = false
    }
  }

  const proofFetcher = async () => {
    if (!activeViewingSubmission.value?.proof_file_id) {
      throw new Error('No proof file available.')
    }
    const blob = await downloadPrivateFile(activeViewingSubmission.value.proof_file_id)
    return { blob, filename: `proof-${activeViewingSubmission.value.id}` }
  }

  const billPdfFetcher = async () => {
    if (!billDetail.value) {
      throw new Error('No bill selected.')
    }
    const blob = await downloadPortalBillPdf(billDetail.value.id)
    return { blob, filename: `${billDetail.value.invoice_number}.pdf` }
  }

  const receiptPdfFetcher = async () => {
    if (!activeReceiptId.value) {
      throw new Error('No receipt selected.')
    }
    const blob = await downloadPortalReceiptPdf(activeReceiptId.value)
    const name = activeReceiptNumber.value || String(activeReceiptId.value)
    const prefix = activeReceiptKind.value === 'ACKNOWLEDGEMENT' ? 'ACK' : 'OR'
    return { blob, filename: `${prefix}-${name}.pdf` }
  }

  function viewAttachment(fileId: number, name?: string | null) {
    attachmentFileId.value = fileId
    attachmentTitle.value = name || `Attachment #${fileId}`
    attachmentVisible.value = true
  }

  const attachmentFetcher = async () => {
    if (!attachmentFileId.value) {
      throw new Error('No attachment selected.')
    }
    const blob = await downloadPrivateFile(attachmentFileId.value)
    return { blob, filename: attachmentTitle.value }
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
