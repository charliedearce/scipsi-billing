<template>
  <div class="page-content space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:hourglass-2-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">VIP Credit Aging</h1>
          <p class="mt-1 max-w-2xl text-sm text-g-500">
            Principal only. Credit charges and effective receipt allocations are reconciled at the
            selected cutoff.
          </p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <ElDatePicker
          v-model="asOf"
          type="date"
          value-format="YYYY-MM-DD"
          class="!w-44"
          @change="load"
        />
        <ElButton :loading="loading" @click="load">
          <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
          Refresh
        </ElButton>
        <ElButton v-if="canExport" type="primary" :loading="exporting" @click="exportCsv">
          <ArtSvgIcon icon="ri:download-2-line" class="mr-1" />
          Export CSV
        </ElButton>
      </div>
    </header>
    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="A credit charge is not paid. Late charges, reversals and formal PPA release are separate workflows."
    />
    <section v-loading="loading" class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>Scoped credit accounts</h4>
          <p>Select an account to open its aging detail</p>
        </div>
      </div>
      <ElEmpty
        v-if="accounts.length === 0"
        description="No VIP credit accounts are available in your scope."
      />
      <ElTable v-else :data="accounts" highlight-current-row @current-change="selectAccount">
        <ElTableColumn label="Customer" min-width="210"
          ><template #default="{ row }"
            >{{ row.account.customer_name
            }}<span class="block text-xs text-g-500">{{
              row.account.account_number
            }}</span></template
          ></ElTableColumn
        >
        <ElTableColumn prop="as_of_date" label="As of" width="130" />
        <ElTableColumn label="Outstanding" align="right" min-width="150"
          ><template #default="{ row }">{{ total(row) }}</template></ElTableColumn
        >
        <ElTableColumn label="Overdue" align="right" min-width="150"
          ><template #default="{ row }">{{ overdue(row) }}</template></ElTableColumn
        >
      </ElTable>
    </section>
    <section v-if="selected" class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>{{ selected.account?.customer_name || 'Credit account' }} — aging detail</h4>
          <p>{{ selected.cutoff_semantics }}</p>
        </div>
      </div>
      <div v-for="group in selected.currencies" :key="group.currency" class="mb-6 last:mb-0">
        <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-4"
          ><div v-for="bucket in buckets" :key="bucket.key" class="art-card-xs p-3"
            ><p class="text-xs text-g-500">{{ bucket.label }}</p
            ><p class="mt-1 font-medium text-g-900">{{
              money(group.buckets[bucket.key], group.currency)
            }}</p></div
          ></div
        >
        <ElTable :data="group.items" size="small"
          ><ElTableColumn prop="invoice_number" label="Invoice" min-width="140" /><ElTableColumn
            label="Due date"
            min-width="150"
            ><template #default="{ row }">{{
              row.due_date || 'Needs terms review'
            }}</template></ElTableColumn
          ><ElTableColumn prop="days_past_due" label="Days" width="90" /><ElTableColumn
            prop="bucket"
            label="Bucket"
            min-width="120"
          /><ElTableColumn label="Outstanding" align="right" min-width="150"
            ><template #default="{ row }">{{
              money(row.outstanding_as_of_amount, group.currency)
            }}</template></ElTableColumn
          ></ElTable
        >
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import {
    exportVipPrincipalAging,
    fetchStaffCreditAgingIndex,
    type AgingBucket,
    type VipCreditAging
  } from '@/api/creditAging'
  import { useUserStore } from '@/store/modules/user'

  defineOptions({ name: 'VipCreditAging' })
  const loading = ref(false)
  const exporting = ref(false)
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
  const asOf = ref(manilaToday())
  const accounts = ref<VipCreditAging[]>([])
  const selectedAccountId = ref<number | null>(null)
  const userStore = useUserStore()
  // The server also requires Administrator authority because credit accounts are
  // organization-scoped until an authoritative location mapping exists.
  const canExport = computed(() => userStore.info.roles?.includes('Administrator'))
  const buckets: Array<{ key: AgingBucket; label: string }> = [
    { key: 'CURRENT', label: 'Current' },
    { key: '1_30', label: '1–30' },
    { key: '31_60', label: '31–60' },
    { key: '61_90', label: '61–90' },
    { key: '91_PLUS', label: '91+' },
    { key: 'UNCLASSIFIED', label: 'Needs terms review' }
  ]
  const selected = computed(
    () =>
      accounts.value.find((item) => item.account?.id === selectedAccountId.value) ||
      accounts.value[0]
  )
  const money = (amount: string, currency = 'PHP') =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number(amount))
  const total = (aging: VipCreditAging) =>
    money(
      aging.currencies.reduce((sum, group) => sum + Number(group.outstanding_amount), 0).toFixed(2)
    )
  const overdue = (aging: VipCreditAging) =>
    money(
      aging.currencies
        .reduce(
          (sum, group) =>
            sum +
            Number(group.buckets['1_30']) +
            Number(group.buckets['31_60']) +
            Number(group.buckets['61_90']) +
            Number(group.buckets['91_PLUS']),
          0
        )
        .toFixed(2)
    )
  function selectAccount(row?: VipCreditAging) {
    selectedAccountId.value = row?.account?.id || null
  }
  async function load() {
    loading.value = true
    try {
      accounts.value = await fetchStaffCreditAgingIndex(asOf.value)
      if (!accounts.value.some((item) => item.account?.id === selectedAccountId.value))
        selectAccount(accounts.value[0])
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load VIP credit aging.')
    } finally {
      loading.value = false
    }
  }
  async function exportCsv() {
    exporting.value = true
    try {
      const blob = await exportVipPrincipalAging(asOf.value)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `vip-principal-aging-as-of-${asOf.value}.csv`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
      ElMessage.success('Principal-aging CSV export created and recorded in audit history.')
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to export VIP principal aging.')
    } finally {
      exporting.value = false
    }
  }
  onMounted(load)
</script>
