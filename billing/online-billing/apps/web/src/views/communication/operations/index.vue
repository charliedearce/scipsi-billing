<template>
  <div class="max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <section
      class="rounded-2xl bg-white border border-slate-200 p-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between"
    >
      <div>
        <h1 class="text-xl font-bold">Communications Operations</h1>
        <p class="text-sm text-slate-500 mt-1">
          Aggregate operational visibility only. Delivery status is not proof of payment, receipt
          delivery, or customer reading.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <ElDatePicker
          v-model="dateRange"
          type="daterange"
          value-format="YYYY-MM-DD"
          range-separator="to"
          start-placeholder="Start date"
          end-placeholder="End date"
          class="!w-72"
          @change="load"
        />
        <ElButton :loading="loading" @click="load">Refresh</ElButton>
      </div>
    </section>

    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="No recipient, message body, provider ID, payment, invoice, or receipt data is included in this dashboard."
    />

    <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
      <ElCard shadow="never" v-loading="loading">
        <p class="text-xs uppercase tracking-wide text-slate-500">Deliveries in period</p>
        <p class="mt-2 text-3xl font-bold">{{ report?.deliveries.total ?? 0 }}</p>
        <p class="mt-1 text-xs text-slate-500">{{ scopeLabel }}</p>
      </ElCard>
      <ElCard shadow="never" v-loading="loading">
        <p class="text-xs uppercase tracking-wide text-slate-500">Follow-up required</p>
        <p class="mt-2 text-3xl font-bold text-amber-600">
          {{ report?.deliveries.follow_up_required_count ?? 0 }}
        </p>
        <p class="mt-1 text-xs text-slate-500">Provider failed or reconciliation required</p>
      </ElCard>
      <ElCard shadow="never" v-loading="loading">
        <p class="text-xs uppercase tracking-wide text-slate-500">Reporting cutoff</p>
        <p class="mt-2 text-lg font-bold">{{ periodLabel }}</p>
        <p class="mt-1 text-xs text-slate-500">Asia/Manila calendar dates</p>
      </ElCard>
    </section>

    <section class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <ElCard shadow="never" v-loading="loading">
        <template #header><h2 class="font-semibold">Transactional SMS status</h2></template>
        <ElTable
          :data="report?.deliveries.statuses || []"
          size="small"
          empty-text="No delivery activity in this period."
        >
          <ElTableColumn label="Status" min-width="240">
            <template #default="{ row }"
              ><ElTag :type="statusType(row.status)">{{ statusLabel(row.status) }}</ElTag></template
            >
          </ElTableColumn>
          <ElTableColumn prop="count" label="Count" width="110" align="right" />
        </ElTable>
      </ElCard>

      <ElCard shadow="never" v-loading="loading">
        <template #header><h2 class="font-semibold">Transactional event volume</h2></template>
        <ElTable
          :data="report?.deliveries.event_keys || []"
          size="small"
          empty-text="No delivery activity in this period."
        >
          <ElTableColumn prop="event_key" label="Event key" min-width="260">
            <template #default="{ row }"
              ><span class="font-mono text-xs">{{ row.event_key }}</span></template
            >
          </ElTableColumn>
          <ElTableColumn prop="count" label="Deliveries" width="110" align="right" />
        </ElTable>
      </ElCard>
    </section>

    <ElCard shadow="never" v-loading="loading">
      <template #header><h2 class="font-semibold">In-app announcement operations</h2></template>
      <template v-if="report?.announcements">
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
          <div
            v-for="status in announcementStatuses"
            :key="status"
            class="rounded-lg border border-slate-200 p-3"
          >
            <p class="text-xs text-slate-500">{{ statusLabel(status) }}</p>
            <p class="mt-1 text-xl font-semibold">{{
              report.announcements.current_effective_statuses[status] || 0
            }}</p>
          </div>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div
            v-for="action in interactionActions"
            :key="action"
            class="rounded-lg bg-slate-50 p-3"
          >
            <p class="text-xs text-slate-500">{{ statusLabel(action) }} in period</p>
            <p class="mt-1 text-xl font-semibold">{{
              report.announcements.interaction_actions[action]
            }}</p>
          </div>
        </div>
      </template>
      <ElAlert
        v-else
        type="info"
        :closable="false"
        show-icon
        title="Announcement aggregates are restricted to organization Administrators because they represent an organization-wide audience."
      />
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import {
    fetchCommunicationOperationsReport,
    type CommunicationOperationsReport
  } from '@/api/communicationOperations'

  defineOptions({ name: 'CommunicationOperations' })

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
  const dateRange = ref<[string, string]>([`${today.slice(0, 8)}01`, today])
  const loading = ref(false)
  const report = ref<CommunicationOperationsReport | null>(null)
  const announcementStatuses = ['draft', 'scheduled', 'published', 'expired', 'retired']
  const interactionActions: Array<'seen' | 'acknowledged' | 'dismissed'> = [
    'seen',
    'acknowledged',
    'dismissed'
  ]
  const scopeLabel = computed(() =>
    report.value?.delivery_scope.type === 'assigned_locations'
      ? 'Assigned source locations only'
      : 'Organization-scoped aggregate'
  )
  const periodLabel = computed(() =>
    report.value ? `${report.value.period.date_from} to ${report.value.period.date_to}` : 'Loading…'
  )

  function statusLabel(value: string) {
    return value.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase())
  }

  function statusType(status: string) {
    if (status === 'provider_failed') return 'danger'
    if (status === 'unknown_reconciliation_required') return 'warning'
    if (status === 'provider_sent') return 'success'
    if (status === 'suppressed') return 'info'
    return 'primary'
  }

  async function load() {
    if (!dateRange.value?.[0] || !dateRange.value?.[1]) return

    loading.value = true
    try {
      const response = await fetchCommunicationOperationsReport({
        date_from: dateRange.value[0],
        date_to: dateRange.value[1]
      })
      report.value = response.data
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load communications operations.')
    } finally {
      loading.value = false
    }
  }

  onMounted(load)
</script>
