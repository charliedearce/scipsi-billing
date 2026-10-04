<template>
  <div class="page-content !p-0 overflow-hidden">
    <header class="flex flex-wrap items-start justify-between gap-4 p-5 sm:p-6">
      <div class="flex items-start gap-3">
        <div class="flex-cc size-11 shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:database-2-line" class="text-2xl" />
        </div>
        <div>
          <h1 class="text-xl font-medium text-g-900">Legacy Import &amp; History</h1>
          <p class="mt-1 text-sm text-g-500"
            >Original records, preserved amounts. No active balances.</p
          >
        </div>
      </div>
      <ElButton :loading="loading" @click="refresh">Refresh</ElButton>
    </header>

    <div class="border-t border-g-200 px-5 pb-5 sm:px-6 sm:pb-6">
      <ElTabs v-model="activeTab">
        <ElTabPane label="Import data" name="imports">
          <ElAlert
            type="info"
            :closable="false"
            show-icon
            class="mb-5"
            title="Searchable history only"
          >
            Importing does not create live customers, invoices, payments or bill numbers. Balances
            need a separate reviewed activation process, which is not enabled here.
          </ElAlert>
          <SqlConnectionImport :busy="running || uploading" @ready="openSqlPreview" />
          <ElCollapse class="mb-5">
            <ElCollapseItem title="Alternative: import an exported history ZIP" name="file-import">
              <div class="art-card-sm p-4 sm:p-5">
                <div class="flex flex-wrap items-start justify-between gap-4">
                  <div class="max-w-3xl">
                    <h2 class="font-medium text-g-900">1. Export a consistent legacy snapshot</h2>
                    <p class="mt-2 text-sm leading-6 text-g-600"
                      >Download and extract the toolkit on the legacy workstation. Use a restored or
                      read-only database, or pre-enabled snapshot isolation. Credentials stay on
                      that workstation.</p
                    >
                    <p class="mt-2 text-sm text-g-600"
                      >Repeat exports of the same database stay together automatically. Store
                      packages privately; they contain customer information.</p
                    >
                  </div>
                  <ElButton :loading="downloading" @click="downloadToolkit"
                    >Download export toolkit</ElButton
                  >
                </div>
                <ElCollapse class="mt-3">
                  <ElCollapseItem title="Export command and safety notes" name="instructions">
                    <p class="mb-2 text-sm text-g-600"
                      >Replace these examples with your restored database and private output folder.
                      Run in PowerShell from the extracted toolkit folder.</p
                    >
                    <pre class="overflow-x-auto rounded-lg bg-g-100 p-3 text-xs text-g-800">
