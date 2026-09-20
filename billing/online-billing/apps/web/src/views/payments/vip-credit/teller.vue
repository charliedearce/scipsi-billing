<template>
  <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <section
      class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
      ><div
        ><h1 class="text-xl font-bold">VIP Repayment Review</h1
        ><p class="mt-1 text-sm text-slate-500"
          >Claim the oldest proof, verify actual funds and post only the explicit confirmed
          allocations.</p
        ></div
      ><div class="flex gap-2"
        ><ElButton :loading="loading" @click="load">Refresh</ElButton
        ><ElButton type="primary" :loading="claiming" @click="claim">Claim oldest</ElButton></div
      ></section
    >
    <ElTable v-loading="loading" :data="submissions"
      ><ElTableColumn prop="id" label="Submission" width="110"
        ><template #default="{ row }">#{{ row.id }}</template></ElTableColumn
      ><ElTableColumn label="Customer" min-width="180"
        ><template #default="{ row }">{{
          row.customer?.name || `Customer #${row.customer_id}`
        }}</template></ElTableColumn
      ><ElTableColumn label="Allocations" min-width="260"
        ><template #default="{ row }"
          ><div v-for="item in row.allocations" :key="item.invoice_id" class="text-xs"
            >{{ item.invoice?.invoice_number || `#${item.invoice_id}` }} —
            {{ money(item.requested_amount, row.currency) }}</div
          ></template
        ></ElTableColumn
      ><ElTableColumn
        prop="declared_reference"
        label="Customer reference"
        min-width="160"
      /><ElTableColumn prop="status" label="Status" min-width="120" /><ElTableColumn
        label="Actions"
        width="220"
        fixed="right"
        ><template #default="{ row }"
          ><ElButton
            v-if="row.status === 'IN_REVIEW'"
            size="small"
            type="success"
            @click="approve(row)"
            >Approve</ElButton
          ><ElButton
            v-if="row.status === 'IN_REVIEW'"
            size="small"
            type="danger"
            plain
            @click="reject(row)"
            >Reject</ElButton
          ></template
        ></ElTableColumn
      ></ElTable
    >
  </div>
</template>
<script setup lang="ts">
  import { onMounted, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    approveVipCreditRepayment,
    claimNextVipCreditRepayment,
    fetchTellerVipCreditRepayments,
    rejectVipCreditRepayment,
    type VipCreditRepayment
  } from '@/api/vipCredit'
  defineOptions({ name: 'VipCreditRepaymentReview' })
  const loading = ref(false)
  const claiming = ref(false)
  const submissions = ref<VipCreditRepayment[]>([])
  const money = (value: string, currency = 'PHP') =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number(value))
  async function load() {
    loading.value = true
    try {
      submissions.value = (await fetchTellerVipCreditRepayments()).data || []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load VIP repayment proofs.')
    } finally {
      loading.value = false
    }
  }
  async function claim() {
    claiming.value = true
    try {
      const item = await claimNextVipCreditRepayment()
      ElMessage.info(
        item ? `Claimed VIP repayment #${item.id}.` : 'No VIP repayment proof is waiting.'
      )
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to claim the next proof.')
    } finally {
      claiming.value = false
    }
  }
  async function approve(row: VipCreditRepayment) {
    try {
      const result = await ElMessageBox.prompt(
        'Enter the confirmed bank-transfer reference. This reference cannot be used by another settlement.',
        'Confirm funds',
        { inputPattern: /.{3,}/, inputErrorMessage: 'Enter at least three characters.' }
      )
      await approveVipCreditRepayment(row.id, {
        expected_version: row.lock_version,
        confirmed_reference: result.value,
        allocations: row.allocations.map((item) => ({
          invoice_id: item.invoice_id,
          cash_amount: item.requested_amount
        }))
      })
      ElMessage.success('VIP repayment verified and collection receipt posted.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to approve this repayment.')
    }
  }
  async function reject(row: VipCreditRepayment) {
    try {
      const result = await ElMessageBox.prompt(
        'Tell the customer what must be corrected. No receipt will be posted.',
        'Reject repayment proof',
        { inputPattern: /.{3,}/, inputErrorMessage: 'Give a reason of at least three characters.' }
      )
      await rejectVipCreditRepayment(row.id, row.lock_version, result.value)
      ElMessage.success('VIP repayment proof rejected.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to reject this repayment.')
    }
  }
  onMounted(load)
</script>
