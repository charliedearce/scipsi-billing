<template>
  <div class="page-content space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:dashboard-3-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">Today's work</h1>
          <p class="mt-1 text-sm text-g-500">
            {{ userDisplayName }} · {{ currentDate }}. Start with the claim you already hold, then
            the oldest item still waiting.
          </p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <ElButton @click="router.push('/walk-in-billing')">
          <ArtSvgIcon icon="ri:store-2-line" class="mr-1" />
          Walk-in
        </ElButton>
        <ElButton :loading="loading" @click="load">
          <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
          Refresh
        </ElButton>
      </div>
    </header>

    <article v-if="assignment" class="art-card-sm border border-theme/25 bg-theme/10 p-4 sm:p-5">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
          <p class="text-xs font-semibold uppercase tracking-wide text-theme">Your open claim</p>
          <h2 class="mt-1 text-base font-medium text-g-900">
            {{ ticketLabel(assignment) }} · {{ customerName(assignment.customer) }}
          </h2>
          <p class="mt-1 text-sm text-g-600">{{ assignmentLabel(assignment.status) }}</p>
        </div>
        <ElButton type="primary" @click="router.push('/billing-request-queue')">
          Continue this bill
        </ElButton>
      </div>
    </article>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
      <button
        v-for="card in cards"
        :key="card.title"
        type="button"
        class="art-card-sm p-4 text-left transition-colors hover:border-theme/40"
        @click="router.push(card.to)"
      >
        <div class="flex items-start justify-between gap-3">
          <div class="size-9 flex-cc shrink-0 rounded-lg bg-g-200/70 text-g-700">
            <ArtSvgIcon :icon="card.icon" class="text-lg" />
          </div>
          <ArtSvgIcon icon="ri:arrow-right-s-line" class="text-g-400" />
        </div>
        <p class="mt-3 text-2xl font-semibold text-g-900">{{ card.count }}</p>
        <p class="mt-1 text-sm font-medium text-g-800">{{ card.title }}</p>
        <p class="mt-1 text-xs text-g-500">{{ card.detail }}</p>
      </button>
    </div>

    <section class="art-card overflow-hidden !p-0">
      <div class="flex items-center justify-between border-b border-g-200 px-4 py-3 sm:px-5">
        <h2 class="text-sm font-medium text-g-900">Next in line</h2>
        <span v-if="loading" class="text-xs text-g-500">Loading</span>
      </div>
      <ul v-if="nextItems.length" class="divide-y divide-g-200">
        <li v-for="item in nextItems" :key="item.key">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-g-100/70 sm:px-5"
            @click="router.push(item.to)"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium text-g-900">{{ item.title }}</p>
              <p class="mt-0.5 truncate text-xs text-g-500">{{ item.detail }}</p>
            </div>
            <span class="shrink-0 text-xs text-theme">{{ item.action }}</span>
          </button>
        </li>
      </ul>
      <p v-else class="px-4 py-8 text-sm text-g-500 sm:px-5">
        {{
          loading
            ? 'Checking the queues…'
            : 'Nothing is waiting. You can start a walk-in bill or open a queue if a new item arrives.'
        }}
      </p>
    </section>

    <p v-if="loadError" class="text-sm text-error">{{ loadError }}</p>
  </div>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import request from '@/utils/http'
  import { fetchGetUserInfo } from '@/api/auth'
  import { onDataRefresh } from '@/utils/echo'
  import { useUserStore } from '@/store/modules/user'
  import type { BillingRequestItem, TellerQueueSummary } from '@/api/billingRequests'
  import type { BillClaimItem, BillClaimListResponse } from '@/api/billClaims'
  import type { ManualPaymentSubmission } from '@/api/payments'
  import type { VipCreditRepayment } from '@/api/vipCredit'

  defineOptions({ name: 'TellerWorkboard' })

  interface CountPage<T> {
    data?: T[]
    total?: number
  }

  interface NextItem {
    key: string
    title: string
    detail: string
    action: string
    to: string
  }

  const router = useRouter()
  const userStore = useUserStore()
  const loading = ref(false)
  const loadError = ref('')
  const queue = ref<TellerQueueSummary | null>(null)
  const proofsWaiting = ref<CountPage<ManualPaymentSubmission> | null>(null)
  const proofsInReview = ref<CountPage<ManualPaymentSubmission> | null>(null)
  const claims = ref<BillClaimListResponse | null>(null)
  const vipWaiting = ref<CountPage<VipCreditRepayment> | null>(null)
  const vipInReview = ref<CountPage<VipCreditRepayment> | null>(null)
  const stopRealtime: Array<() => void> = []
  let recoveryTimer: ReturnType<typeof setInterval> | undefined

  const userDisplayName = computed(() => userStore.info?.userName || 'Teller')
  const currentDate = computed(() =>
    new Intl.DateTimeFormat('en-PH', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      timeZone: 'Asia/Manila'
    }).format(new Date())
  )

  const assignment = computed(() => queue.value?.my_active_assignment || null)

  const cards = computed(() => [
    {
      title: 'Billing requests waiting',
      count: queue.value?.queued_count ?? 0,
      detail: 'Oldest customer request is claimed first.',
      icon: 'ri:user-voice-line',
      to: '/billing-request-queue'
    },
    {
      title: 'Payment proofs to claim',
      count: countOf(proofsWaiting.value),
      detail: `${countOf(proofsInReview.value)} already in review.`,
      icon: 'ri:bank-line',
      to: '/payment-proof-review'
    },
    {
      title: 'Bill claims to review',
      count: claims.value?.total ?? 0,
      detail: 'Customers waiting for a claim decision.',
      icon: 'ri:shield-user-line',
      to: '/bill-claim-review'
    },
    {
      title: 'VIP repayments to claim',
      count: countOf(vipWaiting.value),
      detail: `${countOf(vipInReview.value)} already in review.`,
      icon: 'ri:hand-coin-line',
      to: '/vip-credit-review'
    }
  ])

  const nextItems = computed<NextItem[]>(() => {
    const items: NextItem[] = []
    const oldestRequest = queue.value?.waiting_queue?.[0]
    if (oldestRequest) {
      items.push({
        key: `request-${oldestRequest.id}`,
        title: `${ticketLabel(oldestRequest)} · ${customerName(oldestRequest.customer)}`,
        detail: 'Oldest billing request still waiting.',
        action: 'Open queue',
        to: '/billing-request-queue'
      })
    }
    const oldestProof = proofsWaiting.value?.data?.[0]
    if (oldestProof) {
      items.push({
        key: `proof-${oldestProof.id}`,
        title: `Payment proof · ${customerName(oldestProof.customer)}`,
        detail: proofDetail(oldestProof),
        action: 'Review proofs',
        to: '/payment-proof-review'
      })
    }
    const oldestClaim = claims.value?.data?.[0]
    if (oldestClaim) {
      items.push({
        key: `claim-${oldestClaim.id}`,
        title: `Bill claim · ${claimTitle(oldestClaim)}`,
        detail: 'Waiting for a teller decision.',
        action: 'Review claims',
        to: '/bill-claim-review'
      })
    }
    const oldestVip = vipWaiting.value?.data?.[0]
    if (oldestVip) {
      items.push({
        key: `vip-${oldestVip.id}`,
        title: `VIP repayment · ${customerName(oldestVip.customer)}`,
        detail: 'Oldest repayment proof still waiting.',
        action: 'Review repayments',
        to: '/vip-credit-review'
      })
    }
    return items
  })

  function countOf(page: CountPage<unknown> | null) {
    return page?.total ?? 0
  }

  function customerName(customer?: { name?: string } | null) {
    return customer?.name || 'Customer'
  }

  function ticketLabel(item: BillingRequestItem) {
    return item.ticket_number != null ? `Ticket #${item.ticket_number}` : item.transaction_no
  }

  function assignmentLabel(status: string) {
    if (status === 'IN_REVIEW') return 'Reviewing the customer files.'
    if (status === 'BILLING_IN_PROGRESS') return 'Encoding the bill.'
    if (status === 'BILL_READY') return 'The bill is ready.'
    return status.replaceAll('_', ' ').toLowerCase()
  }

  function proofDetail(proof: ManualPaymentSubmission) {
    const invoice = proof.items?.[0]?.invoice?.invoice_number
    return invoice ? `Covers ${invoice}.` : 'Waiting to be claimed.'
  }

  function claimTitle(claim: BillClaimItem) {
    const invoice = claim.invoice?.invoice_number || claim.invoice_number
    const name = customerName(claim.customer)
    return invoice ? `${invoice} · ${name}` : name
  }

  async function load() {
    loading.value = true
    loadError.value = ''
    const quiet = { showErrorMessage: false as const }
    const results = await Promise.allSettled([
      request.get<TellerQueueSummary>({ url: '/api/v1/teller/queue', ...quiet }),
      request.get<CountPage<ManualPaymentSubmission>>({
        url: '/api/v1/teller/payment-submissions',
        params: { status: 'SUBMITTED' },
        ...quiet
      }),
      request.get<CountPage<ManualPaymentSubmission>>({
        url: '/api/v1/teller/payment-submissions',
        params: { status: 'IN_REVIEW' },
        ...quiet
      }),
      request.get<BillClaimListResponse>({
        url: '/api/v1/teller/bill-claims',
        params: { status: 'PENDING_TELLER_REVIEW' },
        ...quiet
      }),
      request.get<CountPage<VipCreditRepayment>>({
        url: '/api/v1/teller/credit-repayments',
        params: { status: 'SUBMITTED' },
        ...quiet
      }),
      request.get<CountPage<VipCreditRepayment>>({
        url: '/api/v1/teller/credit-repayments',
        params: { status: 'IN_REVIEW' },
        ...quiet
      })
    ])
    const [queueResult, waitingResult, reviewResult, claimResult, vipWaitResult, vipReviewResult] =
      results
    if (queueResult.status === 'fulfilled') queue.value = queueResult.value
    if (waitingResult.status === 'fulfilled') proofsWaiting.value = waitingResult.value
    if (reviewResult.status === 'fulfilled') proofsInReview.value = reviewResult.value
    if (claimResult.status === 'fulfilled') claims.value = claimResult.value
    if (vipWaitResult.status === 'fulfilled') vipWaiting.value = vipWaitResult.value
    if (vipReviewResult.status === 'fulfilled') vipInReview.value = vipReviewResult.value
    if (results.some((result) => result.status === 'rejected')) {
      loadError.value = 'Some queues could not be loaded. Refresh to try again.'
    }
    loading.value = false
  }

  onMounted(async () => {
    await load()
    try {
      const me = await fetchGetUserInfo()
      const organizationId = Number((me as any).organization?.id)
      if (organizationId) {
        stopRealtime.push(onDataRefresh(organizationId, 'queue', load))
        stopRealtime.push(onDataRefresh(organizationId, 'payments', load))
      }
    } catch {
      // The recovery timer keeps the workboard current if realtime is unavailable.
    }
    recoveryTimer = setInterval(() => {
      if (document.visibilityState === 'visible' && !loading.value) void load()
    }, 30_000)
  })

  onBeforeUnmount(() => {
    stopRealtime.forEach((stop) => stop())
    if (recoveryTimer) clearInterval(recoveryTimer)
  })
</script>
