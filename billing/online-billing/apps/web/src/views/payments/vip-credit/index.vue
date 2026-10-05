<template>
  <div class="page-content space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:vip-crown-2-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-medium text-g-900">My VIP Credit</h1>
            <ElTag
              v-if="summary?.profile?.status"
              size="small"
              :type="profileStatusTag(summary.profile.status)"
              >{{ profileStatusLabel(summary.profile.status) }}</ElTag
            >
          </div>
          <p class="mt-1 max-w-2xl text-sm text-g-500">
            Credit terms do not mark a bill as paid. Bank repayments are verified before a
            collection receipt is issued.
          </p>
        </div>
      </div>
      <ElButton :loading="loading" @click="loadWorkspace()">
        <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
        Refresh
      </ElButton>
    </header>

    <ElAlert
      v-if="summary && !summary.eligible"
      type="warning"
      :closable="false"
      show-icon
      :title="summary.reason || 'VIP credit is not available for new charges.'"
    />

    <section v-if="attentionItems.length" class="art-card p-4">
      <p class="mb-3 text-sm font-medium text-g-900">Needs your attention</p>
      <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <button
          v-for="item in attentionItems"
          :key="item.key"
          type="button"
          class="flex items-center gap-3 rounded-lg border border-g-300 px-3 py-2.5 text-left transition-colors hover:bg-g-100"
          @click="scrollToSection(item.target)"
        >
          <div class="size-9 flex-cc shrink-0 rounded-lg" :class="item.toneClass">
            <ArtSvgIcon :icon="item.icon" class="text-lg" />
          </div>
          <div class="min-w-0 flex-1">
            <p class="text-sm font-medium text-g-900">{{ item.title }}</p>
            <p class="truncate text-xs text-g-500">{{ item.detail }}</p>
          </div>
          <ArtSvgIcon icon="ri:arrow-right-s-line" class="text-lg text-g-500" />
        </button>
      </div>
    </section>

    <div v-if="summary?.account" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <MetricCard
        label="Credit Exposure"
        :value="currency(summary.exposure_amount)"
        :detail="`${openCharges.length} open credit bill(s)`"
        icon="ri:wallet-3-line"
      />
      <MetricCard
        label="Available Credit"
        :value="
          summary.terms?.credit_limit_mode === 'UNLIMITED'
            ? 'Unlimited'
            : currency(summary.available_credit_amount)
        "
        :detail="
          summary.terms?.credit_limit_mode === 'UNLIMITED'
            ? 'No credit limit'
            : `Limit ${currency(summary.terms?.credit_limit_amount)}`
        "
        icon="ri:line-chart-line"
      />
      <MetricCard
        label="Overdue Credit"
        :value="currency(summary.overdue_amount)"
        :detail="
          overdueCharges.length ? `${overdueCharges.length} overdue bill(s)` : 'Nothing overdue'
        "
        icon="ri:alarm-warning-line"
        :warning="Number(summary.overdue_amount || 0) > 0"
      />
      <MetricCard
        label="Terms"
        :value="`${summary.terms?.payment_terms_days || 0} calendar day${summary.terms?.payment_terms_days === 1 ? '' : 's'}`"
        :detail="overdueRuleLabel"
        icon="ri:file-list-3-line"
      />
    </div>

    <nav v-if="activeCustomerId" class="flex flex-wrap gap-2" aria-label="My VIP Credit sections">
      <ElButton
        v-for="link in sectionLinks"
        :key="link.target"
        size="small"
        round
        @click="scrollToSection(link.target)"
      >
        <ArtSvgIcon :icon="link.icon" class="mr-1" />
        {{ link.label }}
      </ElButton>
    </nav>

    <section id="vip-pay-credit" v-loading="loading" class="art-card p-5 scroll-mt-4">
      <div class="art-card-header">
        <div class="title">
          <h4>Pay credit bills</h4>
          <p>
            Choose one or more charged bills and explicit amounts. Partial repayment is supported;
            submit a clean bank-transfer proof for verification.
          </p>
        </div>
      </div>
      <ElEmpty
        v-if="openCharges.length === 0"
        :image-size="80"
        description="No open credit bills to repay."
      />
      <template v-else>
        <ElTable :data="openCharges" @selection-change="selectedCharges = $event">
          <ElTableColumn
            type="selection"
            width="50"
            :selectable="(row: CreditCharge) => !pendingInvoiceIds.has(row.invoice_id)"
          />
          <ElTableColumn label="Invoice" min-width="170"
            ><template #default="{ row }"
              ><div class="font-medium text-g-900">{{ row.invoice_number }}</div
              ><ElTag
                v-if="pendingInvoiceIds.has(row.invoice_id)"
                size="small"
                type="warning"
                class="mt-1"
                >Proof in review</ElTag
              ></template
            ></ElTableColumn
          >
          <ElTableColumn label="Due date" min-width="170"
            ><template #default="{ row }"
              ><div :class="row.is_overdue ? 'font-medium text-danger' : 'text-g-900'">{{
                row.due_date || 'Needs terms review'
              }}</div
              ><div
                v-if="row.due_date"
                class="text-xs"
                :class="row.is_overdue ? 'text-danger' : 'text-g-500'"
                >{{ dueHint(row.due_date) }}</div
              ></template
            ></ElTableColumn
          >
          <ElTableColumn label="Credit balance" align="right" min-width="170"
            ><template #default="{ row }"
              ><div class="text-g-900">{{ currency(row.outstanding_amount, row.currency) }}</div
              ><div
                v-if="Number(row.charged_amount) !== Number(row.outstanding_amount)"
                class="text-xs text-g-500"
                >of {{ currency(row.charged_amount, row.currency) }} charged</div
              ></template
            ></ElTableColumn
          >
          <ElTableColumn label="Bank repayment amount" min-width="190"
            ><template #default="{ row }"
              ><ElInput
                v-model="repaymentAmounts[row.invoice_id]"
                :disabled="!isChargeSelected(row.invoice_id)"
                inputmode="decimal" /></template
          ></ElTableColumn>
        </ElTable>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <span class="text-sm text-g-600"
            >{{ selectedCharges.length }} bill(s), {{ currency(selectedRepaymentTotal) }}</span
          >
          <ElButton
            type="primary"
            :disabled="selectedCharges.length === 0"
            @click="repaymentDialog = true"
            >Upload bank repayment proof</ElButton
          >
        </div>
      </template>
    </section>

    <section id="vip-charge-bills" v-loading="loading" class="art-card p-5 scroll-mt-4">
      <div class="art-card-header">
        <div class="title">
          <h4>Charge eligible bills to credit</h4>
          <p>
            Only fully unpaid bills can be charged. The server validates account status, policy,
            current balance, overdue restrictions and shared exposure again at confirmation.
          </p>
        </div>
      </div>
      <ElEmpty
        v-if="!activeCustomerId"
        description="No active customer account is linked to this login."
      />
      <ElEmpty
        v-else-if="eligibleBills.length === 0"
        :image-size="80"
        description="No unpaid bills are available to charge."
      />
      <template v-else>
        <ElTable :data="eligibleBills" @selection-change="selectedBills = $event">
          <ElTableColumn type="selection" width="50" :selectable="() => !!summary?.eligible" />
          <ElTableColumn prop="invoice_number" label="Invoice" min-width="160" />
          <ElTableColumn prop="business_date" label="Invoice date" min-width="130" />
          <ElTableColumn label="Full unpaid balance" align="right" min-width="160"
            ><template #default="{ row }">{{
              currency(row.outstanding_amount, row.currency)
            }}</template></ElTableColumn
          >
        </ElTable>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <span class="text-sm text-g-600"
            >{{ selectedBills.length }} bill(s), {{ currency(selectedBillTotal) }}</span
          >
          <ElButton
            type="primary"
            :disabled="!summary?.eligible || selectedBills.length === 0"
            :loading="charging"
            @click="chargeSelected"
            >Charge selected bills to credit</ElButton
          >
        </div>
      </template>
    </section>

    <section id="vip-history" class="art-card p-5 scroll-mt-4">
      <div class="art-card-header">
        <div class="title">
          <h4>Repayment history</h4>
          <p>Status reflects verification, not the moment a transfer was sent.</p>
        </div>
      </div>
      <ElEmpty
        v-if="repayments.length === 0"
        :image-size="80"
        description="No VIP bank repayments submitted."
      />
      <template v-else>
        <ElTable :data="repayments">
          <ElTableColumn label="Submitted" min-width="150"
            ><template #default="{ row }"
              ><div class="text-g-900">{{ formatDateTime(row.initial_submitted_at) }}</div
              ><div class="text-xs text-g-500"
                >#{{ row.id
                }}<span v-if="row.resubmission_rounds">
                  · resubmitted {{ row.resubmission_rounds }}×</span
                ></div
              ></template
            ></ElTableColumn
          >
          <ElTableColumn label="Bills" min-width="220"
            ><template #default="{ row }"
              ><div class="flex flex-wrap gap-1"
                ><ElTag v-for="item in row.allocations" :key="item.invoice_id" size="small"
                  >{{ item.invoice?.invoice_number || `#${item.invoice_id}` }} ·
                  {{ currency(item.requested_amount, row.currency) }}</ElTag
                ></div
              ></template
            ></ElTableColumn
          >
          <ElTableColumn label="Amount" min-width="130" align="right"
            ><template #default="{ row }">{{
              currency(row.requested_amount, row.currency)
            }}</template></ElTableColumn
          >
          <ElTableColumn label="Bank reference" min-width="150"
            ><template #default="{ row }"
              ><span v-if="row.confirmed_reference" class="text-g-900">{{
                row.confirmed_reference
              }}</span
              ><span v-else-if="row.declared_reference" class="text-g-700">{{
                row.declared_reference
              }}</span
              ><span v-else class="text-g-500">—</span></template
            ></ElTableColumn
          >
          <ElTableColumn label="Status" min-width="200"
            ><template #default="{ row }"
              ><ElTag size="small" :type="repaymentStatusTag(row.status)">{{
                repaymentStatusLabel(row.status)
              }}</ElTag
              ><div v-if="row.receipt" class="mt-1 text-xs text-success"
                >Receipt {{ row.receipt.receipt_number }}</div
              ><div v-else-if="row.status === 'REJECTED'" class="mt-1 text-xs text-danger">{{
                row.rejection_reason
              }}</div></template
            ></ElTableColumn
          >
          <ElTableColumn label="Actions" width="230" align="right" fixed="right"
            ><template #default="{ row }"
              ><div class="flex flex-wrap items-center justify-end gap-1.5">
                <ElButton
                  v-if="row.proof_file_id"
                  size="small"
                  text
                  type="primary"
                  @click="viewProof(row)"
                >
                  <ArtSvgIcon icon="ri:eye-line" class="mr-1" />
                  Proof
                </ElButton>
                <ElButton
                  v-if="row.receipt && receiptPdfReady(row.receipt)"
                  size="small"
                  plain
                  :type="row.receipt.receipt_kind === 'ACKNOWLEDGEMENT' ? 'warning' : 'success'"
                  @click="viewReceipt(row)"
                >
                  {{ row.receipt.receipt_kind === 'ACKNOWLEDGEMENT' ? 'ACK' : 'OR' }}
                </ElButton>
                <ElButton
                  v-if="row.status === 'REJECTED'"
                  size="small"
                  type="warning"
                  plain
                  @click="openResubmit(row)"
                  >Resubmit</ElButton
                >
              </div></template
            ></ElTableColumn
          >
        </ElTable>
        <div v-if="repaymentTotal > repaymentPerPage" class="mt-4 flex justify-end">
          <ElPagination
            v-model:current-page="repaymentPage"
            :page-size="repaymentPerPage"
            :total="repaymentTotal"
            layout="total, prev, pager, next"
            background
            @current-change="loadRepayments"
          />
        </div>
      </template>
    </section>

    <section v-if="summary?.account" id="vip-aging" class="art-card p-5 scroll-mt-4">
      <ElCollapse v-model="agingOpen" class="vip-aging-collapse">
        <ElCollapseItem name="aging">
          <template #title>
            <div class="flex flex-col py-1 text-left">
              <span class="text-base font-medium text-g-900">Principal aging</span>
              <span class="text-xs text-g-500"
                >Historical balances by due-date bucket at a chosen cutoff</span
              >
            </div>
          </template>
          <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-g-500">
              Historical balances use effective receipt business dates. They are not a second credit
              ledger.
            </p>
            <ElDatePicker
              v-model="agingAsOf"
              type="date"
              value-format="YYYY-MM-DD"
              class="!w-44"
              @change="loadAging"
            />
          </div>
          <ElAlert
            v-if="aging?.cutoff_semantics"
            :title="aging.cutoff_semantics"
            type="info"
            :closable="false"
            show-icon
            class="mb-4"
          />
          <ElEmpty
            v-if="!aging || aging.currencies.length === 0"
            :image-size="80"
            description="No VIP credit is included at this cutoff."
          />
          <div
            v-for="currencyAging in aging?.currencies || []"
            :key="currencyAging.currency"
            class="space-y-4 mb-5 last:mb-0"
          >
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-sm">
              <div v-for="bucket in agingBuckets" :key="bucket.key" class="art-card-xs p-3">
                <p class="text-xs text-g-500">{{ bucket.label }}</p>
                <p class="mt-1 font-medium text-g-900">{{
                  currency(currencyAging.buckets[bucket.key], currencyAging.currency)
                }}</p>
              </div>
            </div>
            <ElTable :data="currencyAging.items" size="small">
              <ElTableColumn prop="invoice_number" label="Invoice" min-width="145" />
              <ElTableColumn prop="due_date" label="Due date" min-width="145"
                ><template #default="{ row }">{{
                  row.due_date || 'Needs terms review'
                }}</template></ElTableColumn
              >
              <ElTableColumn prop="days_past_due" label="Days past due" min-width="115" />
              <ElTableColumn prop="bucket" label="Bucket" min-width="125" />
              <ElTableColumn label="Outstanding at cutoff" align="right" min-width="175"
                ><template #default="{ row }">{{
                  currency(row.outstanding_as_of_amount, currencyAging.currency)
                }}</template></ElTableColumn
              >
            </ElTable>
          </div>
        </ElCollapseItem>
      </ElCollapse>
    </section>

    <ElDialog
      v-model="repaymentDialog"
      title="Submit VIP bank repayment"
      width="520px"
      destroy-on-close
    >
      <ElAlert
        type="info"
        :closable="false"
        show-icon
        title="A bank transfer is not a receipt. An authorized reviewer confirms funds and exact allocations before the collection receipt is posted."
      />
      <div class="mt-4 rounded-lg border border-g-300 p-3">
        <div
          v-for="charge in selectedCharges"
          :key="charge.invoice_id"
          class="flex justify-between py-0.5 text-sm"
        >
          <span class="text-g-700">{{ charge.invoice_number }}</span>
          <span class="text-g-900">{{
            currency(
              repaymentAmounts[charge.invoice_id] || charge.outstanding_amount,
              charge.currency
            )
          }}</span>
        </div>
        <div class="mt-2 flex justify-between border-t border-g-300 pt-2 text-sm font-medium">
          <span class="text-g-900">Total to verify</span>
          <span class="text-g-900">{{ currency(selectedRepaymentTotal) }}</span>
        </div>
      </div>
      <div class="mt-4 space-y-4">
        <ElInput
          v-model="declaredReference"
          placeholder="Bank transfer reference (optional at submission)"
          maxlength="128"
        />
        <ElUpload
          drag
          :auto-upload="false"
          :limit="1"
          accept="application/pdf,image/*"
          :on-change="onProofSelected"
          :on-remove="clearProof"
          ><div class="py-3 text-g-500"
            >Drop the bank receipt here or click to choose a file.</div
          ></ElUpload
        >
        <PhotoToPdfPicker
          :allowed-mime-types="proofType?.allowed_mime_types"
          :max-file-size-kb="proofType?.max_file_size_kb"
          @created="(file) => (proofFile = file)"
        />
        <p v-if="proofFile" class="text-xs text-g-600">Selected: {{ proofFile.name }}</p>
      </div>
      <template #footer
        ><ElButton @click="repaymentDialog = false">Cancel</ElButton
        ><ElButton
          type="primary"
          :loading="submitting"
          :disabled="!proofFile"
          @click="submitRepayment"
          >Submit for verification</ElButton
        ></template
      >
    </ElDialog>

    <ElDialog
      v-model="resubmitDialog"
      :title="`Resubmit repayment #${resubmitTarget?.id || ''}`"
      width="520px"
      destroy-on-close
      @closed="clearProof"
    >
      <ElAlert
        v-if="resubmitTarget?.rejection_reason"
        type="error"
        :closable="false"
        show-icon
        :title="`Reviewer note: ${resubmitTarget.rejection_reason}`"
      />
      <p class="mt-4 text-sm text-g-600">
        Upload a corrected bank proof. The same bills and amounts ({{
          currency(resubmitTarget?.requested_amount, resubmitTarget?.currency)
        }}) return to the review queue with their original priority.
      </p>
      <ElUpload
        class="mt-4"
        drag
        :auto-upload="false"
        :limit="1"
        accept="application/pdf,image/*"
        :on-change="onProofSelected"
        :on-remove="clearProof"
        ><div class="py-3 text-g-500"
          >Drop the corrected bank receipt here or click to choose a file.</div
        ></ElUpload
      >
      <PhotoToPdfPicker
        :allowed-mime-types="proofType?.allowed_mime_types"
        :max-file-size-kb="proofType?.max_file_size_kb"
        @created="(file) => (proofFile = file)"
      />
      <p v-if="proofFile" class="text-xs text-g-600">Selected: {{ proofFile.name }}</p>
      <template #footer
        ><ElButton @click="resubmitDialog = false">Cancel</ElButton
        ><ElButton
          type="primary"
          :loading="submitting"
          :disabled="!proofFile"
          @click="confirmResubmit"
          >Resubmit for verification</ElButton
        ></template
      >
    </ElDialog>

    <ProofViewerModal
      v-model="proofViewerVisible"
      :title="proofViewerTitle"
      :file-id="viewingRepayment?.proof_file_id"
      :file-name="viewingRepayment?.proof_file?.latest_version?.original_name || 'payment-proof'"
      :mime-type="viewingRepayment?.proof_file?.latest_version?.mime_type || null"
      :fetcher="proofFetcher"
    />

    <ProofViewerModal
      v-model="receiptViewerVisible"
      :title="receiptViewerTitle"
      :fetcher="receiptFetcher"
    />
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage, type UploadFile } from 'element-plus'
  import { fetchPortalProfile } from '@/api/registration'
  import { fetchGetUserInfo } from '@/api/auth'
  import { useAuthoritativeRealtimeRefresh } from '@/composables/useAuthoritativeRealtimeRefresh'
  import MetricCard from '@/components/business/MetricCard.vue'
  import ProofViewerModal from '@/components/business/ProofViewerModal.vue'
  import PhotoToPdfPicker from '@/components/business/PhotoToPdfPicker.vue'
  import { downloadPortalReceiptPdf, fetchPortalBills, type PortalBill } from '@/api/payments'
  import {
    downloadPrivateFile,
    fetchDocumentTypes,
    uploadPrivateFile,
    type DocumentTypeItem
  } from '@/api/documentRequirements'
  import {
    chargeBillsToVipCredit,
    fetchVipCreditRepayments,
    fetchVipCreditSummary,
    resubmitVipCreditRepayment,
    submitVipCreditRepayment,
    type CreditCharge,
    type VipCreditRepayment,
    type VipCreditSummary
  } from '@/api/vipCredit'
  import { fetchPortalCreditAging, type VipCreditAging } from '@/api/creditAging'

  defineOptions({ name: 'VipCreditPortal' })
  const loading = ref(false)
  const charging = ref(false)
  const submitting = ref(false)
  const repaymentDialog = ref(false)
  const resubmitDialog = ref(false)
  const resubmitTarget = ref<VipCreditRepayment | null>(null)
  const activeCustomerId = ref<number | null>(null)
  const bills = ref<PortalBill[]>([])
  const summary = ref<VipCreditSummary | null>(null)
  const repayments = ref<VipCreditRepayment[]>([])
  const repaymentPage = ref(1)
  const repaymentPerPage = ref(20)
  const repaymentTotal = ref(0)
  const selectedBills = ref<PortalBill[]>([])
  const selectedCharges = ref<CreditCharge[]>([])
  const repaymentAmounts = ref<Record<number, string>>({})
  const proofFile = ref<File | null>(null)
  const proofType = ref<DocumentTypeItem | null>(null)
  const declaredReference = ref('')
  const aging = ref<VipCreditAging | null>(null)
  const agingAsOf = ref(new Date().toISOString().slice(0, 10))
  const agingOpen = ref<string[]>([])
  const proofViewerVisible = ref(false)
  const viewingRepayment = ref<VipCreditRepayment | null>(null)
  const receiptViewerVisible = ref(false)
  const viewingReceipt = ref<NonNullable<VipCreditRepayment['receipt']> | null>(null)
  function isEditingCredit() {
    return (
      loading.value ||
      charging.value ||
      submitting.value ||
      repaymentDialog.value ||
      resubmitDialog.value ||
      selectedBills.value.length > 0 ||
      selectedCharges.value.length > 0
    )
  }
  const realtime = useAuthoritativeRealtimeRefresh({
    scope: 'vip_credit',
    refresh: () => loadWorkspace(false),
    isBusy: isEditingCredit
  })
  const agingBuckets = [
    { key: 'CURRENT', label: 'Current' },
    { key: '1_30', label: '1–30' },
    { key: '31_60', label: '31–60' },
    { key: '61_90', label: '61–90' },
    { key: '91_PLUS', label: '91+' },
    { key: 'UNCLASSIFIED', label: 'Needs terms review' }
  ] as const
  const sectionLinks = [
    { target: 'vip-pay-credit', label: 'Pay credit', icon: 'ri:bank-card-line' },
    { target: 'vip-charge-bills', label: 'Charge bills', icon: 'ri:file-add-line' },
    { target: 'vip-history', label: 'History', icon: 'ri:history-line' },
    { target: 'vip-aging', label: 'Aging', icon: 'ri:bar-chart-box-line' }
  ]
  const eligibleBills = computed(() =>
    bills.value.filter(
      (bill) =>
        Number(bill.outstanding_amount) > 0 &&
        !(summary.value?.charges || []).some((charge) => charge.invoice_id === bill.id)
    )
  )
  const openCharges = computed(() =>
    (summary.value?.charges || []).filter((charge) => Number(charge.outstanding_amount) > 0)
  )
  const overdueCharges = computed(() => openCharges.value.filter((charge) => charge.is_overdue))
  const pendingRepayments = computed(() =>
    repayments.value.filter((item) => item.status === 'SUBMITTED' || item.status === 'IN_REVIEW')
  )
  const rejectedRepayments = computed(() =>
    repayments.value.filter((item) => item.status === 'REJECTED')
  )
  const pendingInvoiceIds = computed(
    () =>
      new Set(pendingRepayments.value.flatMap((item) => item.allocations.map((a) => a.invoice_id)))
  )
  const attentionItems = computed(() => {
    const items: Array<{
      key: string
      title: string
      detail: string
      icon: string
      toneClass: string
      target: string
    }> = []
    if (overdueCharges.value.length)
      items.push({
        key: 'overdue',
        title: `${overdueCharges.value.length} overdue credit bill(s)`,
        detail: `${currency(summary.value?.overdue_amount)} past due — repay to avoid restrictions`,
        icon: 'ri:alarm-warning-line',
        toneClass: 'bg-danger/10 text-danger',
        target: 'vip-pay-credit'
      })
    if (rejectedRepayments.value.length)
      items.push({
        key: 'rejected',
        title: `${rejectedRepayments.value.length} repayment(s) need correction`,
        detail: 'Review the note and resubmit a corrected proof',
        icon: 'ri:error-warning-line',
        toneClass: 'bg-warning/10 text-warning',
        target: 'vip-history'
      })
    if (pendingRepayments.value.length)
      items.push({
        key: 'pending',
        title: `${pendingRepayments.value.length} repayment(s) awaiting verification`,
        detail: 'Balances update after the reviewer posts a receipt',
        icon: 'ri:time-line',
        toneClass: 'bg-theme/10 text-theme',
        target: 'vip-history'
      })
    return items
  })
  const overdueRuleLabel = computed(() => {
    const terms = summary.value?.terms
    if (!terms) return ''
    const grace = terms.overdue_grace_days ? ` after ${terms.overdue_grace_days}-day grace` : ''
    if (terms.overdue_restriction === 'BLOCK') return `Overdue blocks new charges${grace}`
    if (terms.overdue_restriction === 'WARN') return `Overdue shows a warning${grace}`
    return 'Overdue does not block new charges'
  })
  const selectedBillTotal = computed(() =>
    selectedBills.value
      .reduce((total, bill) => total + Number(bill.outstanding_amount), 0)
      .toFixed(2)
  )
  const selectedRepaymentTotal = computed(() =>
    selectedCharges.value
      .reduce(
        (total, charge) =>
          total + Number(repaymentAmounts.value[charge.invoice_id] || charge.outstanding_amount),
        0
      )
      .toFixed(2)
  )
  const proofViewerTitle = computed(() =>
    viewingRepayment.value ? `Repayment proof #${viewingRepayment.value.id}` : 'Repayment proof'
  )
  const receiptViewerTitle = computed(() => {
    const receipt = viewingReceipt.value
    const label =
      receipt?.receipt_kind === 'ACKNOWLEDGEMENT' ? 'Acknowledgement Receipt' : 'Official Receipt'
    return receipt?.receipt_number ? `${label} · ${receipt.receipt_number}` : label
  })
  const currency = (amount?: string | null, code = summary.value?.policy?.currency || 'PHP') =>
    amount === null || amount === undefined
      ? '—'
      : new Intl.NumberFormat('en-PH', { style: 'currency', currency: code }).format(Number(amount))
  const isChargeSelected = (id: number) =>
    selectedCharges.value.some((charge) => charge.invoice_id === id)
  function formatDateTime(value?: string | null) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('en-PH', {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: 'Asia/Manila'
    }).format(new Date(value))
  }
  function dueHint(dueDate: string) {
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Manila' }).format(new Date())
    const days = Math.round((Date.parse(dueDate) - Date.parse(today)) / 86_400_000)
    if (days > 1) return `Due in ${days} days`
    if (days === 1) return 'Due tomorrow'
    if (days === 0) return 'Due today'
    return days === -1 ? '1 day overdue' : `${-days} days overdue`
  }
  function profileStatusLabel(status: string) {
    return { ACTIVE: 'Active', HELD: 'On hold', DISABLED: 'Disabled' }[status] || status
  }
  function profileStatusTag(status: string) {
    return status === 'ACTIVE' ? 'success' : status === 'HELD' ? 'warning' : 'info'
  }
  function repaymentStatusLabel(status: VipCreditRepayment['status']) {
    return {
      SUBMITTED: 'Awaiting review',
      IN_REVIEW: 'In review',
      REJECTED: 'Needs correction',
      APPROVED: 'Approved'
    }[status]
  }
  function repaymentStatusTag(status: VipCreditRepayment['status']) {
    return (
      { SUBMITTED: 'info', IN_REVIEW: 'warning', REJECTED: 'danger', APPROVED: 'success' } as const
    )[status]
  }
  function receiptPdfReady(receipt: NonNullable<VipCreditRepayment['receipt']>) {
    const status = receipt.canonical_artifact?.status
    return Boolean(
      receipt.canonical_artifact?.id && (!status || status === 'RENDERED' || status === 'FAILED')
    )
  }
  function scrollToSection(id: string) {
    if (id === 'vip-aging' && !agingOpen.value.includes('aging')) agingOpen.value = ['aging']
    document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'start' })
  }
  function viewProof(row: VipCreditRepayment) {
    viewingRepayment.value = row
    proofViewerVisible.value = true
  }
  async function proofFetcher() {
    const row = viewingRepayment.value
    if (!row?.proof_file_id) throw new Error('No proof file available.')
    const blob = await downloadPrivateFile(row.proof_file_id, row.proof_file?.current_version)
    return {
      blob,
      filename: row.proof_file?.latest_version?.original_name || `repayment-proof-${row.id}`,
      mimeType: row.proof_file?.latest_version?.mime_type
    }
  }
  function viewReceipt(row: VipCreditRepayment) {
    if (!row.receipt) return
    viewingReceipt.value = row.receipt
    receiptViewerVisible.value = true
  }
  async function receiptFetcher() {
    const receipt = viewingReceipt.value
    if (!receipt) throw new Error('No receipt selected.')
    const blob = await downloadPortalReceiptPdf(receipt.id)
    const prefix = receipt.receipt_kind === 'ACKNOWLEDGEMENT' ? 'ACK' : 'OR'
    return { blob, filename: `${prefix}-${receipt.receipt_number || receipt.id}.pdf` }
  }
  function onProofSelected(file: UploadFile) {
    proofFile.value = file.raw || null
  }
  function clearProof() {
    proofFile.value = null
  }
  function seedRepaymentAmounts() {
    for (const charge of openCharges.value)
      if (!repaymentAmounts.value[charge.invoice_id])
        repaymentAmounts.value[charge.invoice_id] = charge.outstanding_amount
  }
  function applyRepaymentPage(page: Awaited<ReturnType<typeof fetchVipCreditRepayments>>) {
    repayments.value = page.data || []
    repaymentPage.value = page.current_page || 1
    repaymentPerPage.value = page.per_page || 20
    repaymentTotal.value = page.total ?? repayments.value.length
  }
  async function loadWorkspace(showLoading = true) {
    if (showLoading) loading.value = true
    try {
      const [profile, me] = await Promise.all([fetchPortalProfile(), fetchGetUserInfo()])
      realtime.startForUser(Number((me as any).id || (me as any).userId))
      const link = profile.customer_links?.find((item: any) => item.is_active)
      activeCustomerId.value = link?.customer_id || null
      if (!activeCustomerId.value) return
      const [nextSummary, nextBills, nextRepayments, documentTypes, nextAging] = await Promise.all([
        fetchVipCreditSummary(activeCustomerId.value),
        fetchPortalBills(activeCustomerId.value),
        fetchVipCreditRepayments(activeCustomerId.value, repaymentPage.value),
        fetchDocumentTypes({ purpose: 'PAYMENT_PROOF', is_active: true }),
        fetchPortalCreditAging(activeCustomerId.value, agingAsOf.value)
      ])
      if (!showLoading && isEditingCredit()) return
      summary.value = nextSummary
      bills.value = nextBills
      applyRepaymentPage(nextRepayments)
      proofType.value = documentTypes[0] || null
      aging.value = nextAging
      seedRepaymentAmounts()
    } catch (error: any) {
      if (showLoading) ElMessage.error(error?.message || 'Unable to load VIP credit information.')
    } finally {
      if (showLoading) loading.value = false
    }
  }
  async function loadRepayments(page = repaymentPage.value) {
    if (!activeCustomerId.value) return
    try {
      applyRepaymentPage(await fetchVipCreditRepayments(activeCustomerId.value, page))
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load repayment history.')
    }
  }
  async function loadAging() {
    if (!activeCustomerId.value) return
    try {
      aging.value = await fetchPortalCreditAging(activeCustomerId.value, agingAsOf.value)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load credit aging.')
    }
  }
  async function chargeSelected() {
    if (!activeCustomerId.value) return
    charging.value = true
    try {
      summary.value = await chargeBillsToVipCredit({
        customer_id: activeCustomerId.value,
        allocations: selectedBills.value.map((bill) => ({
          invoice_id: bill.id,
          expected_invoice_lock_version: bill.lock_version,
          requested_amount: bill.outstanding_amount
        }))
      })
      selectedBills.value = []
      ElMessage.success('Selected bills are now on VIP credit with captured due dates.')
      await loadWorkspace()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to charge the selected bills to credit.')
    } finally {
      charging.value = false
    }
  }
  async function uploadProof(): Promise<number> {
    if (!proofFile.value || !proofType.value)
      throw new Error(
        'Select a payment-proof file and ensure an active Payment Proof document type is configured.'
      )
    const data = new FormData()
    data.append('file', proofFile.value)
    data.append('document_type_id', String(proofType.value.id))
    return (await uploadPrivateFile(data)).id
  }
  async function submitRepayment() {
    if (!activeCustomerId.value) return
    submitting.value = true
    try {
      const fileId = await uploadProof()
      await submitVipCreditRepayment({
        customer_id: activeCustomerId.value,
        proof_file_id: fileId,
        declared_reference: declaredReference.value || undefined,
        allocations: selectedCharges.value.map((charge) => ({
          invoice_id: charge.invoice_id,
          expected_invoice_lock_version: charge.invoice_lock_version,
          requested_amount: repaymentAmounts.value[charge.invoice_id] || charge.outstanding_amount
        }))
      })
      ElMessage.success('VIP repayment proof submitted for verification.')
      repaymentDialog.value = false
      selectedCharges.value = []
      declaredReference.value = ''
      clearProof()
      repaymentPage.value = 1
      await loadWorkspace()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to submit VIP repayment proof.')
    } finally {
      submitting.value = false
    }
  }
  function openResubmit(row: VipCreditRepayment) {
    resubmitTarget.value = row
    clearProof()
    resubmitDialog.value = true
  }
  async function confirmResubmit() {
    if (!resubmitTarget.value) return
    submitting.value = true
    try {
      const fileId = await uploadProof()
      await resubmitVipCreditRepayment(resubmitTarget.value.id, fileId)
      ElMessage.success('Corrected repayment proof returned to the review queue.')
      resubmitDialog.value = false
      resubmitTarget.value = null
      clearProof()
      await loadWorkspace()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to resubmit the repayment proof.')
    } finally {
      submitting.value = false
    }
  }
  onMounted(() => loadWorkspace())
</script>

<style scoped lang="scss">
  .vip-aging-collapse {
    border: none;

    :deep(.el-collapse-item__header),
    :deep(.el-collapse-item__wrap) {
      background: transparent;
      border-bottom: none;
    }

    :deep(.el-collapse-item__header) {
      height: auto;
      line-height: 1.4;
    }

    :deep(.el-collapse-item__content) {
      padding-bottom: 0;
    }
  }
</style>
