<template>
  <div class="page-content space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:ship-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">PPA overview</h1>
          <p class="mt-1 text-sm text-g-500">
            {{ userDisplayName }} · {{ currentDate }}. Look up a bill or receipt, then review the
            month-to-date share of paid bills in your location.
          </p>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <ElButton @click="router.push('/ppa-verification')">
          <ArtSvgIcon icon="ri:shield-check-line" class="mr-1" />
          Bill Verification
        </ElButton>
        <ElButton @click="router.push('/ppa-share-report')">
          <ArtSvgIcon icon="ri:pie-chart-2-line" class="mr-1" />
          Share Report
        </ElButton>
        <ElButton :loading="loading" @click="load">
          <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
          Refresh
        </ElButton>
      </div>
    </header>

    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="Verification is read-only. Paid and On credit stay separate, and this check never posts money or issues a formal clearance."
    />

    <section class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>Verify a document</h4>
          <p>Search by invoice or official receipt number</p>
        </div>
      </div>
      <div class="flex flex-col gap-2 sm:flex-row">
        <ElInput
          v-model.trim="searchNumber"
          placeholder="Invoice or official receipt number"
          class="sm:max-w-md"
          @keyup.enter="openVerification"
        />
        <ElButton type="primary" :disabled="!searchNumber" @click="openVerification">
          Verify
        </ElButton>
      </div>
    </section>

    <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
      <button
        v-for="card in currencyCards"
        :key="card.currency"
        type="button"
        class="art-card-sm p-4 text-left transition-colors hover:border-theme/40"
        @click="router.push('/ppa-share-report')"
      >
        <p class="text-xs font-semibold uppercase tracking-wide text-g-500">{{ card.currency }}</p>
        <p class="mt-2 text-2xl font-semibold text-g-900">{{ card.ppaShare }}</p>
        <p class="mt-1 text-sm font-medium text-g-800">PPA share this month</p>
        <p class="mt-1 text-xs text-g-500">
          {{ card.paidBillCount }} paid bills · invoice total {{ card.invoiceTotal }}
        </p>
      </button>
      <article
        v-if="!loading && currencyCards.length === 0"
        class="art-card-sm p-4 text-sm text-g-500 md:col-span-3"
      >
        No fully paid bills in your location for {{ monthLabel }}.
      </article>
    </div>

    <section class="art-card overflow-hidden !p-0">
      <div class="flex items-center justify-between border-b border-g-200 px-4 py-3 sm:px-5">
        <div>
          <h2 class="text-sm font-medium text-g-900">Recent paid bills</h2>
          <p class="mt-0.5 text-xs text-g-500">Month to date · {{ monthLabel }}</p>
        </div>
        <span v-if="loading" class="text-xs text-g-500">Loading</span>
        <ElButton v-else text type="primary" @click="router.push('/ppa-share-report')">
          Open share report
        </ElButton>
      </div>
      <ul v-if="recentRows.length" class="divide-y divide-g-200">
        <li v-for="row in recentRows" :key="row.invoice_number">
          <button
            type="button"
            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left hover:bg-g-100/70 sm:px-5"
            @click="openBill(row.invoice_number)"
          >
            <div class="min-w-0">
              <p class="text-sm font-medium text-g-900">
                {{ row.invoice_number }}
                <span v-if="row.customer_name" class="font-normal text-g-600">
                  · {{ row.customer_name }}
                </span>
              </p>
              <p class="mt-0.5 truncate text-xs text-g-500">
                {{ row.business_date || '—' }} · PPA share
                {{ money(row.ppa_share, row.currency) }}
              </p>
            </div>
            <span class="shrink-0 text-xs text-theme">Verify</span>
          </button>
        </li>
      </ul>
      <p v-else class="px-4 py-8 text-sm text-g-500 sm:px-5">
        {{
          loading
            ? 'Loading paid bills…'
            : 'No paid bills to list yet. Open Share Report when you need a wider date range.'
        }}
      </p>
    </section>

    <p v-if="loadError" class="text-sm text-error">{{ loadError }}</p>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import { useUserStore } from '@/store/modules/user'
  import { fetchPpaShareReport, type PpaShareReport } from '@/api/ppaClearance'

  defineOptions({ name: 'PpaWorkboard' })

  const router = useRouter()
  const userStore = useUserStore()
  const loading = ref(false)
  const loadError = ref('')
  const searchNumber = ref('')
  const report = ref<PpaShareReport | null>(null)

  const userDisplayName = computed(() => userStore.info?.userName || 'PPA officer')
  const currentDate = computed(() =>
    new Intl.DateTimeFormat('en-PH', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      timeZone: 'Asia/Manila'
    }).format(new Date())
  )

  const manilaToday = () => {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    }).formatToParts(new Date())
    const value = (type: Intl.DateTimeFormatPartTypes) =>
      parts.find((part) => part.type === type)?.value || ''
    return `${value('year')}-${value('month')}-${value('day')}`
  }

  const today = manilaToday()
  const dateFrom = `${today.slice(0, 8)}01`
  const dateTo = today

  const monthLabel = computed(() =>
    new Intl.DateTimeFormat('en-PH', {
      month: 'long',
      year: 'numeric',
      timeZone: 'Asia/Manila'
    }).format(new Date(`${dateTo}T00:00:00+08:00`))
  )

  const money = (amount: string, currency: string) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: currency || 'PHP' }).format(
      Number(amount)
    )

  const currencyCards = computed(() =>
    (report.value?.totals_by_currency || []).map((total) => ({
      currency: total.currency,
      paidBillCount: total.paid_bill_count,
      invoiceTotal: money(total.invoice_total, total.currency),
      ppaShare: money(total.ppa_share, total.currency)
    }))
  )

  const recentRows = computed(() => (report.value?.rows || []).slice(0, 8))

  function openVerification() {
    if (!searchNumber.value) return
    router.push({
      path: '/ppa-verification',
      query: { number: searchNumber.value }
    })
  }

  function openBill(invoiceNumber: string) {
    router.push({
      path: '/ppa-verification',
      query: { number: invoiceNumber }
    })
  }

  async function load() {
    loading.value = true
    loadError.value = ''
    try {
      report.value = await fetchPpaShareReport({
        date_from: dateFrom,
        date_to: dateTo,
        page: 1
      })
    } catch (error: any) {
      report.value = null
      loadError.value = error?.message || 'Unable to load the month-to-date PPA share summary.'
    } finally {
      loading.value = false
    }
  }

  onMounted(load)
</script>
