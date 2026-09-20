<template>
  <div class="max-w-7xl mx-auto space-y-6 p-4 sm:p-6">
    <section
      class="flex flex-col gap-4 rounded-2xl bg-gradient-to-r from-amber-700 to-amber-600 p-6 text-white shadow-sm sm:flex-row sm:items-center sm:justify-between"
    >
      <div>
        <h1 class="text-xl font-bold sm:text-2xl">Yellow &amp; White Transmittals</h1>
        <p class="mt-1 max-w-2xl text-sm text-amber-50">
          Immutable operational snapshots of selected posted invoices or collection receipts. They
          do not issue a fiscal document, post a payment, or change a source record.
        </p>
      </div>
      <div class="flex gap-2">
        <ElButton
          :loading="loading"
          plain
          class="!border-white/20 !bg-white/10 !text-white"
          @click="load"
        >
          <ElIcon class="mr-1"><Refresh /></ElIcon>Refresh
        </ElButton>
        <ElButton type="primary" @click="openCreate">
          <ElIcon class="mr-1"><Plus /></ElIcon>Generate transmittal
        </ElButton>
      </div>
    </section>

    <ElAlert
      type="warning"
      :closable="false"
      show-icon
      title="Current P4-02 boundary"
      description="Canonical PDFs are rendered from each saved snapshot. Legacy white-transmittal BIR 2307/income/withholding classifications are not inferred; physical output, void/reissue, exports, and delivery remain later gates."
    />

    <ElCard shadow="never" class="!rounded-xl">
      <ElTable
        v-loading="loading"
        :data="transmittals"
        stripe
        empty-text="No transmittal snapshots have been generated."
      >
        <ElTableColumn prop="transmittal_number" label="Reference" min-width="240">
          <template #default="{ row }">
            <span class="font-mono text-xs">{{ row.transmittal_number }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Type" min-width="155">
          <template #default="{ row }">
            <ElTag size="small" :type="row.kind === 'YELLOW_INVOICE' ? 'warning' : 'info'">
              {{ kindLabel(row.kind) }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="as_of_date" label="As of" width="120" />
        <ElTableColumn prop="source_item_count" label="Sources" width="100" align="right" />
        <ElTableColumn label="Captured total" min-width="155" align="right">
          <template #default="{ row }">{{ summaryAmount(row) }}</template>
        </ElTableColumn>
        <ElTableColumn label="" width="100" fixed="right">
          <template #default="{ row }">
            <ElButton link type="primary" @click="show(row.id)">View</ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog
      v-model="createVisible"
      title="Generate immutable transmittal"
      width="min(940px, 96vw)"
      @closed="reset"
    >
      <ElForm label-position="top" class="grid grid-cols-1 gap-x-4 sm:grid-cols-2">
        <ElFormItem label="Transmittal type" required>
          <ElRadioGroup v-model="form.kind" @change="clearSources">
            <ElRadioButton value="YELLOW_INVOICE">Yellow — invoices</ElRadioButton>
            <ElRadioButton value="WHITE_RECEIPT">White — receipts</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem label="As-of date" required>
          <ElDatePicker
            v-model="form.as_of_date"
            type="date"
            value-format="YYYY-MM-DD"
            class="!w-full"
            :disabled-date="disableFuture"
            @change="clearSources"
          />
        </ElFormItem>
      </ElForm>
      <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-600 dark:text-slate-300">
          Choose the exact posted {{ form.kind === 'YELLOW_INVOICE' ? 'invoices' : 'receipts' }} to
          freeze in this snapshot. Documents dated after the as-of date are excluded.
        </p>
        <ElButton :loading="sourceLoading" :disabled="!form.as_of_date" @click="loadSources">
          Load eligible sources
        </ElButton>
      </div>
      <ElTable
        v-loading="sourceLoading"
        :data="sources"
        max-height="360"
        stripe
        empty-text="Choose an as-of date, then load eligible sources."
        @selection-change="selectionChanged"
      >
        <ElTableColumn type="selection" width="48" />
        <ElTableColumn prop="source_number" label="Number" min-width="185">
          <template #default="{ row }">
            <span class="font-mono text-xs">{{ row.source_number }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="business_date" label="Date" width="120" />
        <ElTableColumn prop="party_name" label="Customer / payer" min-width="220" />
        <ElTableColumn label="Amount" min-width="140" align="right">
          <template #default="{ row }">{{ money(row.amount, row.currency) }}</template>
        </ElTableColumn>
      </ElTable>
      <p class="mt-3 text-sm text-slate-500">{{ selectedSourceIds.length }} source(s) selected.</p>
      <template #footer>
        <ElButton @click="createVisible = false">Cancel</ElButton>
        <ElButton
          type="primary"
          :loading="saving"
          :disabled="!selectedSourceIds.length"
          @click="generate"
        >
          Generate immutable snapshot
        </ElButton>
      </template>
    </ElDialog>

    <ElDrawer v-model="detailVisible" title="Transmittal snapshot" size="min(900px, 100%)">
      <template v-if="selected">
        <div class="space-y-4">
          <div class="flex justify-end">
            <ElButton type="primary" :loading="downloadingPdf" @click="downloadPdf">
              Download canonical PDF
            </ElButton>
          </div>
          <div
            class="grid grid-cols-1 gap-3 rounded-xl bg-slate-50 p-4 text-sm sm:grid-cols-4 dark:bg-slate-800"
          >
            <div>
              <span class="block text-xs text-slate-500">Reference</span>
              <b class="font-mono text-xs">{{ selected.transmittal_number }}</b>
            </div>
            <div>
              <span class="block text-xs text-slate-500">Type</span>
              <b>{{ kindLabel(selected.kind) }}</b>
            </div>
            <div>
              <span class="block text-xs text-slate-500">As of</span
              ><b>{{ selected.as_of_date }}</b>
            </div>
            <div>
              <span class="block text-xs text-slate-500">Captured total</span>
              <b>{{ summaryAmount(selected) }}</b>
            </div>
          </div>

          <ElTable
            v-if="selected.kind === 'YELLOW_INVOICE'"
            :data="selected.yellow_items || []"
            stripe
          >
            <ElTableColumn prop="invoice_number" label="Invoice" min-width="170" />
            <ElTableColumn prop="business_date" label="Date" width="120" />
            <ElTableColumn label="Customer" min-width="220">
              <template #default="{ row }">{{ row.buyer_snapshot?.name || '—' }}</template>
            </ElTableColumn>
            <ElTableColumn label="Invoice total" min-width="145" align="right">
              <template #default="{ row }">{{
                money(row.total_charge_amount, row.currency)
              }}</template>
            </ElTableColumn>
          </ElTable>

          <ElTable v-else :data="selected.white_items || []" stripe>
            <ElTableColumn prop="receipt_number" label="Receipt" min-width="170" />
            <ElTableColumn prop="business_date" label="Date" width="120" />
            <ElTableColumn label="Payer" min-width="210">
              <template #default="{ row }">
                {{ row.payer_snapshot?.registered_name || row.payer_snapshot?.name || '—' }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Cash" min-width="125" align="right">
              <template #default="{ row }">{{
                money(row.cash_received_amount, row.currency)
              }}</template>
            </ElTableColumn>
            <ElTableColumn label="Applied" min-width="125" align="right">
              <template #default="{ row }">{{ money(row.applied_amount, row.currency) }}</template>
            </ElTableColumn>
          </ElTable>
        </div>
      </template>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import { Plus, Refresh } from '@element-plus/icons-vue'
  import {
    fetchEligibleTransmittalSources,
    fetchTransmittal,
    fetchTransmittals,
    generateTransmittal,
    downloadTransmittalArtifact,
    type Transmittal,
    type TransmittalKind,
    type TransmittalSource
  } from '@/api/transmittals'

  defineOptions({ name: 'Transmittals' })

  const loading = ref(false)
  const sourceLoading = ref(false)
  const saving = ref(false)
  const downloadingPdf = ref(false)
  const createVisible = ref(false)
  const detailVisible = ref(false)
  const transmittals = ref<Transmittal[]>([])
  const sources = ref<TransmittalSource[]>([])
  const selectedSourceIds = ref<number[]>([])
  const selected = ref<Transmittal | null>(null)
  const form = reactive<{ kind: TransmittalKind; as_of_date: string }>({
    kind: 'YELLOW_INVOICE',
    as_of_date: ''
  })

  const load = async () => {
    loading.value = true
    try {
      const result = await fetchTransmittals()
      transmittals.value = result.data || []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load transmittals')
    } finally {
      loading.value = false
    }
  }

  const openCreate = () => {
    createVisible.value = true
  }

  const reset = () => {
    Object.assign(form, { kind: 'YELLOW_INVOICE', as_of_date: '' })
    clearSources()
  }

  const clearSources = () => {
    sources.value = []
    selectedSourceIds.value = []
  }

  const loadSources = async () => {
    if (!form.as_of_date) return ElMessage.warning('Choose an as-of date first.')
    sourceLoading.value = true
    try {
      const result = await fetchEligibleTransmittalSources({
        kind: form.kind,
        as_of_date: form.as_of_date
      })
      sources.value = result.data || []
      selectedSourceIds.value = []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load eligible sources')
    } finally {
      sourceLoading.value = false
    }
  }

  const selectionChanged = (rows: TransmittalSource[]) => {
    selectedSourceIds.value = rows.map((row) => row.id)
  }

  const generate = async () => {
    if (!form.as_of_date || !selectedSourceIds.value.length) return
    saving.value = true
    try {
      const transmittal = await generateTransmittal({
        kind: form.kind,
        as_of_date: form.as_of_date,
        ...(form.kind === 'YELLOW_INVOICE'
          ? { invoice_ids: selectedSourceIds.value }
          : { receipt_ids: selectedSourceIds.value })
      })
      ElMessage.success('Immutable transmittal snapshot generated')
      createVisible.value = false
      await load()
      await show(transmittal.id)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Could not generate the transmittal')
    } finally {
      saving.value = false
    }
  }

  const show = async (id: number) => {
    try {
      selected.value = await fetchTransmittal(id)
      detailVisible.value = true
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load transmittal details')
    }
  }

  const downloadPdf = async () => {
    if (!selected.value) return
    downloadingPdf.value = true
    try {
      const blob = await downloadTransmittalArtifact(selected.value.id)
      const url = URL.createObjectURL(blob)
      const link = document.createElement('a')
      link.href = url
      link.download = `Transmittal-${selected.value.transmittal_number}.pdf`
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
    } catch (error: any) {
      ElMessage.error(error?.message || 'The canonical transmittal PDF is not available yet.')
    } finally {
      downloadingPdf.value = false
    }
  }

  const kindLabel = (kind: TransmittalKind) =>
    kind === 'YELLOW_INVOICE' ? 'Yellow — invoices' : 'White — receipts'
  const summaryAmount = (transmittal: Transmittal) =>
    money(
      transmittal.summary.invoice_total || transmittal.summary.applied_total || '0.00',
      transmittal.currency
    )
  const money = (value: string, currency: string) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number(value))
  const disableFuture = (date: Date) => date > new Date(new Date().setHours(0, 0, 0, 0))

  onMounted(load)
</script>
