<template>
  <div class="billing-teller-queue page-content max-w-[1600px] mx-auto space-y-5">
    <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-4">
      <div class="min-w-0">
        <div class="flex flex-wrap items-center gap-2">
          <h1 class="text-xl font-semibold text-g-900">Billing Request Queue</h1>
          <ElTag size="small" effect="plain">Teller</ElTag>
        </div>
        <p class="mt-1 text-sm text-g-600 max-w-2xl">
          Claim the oldest portal request, check the files, encode the bill, then post so the
          customer can pay. Counter buyers without uploads stay on Walk-in Billing.
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <ElSelect
          v-if="locations.length > 1"
          v-model="locationId"
          placeholder="Location"
          class="!w-44"
          @change="() => loadQueue()"
        >
          <ElOption
            v-for="loc in locations"
            :key="loc.id"
            :label="loc.name || loc.code || `Location #${loc.id}`"
            :value="loc.id"
          />
        </ElSelect>
        <ElButton @click="$router.push('/walk-in-billing')">Walk-in</ElButton>
        <ElButton :loading="loading" @click="() => loadQueue()">Refresh</ElButton>
        <ElButton
          v-if="!current && canClaimNext"
          type="primary"
          :loading="claiming"
          @click="claimNext"
        >
          Claim next
        </ElButton>
      </div>
    </div>

    <template v-if="!current">
      <div class="grid grid-cols-2 xl:grid-cols-4 gap-3">
        <button type="button" class="art-card-xs p-4 text-left" @click="showListTab('waiting')">
          <p class="text-xs text-g-500">Waiting</p>
          <p class="mt-1 text-2xl font-semibold text-g-900">{{ waitingQueue.length }}</p>
          <p class="mt-1 text-xs text-g-500">Oldest first</p>
        </button>
        <div class="art-card-xs p-4">
          <p class="text-xs text-g-500">In review</p>
          <p class="mt-1 text-2xl font-semibold text-g-900">{{ summary?.in_review_count ?? 0 }}</p>
          <p class="mt-1 text-xs text-g-500">Claimed by tellers</p>
        </div>
        <div class="art-card-xs p-4">
          <p class="text-xs text-g-500">Your claim</p>
          <p class="mt-1 text-2xl font-semibold text-g-900">{{
            summary?.my_active_assignment ? '1' : '0'
          }}</p>
          <p class="mt-1 text-xs text-g-500">Opens automatically</p>
        </div>
        <button type="button" class="art-card-xs p-4 text-left" @click="showListTab('completed')">
          <p class="text-xs text-g-500">Posted</p>
          <p class="mt-1 text-2xl font-semibold text-g-900">{{ completedTracking.length }}</p>
          <p class="mt-1 text-xs text-g-500">Track bills and payment</p>
        </button>
      </div>

      <div
        v-if="nextWaiting"
        class="art-card-sm p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3"
      >
        <div class="min-w-0">
          <p class="text-xs text-g-500">Next to claim</p>
          <p class="mt-0.5 text-base font-medium text-g-900 truncate">
            Ticket #{{ nextWaiting.ticket_number }} · {{ customerName(nextWaiting) }}
          </p>
          <p class="text-xs text-g-500">
            {{ serviceLabel(nextWaiting.service_type) }} · requested
            {{ waitLabel(nextWaiting.timeline?.requested_at || nextWaiting.initial_submitted_at) }}
          </p>
        </div>
        <ElButton type="primary" :loading="claiming" :disabled="!locationId" @click="claimNext">
          Claim this request
        </ElButton>
      </div>
      <div v-else class="art-card-sm px-4 py-3 text-sm text-g-600">
        Nothing is waiting. When a customer submits files, Claim next appears here.
      </div>

      <div class="art-card-sm overflow-hidden" v-loading="loading">
        <div class="px-4 pt-3 pb-2 flex flex-wrap items-center justify-between gap-2">
          <ElRadioGroup v-model="listTab" size="small" @change="lockListTab">
            <ElRadioButton value="waiting">Waiting ({{ waitingQueue.length }})</ElRadioButton>
            <ElRadioButton value="completed">Posted ({{ completedTracking.length }})</ElRadioButton>
          </ElRadioGroup>
        </div>

        <div v-show="listTab === 'waiting'" class="px-2 pb-3">
          <ElEmpty
            v-if="!waitingQueue.length"
            :image-size="72"
            description="No portal requests in line."
          />
          <ElTable v-else :data="waitingQueue" stripe class="w-full">
            <ElTableColumn label="Ticket" width="92">
              <template #default="{ row }">
                <span class="font-mono font-medium">#{{ row.ticket_number }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Customer" min-width="180" show-overflow-tooltip>
              <template #default="{ row }">{{ customerName(row) }}</template>
            </ElTableColumn>
            <ElTableColumn label="Service" min-width="120" show-overflow-tooltip>
              <template #default="{ row }">{{ serviceLabel(row.service_type) }}</template>
            </ElTableColumn>
            <ElTableColumn label="Reference" min-width="140" show-overflow-tooltip>
              <template #default="{ row }">
                <span class="font-mono text-xs">{{ row.transaction_no }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Requested" min-width="130">
              <template #default="{ row }">
                {{ waitLabel(row.timeline?.requested_at || row.initial_submitted_at) }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Now" min-width="220">
              <template #default="{ row }">
                <BillingRequestProgress v-if="row.progress" :progress="row.progress" compact />
              </template>
            </ElTableColumn>
          </ElTable>
          <p v-if="waitingQueue.length" class="px-3 pt-2 text-xs text-g-500">
            Fair queue: Claim next always takes the oldest ticket, not a row you pick.
          </p>
        </div>

        <div v-show="listTab === 'completed'" class="px-2 pb-3">
          <ElEmpty
            v-if="!completedTracking.length"
            :image-size="72"
            description="No posted bills to track yet."
          />
          <ElTable
            v-else
            :data="completedTracking"
            stripe
            class="w-full"
            @row-click="(row) => openTracking(row.id)"
          >
            <ElTableColumn label="Customer" min-width="180" show-overflow-tooltip>
              <template #default="{ row }">{{ customerName(row) }}</template>
            </ElTableColumn>
            <ElTableColumn label="Invoice" min-width="200">
              <template #default="{ row }">
                <div class="flex flex-wrap gap-1">
                  <ElTag
                    v-for="inv in rowInvoices(row)"
                    :key="inv"
                    size="small"
                    class="!font-mono"
                    >{{ inv }}</ElTag
                  >
                </div>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Ticket" width="92">
              <template #default="{ row }">
                <span class="font-mono text-xs">#{{ row.ticket_number }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Payment" width="100">
              <template #default="{ row }">
                <ElTag :type="paymentTag(row).type" size="small">
                  {{ paymentTag(row).label }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Posted" min-width="140">
              <template #default="{ row }">
                {{ formatDateTimeManila(row.timeline?.bill_approved_at) }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Now" min-width="220">
              <template #default="{ row }">
                <BillingRequestProgress v-if="row.progress" :progress="row.progress" compact />
              </template>
            </ElTableColumn>
            <ElTableColumn label="" width="88" align="right" fixed="right">
              <template #default="{ row }">
                <ElButton size="small" @click.stop="openTracking(row.id)">Open</ElButton>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>
      </div>
    </template>

    <div v-else class="space-y-4" v-loading="detailLoading">
      <div class="art-card-sm p-4 flex flex-col xl:flex-row xl:items-center justify-between gap-3">
        <div class="min-w-0 space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-medium text-g-900">{{ customerName(current) }}</span>
            <ElTag size="small">Ticket #{{ current.ticket_number }}</ElTag>
            <ElTag size="small" :type="statusTagType(current.status)">{{
              statusLabel(current.status)
            }}</ElTag>
            <span v-if="waitingQueue.length" class="text-xs text-g-500">
              {{ waitingQueue.length }} still waiting
            </span>
          </div>
          <p class="text-sm text-g-600">{{ workHint }}</p>
          <p class="text-xs text-g-500">
            Ref {{ current.transaction_no }} · requested
            {{
              formatDateTimeManila(current.timeline?.requested_at || current.initial_submitted_at)
            }}
          </p>
        </div>
        <div class="flex flex-wrap gap-2">
          <ElButton
            v-if="isActiveClaim && linkedInvoices.length"
            type="primary"
            :loading="saving"
            @click="finishAndClaimNext"
          >
            Next customer
          </ElButton>
          <ElButton type="success" plain @click="openCustomerChat">Chat</ElButton>
          <ElButton @click="refreshCurrent">Reload</ElButton>
          <ElButton v-if="!isActiveClaim" @click="closeTracking">Back to queue</ElButton>
          <ElDropdown v-if="showClaimMenu" trigger="click" @command="onClaimCommand">
            <ElButton>
              More
              <ArtSvgIcon icon="ri:arrow-down-s-line" class="ml-1" />
            </ElButton>
            <template #dropdown>
              <ElDropdownMenu>
                <ElDropdownItem
                  v-if="current.status === 'IN_REVIEW' || current.status === 'BILLING_IN_PROGRESS'"
                  command="correction"
                >
                  Return for correction
                </ElDropdownItem>
                <ElDropdownItem v-if="!linkedInvoices.length" command="release">
                  Return claim to queue
                </ElDropdownItem>
                <ElDropdownItem
                  v-if="current.status === 'IN_REVIEW' || current.status === 'BILLING_IN_PROGRESS'"
                  command="cancel"
                  divided
                >
                  Cancel request
                </ElDropdownItem>
              </ElDropdownMenu>
            </template>
          </ElDropdown>
        </div>
      </div>

      <BillingRequestProgress v-if="current.progress" :progress="current.progress" />

      <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 items-start">
        <section class="xl:col-span-6 space-y-3 xl:sticky xl:top-4">
          <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
              <h2 class="font-medium text-g-900">Customer files</h2>
              <p class="text-xs text-g-500">{{
                isActiveClaim
                  ? 'Keep this open while encoding the bill.'
                  : 'Files the customer submitted with this request.'
              }}</p>
            </div>
            <ElButton
              v-if="selectedDoc?.private_file_id"
              size="small"
              plain
              @click="expandSelectedDoc"
            >
              Expand
            </ElButton>
          </div>

          <div class="flex flex-wrap gap-2">
            <button
              v-for="doc in current.documents || []"
              :key="doc.id"
              type="button"
              class="max-w-full rounded-lg border px-3 py-1.5 text-left text-sm transition-colors"
              :class="
                selectedDocId === doc.id
                  ? 'border-theme/30 bg-theme/10 text-g-900'
                  : 'border-g-200 bg-g-100/40 hover:border-g-300 text-g-800'
              "
              @click="selectDocument(doc.id)"
            >
              <span class="block truncate font-medium">
                {{ doc.document_type?.name || `Type #${doc.document_type_id}` }}
              </span>
              <span class="block truncate text-[11px] text-g-500">
                {{ doc.private_file?.latest_version?.original_name || 'No file' }}
                ·
                {{ scanLabel(doc.private_file?.latest_version?.scan_status) }}
              </span>
            </button>
            <p v-if="!(current.documents || []).length" class="text-sm text-g-500">
              No documents on this request.
            </p>
          </div>

          <DocumentPreviewPane
            class="min-h-[360px]"
            :active="Boolean(selectedDoc?.private_file_id)"
            :reload-key="selectedDoc?.private_file_id ?? null"
            :title="selectedDocLabel"
            :subtitle="selectedDocMeta"
            :file-name="selectedDocName"
            :mime-type="selectedDocMime"
            :fetcher="selectedDocFetcher"
            min-height="360px"
            empty-text="Select a submitted file to use as billing reference"
          />
        </section>

        <section class="xl:col-span-6 art-card-sm p-4 space-y-4 min-h-[420px]">
          <template v-if="isActiveClaim">
            <p class="text-xs font-medium text-g-500">{{ workStepLabel }}</p>

            <div
              v-if="
                current.status === 'IN_REVIEW' ||
                (current.status === 'BILL_READY' && current.assigned_to_user_id && !draftInvoiceId)
              "
            >
              <ElButton type="primary" class="w-full" :loading="saving" @click="prepareDraft">
                {{
                  linkedInvoices.length ? 'Prepare another invoice draft' : 'Prepare invoice draft'
                }}
              </ElButton>
            </div>

            <div
              v-if="linkedInvoices.length"
              class="rounded-lg border border-g-200 bg-g-100/50 p-3 space-y-2"
            >
              <div>
                <p class="text-xs font-medium text-g-500">Bills on this request</p>
                <p class="text-xs text-g-500">
                  View any bill. Unpaid bills use Correct bill (request review, or open the approved
                  linked draft). Paid bills stay view-only.
                </p>
              </div>
              <div
                v-for="inv in linkedInvoices"
                :key="inv.id"
                class="flex flex-wrap items-center justify-between gap-2 text-sm"
              >
                <div class="min-w-0 flex flex-wrap items-center gap-2">
                  <span class="font-mono font-medium">{{
                    inv.invoice_number || `#${inv.id}`
                  }}</span>
                  <ElTag size="small" effect="plain">{{ inv.total_charge_amount || '—' }}</ElTag>
                  <ElTag
                    size="small"
                    :type="
                      inv.status === 'SUPERSEDED' || inv.settlement_state === 'REPLACED'
                        ? 'info'
                        : invoiceIsUnpaid(inv)
                          ? 'warning'
                          : 'success'
                    "
                    effect="plain"
                  >
                    {{
                      inv.status === 'SUPERSEDED' || inv.settlement_state === 'REPLACED'
                        ? 'Replaced'
                        : invoiceIsUnpaid(inv)
                          ? 'Unpaid'
                          : 'Paid'
                    }}
                  </ElTag>
                </div>
                <div class="flex flex-wrap gap-1">
                  <ElButton size="small" @click="viewLinkedInvoice(inv)">View</ElButton>
                  <ElButton
                    v-if="invoiceIsUnpaid(inv)"
                    size="small"
                    type="primary"
                    plain
                    @click="editLinkedInvoice(inv)"
                  >
                    Correct bill
                  </ElButton>
                </div>
              </div>
            </div>

            <div v-if="showDraftWorkspace" class="space-y-4">
              <div class="flex items-start justify-between gap-3">
                <div>
                  <p class="text-xs text-g-500">Bill #</p>
                  <p class="font-mono text-lg font-semibold tracking-wide text-g-900">{{
                    draftPreview?.invoice_number || `Draft #${draftInvoiceId}`
                  }}</p>
                </div>
                <div class="text-right text-sm text-g-700">
                  <p class="text-xs text-g-500">Account</p>
                  <p>{{ customerName(current) }}</p>
                  <CustomerTaxBadges
                    :customer-id="current?.customer_id"
                    :business-date="businessDate"
                    class="mt-1 justify-end"
                  />
                </div>
              </div>

              <InvoiceShipmentFields
                v-model:vessel-id="vesselId"
                v-model:vessel-name="vesselName"
                v-model:voyage="voyage"
                v-model:notes="shipmentNotes"
                v-model:movement-type="movementType"
                v-model:route-type="routeType"
              />

              <InvoiceBillingItemsGrid
                v-model:lines="draftLines"
                v-model:surcharge-mode="surchargeMode"
                v-model:dangerous-cargo-percent="dangerousCargoPercent"
                :tariffs="tariffs"
                :route-type="routeType"
                :draft="draftPreview"
                :customer-id="current?.customer_id"
                :business-date="businessDate"
                :disabled="!isActiveClaim"
              />

              <div class="flex flex-col gap-2">
                <ElButton
                  type="primary"
                  class="w-full"
                  :loading="saving"
                  @click="postAndMarkReady(false)"
                >
                  Post invoice
                </ElButton>
                <p class="text-xs text-g-500">
                  Post keeps this customer open. When you are finished, use Next customer.
                </p>
              </div>
            </div>

            <ElEmpty
              v-else-if="current.status === 'IN_REVIEW'"
              :image-size="72"
              description="Review the files on the left, then prepare the invoice draft."
            />
          </template>

          <div v-else class="space-y-3">
            <ElAlert
              v-if="current.status === 'BILL_READY'"
              type="success"
              :closable="false"
              show-icon
              :title="trackingTitle"
              description="Tracking only. Claim the next waiting request when you are free."
            />
            <ElAlert
              v-else
              type="info"
              :closable="false"
              show-icon
              :title="`Status: ${statusLabel(current.status)}`"
              description="Read-only. Billing actions stay on your claimed request."
            />
            <div class="rounded-lg border border-g-200 bg-g-100/50 p-3 space-y-2 text-sm">
              <div>
                <p class="text-xs text-g-500">Invoice{{ linkedInvoices.length > 1 ? 's' : '' }}</p>
                <div v-if="!linkedInvoices.length" class="text-g-500">—</div>
                <div
                  v-for="inv in linkedInvoices"
                  :key="inv.id"
                  class="mt-1 flex flex-wrap items-center gap-2"
                >
                  <span class="font-mono font-semibold">{{
                    inv.invoice_number || `#${inv.id}`
                  }}</span>
                  <ElTag
                    size="small"
                    :type="
                      inv.status === 'SUPERSEDED' || inv.settlement_state === 'REPLACED'
                        ? 'info'
                        : invoiceIsUnpaid(inv)
                          ? 'warning'
                          : 'success'
                    "
                    effect="plain"
                  >
                    {{
                      inv.status === 'SUPERSEDED' || inv.settlement_state === 'REPLACED'
                        ? 'Replaced'
                        : invoiceIsUnpaid(inv)
                          ? 'Unpaid'
                          : 'Paid'
                    }}
                  </ElTag>
                  <ElButton size="small" @click="viewLinkedInvoice(inv)">View</ElButton>
                  <ElButton
                    v-if="invoiceIsUnpaid(inv)"
                    size="small"
                    type="primary"
                    plain
                    @click="editLinkedInvoice(inv)"
                  >
                    Correct bill
                  </ElButton>
                  <ElButton
                    v-if="inv.invoice_number"
                    size="small"
                    @click="copyInvoiceNumber(inv.invoice_number)"
                  >
                    Copy
                  </ElButton>
                </div>
              </div>
              <div>Customer: {{ customerName(current) }}</div>
              <div>Files: {{ (current.documents || []).length }}</div>
              <div>Posted: {{ formatDateTimeManila(current.timeline?.bill_approved_at) }}</div>
              <div>Paid: {{ formatDateTimeManila(current.timeline?.paid_at) }}</div>
            </div>
          </div>
        </section>
      </div>
    </div>

    <ElDialog v-model="correctionOpen" title="Return for correction" width="480px">
      <p class="mb-3 text-sm text-g-600">
        The customer keeps the same ticket and queue place after they fix the files. Use Cancel
        request only to stop this ticket.
      </p>
      <ElForm label-position="top">
        <ElFormItem label="Notes for customer" required>
          <ElInput v-model="correctionNotes" type="textarea" :rows="4" maxlength="1000" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="correctionOpen = false">Keep working</ElButton>
        <ElButton type="warning" :loading="saving" @click="submitCorrection"
          >Return to customer</ElButton
        >
      </template>
    </ElDialog>

    <ElDialog v-model="cancelOpen" title="Cancel billing request" width="480px" destroy-on-close>
      <ElAlert
        type="warning"
        :closable="false"
        show-icon
        class="mb-3"
        title="This ends the ticket"
        description="The request leaves the queue and cannot come back as the same ticket. Unposted drafts are abandoned. Posted bills are never rewritten here."
      />
      <ElForm label-position="top">
        <ElFormItem label="Reason for customer" required>
          <ElInput
            v-model="cancelReason"
            type="textarea"
            :rows="4"
            maxlength="500"
            show-word-limit
            placeholder="e.g. Duplicate request / Wrong customer account / Fraudulent documents"
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="cancelOpen = false">Keep request</ElButton>
        <ElButton type="danger" :loading="saving" @click="submitCancel">Cancel request</ElButton>
      </template>
    </ElDialog>

    <ProofViewerModal v-model="proofModalVisible" :title="proofTitle" :fetcher="proofFetcher" />
  </div>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import BillingRequestProgress from '@/components/business/BillingRequestProgress.vue'
  import DocumentPreviewPane from '@/components/business/DocumentPreviewPane.vue'
  import InvoiceShipmentFields from '@/components/business/InvoiceShipmentFields.vue'
  import InvoiceBillingItemsGrid from '@/components/business/InvoiceBillingItemsGrid.vue'
  import CustomerTaxBadges from '@/components/business/CustomerTaxBadges.vue'
  import ProofViewerModal from '@/components/business/ProofViewerModal.vue'
  import { fetchGetUserInfo } from '@/api/auth'
  import {
    cancelTellerBillingRequest,
    claimNextBillingRequest,
    fetchTellerBillingQueue,
    fetchTellerBillingRequest,
    heartbeatBillingRequest,
    markBillingRequestBillReady,
    prepareBillingDraft,
    releaseBillingRequest,
    requestBillingCorrection,
    type BillingRequestDocument,
    type BillingRequestInvoiceRef,
    type BillingRequestItem,
    type BillingRequestStatus,
    type TellerQueueSummary
  } from '@/api/billingRequests'
  import { downloadPrivateFile } from '@/api/documentRequirements'
  import { downloadPortalBillPdf } from '@/api/payments'
  import {
    fetchEffectiveTariffs,
    fetchInvoiceDraft,
    invoiceShipmentPayload,
    emptyBillingLine,
    billingLineFromDraftItem,
    postInvoiceDraft,
    updateInvoiceDraft,
    validateInvoiceShipment,
    type BillingDraftLine,
    type InvoiceDraft,
    type MovementType,
    type RouteType,
    type SurchargeMode
  } from '@/api/invoices'
  import type { Tariff } from '@/api/pricing'
  import { manilaBusinessDate } from '@/utils/date/manilaBusinessDate'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'
  import { onDataRefresh, type DataRefreshPayload } from '@/utils/echo'
  import { mittBus } from '@/utils/sys'

  defineOptions({ name: 'TellerBillingRequestQueue' })

  const router = useRouter()

  const loading = ref(false)
  const detailLoading = ref(false)
  const claiming = ref(false)
  const saving = ref(false)
  const locations = ref<Array<{ id: number; name?: string; code?: string }>>([])
  const locationId = ref<number | null>(null)
  const summary = ref<TellerQueueSummary | null>(null)
  const current = ref<BillingRequestItem | null>(null)
  const tariffs = ref<Tariff[]>([])
  const draftLines = ref<BillingDraftLine[]>([emptyBillingLine()])
  const draftPreview = ref<InvoiceDraft | null>(null)
  const vesselId = ref<number | null>(null)
  const vesselName = ref('')
  const voyage = ref('')
  const shipmentNotes = ref('')
  const movementType = ref<MovementType | ''>('')
  const routeType = ref<RouteType | ''>('')
  const surchargeMode = ref<SurchargeMode>('FUEL')
  const dangerousCargoPercent = ref<string | null>(null)
  const businessDate = computed(() => manilaBusinessDate())
  const correctionOpen = ref(false)
  const correctionNotes = ref('')
  const cancelOpen = ref(false)
  const cancelReason = ref('')
  const proofModalVisible = ref(false)
  const proofTitle = ref('Document')
  const proofFileId = ref<number | null>(null)
  const proofInvoiceId = ref<number | null>(null)
  const selectedDocId = ref<number | null>(null)
  const listTab = ref<'waiting' | 'completed'>('waiting')
  const listTabLocked = ref(false)
  let heartbeatTimer: ReturnType<typeof setInterval> | null = null
  let stopRealtime: (() => void) | null = null
  let recoveryRefreshTimer: ReturnType<typeof setInterval> | null = null

  const waitingQueue = computed(() => summary.value?.waiting_queue || [])
  const completedTracking = computed(() => summary.value?.completed_tracking || [])
  const nextWaiting = computed(() => waitingQueue.value[0] || null)
  const canClaimNext = computed(() => Boolean(locationId.value && waitingQueue.value.length))

  const draftInvoiceId = computed(
    () =>
      current.value?.draft_invoice_id ||
      current.value?.draft_invoice?.id ||
      current.value?.draftInvoice?.id ||
      draftPreview.value?.id ||
      null
  )

  const isActiveClaim = computed(() => {
    const status = current.value?.status
    if (status === 'IN_REVIEW' || status === 'BILLING_IN_PROGRESS') return true
    return status === 'BILL_READY' && !!current.value?.assigned_to_user_id
  })

  const linkedInvoices = computed(() => {
    const list = current.value?.invoices
    if (list?.length) return list
    if (current.value?.invoice) return [current.value.invoice]
    return []
  })

  const showDraftWorkspace = computed(
    () =>
      current.value?.status === 'BILLING_IN_PROGRESS' ||
      Boolean(
        current.value?.draft_invoice_id ||
          current.value?.draftInvoice ||
          current.value?.draft_invoice
      )
  )

  const workHint = computed(() => {
    if (!current.value) return ''
    if (!isActiveClaim.value) {
      return current.value.timeline?.paid_at
        ? 'Customer already paid. Use this view to check the bill number and files.'
        : 'Bill is posted. The customer can pay from My Bills.'
    }
    if (linkedInvoices.value.length && !showDraftWorkspace.value) {
      return 'Bills are posted. Prepare another, or choose Next customer when you are finished.'
    }
    if (showDraftWorkspace.value) {
      return linkedInvoices.value.length
        ? 'Encode this bill, then post. Next customer finishes this customer and takes the oldest waiting ticket.'
        : 'Encode vessel, voyage, type, route and lines. Totals update as you encode, then post.'
    }
    return 'Check the customer files, then prepare an invoice draft.'
  })

  const showClaimMenu = computed(() => {
    if (!isActiveClaim.value || !current.value) return false
    const status = current.value.status
    const canCorrect = status === 'IN_REVIEW' || status === 'BILLING_IN_PROGRESS'
    return canCorrect || linkedInvoices.value.length === 0
  })

  const workStepLabel = computed(() => {
    if (showDraftWorkspace.value) return 'Step 2 of 3 · Encode and post'
    if (linkedInvoices.value.length) return 'Bills posted · Prepare another or next customer'
    return 'Step 1 of 3 · Review files, then prepare a draft'
  })

  const trackingInvoices = computed(() => {
    if (linkedInvoices.value.length) {
      return linkedInvoices.value.map((inv) => inv.invoice_number || `#${inv.id}`)
    }
    if (current.value?.invoice_id) return [`#${current.value.invoice_id}`]
    return []
  })

  const trackingTitle = computed(() => {
    const numbers = trackingInvoices.value
    if (numbers.length > 1) {
      return `Bills posted: ${numbers.join(', ')}. The customer can pay from My Bills.`
    }
    if (numbers.length === 1) {
      return `Bill ready: ${numbers[0]} is visible to the customer for payment.`
    }
    return 'Bill ready. The customer can pay from My Bills.'
  })

  const selectedDoc = computed<BillingRequestDocument | null>(() => {
    const docs = current.value?.documents || []
    return docs.find((d) => d.id === selectedDocId.value) || docs[0] || null
  })

  const selectedDocLabel = computed(
    () =>
      selectedDoc.value?.document_type?.name ||
      (selectedDoc.value ? `Document #${selectedDoc.value.document_type_id}` : 'Document reference')
  )

  const selectedDocName = computed(
    () =>
      selectedDoc.value?.private_file?.latest_version?.original_name ||
      `file-${selectedDoc.value?.private_file_id || 'doc'}`
  )

  const selectedDocMeta = computed(() => {
    if (!selectedDoc.value) return ''
    const scan = selectedDoc.value.private_file?.latest_version?.scan_status || '—'
    return `${selectedDocName.value} · ${scanLabel(scan)}`
  })

  const selectedDocMime = computed(
    () => selectedDoc.value?.private_file?.latest_version?.mime_type || null
  )

  const selectedDocFetcher = computed(() => {
    const fileId = selectedDoc.value?.private_file_id
    if (!fileId) return null
    return async () => {
      const blob = await downloadPrivateFile(fileId)
      return {
        blob,
        filename: selectedDocName.value,
        mimeType: selectedDocMime.value || undefined
      }
    }
  })

  const tariffOptions = computed(() =>
    tariffs.value
      .filter((tariff) => !routeType.value || tariff.route_type === routeType.value)
      .flatMap((tariff) =>
        (tariff.versions || [])
          .filter((v) => v.status === 'effective')
          .map((v) => ({
            versionId: v.id,
            label: `${tariff.tariff_code} — ${tariff.name} (${tariff.route_type}, v${v.version_number}, ${v.rate})`
          }))
      )
  )

  function customerName(row: BillingRequestItem) {
    return row.customer?.name || `Customer #${row.customer_id}`
  }

  function serviceLabel(value?: string) {
    return (value || '—').replaceAll('_', ' ')
  }

  function statusLabel(status: BillingRequestStatus | string) {
    return status.replaceAll('_', ' ')
  }

  function statusTagType(status: BillingRequestStatus | string) {
    if (status === 'BILL_READY') return 'success'
    if (status === 'IN_REVIEW' || status === 'BILLING_IN_PROGRESS') return 'warning'
    if (status === 'CANCELLED') return 'danger'
    return 'info'
  }

  function waitLabel(value?: string | null) {
    if (!value) return '—'
    const date = new Date(value)
    if (Number.isNaN(date.getTime())) return '—'
    const mins = Math.round((Date.now() - date.getTime()) / 60000)
    if (mins < 1) return 'Just now'
    if (mins < 60) return `${mins} min ago`
    const hours = Math.round(mins / 60)
    if (hours < 24) return `${hours}h ago`
    return formatDateTimeManila(value)
  }

  function scanLabel(status?: string | null) {
    if (status === 'CLEAN') return 'Ready'
    if (status === 'PENDING') return 'Scanning'
    if (status === 'QUARANTINED') return 'Blocked'
    return status || '—'
  }

  function rowInvoices(row: BillingRequestItem) {
    const list = row.invoices?.length ? row.invoices : row.invoice ? [row.invoice] : []
    if (list.length) {
      return list.map((inv) => inv.invoice_number || `#${inv.id}`)
    }
    return row.invoice_id ? [`#${row.invoice_id}`] : ['—']
  }

  function paymentTag(row: BillingRequestItem): {
    type: 'success' | 'warning' | 'info'
    label: string
  } {
    if (row.progress?.state === 'complete') return { type: 'success', label: 'Paid' }
    if (row.timeline?.paid_at) return { type: 'warning', label: 'Part paid' }
    return { type: 'info', label: 'Unpaid' }
  }

  function showListTab(tab: 'waiting' | 'completed') {
    listTab.value = tab
    listTabLocked.value = true
  }

  function lockListTab() {
    listTabLocked.value = true
  }

  function preferListTab() {
    if (listTabLocked.value || current.value) return
    listTab.value = waitingQueue.value.length ? 'waiting' : 'completed'
  }

  async function copyInvoiceNumber(number: string) {
    try {
      await navigator.clipboard.writeText(number)
      ElMessage.success(`Copied ${number}`)
    } catch {
      ElMessage.info(number)
    }
  }

  function onClaimCommand(command: string) {
    if (command === 'correction') openCorrection()
    else if (command === 'release') void releaseClaim()
    else if (command === 'cancel') openCancel()
  }

  function resetShipment() {
    vesselId.value = null
    vesselName.value = ''
    voyage.value = ''
    shipmentNotes.value = ''
    movementType.value = ''
    routeType.value = ''
    surchargeMode.value = 'FUEL'
    dangerousCargoPercent.value = null
  }

  function applyShipmentFromDraft(draft: InvoiceDraft) {
    vesselId.value = draft.vessel_id ?? null
    vesselName.value = draft.vessel_name || ''
    voyage.value = draft.voyage || ''
    shipmentNotes.value = draft.notes || ''
    movementType.value = draft.movement_type || ''
    routeType.value = draft.route_type || ''
    const mode = String(draft.surcharge_mode || 'FUEL').toUpperCase()
    surchargeMode.value =
      mode === 'NONE' || mode === 'DANGEROUS_CARGO' || mode === 'FUEL' ? mode : 'FUEL'
    dangerousCargoPercent.value =
      surchargeMode.value === 'DANGEROUS_CARGO' ? draft.dangerous_cargo_percent || null : null
  }

  function currentShipment() {
    return invoiceShipmentPayload({
      vessel_id: vesselId.value,
      voyage: voyage.value,
      notes: shipmentNotes.value,
      movement_type: movementType.value,
      route_type: routeType.value
    })
  }

  watch(routeType, (route, previous) => {
    if (!route || route === previous) return
    if (!tariffOptions.value.length) return
    const allowed = new Set(tariffOptions.value.map((opt) => opt.versionId))
    const dropped = draftLines.value.some(
      (line) => line.tariff_version_id && !allowed.has(line.tariff_version_id)
    )
    if (!dropped) return
    draftLines.value = draftLines.value.map((line) =>
      !line.tariff_version_id || allowed.has(line.tariff_version_id) ? line : emptyBillingLine()
    )
    ElMessage.info('Tariff lines were cleared because they do not match the selected route.')
  })

  function selectDocument(id: number) {
    selectedDocId.value = id
  }

  function ensureSelectedDocument() {
    const docs = current.value?.documents || []
    if (!docs.length) {
      selectedDocId.value = null
      return
    }
    if (!docs.some((d) => d.id === selectedDocId.value)) {
      const clean = docs.find((d) => d.private_file?.latest_version?.scan_status === 'CLEAN')
      selectedDocId.value = (clean || docs[0]).id
    }
  }

  function expandSelectedDoc() {
    if (!selectedDoc.value?.private_file_id) return
    proofFileId.value = selectedDoc.value.private_file_id
    proofInvoiceId.value = null
    proofTitle.value = selectedDocLabel.value
    proofModalVisible.value = true
  }

  function invoiceIsUnpaid(inv: BillingRequestInvoiceRef) {
    if (inv.status === 'SUPERSEDED' || inv.settlement_state === 'REPLACED') return false
    if (inv.settlement_state === 'PAID' || inv.has_posted_settlement === true) return false
    if (inv.settlement_state === 'UNPAID' || inv.has_posted_settlement === false) return true
    // Older payloads without settlement fields: treat posted-but-unknown as unpaid for Edit.
    return inv.status === 'POSTED' || inv.status === 'ISSUED' || !inv.status
  }

  function viewLinkedInvoice(inv: BillingRequestInvoiceRef) {
    proofFileId.value = null
    proofInvoiceId.value = inv.id
    proofTitle.value = inv.invoice_number || `Invoice #${inv.id}`
    proofModalVisible.value = true
  }

  function editLinkedInvoice(inv: BillingRequestInvoiceRef) {
    if (!invoiceIsUnpaid(inv)) {
      ElMessage.info('Paid bills stay view-only. Use Correct bill only while unpaid.')
      return
    }
    const number = inv.invoice_number?.trim()
    if (!number) {
      ElMessage.warning('This bill has no document number yet.')
      return
    }
    // Prefer an APPROVED request (open draft/post path). If none, Document Corrections
    // falls back to the new Correct bill request form for this invoice number.
    void router.push({
      path: '/document-corrections',
      query: {
        document_type: 'INVOICE',
        document_number: number,
        open_approved: '1'
      }
    })
  }

  async function bootstrap() {
    const me = await fetchGetUserInfo()
    setupRealtime(Number((me as any).organization?.id))
    locations.value = ((me as any).locations || []) as Array<{
      id: number
      name?: string
      code?: string
      pivot?: { is_primary?: boolean }
    }>
    const primary =
      locations.value.find((loc) => (loc as any).pivot?.is_primary) || locations.value[0] || null
    locationId.value = primary?.id ?? null
    try {
      tariffs.value = await fetchEffectiveTariffs()
    } catch {
      tariffs.value = []
    }
    await loadQueue()
  }

  function setupRealtime(organizationId: number) {
    if (stopRealtime || !organizationId) return

    stopRealtime = onDataRefresh(organizationId, 'queue', refreshFromQueueChange)
    recoveryRefreshTimer = setInterval(() => {
      if (document.visibilityState === 'visible' && !saving.value && !claiming.value) {
        refreshFromQueueChange()
      }
    }, 30_000)
  }

  let queueRefreshInFlight = false

  async function refreshFromQueueChange(_payload?: DataRefreshPayload) {
    if (saving.value || claiming.value || queueRefreshInFlight) return
    queueRefreshInFlight = true
    try {
      await loadQueue({ silent: true })
    } finally {
      queueRefreshInFlight = false
    }
  }

  function isOpenEditorFor(id: number) {
    return current.value?.id === id && isActiveClaim.value
  }

  async function loadQueue(options?: { silent?: boolean }) {
    if (!locationId.value) return
    const silent = Boolean(options?.silent || current.value)
    if (!silent) loading.value = true
    try {
      summary.value = await fetchTellerBillingQueue({ location_id: locationId.value })
      preferListTab()
      const assignedId = summary.value.my_active_assignment?.id ?? null
      if (assignedId && !current.value) {
        await openAssignment(assignedId)
      } else if (assignedId && isOpenEditorFor(assignedId)) {
        if (!heartbeatTimer) startHeartbeat(assignedId)
      } else if (!assignedId && !current.value) {
        stopHeartbeat()
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load billing queue.')
    } finally {
      loading.value = false
    }
  }

  async function claimNext() {
    if (!locationId.value) return
    claiming.value = true
    try {
      const result = await claimNextBillingRequest({ location_id: locationId.value })
      if (!result.claimed) {
        ElMessage.info(result.message || 'No eligible requests waiting.')
        await loadQueue()
        return
      }
      ElMessage.success(result.message || 'Request claimed.')
      await openAssignment(result.claimed.id)
      await loadQueue()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to claim next request.')
    } finally {
      claiming.value = false
    }
  }

  async function openAssignment(
    id: number,
    options?: { trackingOnly?: boolean; reloadEditor?: boolean }
  ) {
    const keepEditor =
      isOpenEditorFor(id) && options?.reloadEditor !== true && !options?.trackingOnly
    const previousDraftId = draftInvoiceId.value
    if (!keepEditor) detailLoading.value = true
    try {
      const detail = await fetchTellerBillingRequest(id)
      current.value = detail.billing_request
      ensureSelectedDocument()
      const active =
        current.value.status === 'IN_REVIEW' || current.value.status === 'BILLING_IN_PROGRESS'
      if (active && !options?.trackingOnly) {
        startHeartbeat(id)
        if (keepEditor && draftInvoiceId.value === previousDraftId) {
          return
        }
        if (draftInvoiceId.value) {
          await loadDraft(draftInvoiceId.value)
        } else {
          draftPreview.value = null
          draftLines.value = [emptyBillingLine()]
          resetShipment()
        }
      } else {
        stopHeartbeat()
        if (!keepEditor) draftPreview.value = null
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load assignment.')
    } finally {
      detailLoading.value = false
    }
  }

  async function openTracking(id: number) {
    await openAssignment(id, { trackingOnly: true })
  }

  function closeTracking() {
    stopHeartbeat()
    current.value = null
    draftPreview.value = null
    selectedDocId.value = null
  }

  async function refreshCurrent() {
    if (!current.value) return
    await openAssignment(current.value.id, {
      trackingOnly: !(
        current.value.status === 'IN_REVIEW' || current.value.status === 'BILLING_IN_PROGRESS'
      ),
      reloadEditor: true
    })
  }

  function startHeartbeat(id: number) {
    stopHeartbeat()
    heartbeatTimer = setInterval(() => {
      heartbeatBillingRequest(id).catch(() => undefined)
    }, 60_000)
  }

  function stopHeartbeat() {
    if (heartbeatTimer) {
      clearInterval(heartbeatTimer)
      heartbeatTimer = null
    }
  }

  function stopLiveRefresh() {
    stopRealtime?.()
    stopRealtime = null
    if (recoveryRefreshTimer) {
      clearInterval(recoveryRefreshTimer)
      recoveryRefreshTimer = null
    }
  }

  async function loadDraft(invoiceId: number) {
    try {
      const draft = await fetchInvoiceDraft(invoiceId)
      draftPreview.value = draft
      applyShipmentFromDraft(draft)
      if (draft.items?.length) {
        draftLines.value = draft.items.map(billingLineFromDraftItem)
      }
    } catch {
      draftPreview.value = null
    }
  }

  function openCorrection() {
    correctionNotes.value = ''
    correctionOpen.value = true
  }

  async function submitCorrection() {
    if (!current.value || correctionNotes.value.trim().length < 3) {
      ElMessage.warning('Enter correction notes for the customer.')
      return
    }
    saving.value = true
    try {
      await requestBillingCorrection(current.value.id, { notes: correctionNotes.value.trim() })
      ElMessage.success('Request returned to the customer for corrections.')
      correctionOpen.value = false
      current.value = null
      stopHeartbeat()
      await loadQueue()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to request correction.')
    } finally {
      saving.value = false
    }
  }

  async function prepareDraft() {
    if (!current.value) return
    saving.value = true
    try {
      const result = await prepareBillingDraft(current.value.id)
      ElMessage.success(result.message || 'Invoice draft prepared.')
      await openAssignment(current.value.id)
      await loadQueue()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to prepare invoice draft.')
    } finally {
      saving.value = false
    }
  }

  function openCustomerChat() {
    if (!current.value?.id) return
    mittBus.emit('openChat', { billingRequestId: current.value.id })
  }

  async function finishAndClaimNext() {
    if (!current.value || !linkedInvoices.value.length) return
    const draftOpen = showDraftWorkspace.value
    const message = draftOpen
      ? 'Posted bills stay with this customer. The bill you are encoding is not posted and will be discarded. You will then claim the oldest waiting ticket.'
      : 'Posted bills stay with this customer. You will leave this claim and take the oldest waiting ticket.'
    try {
      await ElMessageBox.confirm(message, 'Next customer', {
        confirmButtonText: 'Next customer',
        cancelButtonText: 'Stay on this customer',
        type: 'warning',
        distinguishCancelAndClose: true
      })
    } catch {
      return
    }
    saving.value = true
    try {
      await releaseBillingRequest(current.value.id, 'Teller finished billing this customer')
      current.value = null
      draftPreview.value = null
      selectedDocId.value = null
      stopHeartbeat()
      await loadQueue()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to finish this customer.')
      return
    } finally {
      saving.value = false
    }
    if (waitingQueue.value.length && locationId.value) {
      ElMessage.success('This customer is finished. Opening the next waiting ticket.')
      await claimNext()
      return
    }
    ElMessage.success(
      'This customer is finished. Posted bills stay payable. Nothing else is waiting.'
    )
  }

  async function releaseClaim() {
    if (!current.value) return
    let reason: string | undefined
    try {
      const prompt = await ElMessageBox.prompt(
        'Returns this ticket to Waiting with its original priority. Unposted drafts on this claim are abandoned. Posted bills stay issued and viewable.',
        'Return claim to queue',
        {
          confirmButtonText: 'Return to queue',
          cancelButtonText: 'Keep claim',
          inputPlaceholder: 'e.g. Customer stepped away; taking next ticket',
          inputValue: '',
          distinguishCancelAndClose: true
        }
      )
      reason = prompt.value?.trim() || undefined
    } catch {
      return
    }
    saving.value = true
    try {
      await releaseBillingRequest(current.value.id, reason)
      ElMessage.success('Claim returned to the queue. Claim the next waiting request when ready.')
      current.value = null
      stopHeartbeat()
      await loadQueue()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to release assignment.')
    } finally {
      saving.value = false
    }
  }

  function openCancel() {
    cancelReason.value = ''
    cancelOpen.value = true
  }

  async function submitCancel() {
    if (!current.value) return
    if (cancelReason.value.trim().length < 3) {
      ElMessage.warning('Enter a cancellation reason for the customer.')
      return
    }
    saving.value = true
    try {
      await cancelTellerBillingRequest(current.value.id, cancelReason.value.trim())
      ElMessage.success('Request cancelled. Customer was notified.')
      cancelOpen.value = false
      current.value = null
      stopHeartbeat()
      await loadQueue()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to cancel request.')
    } finally {
      saving.value = false
    }
  }

  function buildItemsPayload() {
    return draftLines.value
      .filter((line) => line.tariff_version_id && Number(line.quantity) > 0)
      .map((line) => ({
        tariff_version_id: line.tariff_version_id!,
        tariff_code: line.tariff_code || undefined,
        service_type: line.service_type || undefined,
        quantity: line.quantity
      }))
  }

  async function postAndMarkReady(complete = true) {
    if (!current.value || !draftInvoiceId.value) return
    const items = buildItemsPayload()
    if (!items.length) {
      ElMessage.warning('Add at least one tariff line before posting.')
      return
    }
    const shipment = currentShipment()
    if (!shipment) {
      ElMessage.warning(
        validateInvoiceShipment({
          vessel_id: vesselId.value,
          voyage: voyage.value,
          notes: shipmentNotes.value,
          movement_type: movementType.value,
          route_type: routeType.value
        }) || 'Complete vessel, voyage, notes, type and route before posting.'
      )
      return
    }
    saving.value = true
    try {
      let draft = draftPreview.value
      if (!draft) {
        draft = await fetchInvoiceDraft(draftInvoiceId.value)
      }
      draft = await updateInvoiceDraft(draftInvoiceId.value, {
        expected_version: draft.lock_version,
        business_date: manilaBusinessDate(),
        ...shipment,
        surcharge_mode: surchargeMode.value,
        dangerous_cargo_percent:
          surchargeMode.value === 'DANGEROUS_CARGO' ? dangerousCargoPercent.value : null,
        items
      })
      const posted = await postInvoiceDraft(draftInvoiceId.value, {
        expected_version: draft.lock_version
      })
      const result = await markBillingRequestBillReady(current.value.id, posted.id, { complete })
      if (complete) {
        ElMessage.success(
          `Invoice ${posted.invoice_number || posted.id} posted. Claim finished; customer can pay on My Bills.`
        )
        stopHeartbeat()
        current.value = null
        draftPreview.value = null
        selectedDocId.value = null
      } else {
        ElMessage.success(
          result.message ||
            `Invoice ${posted.invoice_number || posted.id} posted. Prepare another draft, or use Next customer when finished.`
        )
        draftPreview.value = null
        draftLines.value = [emptyBillingLine()]
        resetShipment()
        await openAssignment(current.value.id)
      }
      await loadQueue()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to post invoice and mark bill ready.')
    } finally {
      saving.value = false
    }
  }

  const proofFetcher = async () => {
    if (proofInvoiceId.value) {
      const blob = await downloadPortalBillPdf(proofInvoiceId.value)
      return {
        blob,
        filename: `${proofTitle.value}.pdf`,
        mimeType: 'application/pdf'
      }
    }
    if (!proofFileId.value) throw new Error('No file selected.')
    const blob = await downloadPrivateFile(proofFileId.value)
    return { blob, filename: proofTitle.value }
  }

  onMounted(bootstrap)
  onBeforeUnmount(() => {
    stopHeartbeat()
    stopLiveRefresh()
  })
</script>
