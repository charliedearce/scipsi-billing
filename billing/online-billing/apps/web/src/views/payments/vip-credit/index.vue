<template>
  <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <section
      class="rounded-2xl bg-gradient-to-r from-indigo-950 to-slate-900 text-white p-6 shadow-sm flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <h1 class="text-xl font-bold">My VIP Credit</h1>
        <p class="mt-1 text-sm text-indigo-100"
          >Credit terms do not mark a bill as paid. Bank repayments are verified before a collection
          receipt is issued.</p
        >
      </div>
      <ElButton
        :loading="loading"
        plain
        class="!bg-white/10 !border-white/20 !text-white"
        @click="loadWorkspace"
        >Refresh</ElButton
      >
    </section>

    <ElAlert
      v-if="summary && !summary.eligible"
      type="warning"
      :closable="false"
      show-icon
      :title="summary.reason || 'VIP credit is not available for new charges.'"
    />

    <div v-if="summary?.account" class="grid grid-cols-1 md:grid-cols-4 gap-4">
      <MetricCard
        label="Credit Exposure"
        :value="currency(summary.exposure_amount)"
        icon="ri:wallet-3-line"
      />
      <MetricCard
        label="Available Credit"
        :value="
          summary.terms?.credit_limit_mode === 'UNLIMITED'
            ? 'Unlimited'
            : currency(summary.available_credit_amount)
        "
        icon="ri:line-chart-line"
      />
      <MetricCard
        label="Overdue Credit"
        :value="currency(summary.overdue_amount)"
        icon="ri:alarm-warning-line"
        :warning="Number(summary.overdue_amount || 0) > 0"
      />
      <MetricCard
        label="Terms"
        :value="`${summary.terms?.payment_terms_days || 0} calendar days`"
        :detail="summary.profile?.status || ''"
        icon="ri:file-list-3-line"
      />
    </div>

    <ElCard v-if="summary?.account" shadow="never">
      <template #header>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="font-semibold">Principal aging</h2>
            <p class="text-xs text-slate-500 mt-1"
              >Historical balances use effective receipt business dates. They are not a second
              credit ledger.</p
            >
          </div>
          <ElDatePicker
            v-model="agingAsOf"
            type="date"
            value-format="YYYY-MM-DD"
            class="!w-44"
            @change="loadAging"
          />
        </div>
      </template>
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
        description="No VIP credit is included at this cutoff."
      />
      <div
        v-for="currencyAging in aging?.currencies || []"
        :key="currencyAging.currency"
        class="space-y-4 mb-5 last:mb-0"
      >
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 text-sm">
          <div
            v-for="bucket in agingBuckets"
            :key="bucket.key"
            class="rounded-lg border border-slate-200 p-3"
          >
            <p class="text-xs text-slate-500">{{ bucket.label }}</p>
            <p class="font-semibold mt-1">{{
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
    </ElCard>

    <ElCard shadow="never" v-loading="loading">
      <template #header
        ><div
          ><h2 class="font-semibold">Charge eligible bills to credit</h2
          ><p class="text-xs text-slate-500 mt-1"
            >Only fully unpaid bills can be charged. The server validates account status, policy,
            current balance, overdue restrictions and shared exposure again at confirmation.</p
          ></div
        ></template
      >
      <ElEmpty
        v-if="!activeCustomerId"
        description="No active customer account is linked to this login."
      />
      <ElTable v-else :data="eligibleBills" @selection-change="selectedBills = $event">
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
        <span class="text-sm text-slate-500"
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
    </ElCard>

    <ElCard shadow="never">
      <template #header
        ><div
          ><h2 class="font-semibold">Credit bills and bank repayment</h2
          ><p class="text-xs text-slate-500 mt-1"
            >Choose one or more charged bills and explicit amounts. Partial repayment is supported;
            submit a clean bank-transfer proof for verification.</p
          ></div
        ></template
      >
      <ElTable :data="openCharges" @selection-change="selectedCharges = $event">
        <ElTableColumn type="selection" width="50" />
        <ElTableColumn prop="invoice_number" label="Invoice" min-width="150" />
        <ElTableColumn prop="due_date" label="Due date" min-width="130"
          ><template #default="{ row }"
            ><span :class="row.is_overdue ? 'text-rose-600 font-medium' : ''">{{
              row.due_date
            }}</span></template
          ></ElTableColumn
        >
        <ElTableColumn label="Credit balance" align="right" min-width="150"
          ><template #default="{ row }">{{
            currency(row.outstanding_amount, row.currency)
          }}</template></ElTableColumn
        >
        <ElTableColumn label="Bank repayment amount" min-width="190"
          ><template #default="{ row }"
            ><ElInput
              v-model="repaymentAmounts[row.invoice_id]"
              :disabled="!isChargeSelected(row.invoice_id)"
              inputmode="decimal" /></template
        ></ElTableColumn>
      </ElTable>
      <div class="mt-4 flex justify-end"
        ><ElButton
          type="primary"
          :disabled="selectedCharges.length === 0"
          @click="repaymentDialog = true"
          >Upload bank repayment proof</ElButton
        ></div
      >
    </ElCard>

    <ElCard shadow="never">
      <template #header><h2 class="font-semibold">VIP repayment history</h2></template>
      <ElEmpty v-if="repayments.length === 0" description="No VIP bank repayments submitted." />
      <ElTable v-else :data="repayments">
        <ElTableColumn prop="id" label="Submission" width="110"
          ><template #default="{ row }">#{{ row.id }}</template></ElTableColumn
        >
        <ElTableColumn label="Bills" min-width="180"
          ><template #default="{ row }"
            ><ElTag
              v-for="item in row.allocations"
              :key="item.invoice_id"
              class="mr-1"
              size="small"
              >{{ item.invoice?.invoice_number || `#${item.invoice_id}` }}</ElTag
            ></template
          ></ElTableColumn
        >
        <ElTableColumn label="Requested" min-width="130" align="right"
          ><template #default="{ row }">{{
            currency(row.requested_amount, row.currency)
          }}</template></ElTableColumn
        >
        <ElTableColumn prop="status" label="Status" min-width="130" />
        <ElTableColumn label="Receipt / reason" min-width="260"
          ><template #default="{ row }"
            ><span v-if="row.receipt" class="text-emerald-600"
              >Receipt {{ row.receipt.receipt_number }}</span
            ><span v-else-if="row.status === 'REJECTED'" class="text-rose-600">{{
              row.rejection_reason
            }}</span
            ><span v-else class="text-slate-500">Awaiting review</span></template
          ></ElTableColumn
        >
      </ElTable>
    </ElCard>

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
      <div class="mt-5 space-y-4">
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
          ><div class="py-3 text-slate-500"
            >Drop the bank receipt here or click to choose a file.</div
          ></ElUpload
        >
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
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage, type UploadFile } from 'element-plus'
  import { fetchPortalProfile } from '@/api/registration'
  import MetricCard from '@/components/business/MetricCard.vue'
  import { fetchPortalBills, type PortalBill } from '@/api/payments'
  import {
    fetchDocumentTypes,
    uploadPrivateFile,
    type DocumentTypeItem
  } from '@/api/documentRequirements'
  import {
    chargeBillsToVipCredit,
    fetchVipCreditRepayments,
    fetchVipCreditSummary,
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
  const activeCustomerId = ref<number | null>(null)
  const bills = ref<PortalBill[]>([])
  const summary = ref<VipCreditSummary | null>(null)
  const repayments = ref<VipCreditRepayment[]>([])
  const selectedBills = ref<PortalBill[]>([])
  const selectedCharges = ref<CreditCharge[]>([])
  const repaymentAmounts = ref<Record<number, string>>({})
  const proofFile = ref<File | null>(null)
  const proofType = ref<DocumentTypeItem | null>(null)
  const declaredReference = ref('')
  const aging = ref<VipCreditAging | null>(null)
  const agingAsOf = ref(new Date().toISOString().slice(0, 10))
  const agingBuckets = [
    { key: 'CURRENT', label: 'Current' },
    { key: '1_30', label: '1–30' },
    { key: '31_60', label: '31–60' },
    { key: '61_90', label: '61–90' },
    { key: '91_PLUS', label: '91+' },
    { key: 'UNCLASSIFIED', label: 'Needs terms review' }
  ] as const
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
  const selectedBillTotal = computed(() =>
    selectedBills.value
      .reduce((total, bill) => total + Number(bill.outstanding_amount), 0)
      .toFixed(2)
  )
  const currency = (amount?: string | null, code = summary.value?.policy?.currency || 'PHP') =>
    amount === null || amount === undefined
      ? '—'
      : new Intl.NumberFormat('en-PH', { style: 'currency', currency: code }).format(Number(amount))
  const isChargeSelected = (id: number) =>
    selectedCharges.value.some((charge) => charge.invoice_id === id)
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
  async function loadWorkspace() {
    loading.value = true
    try {
      const profile = await fetchPortalProfile()
      const link = profile.customer_links?.find((item: any) => item.is_active)
      activeCustomerId.value = link?.customer_id || null
      if (!activeCustomerId.value) return
      const [nextSummary, nextBills, nextRepayments, documentTypes, nextAging] = await Promise.all([
        fetchVipCreditSummary(activeCustomerId.value),
        fetchPortalBills(activeCustomerId.value),
        fetchVipCreditRepayments(activeCustomerId.value),
        fetchDocumentTypes({ purpose: 'PAYMENT_PROOF', is_active: true }),
        fetchPortalCreditAging(activeCustomerId.value, agingAsOf.value)
      ])
      summary.value = nextSummary
      bills.value = nextBills
      repayments.value = nextRepayments.data || []
      proofType.value = documentTypes[0] || null
      aging.value = nextAging
      seedRepaymentAmounts()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load VIP credit information.')
    } finally {
      loading.value = false
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
      await loadWorkspace()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to submit VIP repayment proof.')
    } finally {
      submitting.value = false
    }
  }
  onMounted(loadWorkspace)
</script>