.\Export-LegacyHistory.ps1 -Server '.\SQLEXPRESS' -Database 'billing_restore' -OutputPath 'D:\Private\history.zip' -RestoredDatabase</pre
                    >
                    <p class="mt-2 text-sm text-g-500"
                      >Only use -RestoredDatabase for a verified restore: its read locks can block
                      writers. Windows authentication is the default; SQL credentials can be
                      provided interactively with -Credential (Get-Credential). Use
                      -SampleRowsPerTable 100 for a sample, not a complete rehearsal.</p
                    >
                  </ElCollapseItem>
                </ElCollapse>
                <div class="mt-4 border-t border-g-200 pt-4">
                  <h2 class="mb-3 font-medium text-g-900">2. Upload and preview</h2>
                  <ElUpload
                    action="#"
                    accept=".zip"
                    :auto-upload="false"
                    :show-file-list="false"
                    :disabled="uploading || running"
                    :on-change="choosePackage"
                  >
                    <ElButton type="primary" :loading="uploading" :disabled="running"
                      >Choose history ZIP</ElButton
                    >
                    <template #tip
                      ><p class="mt-2 text-xs text-g-500"
                        >One exporter ZIP, up to 128 MiB compressed / 1 GiB uncompressed. Preview
                        can be paused and resumed.</p
                      ></template
                    >
                  </ElUpload>
                </div>
              </div>
            </ElCollapseItem>
          </ElCollapse>
          <section v-if="selected" class="art-card-sm mb-5 p-4 sm:p-5" aria-live="polite">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div>
                <h2 class="font-medium text-g-900"
                  >{{ selected.source_key }} · {{ batchLabel(selected.status) }}</h2
                >
                <p class="mt-1 text-xs text-g-500"
                  >Exported {{ formatDateTimeManila(selected.manifest.exported_at) }} ·
                  {{
                    selected.manifest.scope === 'SAMPLE' ? 'Sample only' : 'Full history package'
                  }}</p
                >
              </div>
              <div class="flex flex-wrap gap-2">
                <ElButton
                  v-if="selected.status === 'STAGING' && !running"
                  type="primary"
                  @click="runPreview"
                  >Resume preview</ElButton
                >
                <ElButton
                  v-if="running"
                  @click="pauseRequested = true"
                  :disabled="pauseRequested"
                  >{{ pauseRequested ? 'Pausing after this step…' : 'Pause preview' }}</ElButton
                >
                <ElButton
                  v-if="selected.status === 'READY'"
                  type="primary"
                  @click="confirmOpen = true"
                  >Import as history</ElButton
                >
                <ElButton v-if="selected.processed_rows" @click="browseBatch(selected)"
                  >Review records</ElButton
                >
              </div>
            </div>
            <div v-if="selected.status === 'STAGING'" class="mt-4">
              <ElProgress :percentage="progress" />
              <p class="mt-1 text-xs text-g-500"
                >{{ selected.processed_rows.toLocaleString() }} /
                {{ selected.expected_rows.toLocaleString() }} records checked. Closing this page
                pauses after the current step.</p
              >
            </div>
            <ElAlert
              v-if="selected.error_message"
              type="error"
              :closable="false"
              class="mt-4"
              :title="selected.error_message"
              description="Read the corrected source again or regenerate its export package. Incomplete staged rows are not searchable history."
            />
            <ElAlert
              v-if="selected.manifest.scope === 'SAMPLE'"
              type="warning"
              :closable="false"
              class="mt-4"
              title="Sample package — not a complete migration"
              description="Tables were sampled independently. Missing relationships may be expected; do not use this to establish balances."
            />
            <template v-if="selected.summary">
              <div v-if="selected.status === 'IMPORTED'" class="mt-4 flex flex-wrap gap-2">
                <ElTag type="success"
                  >{{ selected.summary.dispositions.IMPORTED || 0 }} added to history</ElTag
                >
                <ElTag type="info"
                  >{{ selected.summary.dispositions.DUPLICATE || 0 }} unchanged / skipped</ElTag
                >
                <ElButton
                  v-if="selected.summary.dispositions.CONFLICT"
                  type="warning"
                  plain
                  size="small"
                  @click="browseBatch(selected, 'CONFLICT')"
                  >{{ selected.summary.dispositions.CONFLICT }} conflicts · review</ElButton
                >
              </div>
              <ElTable :data="selected.summary.tables" class="mt-4">
                <ElTableColumn label="Record type" min-width="160"
                  ><template #default="{ row }">{{
                    tableLabel(row.source_table)
                  }}</template></ElTableColumn
                >
                <ElTableColumn prop="rows" label="Rows" width="100" />
                <ElTableColumn label="Needs review" width="130"
                  ><template #default="{ row }"
                    ><span :class="row.review_rows ? 'text-warning' : 'text-g-500'">{{
                      row.review_rows
                    }}</span></template
                  ></ElTableColumn
                >
                <ElTableColumn label="Stored amount sum" min-width="180" align="right"
                  ><template #default="{ row }">{{
                    money(row.stored_amount_sum)
                  }}</template></ElTableColumn
                >
              </ElTable>
              <p class="mt-3 text-xs text-g-500"
                >Sums are per record type, include cancelled records and are not outstanding
                balances. Bills use legacy “Due to SCIPSI”; receipts use stored net. Never add these
                totals together. Review warnings remain attached to imported records.</p
              >
            </template>
          </section>

          <h2 class="mb-3 font-medium text-g-900">Import activity</h2>
          <ElTable
            v-loading="loading"
            :data="batches"
            empty-text="No imports yet. Connect to SQL Server or use an exported history ZIP."
          >
            <ElTableColumn prop="source_key" label="Source" min-width="150" />
            <ElTableColumn label="Staged" min-width="180"
              ><template #default="{ row }">{{
                formatDateTimeManila(row.created_at)
              }}</template></ElTableColumn
            >
            <ElTableColumn label="Progress" min-width="140"
              ><template #default="{ row }"
                >{{ row.processed_rows.toLocaleString() }} /
                {{ row.expected_rows.toLocaleString() }}</template
              ></ElTableColumn
            >
            <ElTableColumn label="Status" min-width="140"
              ><template #default="{ row }"
                ><ElTag :type="batchType(row.status)" effect="plain">{{
                  batchLabel(row.status)
                }}</ElTag></template
              ></ElTableColumn
            >
            <ElTableColumn label="Action" width="95" fixed="right"
              ><template #default="{ row }"
                ><ElButton link type="primary" :disabled="running" @click="openBatch(row)"
                  >Open</ElButton
                ></template
              ></ElTableColumn
            >
          </ElTable>
          <ElPagination
            v-if="batchTotal > 15"
            v-model:current-page="batchPage"
            :page-size="15"
            :total="batchTotal"
            layout="prev, pager, next"
            class="mt-4"
            @current-change="loadBatches"
          />
        </ElTabPane>

        <ElTabPane label="Search history" name="history">
          <div class="mb-4 flex flex-wrap items-center gap-2">
            <ElTag v-if="batchFilter" closable @close="clearBatch"
              >Package: {{ batchSource }}
              {{ disposition === 'CONFLICT' ? '· conflicts' : '' }}</ElTag
            >
            <ElTag v-else type="info" effect="plain">{{ historyScopeLabel }}</ElTag>
            <span class="text-xs text-g-500">Archived values, not current account balances</span>
          </div>
          <ElForm inline class="history-filters" @submit.prevent="searchHistory">
            <ElFormItem label="Record type"
              ><ElSelect v-model="table" class="!w-48" @change="searchHistory"
                ><ElOption
                  v-for="option in tables"
                  :key="option.value"
                  :value="option.value"
                  :label="option.label" /></ElSelect
            ></ElFormItem>
            <ElFormItem label="Search"
              ><ElInput
                v-model="search"
                clearable
                placeholder="Bill # 0000061024, account or name"
                class="!w-64"
                @clear="searchHistory"
            /></ElFormItem>
            <ElFormItem label="Source"
              ><ElInput
                v-model="source"
                clearable
                placeholder="Database name"
                class="!w-44"
                @clear="searchHistory"
            /></ElFormItem>
            <ElFormItem label="From"
              ><ElDatePicker v-model="from" type="date" value-format="YYYY-MM-DD" class="!w-40"
            /></ElFormItem>
            <ElFormItem label="To"
              ><ElDatePicker v-model="to" type="date" value-format="YYYY-MM-DD" class="!w-40"
            /></ElFormItem>
            <ElFormItem
              ><ElCheckbox v-model="reviewOnly" @change="searchHistory"
                >Needs review</ElCheckbox
              ></ElFormItem
            >
            <ElFormItem
              ><ElButton native-type="submit" type="primary" :loading="historyLoading"
                >Search</ElButton
              ></ElFormItem
            >
          </ElForm>
          <ElTable
            v-loading="historyLoading"
            :data="records"
            empty-text="No records match. Change the filters or import a history package."
          >
            <ElTableColumn :label="referenceColumnLabel" min-width="145"
              ><template #default="{ row }"
                ><ElButton
                  link
                  type="primary"
                  class="!font-mono !font-semibold tracking-wide"
                  @click="openRecord(row)"
                  >{{ documentNumber(row.reference) }}</ElButton
                ><p v-if="row.related_reference" class="text-xs text-g-500"
                  >Bill {{ documentNumber(row.related_reference) }}</p
                ></template
              ></ElTableColumn
            >
            <template v-if="showingBillItems">
              <ElTableColumn label="QTY" min-width="80" align="right"
                ><template #default="{ row }">{{
                  payloadField(row, 'it_qty')
                }}</template></ElTableColumn
              >
              <ElTableColumn label="UNIT" min-width="90"
                ><template #default="{ row }">{{
                  payloadField(row, 'it_unit')
                }}</template></ElTableColumn
              >
              <ElTableColumn label="SERVICE" min-width="200"
                ><template #default="{ row }">{{
                  payloadField(row, 'it_service')
                }}</template></ElTableColumn
              >
              <ElTableColumn label="CARGO" min-width="160"
                ><template #default="{ row }">{{
                  payloadField(row, 'it_cargo') || row.display_name || '—'
                }}</template></ElTableColumn
              >
              <ElTableColumn label="RATE" min-width="100" align="right"
                ><template #default="{ row }">{{
                  money(row.payload?.it_rate)
                }}</template></ElTableColumn
              >
              <ElTableColumn label="DISC." min-width="90" align="right"
                ><template #default="{ row }">{{
                  money(row.payload?.it_disc)
                }}</template></ElTableColumn
              >
              <ElTableColumn label="GROSS" min-width="110" align="right"
                ><template #default="{ row }">{{
                  money(row.payload?.it_gross)
                }}</template></ElTableColumn
              >
            </template>
            <template v-else>
              <ElTableColumn v-if="showingBills" label="Account" min-width="200"
                ><template #default="{ row }"
                  ><p>{{ row.display_name || '—' }}</p
                  ><p class="text-xs text-g-500">{{ row.account_number || '—' }}</p></template
                ></ElTableColumn
              >
              <ElTableColumn v-else label="Account / description" min-width="220"
                ><template #default="{ row }"
                  ><p>{{ row.display_name || '—' }}</p
                  ><p class="text-xs text-g-500">{{
                    row.account_number || row.source_key
                  }}</p></template
                ></ElTableColumn
              >
              <ElTableColumn v-if="showingBills" label="Vessel" min-width="140"
                ><template #default="{ row }">{{
                  payloadField(row, 'bt_vessel')
                }}</template></ElTableColumn
              >
              <ElTableColumn v-if="showingBills" label="Voyage" min-width="90"
                ><template #default="{ row }">{{
                  payloadField(row, 'bt_voyage')
                }}</template></ElTableColumn
              >
              <ElTableColumn v-if="showingBills" label="Route" min-width="110"
                ><template #default="{ row }">{{
                  routeLabel(row.payload?.bt_route)
                }}</template></ElTableColumn
              >
              <ElTableColumn v-if="showingBills" label="Type" min-width="80"
                ><template #default="{ row }">{{
                  typeLabel(row.payload?.bt_type)
                }}</template></ElTableColumn
              >
              <ElTableColumn label="Legacy date" min-width="125"
                ><template #default="{ row }">{{
                  row.source_date?.slice(0, 10) || '—'
                }}</template></ElTableColumn
              >
              <ElTableColumn label="Stored amount" min-width="140" align="right"
                ><template #default="{ row }">{{ money(row.due_amount) }}</template></ElTableColumn
              >
            </template>
            <ElTableColumn label="Review" min-width="160"
              ><template #default="{ row }"
                ><ElTag v-if="row.disposition === 'CONFLICT'" type="danger" size="small"
                  >Source changed</ElTag
                ><ElTag v-else-if="row.disposition === 'STAGED'" type="warning" size="small"
                  >Preview</ElTag
                ><ElTag v-else :type="row.issues.length ? 'warning' : 'info'" size="small">{{
                  row.issues.length ? `${row.issues.length} checks flagged` : 'No checks flagged'
                }}</ElTag
                ><p v-if="row.source_status" class="mt-1 text-xs text-g-500">{{
                  legacyStatus(row.source_status)
                }}</p></template
              ></ElTableColumn
            >
          </ElTable>
          <div class="mt-4 flex flex-wrap items-center justify-between gap-3"
            ><span class="text-xs text-g-500">{{ recordTotal.toLocaleString() }} records</span
            ><ElPagination
              v-model:current-page="recordPage"
              :total="recordTotal"
              :page-size="25"
              layout="prev, pager, next"
              @current-change="loadHistory"
          /></div>
        </ElTabPane>
      </ElTabs>
    </div>

    <ElDialog
      v-model="confirmOpen"
      title="Import as searchable history"
      width="min(560px, 94vw)"
      :close-on-click-modal="!finalizing"
      :show-close="!finalizing"
    >
      <p class="text-sm leading-6 text-g-600"
        >This preserves stored amounts and warnings without activating balances. Existing identical
        rows are skipped; changed rows are kept as conflicts and do not replace imported history.</p
      >
      <ElForm label-position="top" class="mt-4" @submit.prevent="finalize">
        <ElFormItem label="Review note (required)"
          ><ElInput
            v-model="reason"
            type="textarea"
            :rows="3"
            maxlength="1000"
            show-word-limit
            placeholder="Describe the source and review performed. Minimum 10 characters."
        /></ElFormItem>
        <ElCheckbox v-model="acknowledged"
          >I understand this does not activate any balances.</ElCheckbox
        >
      </ElForm>
      <template #footer
        ><ElButton :disabled="finalizing" @click="confirmOpen = false">Cancel</ElButton
        ><ElButton
          type="primary"
          :loading="finalizing"
          :disabled="!acknowledged || reason.trim().length < 10"
          @click="finalize"
          >Import history</ElButton
        ></template
      >
    </ElDialog>

    <ElDrawer v-model="detailOpen" title="Legacy record" size="min(860px, 100vw)" destroy-on-close>
      <div v-loading="detailLoading">
        <template v-if="detail">
          <h2 class="text-lg font-medium text-g-900"
            >{{ tableLabel(detail.source_table) }} ·
            <span class="font-mono font-semibold tracking-wide">{{
              documentNumber(detail.reference)
            }}</span></h2
          >
          <p class="mt-1 text-sm text-g-500">{{ detail.display_name || detail.source_key }}</p>
          <ElAlert
            class="my-4"
            type="info"
            :closable="false"
            title="Historical evidence — not a live bill or payment"
          />
          <div v-if="detail.issues.length" class="art-card-xs mb-4 p-3"
            ><h3 class="mb-2 text-sm font-medium text-warning">Review required</h3
            ><ul class="list-disc space-y-1 pl-4 text-sm text-g-600"
              ><li v-for="issue in detail.issues" :key="issue">{{ issueLabel(issue) }}</li></ul
            ></div
          >
          <div v-if="billHeader" class="art-card-sm mb-4 p-4">
            <div class="grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
              <div
                ><p class="text-xs text-g-500">Bill #</p
                ><p class="font-mono font-semibold tracking-wide text-g-900">{{
                  documentNumber(billHeader.number)
                }}</p></div
              >
              <div
                ><p class="text-xs text-g-500">Account #</p
                ><p class="text-g-800">{{ billHeader.accountNumber }}</p></div
              >
              <div class="sm:col-span-2"
                ><p class="text-xs text-g-500">Customer</p
                ><p class="text-g-800">{{ billHeader.accountName }}</p></div
              >
              <div
                ><p class="text-xs text-g-500">Vessel</p
                ><p class="text-g-800">{{ billHeader.vessel }}</p></div
              >
              <div
                ><p class="text-xs text-g-500">Voyage #</p
                ><p class="text-g-800">{{ billHeader.voyage }}</p></div
              >
              <div
                ><p class="text-xs text-g-500">Route</p
                ><p class="text-g-800">{{ billHeader.route }}</p></div
              >
              <div
                ><p class="text-xs text-g-500">Type</p
                ><p class="text-g-800">{{ billHeader.type }}</p></div
              >
              <div
                ><p class="text-xs text-g-500">Date of trans.</p
                ><p class="text-g-800">{{ billHeader.date }}</p></div
              >
              <div
                ><p class="text-xs text-g-500">Billing clerk</p
                ><p class="text-g-800">{{ billHeader.clerk }}</p></div
              >
            </div>
          </div>
          <template v-if="detail.reconciliation">
            <h3 class="mb-2 font-medium text-g-900">Stored header vs. detail totals</h3>
            <ElTable :data="reconciliationRows">
              <ElTableColumn prop="label" label="Amount" min-width="125" />
              <ElTableColumn label="Header" min-width="125" align="right"
                ><template #default="{ row }">{{ money(row.header) }}</template></ElTableColumn
              >
              <ElTableColumn label="Lines" min-width="125" align="right"
                ><template #default="{ row }">{{ money(row.lines) }}</template></ElTableColumn
              >
              <ElTableColumn label="Difference" min-width="125" align="right"
                ><template #default="{ row }"
                  ><span
                    :class="
                      row.difference && row.difference !== '0.00' ? 'text-warning' : 'text-g-500'
                    "
                    >{{ money(row.difference) }}</span
                  ></template
                ></ElTableColumn
              >
            </ElTable>
            <p class="mt-2 text-xs text-g-500"
              >Header minus line sum. Original values remain unchanged; no recalculation is
              applied.</p
            >
          </template>
          <div v-if="detail.source_table === 'tbl_bill_trans'" class="my-4">
            <h3 class="mb-3 font-medium text-g-900">Billing items</h3>
            <ElTable
              v-loading="billItemsLoading"
              :data="billItems"
              empty-text="No billing items were stored for this bill number."
            >
              <ElTableColumn label="QTY" min-width="80" align="right"
                ><template #default="{ row }">{{
                  payloadField(row, 'it_qty')
                }}</template></ElTableColumn
              >
              <ElTableColumn label="UNIT" min-width="90"
                ><template #default="{ row }">{{
                  payloadField(row, 'it_unit')
                }}</template></ElTableColumn
              >
              <ElTableColumn label="SERVICE" min-width="200"
                ><template #default="{ row }">{{
                  payloadField(row, 'it_service')
                }}</template></ElTableColumn
              >
              <ElTableColumn label="CARGO" min-width="150"
                ><template #default="{ row }">{{
                  payloadField(row, 'it_cargo') || row.display_name || '—'
                }}</template></ElTableColumn
              >
              <ElTableColumn label="RATE" min-width="100" align="right"
                ><template #default="{ row }">{{
                  money(row.payload?.it_rate)
                }}</template></ElTableColumn
              >
              <ElTableColumn label="DISC." min-width="90" align="right"
                ><template #default="{ row }">{{
                  money(row.payload?.it_disc)
                }}</template></ElTableColumn
              >
              <ElTableColumn label="GROSS" min-width="110" align="right"
                ><template #default="{ row }">{{
                  money(row.payload?.it_gross)
                }}</template></ElTableColumn
              >
            </ElTable>
            <p v-if="billItemTotal > billItems.length" class="mt-2 text-xs text-g-500"
              >Showing {{ billItems.length }} of {{ billItemTotal.toLocaleString() }} stored
              lines.</p
            >
            <div class="mt-3"
              ><ElButton @click="browseDetails">Search all items for this bill #</ElButton></div
            >
          </div>
          <div v-else-if="detail.source_table === 'tbl_or_trans'" class="my-4"
            ><ElButton @click="browseDetails">View receipt allocations</ElButton></div
          >
          <h3 class="mb-3 mt-5 font-medium text-g-900">Original source fields</h3>
          <dl class="divide-y divide-g-200 text-sm">
            <div
              v-for="field in sourceFields"
              :key="field.key"
              class="grid grid-cols-1 gap-1 py-2 sm:grid-cols-2 sm:gap-3"
              ><dt class="text-g-500">{{ field.label }}</dt
              ><dd class="break-words whitespace-pre-wrap text-g-800">{{
                field.value ?? 'Not recorded'
              }}</dd></div
            >
          </dl>
        </template>
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { ElMessage, type UploadFile } from 'element-plus'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'
  import SqlConnectionImport from './SqlConnectionImport.vue'
  import {
    advanceLegacyBatch,
    downloadLegacyToolkit,
    fetchLegacyBatch,
    fetchLegacyBatches,
    fetchLegacyRecord,
    fetchLegacyRecords,
    fetchLegacySchema,
    finalizeLegacyBatch,
    uploadLegacyPackage,
    type LegacyBatch,
    type LegacyRecord
  } from '@/api/legacyImports'

  const activeTab = ref('imports')
  const tables = ref<{ value: string; label: string }[]>([])
  const batches = ref<LegacyBatch[]>([])
  const selected = ref<LegacyBatch | null>(null)
  const batchTotal = ref(0)
  const batchPage = ref(1)
  const loading = ref(false)
  const uploading = ref(false)
  const downloading = ref(false)
  const running = ref(false)
  const pauseRequested = ref(false)
  const confirmOpen = ref(false)
  const finalizing = ref(false)
  const acknowledged = ref(false)
  const reason = ref('')
  let disposed = false
  const progress = computed(() =>
    !selected.value?.expected_rows
      ? 0
      : Math.min(
          99,
          Math.floor((selected.value.processed_rows / selected.value.expected_rows) * 100)
        )
  )
  const table = ref('tbl_bill_trans')
  const search = ref('')
  const source = ref('')
  const from = ref('')
  const to = ref('')
  const reviewOnly = ref(false)
  const batchFilter = ref<number>()
  const batchSource = ref('')
  const disposition = ref('')
  const exactReference = ref('')
  const historyLoading = ref(false)
  const records = ref<LegacyRecord[]>([])
  const recordTotal = ref(0)
  const recordPage = ref(1)
  const detailOpen = ref(false)
  const detailLoading = ref(false)
  const detail = ref<LegacyRecord | null>(null)
  const billItems = ref<LegacyRecord[]>([])
  const billItemsLoading = ref(false)
  const billItemTotal = ref(0)
  let historyRequest = 0
  let detailRequest = 0
  const showingBills = computed(() => table.value === 'tbl_bill_trans')
  const showingBillItems = computed(() => table.value === 'tbl_item_trans')
  const historyScopeLabel = computed(() =>
    search.value.trim() ? 'Preview and imported history' : 'Imported history only'
  )
  const referenceColumnLabel = computed(() => {
    if (showingBills.value || showingBillItems.value) return 'Bill #'
    if (table.value === 'tbl_or_trans' || table.value === 'tbl_orbill_trans') return 'Receipt #'
    return 'Reference'
  })
  const billHeader = computed(() => {
    if (detail.value?.source_table !== 'tbl_bill_trans') return null
    const payload = detail.value.payload ?? {}
    return {
      number: payload.bt_bill_num ?? detail.value.reference,
      accountNumber: payload.bt_acc_num ?? detail.value.account_number ?? '—',
      accountName: payload.bt_account ?? detail.value.display_name ?? '—',
      vessel: payload.bt_vessel || '—',
      voyage: payload.bt_voyage || '—',
      route: routeLabel(payload.bt_route),
      type: typeLabel(payload.bt_type),
      date: (payload.bt_date ?? detail.value.source_date)?.slice(0, 10) || '—',
      clerk: payload.bt_employee || '—'
    }
  })
  const reconciliationRows = computed(() =>
    Object.entries(detail.value?.reconciliation?.amounts ?? {}).map(([key, amounts]) => ({
      label: fieldLabel(key),
      ...amounts
    }))
  )
  const sourceFields = computed(() =>
    Object.entries(detail.value?.payload ?? {})
      .filter(([key]) => !key.endsWith('_id'))
      .map(([key, value]) => ({ key, label: fieldLabel(key), value }))
  )

  function documentNumber(value: string | null | undefined) {
    if (!value) return 'View record'
    return /^\d{1,10}$/.test(value) ? value.padStart(10, '0') : value
  }
  function payloadField(row: LegacyRecord, key: string) {
    return row.payload?.[key] || '—'
  }
  function routeLabel(value: string | null | undefined) {
    if (value === 'D') return 'DOMESTIC'
    if (value === 'F') return 'FOREIGN'
    return value || '—'
  }
  function typeLabel(value: string | null | undefined) {
    if (value === 'I') return 'IN'
    if (value === 'O') return 'OUT'
    return value || '—'
  }
  function money(value: string | null | undefined) {
    if (value === null || value === undefined) return '—'
    const [whole, fraction = '00'] = value.split('.')
    return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(2, '0')}`
  }
  function tableLabel(value: string) {
    return tables.value.find((item) => item.value === value)?.label ?? 'Historical records'
  }
  function batchLabel(value: string) {
    return (
      (
        {
          STAGING: 'Preview in progress',
          READY: 'Ready for review',
          IMPORTED: 'History imported',
          FAILED: 'Package needs correction'
        } as Record<string, string>
      )[value] ?? value
    )
  }
  function batchType(value: string): 'success' | 'warning' | 'danger' | 'info' {
    return value === 'IMPORTED'
      ? 'success'
      : value === 'FAILED'
        ? 'danger'
        : value === 'READY'
          ? 'warning'
          : 'info'
  }
  function legacyStatus(value: string) {
    return value === 'TRUE'
      ? 'Cancelled in legacy'
      : value === 'FALSE'
        ? 'Not cancelled in legacy'
        : 'Unknown legacy status'
  }
  function fieldLabel(key: string) {
    const labels: Record<string, string> = {
      gross_amount: 'Gross',
      ppa_amount: 'PPA share',
      discount_amount: 'Discount',
      net_amount: 'Net',
      tax_amount: 'VAT',
      due_amount: 'Due to SCIPSI',
      bt_total: 'Gross total',
      bt_scipsi: 'Due to SCIPSI',
      bt_acc_num: 'Account number',
      bt_account: 'Customer name',
      bt_bill_num: 'Bill number',
      bt_br: 'Legacy BR flag',
      bt_per: 'Payment status (F/P)',
      bt_ref: 'Receipt reference',
      bt_indivat: 'VAT flag',
      bt_indippa: 'PPA flag',
      bt_indiprint: 'Print flag',
      bt_cynum: 'Control / CY number',
      ac_code: 'Account number',
      ac_name: 'Account name',
      ac_address: 'Address',
      ac_tin: 'TIN',
      ac_busi: 'Business style',
      it_bill_num: 'Bill number',
      it_qty: 'Quantity',
      it_unit: 'Unit',
      it_service: 'Service',
      it_cargo: 'Cargo',
      it_rate: 'Rate',
      it_gross: 'Gross',
      it_disc: 'Discount',
      it_scode: 'Service code',
      it_ccode: 'Cargo code',
      bt_vessel: 'Vessel',
      bt_voyage: 'Voyage number',
      bt_route: 'Route',
      bt_type: 'Type (IN/OUT)',
      bt_employee: 'Billing clerk',
      it_scipsi: 'Due to SCIPSI',
      it_charge: 'Legacy charge (gross + VAT)',
      or_num: 'Receipt number',
      or_acc_num: 'Account number',
      or_accname: 'Customer name',
      or_bill_amount: 'Bill amount',
      or_bill_vat: 'VAT',
      or_htax: 'Withholding',
      or_indiprint: 'Print flag',
      ot_or_no: 'Receipt number',
      ot_bill_no: 'Bill number',
      ot_scipsi: 'Allocated bill amount',
      ot_bill_vat: 'VAT',
      ot_citw: 'Withholding',
      ot_indipartial: 'Partial-payment flag',
      b_num: 'Bill number',
      b_user: 'Assigned user',
      or_user: 'Assigned user',
      s_desc: 'Service description',
      sc_code: 'Service code',
      t_desc: 'Tariff description',
      t_scode: 'Service code',
      t_sname: 'Service name',
      vat: 'VAT setting',
      fppa: 'Foreign PPA setting',
      dppa: 'Domestic PPA setting',
      ctiw: 'Withholding setting',
      myprint: 'Print setting',
      sysdate: 'System date'
    }
    if (labels[key]) return labels[key]
    const words = key
      .replace(/^(bt|it|or|ot|t|s)_/, '')
      .replace(/_/g, ' ')
      .replace(/\bdisc\b/g, 'discount')
      .replace(/\bbstyle\b/g, 'business style')
      .replace(/\bbaddress\b/g, 'buyer address')
    return words.charAt(0).toUpperCase() + words.slice(1)
  }
  function issueLabel(issue: string) {
    const labels: Record<string, string> = {
      HEADER_DETAIL_DIFFERENCE: 'Stored header amounts differ from the sum of their detail rows.',
      LINE_ARITHMETIC_DIFFERENCE:
        'Stored line amounts do not match the standard legacy arithmetic.',
      MISSING_BILL: 'Linked bill is missing from this package.',
      MISSING_RECEIPT: 'Linked receipt is missing from this package.',
      MISSING_ACCOUNT: 'Customer account is missing from this package.',
      MISSING_DETAILS: 'No detail rows were found for this document.',
      DUPLICATE_REFERENCE: 'More than one source row uses this reference.',
      NUMBER_ALREADY_ISSUED: 'Number pool includes a number already used by a document.',
      MISSING_REFERENCE: 'Source reference is missing.',
      UNKNOWN_STATUS: 'Legacy cancellation status is not recognized.',
      INVALID_DATE: 'Legacy date is missing or invalid.',
      DATE_REVIEW: 'Legacy date is before 2000 or in the future.',
      NUMBER_FORMAT_REVIEW: 'Reference is not the expected ten-digit number.'
    }
    return issue.startsWith('INVALID_AMOUNT:')
      ? `${fieldLabel(issue.split(':')[1])}: not a valid exact two-decimal amount; original value retained.`
      : (labels[issue] ?? 'Source value needs review.')
  }
  async function loadBatches() {
    loading.value = true
    try {
      const result = await fetchLegacyBatches(batchPage.value)
      batches.value = result.data
      batchTotal.value = result.total
    } finally {
      loading.value = false
    }
  }
  async function refresh() {
    await loadBatches()
    if (selected.value && !running.value) selected.value = await fetchLegacyBatch(selected.value.id)
    if (activeTab.value === 'history') await loadHistory()
  }
  async function openBatch(batch: LegacyBatch) {
    selected.value = await fetchLegacyBatch(batch.id)
    reason.value = ''
    acknowledged.value = false
  }
  async function openSqlPreview(id: number) {
    if (running.value || uploading.value) return
    selected.value = await fetchLegacyBatch(id)
    reason.value = ''
    acknowledged.value = false
    activeTab.value = 'imports'
    await loadBatches()
    if (selected.value.status === 'STAGING') await runPreview()
  }
  async function downloadToolkit() {
    downloading.value = true
    try {
      await downloadLegacyToolkit()
    } finally {
      downloading.value = false
    }
  }
  async function choosePackage(file: UploadFile) {
    if (!file.raw) return
    if (!file.name.toLowerCase().endsWith('.zip') || file.raw.size > 128 * 1024 * 1024) {
      ElMessage.warning('Choose an exporter ZIP up to 128 MiB.')
      return
    }
    uploading.value = true
    try {
      selected.value = await uploadLegacyPackage(file.raw)
      reason.value = ''
      acknowledged.value = false
      await loadBatches()
    } finally {
      uploading.value = false
    }
    if (selected.value?.status === 'STAGING') await runPreview()
  }
  async function runPreview() {
    if (!selected.value || running.value) return
    running.value = true
    pauseRequested.value = false
    const id = selected.value.id
    try {
      while (!pauseRequested.value && !disposed && selected.value?.status === 'STAGING')
        selected.value = await advanceLegacyBatch(id)
    } catch {
      if (!disposed) selected.value = await fetchLegacyBatch(id)
    } finally {
      running.value = false
      if (!disposed) await loadBatches()
    }
  }
  async function finalize() {
    if (!selected.value || !acknowledged.value || reason.value.trim().length < 10) return
    finalizing.value = true
    try {
      selected.value = await finalizeLegacyBatch(selected.value, reason.value.trim())
      confirmOpen.value = false
      ElMessage.success('History imported. No balances were activated.')
      await loadBatches()
    } finally {
      finalizing.value = false
    }
  }
  function browseBatch(batch: LegacyBatch, filter = '') {
    batchFilter.value = batch.id
    batchSource.value = batch.source_key
    disposition.value = filter
    table.value = 'tbl_bill_trans'
    if (filter === 'CONFLICT') table.value = ''
    search.value = ''
    source.value = ''
    from.value = ''
    to.value = ''
    reviewOnly.value = false
    exactReference.value = ''
    recordPage.value = 1
    if (activeTab.value === 'history') void loadHistory()
    else activeTab.value = 'history'
  }
  function clearBatch() {
    batchFilter.value = undefined
    disposition.value = ''
    exactReference.value = ''
    void searchHistory()
  }
  async function searchHistory() {
    exactReference.value = ''
    recordPage.value = 1
    await loadHistory()
  }
  async function loadHistory() {
    const requestId = ++historyRequest
    historyLoading.value = true
    try {
      const result = await fetchLegacyRecords({
        batch_id: batchFilter.value,
        table: table.value || undefined,
        search: search.value || undefined,
        source_key: source.value || undefined,
        from: from.value || undefined,
        to: to.value || undefined,
        review_only: reviewOnly.value ? 1 : 0,
        disposition: disposition.value || undefined,
        reference: exactReference.value || undefined,
        page: recordPage.value
      })
      if (requestId === historyRequest) {
        records.value = result.data
        recordTotal.value = result.total
      }
    } finally {
      if (requestId === historyRequest) historyLoading.value = false
    }
  }
  async function openRecord(record: LegacyRecord) {
    const requestId = ++detailRequest
    detailOpen.value = true
    detail.value = null
    billItems.value = []
    billItemTotal.value = 0
    detailLoading.value = true
    try {
      const result = await fetchLegacyRecord(record.id)
      if (requestId !== detailRequest) return
      detail.value = result
      if (result.source_table === 'tbl_bill_trans' && result.reference) {
        billItemsLoading.value = true
        try {
          const items = await fetchLegacyRecords({
            batch_id: result.batch_id,
            table: 'tbl_item_trans',
            reference: result.reference,
            page: 1,
            per_page: 100
          })
          if (requestId === detailRequest) {
            billItems.value = items.data
            billItemTotal.value = items.total
          }
        } finally {
          if (requestId === detailRequest) billItemsLoading.value = false
        }
      }
    } finally {
      if (requestId === detailRequest) detailLoading.value = false
    }
  }
  function browseDetails() {
    if (!detail.value) return
    batchFilter.value = detail.value.batch_id
    batchSource.value = detail.value.source_key
    table.value =
      detail.value.source_table === 'tbl_bill_trans' ? 'tbl_item_trans' : 'tbl_orbill_trans'
    exactReference.value = detail.value.reference ?? ''
    search.value = exactReference.value
    disposition.value = ''
    source.value = ''
    from.value = ''
    to.value = ''
    reviewOnly.value = false
    recordPage.value = 1
    detailOpen.value = false
    void loadHistory()
  }
  watch(activeTab, (value) => {
    if (value === 'history') void loadHistory()
  })
  onMounted(async () => {
    tables.value = [{ value: '', label: 'All record types' }, ...(await fetchLegacySchema())]
    await loadBatches()
  })
  onBeforeUnmount(() => {
    disposed = true
    pauseRequested.value = true
  })
</script>

<style scoped>
  @media (max-width: 640px) {
    .history-filters :deep(.el-form-item) {
      display: flex;
      margin-right: 0;
    }
    .history-filters :deep(.el-form-item__content) {
      min-width: 0;
    }
    .history-filters :deep(.el-input),
    .history-filters :deep(.el-select) {
      width: 100% !important;
    }
  }
</style>
