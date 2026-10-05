<template>
  <div class="max-w-7xl mx-auto space-y-6 p-4 sm:p-6">
    <section
      class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-slate-900 to-slate-800 p-6 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
    >
      <div
        ><h1 class="text-xl font-bold sm:text-2xl">Account Statements</h1
        ><p class="mt-1 max-w-2xl text-sm text-slate-300"
          >Immutable, as-of receivables snapshots. They show posted invoices and posted
          collection-receipt allocations only; they do not post payment, late charges, or a fiscal
          document.</p
        ></div
      >
      <div class="flex gap-2"
        ><ElButton
          :loading="loading"
          plain
          class="!border-white/20 !bg-white/10 !text-white"
          @click="load"
          ><ElIcon class="mr-1"><Refresh /></ElIcon>Refresh</ElButton
        ><ElButton type="primary" @click="openCreate"
          ><ElIcon class="mr-1"><Plus /></ElIcon>Generate statement</ElButton
        ></div
      >
    </section>
    <ElAlert
      type="info"
      :closable="false"
      show-icon
      title="Current scope"
      description="The canonical PDF is rendered from the saved snapshot and remains unchanged if the customer pays later. It is a non-fiscal receivables communication, not an invoice or receipt."
    />
    <ElCard shadow="never" class="!rounded-xl"
      ><ElTable
        v-loading="loading"
        :data="statements"
        stripe
        empty-text="No account statement snapshots have been generated."
        ><ElTableColumn prop="statement_number" label="Statement" min-width="240"
          ><template #default="{ row }"
            ><span class="font-mono text-xs">{{ row.statement_number }}</span></template
          ></ElTableColumn
        ><ElTableColumn label="Customer" min-width="190"
          ><template #default="{ row }">{{
            row.customer?.name || row.customer_snapshot?.name || '—'
          }}</template></ElTableColumn
        ><ElTableColumn prop="as_of_date" label="As of" min-width="125" /><ElTableColumn
          label="Outstanding"
          min-width="145"
          align="right"
          ><template #default="{ row }">{{
            money(row.outstanding_total, row.currency)
          }}</template></ElTableColumn
        ><ElTableColumn prop="status" label="Status" width="110"
          ><template #default="{ row }"
            ><ElTag size="small" type="success">{{ row.status }}</ElTag></template
          ></ElTableColumn
        ><ElTableColumn label="" width="100" fixed="right"
          ><template #default="{ row }"
            ><ElButton link type="primary" @click="show(row.id)">View</ElButton></template
          ></ElTableColumn
        ></ElTable
      ></ElCard
    >
    <ElDialog
      v-model="createVisible"
      title="Generate account statement"
      width="min(560px, 94vw)"
      @closed="reset"
      ><ElForm label-position="top"
        ><ElFormItem label="Customer" required
          ><ElSelect
            v-model="form.customer_id"
            filterable
            class="!w-full"
            placeholder="Select an active customer"
            ><ElOption
              v-for="customer in customers"
              :key="customer.id"
              :value="customer.id"
              :label="`${customer.account_number} — ${customer.name}`" /></ElSelect></ElFormItem
        ><ElFormItem label="As-of date" required
          ><ElDatePicker
            v-model="form.as_of_date"
            type="date"
            value-format="YYYY-MM-DD"
            class="!w-full"
            :disabled-date="disableFuture" /></ElFormItem></ElForm
      ><template #footer
        ><ElButton @click="createVisible = false">Cancel</ElButton
        ><ElButton type="primary" :loading="saving" @click="generate"
          >Generate immutable snapshot</ElButton
        ></template
      ></ElDialog
    >
    <ElDrawer v-model="detailVisible" title="Account statement snapshot" size="min(760px, 100%)"
      ><template v-if="selected"
        ><div class="space-y-4"
          ><div class="flex justify-end"
            ><ElButton type="primary" :loading="downloadingPdf" @click="downloadPdf"
              >Download canonical PDF</ElButton
            ></div
          ><div class="grid grid-cols-2 gap-3 sm:grid-cols-3"
            ><div v-for="fact in summaryFacts" :key="fact.label" class="art-card-xs px-4 py-3"
              ><span class="block text-xs text-g-500">{{ fact.label }}</span
              ><b class="text-sm text-g-900">{{ fact.value }}</b></div
            ></div
          ><p v-if="hasBreakdown" class="text-xs text-g-500"
            >Tax is the amount stored on each posted invoice. Cash and withholding are the
            collections applied on or before the as-of date.</p
          ><ElTable :data="selected.items || []" stripe
            ><ElTableColumn v-if="hasBreakdown" type="expand"
              ><template #default="{ row }"
                ><div class="space-y-3 px-2 py-2"
                  ><div class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4"
                    ><div
                      ><span class="block text-xs text-g-500">Days open</span
                      ><span class="text-g-900">{{ row.snapshot?.days_open ?? '—' }}</span></div
                    ><div
                      ><span class="block text-xs text-g-500">TIN</span
                      ><span class="text-g-900">{{ row.snapshot?.buyer_tin || '—' }}</span></div
                    ><div
                      ><span class="block text-xs text-g-500">Vessel</span
                      ><span class="text-g-900">{{
                        shipmentLabel(row.snapshot?.vessel_name, row.snapshot?.voyage)
                      }}</span></div
                    ><div
                      ><span class="block text-xs text-g-500">Net</span
                      ><span class="text-g-900">{{ snapshotMoney(row, 'net_amount') }}</span></div
                    ><div
                      ><span class="block text-xs text-g-500">Fuel surcharge</span
                      ><span class="text-g-900">{{
                        snapshotMoney(row, 'fuel_surcharge_amount')
                      }}</span></div
                    ><div
                      ><span class="block text-xs text-g-500">PPA share</span
                      ><span class="text-g-900">{{ snapshotMoney(row, 'ppa_amount') }}</span></div
                    ><div
                      ><span class="block text-xs text-g-500">Discount</span
                      ><span class="text-g-900">{{
                        snapshotMoney(row, 'discount_amount')
                      }}</span></div
                    ></div
                  ><ElTable
                    :data="row.snapshot?.receipts || []"
                    size="small"
                    empty-text="No collections applied"
                    ><ElTableColumn
                      prop="receipt_number"
                      label="Receipt"
                      min-width="140"
                    /><ElTableColumn prop="business_date" label="Date" width="115" /><ElTableColumn
                      label="Cash"
                      align="right"
                      ><template #default="{ row: receipt }">{{
                        money(receipt.cash_applied_amount, selected.currency)
                      }}</template></ElTableColumn
                    ><ElTableColumn label="Withholding" align="right"
                      ><template #default="{ row: receipt }">{{
                        money(receipt.withholding_applied_amount, selected.currency)
                      }}</template></ElTableColumn
                    ><ElTableColumn label="Applied" align="right"
                      ><template #default="{ row: receipt }">{{
                        money(receipt.applied_amount, selected.currency)
                      }}</template></ElTableColumn
                    ></ElTable
                  ></div
                ></template
              ></ElTableColumn
            ><ElTableColumn prop="invoice_number" label="Invoice" min-width="140" /><ElTableColumn
              prop="business_date"
              label="Date"
              width="115"
            /><ElTableColumn v-if="hasBreakdown" label="Buyer" min-width="160"
              ><template #default="{ row }">{{
                row.snapshot?.buyer_name || '—'
              }}</template></ElTableColumn
            ><ElTableColumn label="Charge" align="right" min-width="120"
              ><template #default="{ row }">{{
                money(row.invoice_amount, selected.currency)
              }}</template></ElTableColumn
            ><ElTableColumn v-if="hasBreakdown" label="Tax" align="right" min-width="110"
              ><template #default="{ row }">{{
                snapshotMoney(row, 'tax_amount')
              }}</template></ElTableColumn
            ><ElTableColumn v-if="hasBreakdown" label="Cash" align="right" min-width="110"
              ><template #default="{ row }">{{
                snapshotMoney(row, 'cash_applied_amount')
              }}</template></ElTableColumn
            ><ElTableColumn v-if="hasBreakdown" label="Withholding" align="right" min-width="120"
              ><template #default="{ row }">{{
                snapshotMoney(row, 'withholding_applied_amount')
              }}</template></ElTableColumn
            ><ElTableColumn v-if="!hasBreakdown" label="Applied" align="right" min-width="120"
              ><template #default="{ row }">{{
                money(row.payment_amount, selected.currency)
              }}</template></ElTableColumn
            ><ElTableColumn label="Balance" align="right" min-width="120"
              ><template #default="{ row }">{{
                money(row.outstanding_amount, selected.currency)
              }}</template></ElTableColumn
            ></ElTable
          ></div
        ></template
      ></ElDrawer
    >
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import { Plus, Refresh } from '@element-plus/icons-vue'
  import { fetchAdminCustomers } from '@/api/registration'
  import {
    fetchAccountStatement,
    fetchAccountStatements,
    generateAccountStatement,
    downloadAccountStatementArtifact,
    type AccountStatement,
    type AccountStatementItem,
    type AccountStatementLineSnapshot
  } from '@/api/accountStatements'
  defineOptions({ name: 'AccountStatements' })
  const loading = ref(false)
  const saving = ref(false)
  const downloadingPdf = ref(false)
  const statements = ref<AccountStatement[]>([])
  const customers = ref<any[]>([])
  const selected = ref<AccountStatement | null>(null)
  const createVisible = ref(false)
  const detailVisible = ref(false)
  const form = reactive({ customer_id: undefined as number | undefined, as_of_date: '' })
  const load = async () => {
    loading.value = true
    try {
      const result = await fetchAccountStatements()
      statements.value = result.data || []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load account statements')
    } finally {
      loading.value = false
    }
  }
  const openCreate = async () => {
    try {
      const result = await fetchAdminCustomers({ per_page: 100, status: 'active' })
      customers.value = result.data || []
      createVisible.value = true
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load active customers')
    }
  }
  const reset = () => Object.assign(form, { customer_id: undefined, as_of_date: '' })
  const generate = async () => {
    if (!form.customer_id || !form.as_of_date)
      return ElMessage.warning('Select a customer and as-of date.')
    saving.value = true
    try {
      const statement = await generateAccountStatement({
        customer_id: form.customer_id,
        as_of_date: form.as_of_date
      })
      ElMessage.success('Account statement snapshot generated')
      createVisible.value = false
      await load()
      await show(statement.id)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Could not generate the account statement')
    } finally {
      saving.value = false
    }
  }
  const show = async (id: number) => {
    try {
      selected.value = await fetchAccountStatement(id)
      detailVisible.value = true
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load statement details')
    }
  }
  const downloadPdf = async () => {
    if (!selected.value) return
    downloadingPdf.value = true
    try {
      const blob = await downloadAccountStatementArtifact(selected.value.id)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `Account-Statement-${selected.value.statement_number}.pdf`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
    } catch (error: any) {
      ElMessage.error(error?.message || 'The canonical statement PDF is not available yet.')
    } finally {
      downloadingPdf.value = false
    }
  }
  const disableFuture = (date: Date) => date > new Date(new Date().setHours(0, 0, 0, 0))
  const money = (value: string, currency: string) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number(value))
  const hasBreakdown = computed(() =>
    (selected.value?.items || []).some(
      (item) => item.snapshot && 'cash_applied_amount' in item.snapshot
    )
  )
  const summaryFacts = computed(() => {
    const statement = selected.value
    if (!statement) return []
    const currency = statement.currency
    const facts = [
      {
        label: 'Account',
        value:
          statement.customer?.account_number || statement.customer_snapshot?.account_number || '—'
      },
      {
        label: 'Customer',
        value: statement.customer?.name || statement.customer_snapshot?.name || '—'
      },
      { label: 'As of', value: statement.as_of_date },
      { label: 'Charges', value: money(statement.invoice_total, currency) }
    ]
    if (hasBreakdown.value) {
      facts.push(
        { label: 'Tax included', value: money(sumSnapshot('tax_amount'), currency) },
        { label: 'Cash applied', value: money(statement.cash_applied_total || '0.00', currency) },
        {
          label: 'Withholding applied',
          value: money(statement.withholding_applied_total || '0.00', currency)
        }
      )
    } else {
      facts.push({ label: 'Applied', value: money(statement.payment_total, currency) })
    }
    facts.push({ label: 'Balance due', value: money(statement.outstanding_total, currency) })
    return facts
  })
  const snapshotMoney = (row: AccountStatementItem, key: keyof AccountStatementLineSnapshot) => {
    const value = row.snapshot?.[key]
    if (typeof value !== 'string' || !selected.value) return '—'
    return money(value, selected.value.currency)
  }
  const shipmentLabel = (vessel?: string | null, voyage?: string | null) => {
    const parts = [vessel, voyage].filter((part) => part && part.trim() !== '')
    return parts.length ? parts.join(' / ') : '—'
  }
  const sumSnapshot = (key: 'tax_amount') =>
    (selected.value?.items || []).reduce(
      (total, item) => addMoney(total, item.snapshot?.[key] || '0.00'),
      '0.00'
    )
  const addMoney = (left: string, right: string) => {
    const cents = (value: string) => {
      const [whole, fraction = ''] = value.split('.')
      const sign = whole.startsWith('-') ? -1 : 1
      const pesos = Math.abs(Number(whole || '0'))
      return sign * (pesos * 100 + Number(fraction.padEnd(2, '0').slice(0, 2)))
    }
    const total = cents(left) + cents(right)
    const sign = total < 0 ? '-' : ''
    const absolute = Math.abs(total)
    return `${sign}${Math.floor(absolute / 100)}.${String(absolute % 100).padStart(2, '0')}`
  }
  onMounted(load)
</script>
