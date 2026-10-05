<template>
  <div class="page-content space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:shield-check-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">VIP Repayment Review</h1>
          <p class="mt-1 max-w-2xl text-sm text-g-500">
            Claim the oldest proof, verify actual funds and post only the explicit confirmed
            allocations.
          </p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <ElButton :loading="loading" @click="load">
          <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
          Refresh
        </ElButton>
        <ElButton type="primary" :loading="claiming" @click="claim">Claim oldest</ElButton>
      </div>
    </header>
    <section class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>Waiting bank-transfer proofs</h4>
          <p>A proof is not a receipt until the confirmed allocations are posted</p>
        </div>
      </div>
      <ElAlert
        class="mb-4"
        type="info"
        :closable="false"
        show-icon
        title="Pending proof does not change the bill due date or principal aging. Review the oldest submissions promptly; approve only confirmed funds or reject with a reason."
      />
      <ElAlert
        v-if="overdueCount > 0"
        class="mb-4"
        type="warning"
        :closable="false"
        show-icon
        :title="`${overdueCount} VIP repayment proof${overdueCount === 1 ? '' : 's'} past the review target. Verify the bank record and decide each proof.`"
      />
      <ElEmpty
        v-if="!loading && submissions.length === 0"
        description="No VIP repayment proof is waiting for review."
      />
      <ElTable v-else v-loading="loading" :data="submissions"
        ><ElTableColumn prop="id" label="Submission" width="110"
          ><template #default="{ row }">#{{ row.id }}</template></ElTableColumn
        ><ElTableColumn label="First submitted" min-width="170"
          ><template #default="{ row }">{{
            formatDateTimeManila(row.initial_submitted_at)
          }}</template></ElTableColumn
        ><ElTableColumn label="Review by" min-width="190"
          ><template #default="{ row }">
            <span>{{ formatDateTimeManila(row.review_due_at) }}</span>
            <ElTag v-if="row.review_overdue" class="ml-2" type="warning" size="small"
              >Overdue</ElTag
            >
          </template></ElTableColumn
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
    </section>
  </div>
</template>
<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'
  import { fetchGetUserInfo } from '@/api/auth'
  import { useAuthoritativeRealtimeRefresh } from '@/composables/useAuthoritativeRealtimeRefresh'
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
  const overdueCount = computed(() => submissions.value.filter((row) => row.review_overdue).length)
  const realtime = useAuthoritativeRealtimeRefresh({
    scope: 'vip_credit',
    refresh: () => load(),
    isBusy: () => claiming.value
  })
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
  onMounted(async () => {
    const me = await fetchGetUserInfo()
    realtime.startForOrganization(
      Number((me as any).organization?.id || (me as any).organization_id)
    )
    await load()
  })
</script>
