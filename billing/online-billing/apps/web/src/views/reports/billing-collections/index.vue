<template>
  <div class="billing-collections-report page-content !p-0 overflow-hidden">
    <header
      class="flex flex-col gap-4 border-b border-g-200 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6"
    >
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:bar-chart-2-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">Billing &amp; Collections Register</h1>
          <p class="mt-1 max-w-2xl text-sm text-g-500">
            Scoped operational activity for issued invoices and posted collection receipts,
            including acknowledgement receipts flagged as non-OR.
          </p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <ElButton :loading="loading" @click="load(1)">
          <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
          Refresh
        </ElButton>
        <ElButton v-if="canExport" type="primary" :loading="exporting" @click="exportCsv">
          <ArtSvgIcon icon="ri:download-2-line" class="mr-1" />
          Export CSV
        </ElButton>
      </div>
    </header>

    <section class="border-b border-g-200 bg-g-100/40 px-5 py-3.5 sm:px-6">
      <ElAlert
        type="info"
        :closable="false"
        show-icon
        :title="report?.report.title || 'Operational report boundary'"
        :description="report?.report.interpretation_notice || defaultInterpretationNotice"
      />
    </section>

    <section class="space-y-5 p-5 sm:p-6">
      <div class="art-card space-y-4 !p-4 sm:!p-5">
        <ElForm
          class="grid grid-cols-1 gap-x-4 gap-y-1 sm:grid-cols-2 xl:grid-cols-4"
          label-position="top"
        >
          <ElFormItem label="Business-date range">
            <ElDatePicker
              v-model="dateRange"
              type="daterange"
              value-format="YYYY-MM-DD"
              range-separator="to"
              start-placeholder="Start date"
              end-placeholder="End date"
              class="!w-full"
              :clearable="false"
              :disabled-date="disableFuture"
            />
          </ElFormItem>
          <ElFormItem label="Location">
            <ElSelect
              v-model="locationId"
              clearable
              filterable
              class="!w-full"
              :placeholder="locationPlaceholder"
            >
              <ElOption
                v-for="location in locations"
                :key="location.id"
                :label="`${location.code} — ${location.name}`"
                :value="location.id"
              />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="Customer">
            <ElSelect
              v-model="customerId"
              clearable
              filterable
              class="!w-full"
              placeholder="All accessible customers"
            >
              <ElOption
                v-for="customer in customers"
                :key="customer.id"
                :label="`${customer.account_number} — ${customer.name}`"
                :value="customer.id"
              />
            </ElSelect>
          </ElFormItem>
          <ElFormItem label="Invoice statuses">
            <div class="flex min-h-8 flex-col justify-center gap-1">
              <ElCheckbox v-model="includeCancelled">Include cancelled invoices</ElCheckbox>
              <p class="text-xs text-g-500"
                >Posted and superseded issued originals are always included.</p
              >
            </div>
          </ElFormItem>
          <ElFormItem class="sm:col-span-2 xl:col-span-4">
            <ElButton type="primary" :loading="loading" @click="load(1)"> Apply filters </ElButton>
          </ElFormItem>
        </ElForm>
      </div>

      <div v-if="report" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div
          v-for="total in report.totals_by_currency"
          :key="total.currency"
          class="art-card space-y-4 !p-4 sm:!p-5"
        >
          <div class="flex items-center justify-between gap-2">
            <h2 class="font-medium text-g-900">{{ total.currency }} activity</h2>
            <ElTag size="small" type="info" effect="plain">
              {{ total.invoices.count + total.receipts.count }} records
            </ElTag>
          </div>
          <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
            <div>
              <dt class="text-g-500">Invoices issued</dt>
              <dd class="font-medium text-g-900">
                {{ money(total.invoices.billed_amount, total.currency) }}
              </dd>
            </div>
            <div>
              <dt class="text-g-500">Invoice count</dt>
              <dd class="font-medium text-g-900">{{ total.invoices.count }}</dd>
            </div>
            <div>
              <dt class="text-g-500">Cash received</dt>
              <dd class="font-medium text-g-900">
                {{ money(total.receipts.cash_received_amount, total.currency) }}
              </dd>
            </div>
            <div>
              <dt class="text-g-500">Withholding received</dt>
              <dd class="font-medium text-g-900">
                {{ money(total.receipts.withholding_received_amount, total.currency) }}
              </dd>
            </div>
            <div>
              <dt class="text-g-500">Applied to invoices</dt>
              <dd class="font-medium text-g-900">
                {{ money(total.receipts.applied_amount, total.currency) }}
              </dd>
            </div>
            <div>
              <dt class="text-g-500">Unapplied funds</dt>
              <dd class="font-medium text-g-900">
                {{ money(total.receipts.unapplied_amount, total.currency) }}
              </dd>
            </div>
          </dl>
        </div>
      </div>

      <div class="art-card !p-0 overflow-hidden">
        <div
          class="flex flex-col gap-1 border-b border-g-200 px-4 py-3.5 sm:flex-row sm:items-center sm:justify-between sm:px-5"
        >
          <div>
            <h2 class="font-medium text-g-900">Activity rows</h2>
            <p class="text-xs text-g-500">
              {{ report?.report.scope_notice || 'Loading report scope…' }}
            </p>
          </div>
          <span v-if="report" class="text-xs text-g-500">
            {{ report.pagination.total }} rows in the selected scope
          </span>
        </div>

        <div class="px-2 py-2 sm:px-3">
          <ElTable
            v-loading="loading"
            :data="report?.rows || []"
            stripe
            empty-text="No reportable documents for the selected scope."
          >
            <ElTableColumn label="Type" width="110">
              <template #default="{ row }">
                <ElTag
                  size="small"
                  :type="row.document_type === 'INVOICE' ? 'primary' : 'success'"
                  effect="plain"
                >
                  {{ row.document_type }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="business_date" label="Business date" width="128" />
            <ElTableColumn prop="document_number" label="Document number" min-width="175">
              <template #default="{ row }">
                <span class="font-mono text-xs">{{ row.document_number }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Receipt kind" min-width="150">
              <template #default="{ row }">
                <template v-if="row.document_type === 'RECEIPT'">
                  <ElTag
                    size="small"
                    :type="row.counts_as_official_receipt ? 'success' : 'warning'"
                    effect="plain"
                  >
                    {{ receiptKindLabel(row) }}
                  </ElTag>
                </template>
                <span v-else class="text-g-500">—</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Customer / payer" min-width="220">
              <template #default="{ row }">
                <div class="text-g-900">{{ row.customer_name || '—' }}</div>
                <div class="text-xs text-g-500">{{ row.customer_account_number || '—' }}</div>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="status" label="Status" width="120">
              <template #default="{ row }">
                <ElTag size="small" :type="statusTagType(row.status)" effect="plain">
                  {{ row.status }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Issued" min-width="130" align="right">
              <template #default="{ row }">
                {{ money(row.billed_amount, row.currency) }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Cash" min-width="120" align="right">
              <template #default="{ row }">
                {{ money(row.cash_received_amount, row.currency) }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Withholding" min-width="120" align="right">
              <template #default="{ row }">
                {{ money(row.withholding_received_amount, row.currency) }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Applied" min-width="120" align="right">
              <template #default="{ row }">
                {{ money(row.applied_amount, row.currency) }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Unapplied" min-width="120" align="right">
              <template #default="{ row }">
                {{ money(row.unapplied_amount, row.currency) }}
              </template>
            </ElTableColumn>
          </ElTable>
        </div>

        <div v-if="report" class="flex justify-end border-t border-g-200 px-4 py-3 sm:px-5">
          <ElPagination
            background
            layout="prev, pager, next"
            :current-page="report.pagination.current_page"
            :page-size="report.pagination.per_page"
            :total="report.pagination.total"
            @current-change="load"
          />
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import { fetchAdminCustomers } from '@/api/registration'
  import {
    exportBillingCollectionsReport,
    fetchBillingCollectionsReport,
    type BillingCollectionsReport,
    type BillingCollectionsReportParams,
    type BillingCollectionsRow
  } from '@/api/reports'
  import { useUserStore } from '@/store/modules/user'

  defineOptions({ name: 'BillingCollectionsReport' })

  type LocationOption = { id: number; code: string; name: string }

  const userStore = useUserStore()

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
  const monthStart = `${today.slice(0, 8)}01`
  const defaultInterpretationNotice =
    'Invoice issuance and posted collections are separate activity streams. Their difference is not an account balance, aging result, or fiscal reconciliation. Acknowledgement receipts appear for internal visibility but are not Official Receipts.'

  const loading = ref(false)
  const exporting = ref(false)
  const report = ref<BillingCollectionsReport | null>(null)
  const customers = ref<Array<{ id: number; account_number: string; name: string }>>([])
  const dateRange = ref<[string, string]>([monthStart, today])
  const locationId = ref<number | undefined>()
  const customerId = ref<number | undefined>()
  const includeCancelled = ref(false)

  const locations = computed<LocationOption[]>(() => {
    const raw = (userStore.info as { locations?: LocationOption[] } | undefined)?.locations
    return Array.isArray(raw) ? raw : []
  })

  const canExport = computed(() => {
    const permissions = (userStore.info as { permissions?: string[] } | undefined)?.permissions
    return Array.isArray(permissions) && permissions.includes('reports:export')
  })

  const locationPlaceholder = computed(() =>
    locations.value.length ? 'All authorized locations' : 'All accessible locations'
  )

  const filters = (page = 1): BillingCollectionsReportParams => ({
    date_from: dateRange.value[0],
    date_to: dateRange.value[1],
    location_id: locationId.value,
    customer_id: customerId.value,
    invoice_statuses: includeCancelled.value
      ? ['POSTED', 'SUPERSEDED', 'CANCELLED']
      : ['POSTED', 'SUPERSEDED'],
    receipt_statuses: ['POSTED'],
    page,
    per_page: 50
  })

  const load = async (page = 1) => {
    if (!dateRange.value?.[0] || !dateRange.value?.[1]) {
      ElMessage.warning('Choose a business-date range.')
      return
    }
    loading.value = true
    try {
      report.value = await fetchBillingCollectionsReport(filters(page))
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load the Billing & Collections Register.')
    } finally {
      loading.value = false
    }
  }

  const exportCsv = async () => {
    if (!dateRange.value?.[0] || !dateRange.value?.[1]) {
      ElMessage.warning('Choose a business-date range.')
      return
    }

    exporting.value = true
    try {
      const blob = await exportBillingCollectionsReport(filters(1))
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `billing-collections-${dateRange.value[0]}-to-${dateRange.value[1]}.csv`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
      ElMessage.success('Scoped CSV export created and recorded in the audit history.')
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to export the Billing & Collections Register.')
    } finally {
      exporting.value = false
    }
  }

  const money = (value: string, currency: string) => {
    const [wholeValue, fractionValue = '00'] = String(value ?? '0').split('.')
    const negative = wholeValue.startsWith('-')
    const whole = wholeValue.replace(/^[+-]/, '') || '0'
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    const fraction = fractionValue.padEnd(2, '0').slice(0, 2)

    return `${currency} ${negative ? '-' : ''}${grouped}.${fraction}`
  }

  const receiptKindLabel = (row: BillingCollectionsRow) => {
    if (row.receipt_kind === 'ACKNOWLEDGEMENT' || row.counts_as_official_receipt === false) {
      return 'Acknowledgement'
    }
    return 'Official Receipt'
  }

  const statusTagType = (status: string) => {
    if (status === 'POSTED') return 'success'
    if (status === 'SUPERSEDED') return 'warning'
    if (status === 'CANCELLED' || status === 'REVERSED' || status === 'VOID') return 'danger'
    return 'info'
  }

  const disableFuture = (date: Date) => date > new Date(new Date().setHours(0, 0, 0, 0))

  onMounted(async () => {
    try {
      const result = await fetchAdminCustomers({ per_page: 100, status: 'active' })
      customers.value = result.data || []
    } catch {
      // The report remains usable without an optional customer filter list.
    }
    await load()
  })
</script>
