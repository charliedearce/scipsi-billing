<template>
  <div class="page-content space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:dashboard-3-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">Overview</h1>
          <p class="mt-1 text-sm text-g-500">{{ currentDate }}</p>
        </div>
      </div>
      <ElButton :loading="loading" @click="load">
        <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
        Refresh
      </ElButton>
    </header>

    <p v-if="loadError" class="text-sm text-error">{{ loadError }}</p>

    <template v-for="currency in currencies" :key="currency.currency">
      <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <button
          v-for="card in moneyCards(currency)"
          :key="card.title"
          type="button"
          class="art-card relative flex h-35 flex-col justify-center px-5 text-left transition-colors hover:border-theme/40"
          :class="card.lead ? 'border-theme/25 bg-theme/10' : ''"
          @click="router.push('/billing-collections-report')"
        >
          <span class="text-sm text-g-700">{{ card.title }}</span>
          <MoneyDisplay
            class="mt-2"
            :value="card.amount"
            :currency="currency.currency"
            size="2xl"
          />
          <span class="mt-1 text-xs text-g-600">{{ card.caption }}</span>
          <div
            class="absolute bottom-0 right-5 top-0 m-auto size-12.5 flex-cc rounded-xl"
            :class="card.lead ? 'bg-theme/15 text-theme' : 'bg-g-200/70 text-g-700'"
          >
            <ArtSvgIcon :icon="card.icon" class="text-xl" />
          </div>
        </button>
      </div>

      <p class="text-xs text-g-500">{{ dashboard?.notice }}</p>

      <section class="art-card h-100 p-5">
        <div class="art-card-header flex-wrap gap-y-2">
          <div class="title">
            <h4>Last 7 days</h4>
            <p>
              Bills issued and collections received in {{ currency.currency }}, by Asia/Manila
              business date
            </p>
          </div>
          <div class="flex flex-wrap items-center gap-x-4 gap-y-1">
            <span
              v-for="(series, index) in chartSeries(currency)"
              :key="series.name"
              class="flex items-center gap-1.5 text-xs text-g-600"
            >
              <span
                class="size-2.5 shrink-0 rounded-sm"
                :style="{ backgroundColor: chartColors[index] }"
              />
              {{ series.name }}
            </span>
          </div>
        </div>
        <ArtBarChart
          height="calc(100% - 72px)"
          :data="chartSeries(currency)"
          :xAxisData="chartLabels(currency)"
          :colors="chartColors"
          :loading="loading"
          :showAxisLine="false"
          barWidth="16%"
        />
      </section>
    </template>

    <div class="grid grid-cols-1 gap-3 lg:grid-cols-3">
      <section class="art-card p-5">
        <div class="art-card-header">
          <div class="title">
            <h4>Waiting</h4>
            <p>Work that still needs a teller</p>
          </div>
        </div>
        <ul class="divide-y divide-g-200">
          <li v-for="item in queueItems" :key="item.title">
            <component
              :is="item.to ? 'button' : 'div'"
              class="flex w-full items-center gap-3 py-3.5 text-left"
              v-bind="item.to ? { type: 'button' } : {}"
              @click="item.to && router.push(item.to)"
            >
              <div
                class="size-10 flex-cc shrink-0 rounded-lg"
                :class="item.count > 0 ? 'bg-warning/10 text-warning' : 'bg-g-200/70 text-g-600'"
              >
                <ArtSvgIcon :icon="item.icon" class="text-lg" />
              </div>
              <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-g-900">{{ item.title }}</p>
                <p class="mt-0.5 truncate text-xs text-g-500">{{ item.detail }}</p>
              </div>
              <span
                class="shrink-0 text-lg font-medium"
                :class="item.count > 0 ? 'text-warning' : 'text-g-500'"
              >
                {{ item.count }}
              </span>
              <ArtSvgIcon v-if="item.to" icon="ri:arrow-right-s-line" class="shrink-0 text-g-400" />
            </component>
          </li>
        </ul>
      </section>

      <section class="art-card p-5 lg:col-span-2">
        <div class="art-card-header">
          <div class="title">
            <h4>Tellers today</h4>
            <p>Documents posted and review work assigned</p>
          </div>
        </div>
        <ElTable
          v-loading="loading"
          :data="dashboard?.tellers || []"
          size="small"
          max-height="320"
          empty-text="No teller posted a document or holds review work today."
        >
          <ElTableColumn prop="name" label="Teller" min-width="160" />
          <ElTableColumn label="Bills posted" min-width="140">
            <template #default="{ row }">
              <MoneyDisplay
                :value="row.bills_posted_today.amount"
                :currency="primaryCurrency"
                size="sm"
              />
              <span class="ml-2 text-xs text-g-500">{{ row.bills_posted_today.count }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Official receipts" min-width="150">
            <template #default="{ row }">
              <MoneyDisplay
                :value="row.official_receipts_posted_today.amount"
                :currency="primaryCurrency"
                size="sm"
              />
              <span class="ml-2 text-xs text-g-500">{{
                row.official_receipts_posted_today.count
              }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Acknowledgements" min-width="150">
            <template #default="{ row }">
              <MoneyDisplay
                :value="row.acknowledgements_posted_today.amount"
                :currency="primaryCurrency"
                size="sm"
              />
              <span class="ml-2 text-xs text-g-500">{{
                row.acknowledgements_posted_today.count
              }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn
            prop="billing_requests_in_review"
            label="Requests in review"
            width="150"
            align="right"
          />
          <ElTableColumn
            prop="payment_proofs_assigned"
            label="Proofs assigned"
            width="140"
            align="right"
          />
        </ElTable>
      </section>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import { useChartOps } from '@/hooks/core/useChart'
  import MoneyDisplay from '@/components/business/MoneyDisplay.vue'
  import {
    fetchAdminDashboard,
    type AdminDashboard,
    type AdminDashboardCurrency
  } from '@/api/reports'

  defineOptions({ name: 'AdminOverview' })

  const router = useRouter()
  const loading = ref(false)
  const loadError = ref('')
  const dashboard = ref<AdminDashboard | null>(null)

  const palette = useChartOps().colors
  const chartColors = [palette[0], palette[1], palette[3]]

  const dayLabelFormat = new Intl.DateTimeFormat('en-PH', {
    weekday: 'short',
    day: 'numeric',
    timeZone: 'Asia/Manila'
  })

  const currentDate = computed(() =>
    new Intl.DateTimeFormat('en-PH', {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      timeZone: 'Asia/Manila'
    }).format(new Date())
  )
  const currencies = computed(() => dashboard.value?.currencies ?? [])
  const primaryCurrency = computed(() => currencies.value[0]?.currency || 'PHP')

  const queueItems = computed(() => [
    {
      title: 'Billing requests queued',
      detail: 'Waiting for a teller to claim',
      count: dashboard.value?.work_waiting.billing_requests_queued ?? 0,
      icon: 'ri:user-voice-line',
      to: undefined
    },
    {
      title: 'Payment proofs waiting',
      detail: 'Submitted or already in review',
      count: dashboard.value?.work_waiting.payment_proofs_waiting ?? 0,
      icon: 'ri:file-shield-2-line',
      to: undefined
    },
    {
      title: 'Corrections pending',
      detail: 'Waiting for a decision',
      count: dashboard.value?.work_waiting.document_corrections_pending ?? 0,
      icon: 'ri:file-edit-line',
      to: '/document-corrections'
    }
  ])

  function plural(count: number, noun: string) {
    return `${count} ${noun}${count === 1 ? '' : 's'}`
  }

  function moneyCards(currency: AdminDashboardCurrency) {
    return [
      {
        title: 'Open bills',
        amount: currency.open_bills.amount,
        caption: `${plural(currency.open_bills.count, 'bill')} still unpaid`,
        icon: 'ri:wallet-3-line',
        lead: true
      },
      {
        title: 'Bills posted today',
        amount: currency.bills_posted_today.amount,
        caption: `${plural(currency.bills_posted_today.count, 'bill')} posted`,
        icon: 'ri:bill-line',
        lead: false
      },
      {
        title: 'Official receipts today',
        amount: currency.official_receipts_posted_today.amount,
        caption: `${plural(currency.official_receipts_posted_today.count, 'receipt')} posted`,
        icon: 'ri:receipt-line',
        lead: false
      },
      {
        title: 'Acknowledgements today',
        amount: currency.acknowledgements_posted_today.amount,
        caption: `${plural(currency.acknowledgements_posted_today.count, 'acknowledgement')} posted`,
        icon: 'ri:file-list-3-line',
        lead: false
      }
    ]
  }

  function chartLabels(currency: AdminDashboardCurrency) {
    return currency.last_7_days.map((day) =>
      dayLabelFormat.format(new Date(`${day.business_date}T00:00:00+08:00`))
    )
  }

  // ECharts plots numbers; the cards above carry the server's decimal amounts.
  function chartSeries(currency: AdminDashboardCurrency) {
    return [
      {
        name: 'Bills posted',
        data: currency.last_7_days.map((day) => Number(day.bills.amount))
      },
      {
        name: 'Official receipts',
        data: currency.last_7_days.map((day) => Number(day.official_receipts.amount))
      },
      {
        name: 'Acknowledgements',
        data: currency.last_7_days.map((day) => Number(day.acknowledgements.amount))
      }
    ]
  }

  async function load() {
    loading.value = true
    loadError.value = ''
    try {
      dashboard.value = await fetchAdminDashboard()
    } catch (error: unknown) {
      loadError.value = error instanceof Error ? error.message : 'Unable to load the dashboard.'
    } finally {
      loading.value = false
    }
  }

  onMounted(load)
</script>
