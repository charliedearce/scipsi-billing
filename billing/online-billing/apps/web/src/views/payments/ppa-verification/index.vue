<template>
  <div class="mx-auto max-w-6xl space-y-6 p-4 sm:p-6">
    <section class="art-card p-6">
      <h1 class="text-xl font-semibold text-g-900">PPA Bill Verification</h1>
      <p class="mt-1 text-sm text-g-500">
        Search a bill or an official receipt. The check stays read-only and never posts money or
        issues a release.
      </p>
      <div class="mt-5 flex gap-2">
        <ElInput
          v-model.trim="searchNumber"
          placeholder="Invoice or official receipt number"
          @keyup.enter="verify"
        />
        <ElButton type="primary" :loading="loading" :disabled="!searchNumber" @click="verify">
          Verify
        </ElButton>
      </div>
    </section>
    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="Paid and On credit are intentionally separate statuses. Clearance eligibility is a policy result, not a formal clearance document."
    />
    <section v-if="documentKind" class="art-card overflow-hidden">
      <div class="border-b border-g-300 px-5 py-3">
        <h2 class="text-sm font-semibold text-g-900">{{ layoutTitle }}</h2>
        <p class="mt-1 text-xs text-g-500">
          The posted document. Payment status stays in the summary below.
        </p>
      </div>
      <div v-if="layoutLoading" class="px-5 py-8 text-sm text-g-500"
        >Loading the issued invoice…</div
      >
      <p v-else-if="layoutError" class="px-5 py-8 text-sm text-g-500">{{ layoutError }}</p>
      <iframe
        v-else-if="layoutUrl"
        :src="layoutUrl"
        class="h-[760px] w-full bg-white"
        :title="layoutTitle"
      />
    </section>
    <section v-if="linkedInvoices.length > 1" class="art-card p-5">
      <h2 class="text-sm font-semibold text-g-900">Bills on this receipt</h2>
      <div class="mt-3 flex flex-wrap gap-2">
        <ElButton
          v-for="invoice in linkedInvoices"
          :key="invoice.invoice_number"
          size="small"
          :type="result?.invoice_number === invoice.invoice_number ? 'primary' : 'default'"
          @click="showLinkedInvoice(invoice.invoice_number)"
        >
          {{ invoice.invoice_number }}
        </ElButton>
      </div>
    </section>
    <ElCard v-if="result" shadow="never"
      ><template #header
        ><div class="flex items-center justify-between"
          ><h2 class="font-semibold">{{ result.invoice_number }}</h2
          ><ElTag :type="result.clearance_eligibility === 'NOT_ELIGIBLE' ? 'danger' : 'success'">{{
            result.clearance_eligibility
          }}</ElTag></div
        ></template
      ><ElDescriptions :column="1" border
        ><ElDescriptionsItem label="Customer"
          >{{ result.customer.name }} · {{ result.customer.account_number }}</ElDescriptionsItem
        ><ElDescriptionsItem label="Settlement">{{ result.settlement_status }}</ElDescriptionsItem
        ><ElDescriptionsItem label="Invoice total">{{
          money(result.invoice_total_amount, result.currency)
        }}</ElDescriptionsItem
        ><ElDescriptionsItem label="Confirmed applied amount">{{
          money(result.applied_amount, result.currency)
        }}</ElDescriptionsItem
        ><ElDescriptionsItem label="Credit status">{{ result.credit_status }}</ElDescriptionsItem
        ><ElDescriptionsItem label="Outstanding">{{
          money(result.outstanding_amount, result.currency)
        }}</ElDescriptionsItem
        ><ElDescriptionsItem label="Credit due date">{{
          result.credit_due_date || '—'
        }}</ElDescriptionsItem
        ><ElDescriptionsItem label="Policy">{{
          result.policy
            ? `Version ${result.policy.version}`
            : 'No active PPA credit-acceptance policy; full payment required.'
        }}</ElDescriptionsItem
        ><ElDescriptionsItem label="Checked at">{{
          dateTime(result.checked_at)
        }}</ElDescriptionsItem></ElDescriptions
      ><section class="mt-5"
        ><div class="flex items-center justify-between mb-2"
          ><h3 class="font-medium text-sm">Confirmed collection receipts</h3
          ><span class="text-xs text-g-500"
            >{{ result.confirmed_receipt_count }} recorded</span
          ></div
        ><ElTable v-if="result.receipts.length" :data="result.receipts" size="small" border
          ><ElTableColumn prop="receipt_number" label="Receipt number" min-width="180" />
          <ElTableColumn prop="business_date" label="Business date" width="150" />
          <ElTableColumn label="Applied amount" min-width="160" align="right"
            ><template #default="{ row }">{{
              money(row.applied_amount, result.currency)
            }}</template></ElTableColumn
          ></ElTable
        ><ElEmpty
          v-else
          description="No confirmed receipt has been applied to this bill."
          :image-size="56"
        />
        <p v-if="result.receipt_history_truncated" class="mt-2 text-xs text-g-500"
          >Only the 10 most recent confirmed receipts are displayed.</p
        ></section
      ><section v-if="result.reversed_receipt_count > 0" class="mt-5"
        ><div class="flex items-center justify-between mb-2"
          ><h3 class="font-medium text-sm">Reversed collection receipts</h3
          ><span class="text-xs text-g-500"
            >{{ result.reversed_receipt_count }} history only</span
          ></div
        ><ElAlert
          class="mb-3"
          type="warning"
          :closable="false"
          show-icon
          title="Reversed receipts do not count toward confirmed settlement or clearance eligibility."
        />
        <ElTable :data="result.reversed_receipts" size="small" border
          ><ElTableColumn prop="receipt_number" label="Receipt number" min-width="180" />
          <ElTableColumn prop="business_date" label="Business date" width="150" />
          <ElTableColumn label="Former applied amount" min-width="180" align="right"
            ><template #default="{ row }">{{
              money(row.applied_amount, result.currency)
            }}</template></ElTableColumn
          ><ElTableColumn label="Status" width="120"
            ><template #default="{ row }"
              ><ElTag type="warning">{{ row.receipt_status }}</ElTag></template
            ></ElTableColumn
          ></ElTable
        >
        <p v-if="result.reversed_receipt_history_truncated" class="mt-2 text-xs text-g-500"
          >Only the 10 most recent reversed receipts are displayed.</p
        ></section
      ><p class="mt-4 text-xs text-g-500">{{ result.notice }}</p></ElCard
    >
  </div>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRoute } from 'vue-router'
  import { ElMessage } from 'element-plus'
  import {
    downloadPpaBillLayout,
    downloadPpaReceiptLayout,
    resolvePpaDocument,
    verifyPpaBill,
    type PpaBillSettlement,
    type PpaDocumentMatch
  } from '@/api/ppaClearance'

  defineOptions({ name: 'PpaBillVerification' })
  const route = useRoute()
  const searchNumber = ref('')
  const loading = ref(false)
  const result = ref<PpaBillSettlement | null>(null)
  const documentKind = ref<PpaDocumentMatch['document_kind'] | ''>('')
  const linkedInvoices = ref<PpaDocumentMatch['linked_invoices']>([])
  const layoutUrl = ref('')
  const layoutLoading = ref(false)
  const layoutError = ref('')
  const layoutTitle = computed(() => {
    if (documentKind.value === 'OFFICIAL_RECEIPT') return 'Official receipt'
    if (documentKind.value === 'ACKNOWLEDGEMENT_RECEIPT') return 'Acknowledgement receipt'
    return 'Issued invoice'
  })
  const money = (amount: string, currency: string) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number(amount))
  const dateTime = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: 'Asia/Manila'
    }).format(new Date(value))
  function clearLayout() {
    if (layoutUrl.value) URL.revokeObjectURL(layoutUrl.value)
    layoutUrl.value = ''
    layoutError.value = ''
  }

  async function loadLayout(kind: PpaDocumentMatch['document_kind'], number: string) {
    clearLayout()
    layoutLoading.value = true
    const missing =
      kind === 'INVOICE'
        ? 'The issued invoice layout is not ready.'
        : 'The issued receipt layout is not ready.'
    try {
      const blob =
        kind === 'INVOICE'
          ? await downloadPpaBillLayout(number)
          : await downloadPpaReceiptLayout(number)
      if (!(blob instanceof Blob) || blob.size < 5) {
        layoutError.value = missing
        return
      }
      layoutUrl.value = URL.createObjectURL(blob)
    } catch {
      layoutError.value = missing
    } finally {
      layoutLoading.value = false
    }
  }

  async function showLinkedInvoice(invoiceNumber: string) {
    try {
      result.value = await verifyPpaBill(invoiceNumber)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Invoice is unavailable in your PPA scope.')
    }
  }

  async function verify() {
    if (!searchNumber.value) return
    loading.value = true
    clearLayout()
    result.value = null
    documentKind.value = ''
    linkedInvoices.value = []
    try {
      const match = await resolvePpaDocument(searchNumber.value)
      documentKind.value = match.document_kind
      linkedInvoices.value = match.linked_invoices || []
      if (match.document_kind === 'INVOICE' && match.invoice_number) {
        result.value = await verifyPpaBill(match.invoice_number)
        await loadLayout('INVOICE', match.invoice_number)
      } else if (match.receipt_number) {
        const linked = linkedInvoices.value[0]?.invoice_number
        if (linked) result.value = await verifyPpaBill(linked)
        await loadLayout(match.document_kind, match.receipt_number)
      }
    } catch (error: any) {
      result.value = null
      documentKind.value = ''
      linkedInvoices.value = []
      clearLayout()
      ElMessage.error(error?.message || 'That bill or receipt is unavailable in your PPA scope.')
    } finally {
      loading.value = false
    }
  }

  function applyQueryNumber(value: unknown) {
    const number = typeof value === 'string' ? value.trim() : ''
    if (!number) return
    searchNumber.value = number
    void verify()
  }

  watch(
    () => route.query.number,
    (value) => applyQueryNumber(value)
  )

  onMounted(() => applyQueryNumber(route.query.number))
  onBeforeUnmount(clearLayout)
</script>
