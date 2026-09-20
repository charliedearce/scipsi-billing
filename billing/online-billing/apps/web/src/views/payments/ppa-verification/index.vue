<template>
  <div class="max-w-4xl mx-auto p-4 sm:p-6 space-y-6">
    <section class="rounded-2xl bg-white border border-slate-200 p-6"
      ><h1 class="text-xl font-bold">PPA Bill Verification</h1
      ><p class="text-sm text-slate-500 mt-1"
        >Checks current settlement and credit status only. It never posts money or issues a
        release.</p
      ><div class="mt-5 flex gap-2"
        ><ElInput
          v-model.trim="invoiceNumber"
          placeholder="Enter invoice number"
          @keyup.enter="verify"
        /><ElButton type="primary" :loading="loading" :disabled="!invoiceNumber" @click="verify"
          >Verify</ElButton
        ></div
      ></section
    >
    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="Paid and On credit are intentionally separate statuses. Clearance eligibility is a policy result, not a formal clearance document."
    />
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
          ><span class="text-xs text-slate-500"
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
        <p v-if="result.receipt_history_truncated" class="text-xs text-slate-500 mt-2"
          >Only the 10 most recent confirmed receipts are displayed.</p
        ></section
      ><p class="text-xs text-slate-500 mt-4">{{ result.notice }}</p></ElCard
    >
  </div>
</template>

<script setup lang="ts">
  import { ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import { verifyPpaBill, type PpaBillSettlement } from '@/api/ppaClearance'

  defineOptions({ name: 'PpaBillVerification' })
  const invoiceNumber = ref('')
  const loading = ref(false)
  const result = ref<PpaBillSettlement | null>(null)
  const money = (amount: string, currency: string) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number(amount))
  const dateTime = (value: string) =>
    new Intl.DateTimeFormat('en-PH', {
      dateStyle: 'medium',
      timeStyle: 'short',
      timeZone: 'Asia/Manila'
    }).format(new Date(value))
  async function verify() {
    loading.value = true
    try {
      result.value = await verifyPpaBill(invoiceNumber.value)
    } catch (error: any) {
      result.value = null
      ElMessage.error(error?.message || 'Invoice is unavailable in your PPA scope.')
    } finally {
      loading.value = false
    }
  }
</script>
