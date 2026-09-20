<template>
  <div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <section
      class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-sky-700 to-cyan-700 p-6 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <h1 class="text-xl font-bold sm:text-2xl">Billing &amp; Collections Register</h1>
        <p class="mt-1 max-w-3xl text-sm text-sky-50">
          Scoped operational activity for issued invoices and posted collection receipts.
        </p>
      </div>
      <div class="flex gap-2">
        <ElButton
          plain
          :loading="loading"
          class="!border-white/20 !bg-white/10 !text-white"
          @click="load(1)"
        >
          <ElIcon class="mr-1"><Refresh /></ElIcon>Refresh
        </ElButton>
        <ElButton type="primary" :loading="exporting" @click="exportCsv">
          <ElIcon class="mr-1"><Download /></ElIcon>Export CSV
        </ElButton>
      </div>
    </section>

    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="Operational report boundary"
      :description="report?.report.interpretation_notice || defaultInterpretationNotice"
    />

    <ElCard shadow="never" class="!rounded-xl">
      <ElForm class="grid grid-cols-1 gap-x-4 sm:grid-cols-2 lg:grid-cols-4" label-position="top">
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
          <ElCheckbox v-model="includeCancelled">Include cancelled invoices</ElCheckbox>
        </ElFormItem>
        <ElFormItem class="flex items-end">
          <ElButton type="primary" class="w-full" :loading="loading" @click="load(1)">
            Apply filters
          </ElButton>
        </ElFormItem>
      </ElForm>
    </ElCard>

    <div v-if="report" class="grid grid-cols-1 gap-4 lg:grid-cols-2">
      <ElCard
        v-for="total in report.totals_by_currency"
        :key="total.currency"
        shadow="never"
        class="!rounded-xl"
      >
        <template #header>
          <div class="flex items-center justify-between">
            <span class="font-semibold">{{ total.currency }} activity</span>
            <ElTag size="small" type="info"
              >{{ total.invoices.count + total.receipts.count }} records</ElTag
            >
          </div>
        </template>
        <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
          <div
            ><dt class="text-slate-500">Invoices issued</dt
            ><dd class="font-semibold">{{
              money(total.invoices.billed_amount, total.currency)
            }}</dd></div
          >
          <div
            ><dt class="text-slate-500">Invoice count</dt
            ><dd class="font-semibold">{{ total.invoices.count }}</dd></div
          >
          <div
            ><dt class="text-slate-500">Cash received</dt
            ><dd class="font-semibold">{{
              money(total.receipts.cash_received_amount, total.currency)
            }}</dd></div
          >
          <div
            ><dt class="text-slate-500">Withholding received</dt
            ><dd class="font-semibold">{{
              money(total.receipts.withholding_received_amount, total.currency)
            }}</dd></div
          >
          <div
            ><dt class="text-slate-500">Applied to invoices</dt
            ><dd class="font-semibold">{{
              money(total.receipts.applied_amount, total.currency)
            }}</dd></div
          >
          <div
            ><dt class="text-slate-500">Unapplied funds</dt
            ><dd class="font-semibold">{{
              money(total.receipts.unapplied_amount, total.currency)
            }}</dd></div
          >
        </dl>
      </ElCard>
    </div>

    <ElCard shadow="never" class="!rounded-xl">
      <template #header>
        <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h2 class="font-semibold">Activity rows</h2>
            <p class="text-xs text-slate-500">{{
              report?.report.scope_notice || 'Loading report scope…'
            }}</p>
          </div>
          <span v-if="report" class="text-xs text-slate-500"
            >{{ report.pagination.total }} rows in the selected scope</span
          >
        </div>
      </template>
      <ElTable
        v-loading="loading"
        :data="report?.rows || []"
        stripe
        empty-text="No reportable documents for the selected scope."
      >
        <ElTableColumn label="Type" width="105">
          <template #default="{ row }"
            ><ElTag size="small" :type="row.document_type === 'INVOICE' ? 'primary' : 'success'">{{
              row.document_type
            }}</ElTag></template
          >
        </ElTableColumn>
        <ElTableColumn prop="business_date" label="Business date" width="128" />
        <ElTableColumn prop="document_number" label="Document number" min-width="175">
          <template #default="{ row }"
            ><span class="font-mono text-xs">{{ row.document_number }}</span></template
          >
        </ElTableColumn>
        <ElTableColumn label="Customer / payer" min-width="230">
          <template #default="{ row }">
            <div>{{ row.customer_name || '—' }}</div
            ><div class="text-xs text-slate-500">{{ row.customer_account_number || '—' }}</div>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="status" label="Status" width="115" />
        <ElTableColumn label="Issued" min-width="135" align="right"
          ><template #default="{ row }">{{
            money(row.billed_amount, row.currency)
          }}</template></ElTableColumn
        >
        <ElTableColumn label="Applied" min-width="135" align="right"
          ><template #default="{ row }">{{
            money(row.applied_amount, row.currency)
          }}</template></ElTableColumn
        >
        <ElTableColumn label="Unapplied" min-width="135" align="right"
          ><template #default="{ row }">{{
            money(row.unapplied_amount, row.currency)
          }}</template></ElTableColumn
        >
      </ElTable>
      <div v-if="report" class="mt-4 flex justify-end">
        <ElPagination
          background
          layout="prev, pager, next"
          :current-page="report.pagination.current_page"
          :page-size="report.pagination.per_page"
          :total="report.pagination.total"
          @current-change="load"
        />
      </div>
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { onMounted, ref } from 'vue'
  import { Download, Refresh } from '@element-plus/icons-vue'
  import { ElMessage } from 'element-plus'
  import { fetchAdminCustomers } from '@/api/registration'
  import {
    exportBillingCollectionsReport,
    fetchBillingCollectionsReport,
    type BillingCollectionsReport,
    type BillingCollectionsReportParams
  } from '@/api/reports'

  defineOptions({ name: 'BillingCollectionsReport' })

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
    'Invoice issuance and posted collections are separate activity streams. Their difference is not an account balance, aging result, or fiscal reconciliation.'
  const loading = ref(false)
  const exporting = ref(false)
  const report = ref<BillingCollectionsReport | null>(null)
  const customers = ref<Array<{ id: number; account_number: string; name: string }>>([])
  const dateRange = ref<[string, string]>([monthStart, today])
  const customerId = ref<number | undefined>()
  const includeCancelled = ref(false)

  const filters = (page = 1): BillingCollectionsReportParams => ({
    date_from: dateRange.value[0],
    date_to: dateRange.value[1],
    customer_id: customerId.value,
    invoice_statuses: includeCancelled.value ? ['POSTED', 'CANCELLED'] : ['POSTED'],
    receipt_statuses: ['POSTED'],
    page,
    per_page: 50
  })

  const load = async (page = 1) => {
    if (!dateRange.value?.[0] || !dateRange.value?.[1])
      return ElMessage.warning('Choose a business-date range.')
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
    const [wholeValue, fractionValue = '00'] = value.split('.')
    const negative = wholeValue.startsWith('-')
    const whole = wholeValue.replace(/^[+-]/, '') || '0'
    const grouped = whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')
    const fraction = fractionValue.padEnd(2, '0').slice(0, 2)

    return `${currency} ${negative ? '-' : ''}${grouped}.${fraction}`
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
