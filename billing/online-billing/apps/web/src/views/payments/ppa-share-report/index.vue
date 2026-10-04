<template>
  <div class="page-content !p-0 overflow-hidden">
    <header
      class="flex flex-col gap-4 border-b border-g-200 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6"
    >
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:pie-chart-2-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">PPA Share Report</h1>
          <p class="mt-1 max-w-2xl text-sm text-g-500">
            Paid bills in your location, the PPA share stored on each bill, and the receipts that
            settled them.
          </p>
        </div>
      </div>
      <ElButton :loading="loading" @click="load(1)">
        <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
        Refresh
      </ElButton>
    </header>

    <section class="space-y-5 p-5 sm:p-6">
      <div class="art-card !p-4 sm:!p-5">
        <ElForm
          class="grid grid-cols-1 gap-x-4 sm:grid-cols-[minmax(0,1fr)_auto]"
          label-position="top"
        >
          <ElFormItem label="Bill date range">
            <ElDatePicker
              v-model="dateRange"
              type="daterange"
              value-format="YYYY-MM-DD"
              range-separator="to"
              start-placeholder="Start date"
              end-placeholder="End date"
              class="!w-full"
              :clearable="false"
            />
          </ElFormItem>
          <ElFormItem label=" ">
            <ElButton type="primary" :loading="loading" @click="load(1)">Show paid bills</ElButton>
          </ElFormItem>
        </ElForm>
      </div>

      <ElAlert
        type="info"
        :closable="false"
        show-icon
        :title="report?.report.notice || defaultNotice"
      />

      <div v-if="report" class="grid grid-cols-1 gap-3 md:grid-cols-3">
        <article
          v-for="total in report.totals_by_currency"
          :key="total.currency"
          class="art-card-sm space-y-2 p-4"
        >
          <p class="text-xs font-semibold uppercase tracking-wide text-g-500">{{
            total.currency
          }}</p>
          <p class="text-sm text-g-600">{{ total.paid_bill_count }} paid bills</p>
          <p class="text-sm text-g-800"
            >Invoice total {{ money(total.invoice_total, total.currency) }}</p
          >
          <p class="text-base font-semibold text-g-900">
            PPA share {{ money(total.ppa_share, total.currency) }}
          </p>
        </article>
        <article
          v-if="report.totals_by_currency.length === 0"
          class="art-card-sm p-4 text-sm text-g-500"
        >
          No fully paid bills in this date range.
        </article>
      </div>

      <div class="art-card overflow-hidden !p-0" v-loading="loading">
        <ElTable :data="report?.rows || []" size="small">
          <ElTableColumn label="Bill" min-width="160">
            <template #default="{ row }">
              <ElButton link type="primary" @click="openBill(row)">{{
                row.invoice_number
              }}</ElButton>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="business_date" label="Bill date" width="120" />
          <ElTableColumn label="Customer" min-width="200">
            <template #default="{ row }">
              {{ row.customer_name || '—' }}
              <span v-if="row.customer_account_number" class="text-g-500">
                · {{ row.customer_account_number }}
              </span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Invoice total" min-width="140" align="right">
            <template #default="{ row }">{{ money(row.invoice_total, row.currency) }}</template>
          </ElTableColumn>
          <ElTableColumn label="PPA share" min-width="140" align="right">
            <template #default="{ row }">{{ money(row.ppa_share, row.currency) }}</template>
          </ElTableColumn>
          <ElTableColumn label="Settled by" min-width="220">
            <template #default="{ row }">
              <p
                v-for="(receipt, index) in row.receipts"
                :key="`${row.invoice_number}-${index}`"
                class="text-xs"
              >
                <ElButton
                  v-if="receipt.receipt_number"
                  link
                  type="primary"
                  @click="openReceipt(row, receipt)"
                >
                  {{ receipt.receipt_number }}
                </ElButton>
                <span class="text-g-800">
                  · {{ receipt.business_date }} · {{ money(receipt.applied_amount, row.currency) }}
                </span>
              </p>
            </template>
          </ElTableColumn>
        </ElTable>
        <div v-if="(report?.pagination.last_page || 1) > 1" class="flex justify-end px-4 py-3">
          <ElPagination
            background
            layout="prev, pager, next"
            :current-page="report?.pagination.current_page || 1"
            :page-size="report?.pagination.per_page || 25"
            :total="report?.pagination.total || 0"
            @current-change="load"
          />
        </div>
      </div>
    </section>

    <ElDrawer v-model="drawerOpen" :title="detailTitle" size="72%" @closed="clearDetail">
      <div v-if="detailSummary" class="space-y-4">
        <div class="art-card space-y-2 p-4 text-sm text-g-800">
          <template v-if="activeReceipt">
            <p class="font-semibold text-g-900"
              >Official receipt {{ activeReceipt.receipt_number }}</p
            >
            <p>Receipt date {{ activeReceipt.business_date || '—' }}</p>
            <p>
              Applied to {{ detailSummary.invoice_number }}:
              {{ money(activeReceipt.applied_amount, detailSummary.currency) }}
            </p>
            <ElButton link type="primary" @click="openBill(detailSummary)">
              View bill {{ detailSummary.invoice_number }}
            </ElButton>
          </template>
          <template v-else>
            <p class="font-semibold text-g-900">{{ detailSummary.invoice_number }}</p>
            <p>
              {{ detailSummary.customer_name || '—' }}
              <span v-if="detailSummary.customer_account_number">
                · {{ detailSummary.customer_account_number }}
              </span>
            </p>
            <p>Bill date {{ detailSummary.business_date || '—' }}</p>
            <p>Invoice total {{ money(detailSummary.invoice_total, detailSummary.currency) }}</p>
            <p>PPA share {{ money(detailSummary.ppa_share, detailSummary.currency) }}</p>
            <div class="pt-1">
              <p class="mb-1 text-xs font-semibold uppercase tracking-wide text-g-500"
                >Related receipts</p
              >
              <ElButton
                v-for="(receipt, index) in detailSummary.receipts"
                :key="`${detailSummary.invoice_number}-${index}`"
                link
                type="primary"
                :disabled="!receipt.receipt_number"
                @click="openReceipt(detailSummary, receipt)"
              >
                {{ receipt.receipt_number }} · {{ receipt.business_date }} ·
                {{ money(receipt.applied_amount, detailSummary.currency) }}
              </ElButton>
            </div>
          </template>
        </div>
        <div v-if="detailLoading" class="text-sm text-g-500">Loading the issued document…</div>
        <p v-else-if="detailError" class="text-sm text-g-500">{{ detailError }}</p>
        <iframe
          v-else-if="detailUrl"
          :src="detailUrl"
          class="h-[760px] w-full rounded-lg border border-g-300 bg-white"
          :title="detailTitle"
        />
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { onBeforeUnmount, onMounted, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import {
    downloadPpaBillLayout,
    downloadPpaReceiptLayout,
    fetchPpaShareReport,
    type PpaShareReceipt,
    type PpaShareReport,
    type PpaShareRow
  } from '@/api/ppaClearance'

  defineOptions({ name: 'PpaShareReport' })

  const defaultNotice =
    'Fully paid posted bills in your location. PPA share is the amount stored on the issued bill.'

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
  const report = ref<PpaShareReport | null>(null)
  const drawerOpen = ref(false)
  const detailLoading = ref(false)
  const detailTitle = ref('')
  const detailError = ref('')
  const detailUrl = ref('')
  const detailSummary = ref<PpaShareRow | null>(null)
  const activeReceipt = ref<PpaShareReceipt | null>(null)

  const money = (amount: string, currency: string) =>
    new Intl.NumberFormat('en-PH', { style: 'currency', currency: currency || 'PHP' }).format(
      Number(amount)
    )

  function clearDetail() {
    if (detailUrl.value) URL.revokeObjectURL(detailUrl.value)
    detailUrl.value = ''
    detailError.value = ''
  }

  async function showDocument(kind: 'invoice' | 'receipt', number: string) {
    clearDetail()
    detailLoading.value = true
    const missing =
      kind === 'invoice'
        ? 'The issued invoice layout is not ready.'
        : 'The issued receipt layout is not ready.'
    try {
      const blob =
        kind === 'invoice'
          ? await downloadPpaBillLayout(number)
          : await downloadPpaReceiptLayout(number)
      if (!(blob instanceof Blob) || blob.size < 5) {
        detailError.value = missing
        return
      }
      detailUrl.value = URL.createObjectURL(blob)
    } catch {
      detailError.value = missing
    } finally {
      detailLoading.value = false
    }
  }

  async function openBill(row: PpaShareRow) {
    drawerOpen.value = true
    detailSummary.value = row
    activeReceipt.value = null
    detailTitle.value = row.invoice_number
    await showDocument('invoice', row.invoice_number)
  }

  async function openReceipt(row: PpaShareRow, receipt: PpaShareReceipt) {
    if (!receipt.receipt_number) return
    drawerOpen.value = true
    detailSummary.value = row
    activeReceipt.value = receipt
    detailTitle.value = receipt.receipt_number
    await showDocument('receipt', receipt.receipt_number)
  }

  async function load(page = 1) {
    if (!dateRange.value?.[0] || !dateRange.value?.[1]) return
    loading.value = true
    try {
      report.value = await fetchPpaShareReport({
        date_from: dateRange.value[0],
        date_to: dateRange.value[1],
        page
      })
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load the PPA share report.')
    } finally {
      loading.value = false
    }
  }

  onMounted(() => load(1))
  onBeforeUnmount(clearDetail)
</script>
