<template>
  <div class="billing-requests-page max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-sm"
    >
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Request Billing</h1>
          <span
            class="bg-blue-500/20 text-blue-200 border border-blue-400/30 text-xs px-2.5 py-0.5 rounded-full font-medium"
            >Customer Portal</span
          >
        </div>
        <p class="text-slate-300 text-sm max-w-2xl">
          Upload the required picture or PDF for your service, submit to the teller queue, then pay
          once your bill is ready. Already have an invoice number from the counter? Use Claim Bill.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <ElButton
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="$router.push('/claim-bill')"
        >
          Claim Bill
        </ElButton>
        <ElButton
          :loading="loading"
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="loadWorkspace"
        >
          Refresh
        </ElButton>
        <ElButton type="primary" :disabled="!activeCustomerId" @click="openCreateDrawer">
          New request
        </ElButton>
      </div>
    </div>

    <ElAlert
      v-if="!activeCustomerId"
      type="warning"
      :closable="false"
      show-icon
      title="No active customer account is linked to this portal user. Complete registration or ask Admin to link your account before requesting billing."
    />

    <ElCard shadow="never" v-loading="loading">
      <template #header>
        <div class="flex items-center justify-between">
          <span class="font-semibold">Your billing requests</span>
          <ElTag>{{ requests.length }} shown</ElTag>
        </div>
      </template>
      <ElEmpty
        v-if="requests.length === 0"
        description="No billing requests yet. Start one and upload the required documents."
      />
      <ElTable v-else :data="requests" stripe @row-click="openDetail">
        <ElTableColumn prop="transaction_no" label="Reference" min-width="150" />
        <ElTableColumn label="Ticket" width="100">
          <template #default="{ row }">
            {{ row.ticket_number ? `#${row.ticket_number}` : '—' }}
          </template>
        </ElTableColumn>
        <ElTableColumn prop="service_type" label="Service" min-width="130" />
        <ElTableColumn label="Status" min-width="150">
          <template #default="{ row }">
            <ElTag :type="statusTag(row.status)" size="small">{{ labelStatus(row.status) }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Now" min-width="220">
          <template #default="{ row }">
            <BillingRequestProgress v-if="row.progress" :progress="row.progress" compact />
            <span v-else>—</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Requested" min-width="150">
          <template #default="{ row }">{{
            formatDateTimeManila(row.timeline?.requested_at || row.initial_submitted_at)
          }}</template>
        </ElTableColumn>
        <ElTableColumn label="Bill approved" min-width="150">
          <template #default="{ row }">{{
            formatDateTimeManila(row.timeline?.bill_approved_at)
          }}</template>
        </ElTableColumn>
        <ElTableColumn label="Paid" min-width="150">
          <template #default="{ row }">{{ formatDateTimeManila(row.timeline?.paid_at) }}</template>
        </ElTableColumn>
        <ElTableColumn label="Queue" min-width="130">
          <template #default="{ row }">
            <div class="text-sm text-g-800">{{ queueAheadLabel(row) }}</div>
            <div v-if="queueWaitLabel(row)" class="text-xs text-g-500">{{
              queueWaitLabel(row)
            }}</div>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Files" width="80" align="center">
          <template #default="{ row }">
            <ElTag size="small" effect="plain">{{ row.documents?.length || 0 }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Invoices" min-width="200">
          <template #default="{ row }">
            <div v-if="requestInvoices(row).length" class="flex flex-col gap-1">
              <RouterLink
                v-for="inv in requestInvoices(row)"
                :key="inv.id"
                class="text-sky-600 hover:underline text-sm"
                to="/my-bills"
                @click.stop
              >
                {{ inv.invoice_number || `Invoice #${inv.id}` }} — Pay
              </RouterLink>
            </div>
            <span v-else>—</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Actions" width="120" fixed="right">
          <template #default="{ row }">
            <ElButton size="small" @click.stop="openDetail(row)">Open</ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDrawer
      v-model="createOpen"
      title="Request billing"
      size="560px"
      destroy-on-close
      @closed="resetCreateForm"
    >
      <ElForm label-position="top" @submit.prevent="submitNewRequest">
        <ElFormItem label="Service type" required>
          <ElSelect
            v-model="createForm.service_type"
            class="w-full"
            @change="onCreateServiceChange"
          >
            <ElOption label="GENERAL" value="GENERAL" />
            <ElOption label="CARGO_HANDLING" value="CARGO_HANDLING" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem v-if="showLocationPicker" label="Location" required>
          <ElSelect v-model="createForm.location_id" class="w-full" placeholder="Select location">
            <ElOption
              v-for="loc in locations"
              :key="loc.id"
              :label="loc.name || loc.code || `Location #${loc.id}`"
              :value="loc.id"
            />
          </ElSelect>
        </ElFormItem>
        <p v-else-if="singleLocationLabel" class="mb-4 text-xs text-slate-500">
          Branch: {{ singleLocationLabel }}
        </p>
        <ElFormItem label="Notes">
          <ElInput v-model="createForm.notes" type="textarea" :rows="2" maxlength="1000" />
        </ElFormItem>

        <div class="space-y-3 mb-5">
          <div class="flex items-center justify-between">
            <h3 class="font-semibold text-slate-800">Documents</h3>
            <ElTag size="small" effect="plain">Upload before sending</ElTag>
          </div>
          <ElEmpty
            v-if="!createRequirements.length"
            description="No document requirements for this service yet."
          />
          <div
            v-for="req in createRequirements"
            :key="req.id"
            class="border border-slate-200 rounded-xl p-4 space-y-3"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-medium">
                  {{ req.document_type?.name || `Document #${req.document_type_id}` }}
                  <ElTag v-if="req.is_required" size="small" type="danger" class="ml-2"
                    >Required</ElTag
                  >
                </div>
                <p class="text-xs text-slate-500 mt-1">
                  Accepts
                  {{ (req.document_type?.allowed_mime_types || []).join(', ') || 'PDF/JPEG/PNG' }}
                  · max {{ formatMaxUploadSize(req.document_type?.max_file_size_kb) }} each · up to
                  {{ maxFilesFor(req) }} file{{ maxFilesFor(req) === 1 ? '' : 's' }}
                </p>
              </div>
              <ElTag
                size="small"
                :type="
                  pendingCount(req.document_type_id)
                    ? 'success'
                    : req.is_required
                      ? 'warning'
                      : 'info'
                "
              >
                {{
                  pendingCount(req.document_type_id)
                    ? `${pendingCount(req.document_type_id)} ready`
                    : req.is_required
                      ? 'Needed'
                      : 'Optional'
                }}
              </ElTag>
            </div>
            <ElUpload
              :auto-upload="false"
              :show-file-list="false"
              :multiple="maxFilesFor(req) > 1"
              :accept="acceptFromMimeTypes(req.document_type?.allowed_mime_types)"
              :disabled="saving || !canAddPending(req)"
              @change="(file) => onCreateFileChosen(file, req)"
            >
              <ElButton type="primary" plain :disabled="saving || !canAddPending(req)">
                {{
                  pendingCount(req.document_type_id)
                    ? canAddPending(req)
                      ? 'Add another file'
                      : 'Limit reached'
                    : 'Choose picture or PDF'
                }}
              </ElButton>
            </ElUpload>
            <PhotoToPdfPicker
              :allowed-mime-types="req.document_type?.allowed_mime_types"
              :max-file-size-kb="req.document_type?.max_file_size_kb"
              :disabled="saving || !canAddPending(req)"
              @created="(file) => onCreateFileChosen({ raw: file } as UploadFile, req)"
            />
            <ul v-if="pendingCount(req.document_type_id)" class="space-y-1 text-xs text-slate-600">
              <li
                v-for="(file, idx) in pendingFiles[req.document_type_id]"
                :key="`${file.name}-${file.size}-${idx}`"
                class="flex items-center justify-between gap-2"
              >
                <span class="truncate">
                  {{ file.name }} · {{ formatMaxUploadSize(Math.ceil(file.size / 1024)) }}
                </span>
                <ElTooltip content="Remove file" placement="top">
                  <ElButton
                    link
                    type="danger"
                    size="small"
                    :icon="Delete"
                    :disabled="saving"
                    aria-label="Remove file"
                    @click="removePendingFile(req.document_type_id, idx)"
                  />
                </ElTooltip>
              </li>
            </ul>
          </div>
        </div>

        <ElButton
          type="primary"
          :loading="saving"
          class="w-full"
          :disabled="!canSubmitNewRequest"
          @click="submitNewRequest"
        >
          Submit to teller queue
        </ElButton>
        <p class="mt-2 text-xs text-slate-500 text-center">
          Creates the request, uploads your files, and sends it to the teller in one step.
        </p>
      </ElForm>
    </ElDrawer>

    <ElDrawer
      v-model="detailOpen"
      :title="activeRequest?.transaction_no || 'Billing request'"
      size="640px"
      destroy-on-close
    >
      <div v-if="activeRequest" class="space-y-5" v-loading="detailLoading">
        <div class="flex flex-wrap gap-2 items-center">
          <ElTag :type="statusTag(activeRequest.status)">{{
            labelStatus(activeRequest.status)
          }}</ElTag>
          <ElTag v-if="activeRequest.ticket_number" type="warning"
            >Ticket #{{ activeRequest.ticket_number }}</ElTag
          >
          <ElTag
            v-if="activeRequest.status === 'QUEUED' && requestsAhead(activeRequest) != null"
            effect="plain"
          >
            {{
              requestsAhead(activeRequest) === 0
                ? "You're next"
                : `${requestsAhead(activeRequest)} ahead`
            }}
          </ElTag>
          <ElTag v-else-if="isPreparingStatus(activeRequest.status)" effect="plain">
            Preparing your bill
          </ElTag>
          <ElTag v-if="cateringTellerName" type="success" effect="plain">
            Teller: {{ cateringTellerName }}
          </ElTag>
          <ElButton
            v-if="canChatWithTeller"
            size="small"
            type="success"
            plain
            @click="openTellerChat"
          >
            {{ cateringTellerName ? `Chat with ${cateringTellerName}` : 'Chat with teller' }}
          </ElButton>
        </div>

        <div
          v-if="showQueueEstimate(activeRequest)"
          class="rounded-lg border border-g-200 bg-g-100/40 p-3 space-y-1.5"
        >
          <div class="flex items-center gap-2 text-xs uppercase tracking-wider text-g-500">
            <ArtSvgIcon icon="ri:time-line" class="text-sm" />
            Queue estimate
          </div>
          <template v-if="isPreparingStatus(activeRequest.status)">
            <p class="text-sm font-medium text-g-900">A teller is preparing your bill</p>
            <p
              v-if="activeRequest.queue_estimate?.estimated_ready_minutes != null"
              class="text-sm text-g-700"
            >
              About {{ activeRequest.queue_estimate.estimated_ready_minutes }} minutes until your
              bill is ready
            </p>
            <p v-else class="text-sm text-g-600">Estimate not available yet</p>
          </template>
          <template v-else>
            <p class="text-sm font-medium text-g-900">{{ aheadSentence(activeRequest) }}</p>
            <template v-if="activeRequest.queue_estimate?.estimate_basis === 'recent_bills'">
              <p class="text-sm text-g-700">
                Until a teller starts: {{ waitSentence(activeRequest) }}
              </p>
              <p class="text-sm text-g-700">
                Until the bill is ready: about
                {{ activeRequest.queue_estimate.estimated_ready_minutes }} minutes
              </p>
            </template>
            <p v-else class="text-sm text-g-600">Estimate not available yet</p>
          </template>
          <p class="text-xs text-g-500">
            Based on recent bills at this location. This is an estimate, not a promised start or
            finish time.
          </p>
        </div>

        <BillingRequestProgress v-if="activeRequest.progress" :progress="activeRequest.progress" />

        <p
          v-if="activeRequest.correction_notes"
          class="text-sm text-amber-700 bg-amber-50 p-3 rounded"
        >
          Correction needed: {{ activeRequest.correction_notes }}
        </p>

        <div
          v-if="requestInvoices(activeRequest).length"
          class="rounded-xl border border-slate-200 bg-slate-50 dark:bg-slate-800/40 p-3 space-y-2"
        >
          <div class="text-xs uppercase tracking-wider text-slate-500">Invoices</div>
          <div
            v-for="inv in requestInvoices(activeRequest)"
            :key="inv.id"
            class="flex items-center justify-between gap-2 text-sm"
          >
            <RouterLink class="text-sky-600 hover:underline font-mono" to="/my-bills">
              {{ inv.invoice_number || `Invoice #${inv.id}` }}
            </RouterLink>
            <ElTag size="small" effect="plain">{{ inv.total_charge_amount || inv.status }}</ElTag>
          </div>
          <ElButton size="small" type="primary" plain @click="router.push('/my-bills')">
            Pay on My Bills
          </ElButton>
        </div>

        <div v-if="canEditDocuments" class="space-y-4">
          <h3 class="font-semibold text-slate-800">Required documents</h3>
          <div
            v-for="req in detailRequirements"
            :key="req.id"
            class="border border-slate-200 rounded-xl p-4 space-y-3"
          >
            <div class="flex items-start justify-between gap-3">
              <div>
                <div class="font-medium">
                  {{ req.document_type?.name || `Document #${req.document_type_id}` }}
                  <ElTag v-if="req.is_required" size="small" type="danger" class="ml-2"
                    >Required</ElTag
                  >
                </div>
                <p class="text-xs text-slate-500 mt-1">
                  Accepts
                  {{ (req.document_type?.allowed_mime_types || []).join(', ') || 'PDF/JPEG/PNG' }}
                  · max {{ formatMaxUploadSize(req.document_type?.max_file_size_kb) }} each · up to
                  {{ maxFilesFor(req) }} file{{ maxFilesFor(req) === 1 ? '' : 's' }}
                </p>
              </div>
              <ElTag size="small" :type="attachmentTag(req.document_type_id)">
                {{ attachmentLabel(req.document_type_id) }}
              </ElTag>
            </div>
            <ul
              v-if="attachedDocs(req.document_type_id).length"
              class="space-y-1 text-xs text-slate-600"
            >
              <li
                v-for="doc in attachedDocs(req.document_type_id)"
                :key="doc.id"
                class="flex items-center justify-between gap-3 rounded-lg border border-slate-100 bg-slate-50/70 p-2"
              >
                <div class="flex min-w-0 items-center gap-3">
                  <button
                    type="button"
                    class="rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40"
                    aria-label="Open attachment preview"
                    @click="viewDocument(doc)"
                  >
                    <PrivateFileThumbnail
                      :file-id="doc.private_file_id"
                      :file-name="doc.private_file?.latest_version?.original_name"
                      :mime-type="doc.private_file?.latest_version?.mime_type"
                      :reload-key="doc.private_file?.latest_version?.id"
                    />
                  </button>
                  <div class="min-w-0">
                    <p class="truncate font-medium text-slate-700">
                      {{
                        doc.private_file?.latest_version?.original_name ||
                        `File #${doc.private_file_id}`
                      }}
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5">
                      <ElTag size="small" effect="plain">
                        Scan:
                        {{ labelStatus(doc.private_file?.latest_version?.scan_status || '—') }}
                      </ElTag>
                      <ElTag size="small" effect="plain" :type="documentReviewTag(doc)">
                        {{ documentReviewLabel(doc) }}
                      </ElTag>
                    </div>
                  </div>
                </div>
                <div class="flex items-center gap-0.5 shrink-0">
                  <ElTooltip content="View file" placement="top">
                    <ElButton
                      link
                      type="primary"
                      size="small"
                      :icon="View"
                      :disabled="!doc.private_file_id"
                      aria-label="View file"
                      @click="viewDocument(doc)"
                    />
                  </ElTooltip>
                  <ElTooltip content="Remove file" placement="top">
                    <ElButton
                      link
                      type="danger"
                      size="small"
                      :icon="Delete"
                      :disabled="uploading || saving"
                      aria-label="Remove file"
                      @click="removeAttachedDocument(doc)"
                    />
                  </ElTooltip>
                  <ElTooltip
                    v-if="doc.review_status === 'NEEDS_CORRECTION'"
                    content="Replace file"
                    placement="top"
                  >
                    <ElButton
                      link
                      type="warning"
                      size="small"
                      :icon="RefreshRight"
                      :disabled="uploading"
                      aria-label="Replace file"
                      @click="replaceTargetId = doc.id"
                    />
                  </ElTooltip>
                </div>
              </li>
            </ul>
            <ElUpload
              :auto-upload="false"
              :show-file-list="false"
              :multiple="maxFilesFor(req) > 1 && !replaceTargetId"
              :accept="acceptFromMimeTypes(req.document_type?.allowed_mime_types)"
              :disabled="uploading || (!canAddAttached(req) && !replaceTargetId)"
              @change="(file) => onFileChosen(file, req)"
            >
              <ElButton
                :loading="uploading"
                type="primary"
                plain
                :disabled="uploading || (!canAddAttached(req) && !replaceTargetId)"
              >
                {{
                  replaceTargetId
                    ? 'Choose replacement file'
                    : attachedDocs(req.document_type_id).length
                      ? canAddAttached(req)
                        ? 'Add another file'
                        : 'Limit reached'
                      : 'Upload picture or PDF'
                }}
              </ElButton>
            </ElUpload>
            <PhotoToPdfPicker
              :allowed-mime-types="req.document_type?.allowed_mime_types"
              :max-file-size-kb="req.document_type?.max_file_size_kb"
              :disabled="uploading || (!canAddAttached(req) && !replaceTargetId)"
              @created="(file) => onFileChosen({ raw: file } as UploadFile, req)"
            />
            <p v-if="replaceTargetId" class="text-xs text-amber-700">
              Replacing selected file.
              <ElButton link type="info" size="small" @click="replaceTargetId = null"
                >Cancel</ElButton
              >
            </p>
          </div>

          <div class="flex flex-wrap gap-2 pt-2">
            <ElButton v-if="canChatWithTeller" plain type="success" @click="openTellerChat">
              {{ cateringTellerName ? `Chat with ${cateringTellerName}` : 'Chat with teller' }}
            </ElButton>
            <ElButton
              v-if="activeRequest.status === 'DRAFT'"
              type="primary"
              :loading="saving"
              :disabled="!canSubmit"
              @click="submitRequest"
            >
              Submit to teller queue
            </ElButton>
            <ElButton
              v-if="activeRequest.status === 'NEEDS_CORRECTION'"
              type="primary"
              :loading="saving"
              :disabled="!canSubmit"
              @click="resubmitRequest"
            >
              Resubmit with original priority
            </ElButton>
            <ElButton
              v-if="['DRAFT', 'QUEUED', 'NEEDS_CORRECTION'].includes(activeRequest.status)"
              :loading="saving"
              @click="cancelRequest"
            >
              Cancel
            </ElButton>
          </div>
        </div>

        <div v-else class="space-y-3">
          <h3 class="font-semibold text-slate-800">Attached documents</h3>
          <p class="text-xs text-slate-500"
            >Open any file to confirm what you submitted while the teller processes your request.</p
          >
          <ElTable :data="activeRequest.documents || []" size="small">
            <ElTableColumn label="Preview" width="88">
              <template #default="{ row }">
                <button
                  type="button"
                  class="rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/40"
                  aria-label="Open attachment preview"
                  @click="viewDocument(row)"
                >
                  <PrivateFileThumbnail
                    :file-id="row.private_file_id"
                    :file-name="row.private_file?.latest_version?.original_name"
                    :mime-type="row.private_file?.latest_version?.mime_type"
                    :reload-key="row.private_file?.latest_version?.id"
                  />
                </button>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Type" min-width="160">
              <template #default="{ row }">{{
                row.document_type?.name || row.document_type_id
              }}</template>
            </ElTableColumn>
            <ElTableColumn label="File" min-width="160">
              <template #default="{ row }">
                {{ row.private_file?.latest_version?.original_name || '—' }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Scan" width="110">
              <template #default="{ row }">
                {{ row.private_file?.latest_version?.scan_status || '—' }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Review" width="120">
              <template #default="{ row }">
                <ElTag size="small" effect="plain" :type="documentReviewTag(row)">
                  {{ documentReviewLabel(row) }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="" width="56" align="right">
              <template #default="{ row }">
                <ElTooltip content="View file" placement="top">
                  <ElButton
                    size="small"
                    text
                    type="primary"
                    :icon="View"
                    :disabled="!row.private_file_id"
                    aria-label="View file"
                    @click="viewDocument(row)"
                  />
                </ElTooltip>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>

        <ElAlert
          v-if="activeRequest.status === 'BILL_READY'"
          type="success"
          :closable="false"
          show-icon
          title="Your bill is ready. Open My Bills & Payments to select it and pay."
        >
          <ElButton type="success" class="mt-2" @click="goPay">Go to My Bills</ElButton>
        </ElAlert>
      </div>
    </ElDrawer>

    <ProofViewerModal
      v-model="viewerVisible"
      :title="viewerTitle"
      :file-id="viewerFileId"
      :fetcher="viewerFetcher"
    />
  </div>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import { ElMessage, ElMessageBox, type UploadFile } from 'element-plus'
  import { Delete, RefreshRight, View } from '@element-plus/icons-vue'
  import {
    attachBillingRequestDocument,
    cancelBillingRequest,
    createCustomerBillingRequest,
    fetchCustomerBillingRequest,
    fetchCustomerBillingRequests,
    removeBillingRequestDocument,
    resubmitBillingRequest,
    submitBillingRequest,
    type BillingRequestDocument,
    type BillingRequestItem
  } from '@/api/billingRequests'
  import {
    downloadPrivateFile,
    fetchDocumentRequirements,
    replacePrivateFile,
    uploadPrivateFile,
    type DocumentRequirementItem
  } from '@/api/documentRequirements'
  import { fetchGetUserInfo } from '@/api/auth'
  import { fetchPortalProfile } from '@/api/registration'
  import BillingRequestProgress from '@/components/business/BillingRequestProgress.vue'
  import ProofViewerModal from '@/components/business/ProofViewerModal.vue'
  import PrivateFileThumbnail from '@/components/business/PrivateFileThumbnail.vue'
  import PhotoToPdfPicker from '@/components/business/PhotoToPdfPicker.vue'
  import {
    acceptFromMimeTypes,
    formatMaxUploadSize,
    validateUploadAgainstDocumentType
  } from '@/utils/uploads/documentUpload'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'
  import { onUserDataRefresh, type DataRefreshPayload } from '@/utils/echo'
  import { mittBus } from '@/utils/sys'

  defineOptions({ name: 'CustomerBillingRequests' })

  const router = useRouter()
  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const uploading = ref(false)
  const requests = ref<BillingRequestItem[]>([])
  const activeCustomerId = ref<number | null>(null)
  const locations = ref<
    Array<{ id: number; name?: string; code?: string; pivot?: { is_primary?: boolean } }>
  >([])

  const createOpen = ref(false)
  const createForm = reactive({
    service_type: 'GENERAL',
    location_id: null as number | null,
    notes: ''
  })
  const createRequirements = ref<DocumentRequirementItem[]>([])
  const pendingFiles = reactive<Record<number, File[]>>({})
  const replaceTargetId = ref<number | null>(null)

  const detailOpen = ref(false)
  const activeRequest = ref<BillingRequestItem | null>(null)
  const detailRequirements = ref<DocumentRequirementItem[]>([])
  const viewerVisible = ref(false)
  const viewerFileId = ref<number | null>(null)
  const viewerTitle = ref('Attachment')
  let stopRealtime: (() => void) | null = null
  let recoveryRefreshTimer: ReturnType<typeof setInterval> | null = null

  const showLocationPicker = computed(() => locations.value.length > 1)
  const singleLocationLabel = computed(() => {
    if (locations.value.length !== 1) return ''
    const loc = locations.value[0]
    return loc.name || loc.code || `Location #${loc.id}`
  })

  const canSubmitNewRequest = computed(() => {
    const required = createRequirements.value.filter((r) => r.is_required)
    return required.every((req) => pendingCount(req.document_type_id) > 0)
  })

  const canEditDocuments = computed(() =>
    ['DRAFT', 'NEEDS_CORRECTION'].includes(activeRequest.value?.status || '')
  )

  const cateringTellerName = computed(() => {
    const request = activeRequest.value
    if (!request) return ''
    return (
      request.catering_teller?.name ||
      request.assigned_teller?.name ||
      request.assignedTeller?.name ||
      ''
    )
  })

  function requestInvoices(row: BillingRequestItem) {
    if (row.status !== 'BILL_READY') return []
    if (row.invoices?.length) return row.invoices
    if (row.invoice) return [row.invoice]
    if (row.invoice_id) {
      return [
        {
          id: row.invoice_id,
          invoice_number: null,
          status: 'POSTED'
        }
      ]
    }
    return []
  }

  const canChatWithTeller = computed(() => {
    const status = activeRequest.value?.status
    return Boolean(status && !['DRAFT', 'CANCELLED'].includes(status))
  })

  const canSubmit = computed(() => {
    if (!activeRequest.value || detailRequirements.value.length === 0) return false
    const required = detailRequirements.value.filter((r) => r.is_required)
    return required.every((req) =>
      attachedDocs(req.document_type_id).some(
        (doc) => doc.private_file?.latest_version?.scan_status === 'CLEAN'
      )
    )
  })

  function isPreparingStatus(status?: string | null) {
    return status === 'IN_REVIEW' || status === 'BILLING_IN_PROGRESS'
  }

  function showQueueEstimate(row: BillingRequestItem) {
    return row.status === 'QUEUED' || isPreparingStatus(row.status)
  }

  function requestsAhead(row: BillingRequestItem) {
    return row.queue_estimate?.requests_ahead ?? row.queue_position ?? null
  }

  function queueAheadLabel(row: BillingRequestItem) {
    if (isPreparingStatus(row.status)) return 'Preparing'
    if (row.status !== 'QUEUED') return '—'
    const ahead = requestsAhead(row)
    if (ahead == null) return '—'
    if (ahead === 0) return 'Next'
    return ahead === 1 ? '1 ahead' : `${ahead} ahead`
  }

  function queueWaitLabel(row: BillingRequestItem) {
    if (row.status !== 'QUEUED' || row.queue_estimate?.estimate_basis !== 'recent_bills') return ''
    const minutes = row.queue_estimate.estimated_wait_minutes
    if (minutes == null) return ''
    if (minutes <= 0) return 'Starts shortly'
    return `About ${minutes} min`
  }

  function aheadSentence(row: BillingRequestItem) {
    const ahead = requestsAhead(row)
    if (ahead == null) return 'Waiting in the queue'
    if (ahead === 0) return "You're next"
    if (ahead === 1) return '1 request ahead'
    return `${ahead} requests ahead`
  }

  function waitSentence(row: BillingRequestItem) {
    const minutes = row.queue_estimate?.estimated_wait_minutes
    if (minutes == null || minutes <= 0) return "You're next"
    return `about ${minutes} minutes`
  }

  function labelStatus(status: string) {
    return status.replaceAll('_', ' ')
  }

  function statusTag(status: string) {
    if (status === 'BILL_READY') return 'success'
    if (status === 'NEEDS_CORRECTION' || status === 'CANCELLED') return 'danger'
    if (status === 'QUEUED' || status === 'IN_REVIEW' || status === 'BILLING_IN_PROGRESS')
      return 'warning'
    return 'info'
  }

  function documentReviewLabel(document: BillingRequestDocument) {
    if (document.review_status === 'ACCEPTED') return 'Accepted'
    if (document.review_status === 'NEEDS_CORRECTION') return 'Needs correction'
    if (activeRequest.value?.status === 'DRAFT') return 'Not submitted'
    if (activeRequest.value?.status === 'IN_REVIEW') return 'Under teller review'
    return 'Awaiting teller review'
  }

  function documentReviewTag(document: BillingRequestDocument) {
    if (document.review_status === 'ACCEPTED') return 'success'
    if (document.review_status === 'NEEDS_CORRECTION') return 'danger'
    return 'warning'
  }

  function maxFilesFor(req: DocumentRequirementItem) {
    return Math.max(1, Number(req.document_type?.max_files || 1))
  }

  function pendingCount(documentTypeId: number) {
    return pendingFiles[documentTypeId]?.length || 0
  }

  function canAddPending(req: DocumentRequirementItem) {
    return pendingCount(req.document_type_id) < maxFilesFor(req)
  }

  function attachedDocs(documentTypeId: number) {
    return (activeRequest.value?.documents || []).filter(
      (d) => d.document_type_id === documentTypeId
    )
  }

  function canAddAttached(req: DocumentRequirementItem) {
    return attachedDocs(req.document_type_id).length < maxFilesFor(req)
  }

  function attachmentLabel(documentTypeId: number) {
    const docs = attachedDocs(documentTypeId)
    if (!docs.length) return 'Missing'
    const clean = docs.filter((d) => d.private_file?.latest_version?.scan_status === 'CLEAN').length
    return `${docs.length} attached${clean ? ` · ${clean} clean` : ''}`
  }

  function attachmentTag(documentTypeId: number) {
    const docs = attachedDocs(documentTypeId)
    if (!docs.length) return 'info'
    if (docs.some((d) => d.private_file?.latest_version?.scan_status === 'QUARANTINED'))
      return 'danger'
    if (docs.some((d) => d.private_file?.latest_version?.scan_status === 'CLEAN')) return 'success'
    if (docs.some((d) => d.private_file?.latest_version?.scan_status === 'PENDING'))
      return 'warning'
    return 'info'
  }

  async function loadWorkspace() {
    loading.value = true
    try {
      const [profile, me] = await Promise.all([fetchPortalProfile(), fetchGetUserInfo()])
      setupRealtime(Number((me as any).id), Number((me as any).organization?.id))
      const link = (profile as any).customer_links?.find((item: any) => item.is_active)
      activeCustomerId.value = link?.customer_id || null
      locations.value = ((me as any).locations || []) as Array<{
        id: number
        name?: string
        code?: string
        pivot?: { is_primary?: boolean }
      }>
      const primary =
        locations.value.find((loc) => loc.pivot?.is_primary) || locations.value[0] || null
      createForm.location_id = primary?.id ?? null
      if (!activeCustomerId.value) {
        requests.value = []
        return
      }
      const list = await fetchCustomerBillingRequests()
      requests.value = list.data || []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load billing requests.')
    } finally {
      loading.value = false
    }
  }

  function setupRealtime(userId: number, organizationId: number) {
    if (stopRealtime || !userId || !organizationId) return

    stopRealtime = onUserDataRefresh(userId, 'queue', refreshFromQueueChange)
    recoveryRefreshTimer = setInterval(() => {
      if (document.visibilityState === 'visible' && !saving.value && !uploading.value) {
        refreshFromQueueChange()
      }
    }, 30_000)
  }

  async function refreshFromQueueChange(payload?: DataRefreshPayload) {
    if (saving.value || uploading.value) return

    const openRequestId = activeRequest.value?.id ?? null
    await loadWorkspace()

    if (
      openRequestId &&
      (!payload?.entity_id || Number(payload.entity_id) === openRequestId) &&
      detailOpen.value
    ) {
      const row = requests.value.find((request) => request.id === openRequestId)
      if (row) {
        await openDetail(row)
      }
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

  async function loadRequirements() {
    try {
      createRequirements.value = await fetchDocumentRequirements({
        service_type: createForm.service_type,
        location_id: createForm.location_id || undefined
      })
    } catch {
      createRequirements.value = []
    }
  }

  function clearPendingFiles() {
    Object.keys(pendingFiles).forEach((key) => {
      delete pendingFiles[Number(key)]
    })
  }

  function removePendingFile(documentTypeId: number, index: number) {
    const list = pendingFiles[documentTypeId]
    if (!list) return
    list.splice(index, 1)
    if (!list.length) delete pendingFiles[documentTypeId]
  }

  function resetCreateForm() {
    createForm.service_type = 'GENERAL'
    createForm.notes = ''
    clearPendingFiles()
    createRequirements.value = []
  }

  async function onCreateServiceChange() {
    clearPendingFiles()
    await loadRequirements()
  }

  function openCreateDrawer() {
    clearPendingFiles()
    createOpen.value = true
    loadRequirements()
  }

  function onCreateFileChosen(uploadFile: UploadFile, req: DocumentRequirementItem) {
    const file = uploadFile.raw
    if (!file || !req.document_type) return

    const validationError = validateUploadAgainstDocumentType(file, req.document_type)
    if (validationError) {
      ElMessage.error(validationError)
      return
    }

    if (!canAddPending(req)) {
      ElMessage.warning(`At most ${maxFilesFor(req)} file(s) allowed for this document type.`)
      return
    }

    const list = pendingFiles[req.document_type_id] || []
    list.push(file)
    pendingFiles[req.document_type_id] = list
  }

  async function submitNewRequest() {
    if (!activeCustomerId.value) {
      ElMessage.warning('No active customer account is linked.')
      return
    }
    if (!canSubmitNewRequest.value) {
      ElMessage.warning('Attach every required document before submitting.')
      return
    }

    saving.value = true
    try {
      const created = await createCustomerBillingRequest({
        customer_id: activeCustomerId.value,
        location_id: createForm.location_id || undefined,
        service_type: createForm.service_type,
        notes: createForm.notes || undefined
      })
      const request = created.billing_request

      if (request.location_id) {
        createForm.location_id = request.location_id
        if (!locations.value.some((loc) => loc.id === request.location_id)) {
          locations.value = [
            {
              id: request.location_id,
              name: request.location?.name,
              code: request.location?.code
            }
          ]
        }
      }

      for (const req of createRequirements.value) {
        const files = pendingFiles[req.document_type_id] || []
        for (const file of files) {
          const form = new FormData()
          form.append('file', file)
          form.append('document_type_id', String(req.document_type_id))
          if (request.location_id) {
            form.append('location_id', String(request.location_id))
          }
          const uploaded = await uploadPrivateFile(form)
          await attachBillingRequestDocument(request.id, {
            document_type_id: req.document_type_id,
            private_file_id: uploaded.id,
            document_requirement_id: req.id
          })
        }
      }

      const submitted = await submitBillingRequest(request.id)
      ElMessage.success(
        submitted.message ||
          `Submitted to teller queue${
            submitted.billing_request?.ticket_number
              ? ` with Ticket #${submitted.billing_request.ticket_number}`
              : ''
          }.`
      )
      createOpen.value = false
      resetCreateForm()
      await loadWorkspace()
      await openDetail(submitted.billing_request)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to submit billing request.')
      await loadWorkspace()
    } finally {
      saving.value = false
    }
  }

  async function openDetail(row: BillingRequestItem) {
    detailOpen.value = true
    detailLoading.value = true
    try {
      const detail = await fetchCustomerBillingRequest(row.id)
      activeRequest.value = detail.billing_request
      detailRequirements.value = await fetchDocumentRequirements({
        service_type: activeRequest.value.service_type,
        location_id: activeRequest.value.location_id
      })
      if (
        (!detailRequirements.value.length ||
          !detailRequirements.value.some((r) => r.is_required)) &&
        activeRequest.value.requirement_snapshot?.length
      ) {
        // Fallback display from frozen snapshot when admin changed later sets
        detailRequirements.value = activeRequest.value.requirement_snapshot.map((snap: any) => ({
          id: snap.requirement_id,
          organization_id: activeRequest.value!.organization_id,
          service_type: activeRequest.value!.service_type,
          document_type_id: snap.document_type_id,
          is_required: true,
          version: snap.version,
          lock_version: snap.version,
          created_at: '',
          document_type: {
            id: snap.document_type_id,
            organization_id: activeRequest.value!.organization_id,
            code: snap.document_type_code,
            name: snap.document_type_name,
            purpose: 'BILLING_SUPPORT',
            allowed_mime_types: ['application/pdf', 'image/jpeg', 'image/png'],
            max_file_size_kb: 10240,
            max_files: 1,
            is_active: true,
            created_at: ''
          }
        }))
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to open billing request.')
      detailOpen.value = false
    } finally {
      detailLoading.value = false
    }
  }

  async function onFileChosen(uploadFile: UploadFile, req: DocumentRequirementItem) {
    const file = uploadFile.raw
    if (!file || !activeRequest.value || !req.document_type) return

    const validationError = validateUploadAgainstDocumentType(file, req.document_type)
    if (validationError) {
      ElMessage.error(validationError)
      return
    }

    const replaceDoc = replaceTargetId.value
      ? attachedDocs(req.document_type_id).find((d) => d.id === replaceTargetId.value)
      : null

    if (!replaceDoc && !canAddAttached(req)) {
      ElMessage.warning(`At most ${maxFilesFor(req)} file(s) allowed for this document type.`)
      return
    }

    uploading.value = true
    try {
      let privateFileId: number

      if (replaceDoc?.private_file_id && activeRequest.value.status === 'NEEDS_CORRECTION') {
        const form = new FormData()
        form.append('file', file)
        form.append('reason', 'Customer correction resubmission')
        await replacePrivateFile(replaceDoc.private_file_id, form)
        privateFileId = replaceDoc.private_file_id
      } else {
        const form = new FormData()
        form.append('file', file)
        form.append('document_type_id', String(req.document_type_id))
        if (activeRequest.value.location_id) {
          form.append('location_id', String(activeRequest.value.location_id))
        }
        const uploaded = await uploadPrivateFile(form)
        privateFileId = uploaded.id
      }

      await attachBillingRequestDocument(activeRequest.value.id, {
        document_type_id: req.document_type_id,
        private_file_id: privateFileId,
        document_requirement_id: req.id
      })
      replaceTargetId.value = null
      ElMessage.success(replaceDoc ? 'Document replaced.' : 'Document attached.')
      await openDetail(activeRequest.value)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Upload failed.')
    } finally {
      uploading.value = false
    }
  }

  async function submitRequest() {
    if (!activeRequest.value) return
    saving.value = true
    try {
      const result = await submitBillingRequest(activeRequest.value.id)
      ElMessage.success(result.message || 'Submitted to teller queue.')
      await loadWorkspace()
      await openDetail(result.billing_request)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to submit request.')
    } finally {
      saving.value = false
    }
  }

  async function resubmitRequest() {
    if (!activeRequest.value) return
    saving.value = true
    try {
      const result = await resubmitBillingRequest(activeRequest.value.id)
      ElMessage.success(result.message || 'Resubmitted with original priority.')
      await loadWorkspace()
      await openDetail(result.billing_request)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to resubmit request.')
    } finally {
      saving.value = false
    }
  }

  async function cancelRequest() {
    if (!activeRequest.value) return
    try {
      const { value } = await ElMessageBox.prompt('Reason for cancellation', 'Cancel request', {
        confirmButtonText: 'Cancel request',
        cancelButtonText: 'Keep',
        inputPattern: /.{3,}/,
        inputErrorMessage: 'Enter a short reason'
      })
      saving.value = true
      await cancelBillingRequest(activeRequest.value.id, value)
      ElMessage.success('Request cancelled.')
      detailOpen.value = false
      await loadWorkspace()
    } catch (error: any) {
      if (error === 'cancel' || error === 'close') return
      ElMessage.error(error?.message || 'Unable to cancel request.')
    } finally {
      saving.value = false
    }
  }

  function goPay() {
    router.push('/my-bills')
  }

  function openTellerChat() {
    if (!activeRequest.value?.id) return
    mittBus.emit('openChat', { billingRequestId: activeRequest.value.id })
  }

  function viewDocument(doc: BillingRequestDocument) {
    if (!doc.private_file_id) return
    viewerFileId.value = doc.private_file_id
    viewerTitle.value =
      doc.private_file?.latest_version?.original_name ||
      doc.document_type?.name ||
      `Attachment #${doc.private_file_id}`
    viewerVisible.value = true
  }

  async function removeAttachedDocument(doc: BillingRequestDocument) {
    if (!activeRequest.value || !canEditDocuments.value) return
    try {
      await ElMessageBox.confirm(
        `Remove “${doc.private_file?.latest_version?.original_name || `File #${doc.private_file_id}`}” from this request?`,
        'Remove attachment',
        {
          confirmButtonText: 'Remove',
          cancelButtonText: 'Keep',
          type: 'warning'
        }
      )
    } catch {
      return
    }

    saving.value = true
    try {
      const result = await removeBillingRequestDocument(activeRequest.value.id, doc.id)
      activeRequest.value = result.billing_request
      if (replaceTargetId.value === doc.id) {
        replaceTargetId.value = null
      }
      ElMessage.success('Attachment removed.')
      await loadWorkspace()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to remove attachment.')
    } finally {
      saving.value = false
    }
  }

  const viewerFetcher = async () => {
    if (!viewerFileId.value) {
      throw new Error('No attachment selected.')
    }
    const blob = await downloadPrivateFile(viewerFileId.value)
    return { blob, filename: viewerTitle.value }
  }

  onMounted(loadWorkspace)
  onBeforeUnmount(stopLiveRefresh)
</script>
