<template>
  <div class="document-studio-page page-content space-y-4 p-4 sm:p-6">
    <ElCard shadow="never" class="art-card">
      <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex min-w-0 items-start gap-3">
          <ArtSvgIcon icon="ri:layout-masonry-line" class="mt-0.5 shrink-0 text-2xl text-theme" />
          <div class="min-w-0">
            <h1 class="text-lg font-semibold text-g-900">Admin Document Studio</h1>
            <p class="mt-1 text-sm text-g-500">
              Design, preview, validate, and activate invoice, receipt, and non-fiscal operational
              snapshot layouts.
            </p>
          </div>
        </div>
        <ElButton type="primary" :icon="Plus" @click="openCreateDialog">Create Template</ElButton>
      </div>
    </ElCard>

    <ElTabs v-model="activeTab" class="studio-tabs art-card rounded-lg p-4">
      <ElTabPane label="Templates Master" name="templates">
        <!-- Templates Filter -->
        <div class="flex flex-wrap justify-between items-center gap-3 mb-4">
          <ElRadioGroup v-model="filterKind" size="small" class="flex flex-wrap">
            <ElRadioButton label="">All Layouts</ElRadioButton>
            <ElRadioButton label="SERVICE">Service Invoice</ElRadioButton>
            <ElRadioButton label="SERVICE_NSCL">NSCL Invoice</ElRadioButton>
            <ElRadioButton label="PPA">PPA Invoice</ElRadioButton>
            <ElRadioButton label="COLLECTION_RECEIPT">Collection Receipt</ElRadioButton>
            <ElRadioButton label="ACKNOWLEDGEMENT_RECEIPT">Acknowledgement</ElRadioButton>
            <ElRadioButton label="ACCOUNT_STATEMENT">Account Statement</ElRadioButton>
            <ElRadioButton label="YELLOW_INVOICE">Yellow Transmittal</ElRadioButton>
            <ElRadioButton label="WHITE_RECEIPT">White Transmittal</ElRadioButton>
          </ElRadioGroup>
          <ElButton size="small" :icon="Refresh" @click="loadData">Refresh</ElButton>
        </div>

        <!-- Templates Table -->
        <ElTable :data="filteredTemplates" v-loading="loading" stripe style="width: 100%">
          <ElTableColumn prop="code" label="Template Code" min-width="160">
            <template #default="{ row }">
              <span class="font-mono font-bold text-theme">{{ row.code }}</span>
              <ElTag v-if="row.is_system" size="small" type="info" class="ml-2">System</ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="name" label="Display Name" min-width="180" />
          <ElTableColumn prop="document_kind" label="Document Kind" width="160">
            <template #default="{ row }">
              <ElTag :type="getKindTagType(row.document_kind)" size="small">
                {{ row.document_kind }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Latest Version" width="130" align="center">
            <template #default="{ row }">
              <span v-if="row.latest_version" class="font-medium">
                v{{ row.latest_version.version_number }}
                <ElTag
                  :type="getStatusTagType(row.latest_version.status)"
                  size="small"
                  class="ml-1"
                >
                  {{ row.latest_version.status }}
                </ElTag>
              </span>
              <span v-else class="text-g-500">—</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Published Version" width="140" align="center">
            <template #default="{ row }">
              <span v-if="row.published_version" class="font-semibold text-success">
                v{{ row.published_version.version_number }} (Active)
              </span>
              <span v-else class="text-xs text-warning">Unpublished Draft</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Actions" width="260" align="right">
            <template #default="{ row }">
              <ElButton size="small" type="primary" link @click="openEditor(row)">
                Open Studio
              </ElButton>
              <ElButton size="small" type="success" link @click="forkDraft(row)">
                Fork Draft
              </ElButton>
              <ElButton size="small" type="info" link @click="previewTemplate(row)">
                PDF Preview
              </ElButton>
            </template>
          </ElTableColumn>
        </ElTable>
      </ElTabPane>

      <!-- Active Routes & Activations Tab -->
      <ElTabPane label="Active Routes & Activations" name="activations">
        <div class="flex justify-between items-center mb-4">
          <p class="text-sm text-g-500"
            >Scheduled and active layout definitions governing current document issuance.</p
          >
          <ElButton size="small" type="primary" :icon="Plus" @click="openActivateDialog">
            Activate Published Version
          </ElButton>
        </div>

        <ElTable :data="activations" v-loading="loadingActivations" stripe style="width: 100%">
          <ElTableColumn prop="document_kind" label="Document Route" width="180">
            <template #default="{ row }">
              <ElTag :type="getKindTagType(row.document_kind)" size="small">
                {{ row.document_kind }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Active Template Version" min-width="200">
            <template #default="{ row }">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <span class="min-w-0 font-semibold text-g-900">{{
                  row.template_version?.template?.name || 'Template'
                }}</span>
                <span
                  class="shrink-0 rounded border border-g-300 bg-g-200 px-1.5 py-0.5 font-mono text-xs text-g-800"
                  >v{{ row.template_version?.version_number }}</span
                >
              </div>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Location Scope" width="160">
            <template #default="{ row }">
              <span v-if="row.location">{{ row.location.name }}</span>
              <span v-else class="italic text-g-500">All Locations (Global)</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Series Scope" width="160">
            <template #default="{ row }">
              <span v-if="row.series" class="font-mono">{{ row.series.series_code }}</span>
              <span v-else class="italic text-g-500">All Series (Default)</span>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="effective_from" label="Effective From" width="180">
            <template #default="{ row }">
              <span class="text-xs text-g-800">{{ formatDateTime(row.effective_from) }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="is_active" label="State" width="100" align="center">
            <template #default="{ row }">
              <ElTag :type="row.is_active ? 'success' : 'info'" size="small">
                {{ row.is_active ? 'ACTIVE' : 'INACTIVE' }}
              </ElTag>
            </template>
          </ElTableColumn>
        </ElTable>
      </ElTabPane>

      <ElTabPane label="Branding Assets" name="assets">
        <div class="flex justify-between items-center mb-4">
          <div>
            <p class="text-sm font-medium text-g-800">Private branding assets</p>
            <p class="mt-1 text-xs text-g-500"
              >PNG and JPEG only (up to 2 MB). Retiring an asset blocks new draft use, while
              published layouts and issued PDFs retain their historical rendering source.</p
            >
          </div>
          <div class="flex gap-2">
            <ElButton size="small" :icon="Refresh" @click="loadAssets">Refresh</ElButton>
            <ElButton size="small" type="primary" :icon="Plus" @click="openAssetUploadDialog">
              Upload Asset
            </ElButton>
          </div>
        </div>
        <ElTable :data="assets" v-loading="loadingAssets" stripe style="width: 100%">
          <ElTableColumn prop="name" label="Name" min-width="200" />
          <ElTableColumn prop="asset_type" label="Type" width="130">
            <template #default="{ row }"
              ><ElTag size="small">{{ row.asset_type }}</ElTag></template
            >
          </ElTableColumn>
          <ElTableColumn label="Image" width="150">
            <template #default="{ row }">{{ row.width_px }} × {{ row.height_px }} px</template>
          </ElTableColumn>
          <ElTableColumn label="Size" width="115">
            <template #default="{ row }">{{ formatBytes(row.file_size_bytes) }}</template>
          </ElTableColumn>
          <ElTableColumn label="State" width="110">
            <template #default="{ row }">
              <ElTag :type="row.status === 'ACTIVE' ? 'success' : 'info'" size="small">{{
                row.status
              }}</ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Actions" width="160" align="right">
            <template #default="{ row }">
              <ElButton
                v-if="row.status === 'ACTIVE'"
                size="small"
                type="danger"
                link
                @click="retireAsset(row)"
              >
                Retire
              </ElButton>
              <span v-else class="text-xs text-g-500">{{
                row.retirement_reason || 'Retired'
              }}</span>
            </template>
          </ElTableColumn>
        </ElTable>
      </ElTabPane>
    </ElTabs>

    <!-- Studio Layout Editor Drawer -->
    <ElDrawer
      v-model="editorVisible"
      :title="`Document Studio — ${selectedTemplate?.name || ''}`"
      size="96%"
      destroy-on-close
    >
      <div v-if="selectedTemplate" class="studio-editor flex h-full flex-col gap-4">
        <div
          class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-g-300 bg-g-200 p-3"
        >
          <div class="flex flex-wrap items-center gap-3">
            <span class="font-semibold text-g-800">Version:</span>
            <ElSelect
              v-model="selectedVersionId"
              size="small"
              class="w-48"
              @change="onVersionSelected"
            >
              <ElOption
                v-for="v in selectedTemplate.versions"
                :key="v.id"
                :label="`v${v.version_number} [${v.status}]`"
                :value="v.id"
              />
            </ElSelect>
            <ElTag
              v-if="currentVersion"
              :type="getStatusTagType(currentVersion.status)"
              size="small"
            >
              {{ currentVersion.status }}
            </ElTag>
          </div>
          <div class="flex flex-wrap gap-2">
            <ElButton
              v-if="canEditCurrentVersion"
              size="small"
              :icon="Check"
              type="warning"
              :loading="validating"
              @click="runValidation"
            >
              {{
                selectedTemplate && !isFiscalDocument
                  ? 'Validate Structure'
                  : 'Validate Fiscal Blocks'
              }}
            </ElButton>
            <ElButton
              size="small"
              :icon="Document"
              type="info"
              :loading="previewLoading"
              @click="loadPreview"
            >
              Refresh preview
            </ElButton>
            <ElButton
              v-if="canEditCurrentVersion"
              size="small"
              type="primary"
              :loading="saving"
              @click="saveDraftLayout"
            >
              Save Draft
            </ElButton>
            <ElButton
              v-if="currentVersion?.status === 'VALIDATED'"
              size="small"
              type="success"
              :loading="publishing"
              @click="publishCurrentVersion"
            >
              Publish Immutable
            </ElButton>
            <ElButton
              v-if="currentVersion && currentVersion.status !== 'RETIRED'"
              size="small"
              type="danger"
              plain
              :loading="retiring"
              @click="retireCurrentVersion"
            >
              Retire Version
            </ElButton>
          </div>
        </div>

        <!-- Validation Summary Alert -->
        <div
          v-if="validationResult"
          class="rounded-lg border p-3"
          :class="
            validationResult.fiscal_valid
              ? 'border-success/30 bg-success/12 text-success'
              : 'border-error/30 bg-error/12 text-error'
          "
        >
          <div class="flex items-center gap-2 font-semibold">
            <ArtSvgIcon
              :icon="
                validationResult.fiscal_valid ? 'ri:checkbox-circle-fill' : 'ri:error-warning-fill'
              "
              class="text-lg"
            />
            <span>{{
              validationResult.fiscal_valid
                ? isFiscalDocument
                  ? 'BIR Fiscal & Structural Compliance: PASS'
                  : 'Structural Validation: PASS (non-fiscal layout)'
                : 'Layout Validation: ERRORS DETECTED'
            }}</span>
          </div>
          <ul
            v-if="validationResult.errors.length > 0"
            class="mt-2 text-xs list-disc list-inside space-y-1"
          >
            <li v-for="(err, idx) in validationResult.errors" :key="idx">{{ err }}</li>
          </ul>
        </div>

        <!-- Split View: visual layout designer and server PDF preview -->
        <div
          class="grid min-h-[500px] flex-1 grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1.4fr)_minmax(300px,0.7fr)]"
        >
          <div class="flex min-h-0 flex-col rounded-lg border border-g-300 bg-box p-3">
            <LayoutCanvas
              v-model="layoutJsonText"
              :editable="canEditCurrentVersion"
              :document-kind="selectedTemplate?.document_kind || ''"
              :assets="activeAssets"
            />
          </div>

          <!-- Right: Live PDF Preview -->
          <div class="flex flex-col rounded-lg border border-g-300 bg-g-200 p-3">
            <div class="mb-2 flex items-center justify-between gap-2">
              <span class="text-xs font-semibold uppercase tracking-wide text-g-600"
                >Live preview</span
              >
              <span v-if="previewLoading" class="text-xs text-g-500">Updating preview…</span>
              <ElButton
                v-else-if="previewPdfBase64"
                size="small"
                link
                type="primary"
                @click="downloadPdf"
                >Download</ElButton
              >
            </div>
            <p v-if="previewError" class="mb-2 text-xs text-error">{{ previewError }}</p>
            <div
              v-if="previewPdfBase64"
              class="relative flex-1 overflow-hidden rounded-lg border border-g-300 bg-g-100"
            >
              <iframe
                :key="previewFrameKey"
                :src="`data:application/pdf;base64,${previewPdfBase64}`"
                class="h-full w-full"
                style="min-height: 520px"
              />
            </div>
            <div v-else class="flex flex-1 flex-col items-center justify-center text-g-500">
              <ArtSvgIcon icon="ri:file-pdf-line" class="mb-2 text-5xl" />
              <p class="text-xs">
                {{
                  previewLoading
                    ? 'Preparing the server preview…'
                    : 'The preview appears here as you design.'
                }}
              </p>
            </div>
          </div>
        </div>
      </div>
    </ElDrawer>

    <!-- Create Template Dialog -->
    <ElDialog v-model="createDialogVisible" title="Create New Document Template" width="500px">
      <ElForm :model="createForm" label-position="top">
        <ElFormItem label="Document Kind" required>
          <ElSelect v-model="createForm.document_kind" class="w-full">
            <ElOption label="Service Sales Invoice (Regular)" value="SERVICE" />
            <ElOption label="NSCL Cargo Service Sales Invoice" value="SERVICE_NSCL" />
            <ElOption label="PPA Applicable Cargo Sales Invoice" value="PPA" />
            <ElOption label="Collection Receipt / Official Receipt" value="COLLECTION_RECEIPT" />
            <ElOption label="Acknowledgement Receipt (not OR)" value="ACKNOWLEDGEMENT_RECEIPT" />
            <ElOption label="Account Statement (non-fiscal)" value="ACCOUNT_STATEMENT" />
            <ElOption label="Yellow Invoice Transmittal (non-fiscal)" value="YELLOW_INVOICE" />
            <ElOption label="White Receipt Transmittal (non-fiscal)" value="WHITE_RECEIPT" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="Template Code" required>
          <ElInput v-model="createForm.code" placeholder="e.g. SI-STANDARD-2026" />
        </ElFormItem>
        <ElFormItem label="Display Name" required>
          <ElInput
            v-model="createForm.name"
            placeholder="e.g. Standard Port Service Invoice Layout"
          />
        </ElFormItem>
        <ElFormItem label="Description">
          <ElInput
            v-model="createForm.description"
            type="textarea"
            :rows="2"
            placeholder="Layout purpose and applicability notes"
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="createDialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="creating" @click="submitCreateTemplate"
          >Create Template</ElButton
        >
      </template>
    </ElDialog>

    <ElDialog
      v-model="assetUploadDialogVisible"
      title="Upload Private Branding Asset"
      width="500px"
    >
      <ElForm :model="assetUploadForm" label-position="top">
        <ElFormItem label="Asset name" required>
          <ElInput v-model="assetUploadForm.name" placeholder="e.g. Company logo — primary" />
        </ElFormItem>
        <ElFormItem label="Asset type" required>
          <ElSelect v-model="assetUploadForm.asset_type" class="w-full">
            <ElOption label="Logo" value="LOGO" />
            <ElOption label="Watermark" value="WATERMARK" />
            <ElOption label="Signature" value="SIGNATURE" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="PNG or JPEG file" required>
          <input
            ref="assetFileInput"
            type="file"
            accept="image/png,image/jpeg"
            class="block w-full text-sm"
            @change="onAssetFileSelected"
          />
          <p v-if="assetUploadFile" class="mt-2 text-xs text-g-500">{{ assetUploadFile.name }}</p>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="assetUploadDialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="uploadingAsset" @click="submitAssetUpload"
          >Upload Asset</ElButton
        >
      </template>
    </ElDialog>

    <!-- Activate Version Dialog -->
    <ElDialog v-model="activateDialogVisible" title="Activate Published Layout Route" width="500px">
      <ElForm :model="activateForm" label-position="top">
        <ElFormItem label="Select Published Version" required>
          <ElSelect v-model="activateForm.template_version_id" class="w-full">
            <ElOption
              v-for="opt in publishedVersionOptions"
              :key="opt.id"
              :label="`${opt.template_name} (v${opt.version_number}) [${opt.document_kind}]`"
              :value="opt.id"
            />
          </ElSelect>
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="activateDialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="activating" @click="submitActivation"
          >Confirm Activation</ElButton
        >
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, computed, onMounted, onBeforeUnmount, watch } from 'vue'
  import LayoutCanvas from './LayoutCanvas.vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { HttpError } from '@/utils/http/error'
  import { Plus, Refresh, Check, Document } from '@element-plus/icons-vue'
  import {
    fetchTemplates,
    fetchTemplate,
    createTemplate,
    forkDraftVersion,
    updateDraftVersion,
    validateTemplateVersion,
    previewTemplateVersion,
    publishTemplateVersion,
    retireTemplateVersion,
    fetchActivations,
    activateTemplateVersion,
    fetchDocumentTemplateAssets,
    uploadDocumentTemplateAsset,
    retireDocumentTemplateAsset,
    type DocumentTemplate,
    type DocumentTemplateVersion,
    type DocumentTemplateActivation,
    type DocumentTemplateAsset,
    type DocumentTemplateAssetType,
    type DocumentKind
  } from '@/api/documentStudio'

  defineOptions({ name: 'DocumentStudioWorkspace' })

  const activeTab = ref('templates')
  const filterKind = ref('')
  const loading = ref(false)
  const templates = ref<DocumentTemplate[]>([])

  const activations = ref<DocumentTemplateActivation[]>([])
  const loadingActivations = ref(false)
  const assets = ref<DocumentTemplateAsset[]>([])
  const loadingAssets = ref(false)

  // Editor state
  const editorVisible = ref(false)
  const selectedTemplate = ref<DocumentTemplate | null>(null)
  const selectedVersionId = ref<number | null>(null)
  const currentVersion = ref<DocumentTemplateVersion | null>(null)
  const layoutJsonText = ref('')
  const previewPdfBase64 = ref('')
  const previewLoading = ref(false)
  const previewError = ref('')
  const previewFrameKey = ref(0)
  const validating = ref(false)
  const saving = ref(false)
  const publishing = ref(false)
  const retiring = ref(false)
  const validationResult = ref<any>(null)

  const assetUploadDialogVisible = ref(false)
  const uploadingAsset = ref(false)
  const assetUploadFile = ref<File | null>(null)
  const assetFileInput = ref<HTMLInputElement | null>(null)
  const assetUploadForm = ref<{ name: string; asset_type: DocumentTemplateAssetType }>({
    name: '',
    asset_type: 'LOGO'
  })

  // Create dialog
  const createDialogVisible = ref(false)
  const creating = ref(false)
  const createForm = ref<{
    document_kind: DocumentKind
    code: string
    name: string
    description: string
  }>({
    document_kind: 'SERVICE',
    code: '',
    name: '',
    description: ''
  })

  // Activate dialog
  const activateDialogVisible = ref(false)
  const activating = ref(false)
  const activateForm = ref<{
    template_version_id: number | null
  }>({
    template_version_id: null
  })

  const filteredTemplates = computed(() => {
    if (!filterKind.value) return templates.value
    return templates.value.filter((t) => t.document_kind === filterKind.value)
  })

  const isFiscalDocument = computed(() =>
    ['SERVICE', 'SERVICE_NSCL', 'PPA'].includes(selectedTemplate.value?.document_kind || '')
  )
  const canEditCurrentVersion = computed(() =>
    ['DRAFT', 'VALIDATED'].includes(currentVersion.value?.status || '')
  )
  const activeAssets = computed(() => assets.value.filter((asset) => asset.status === 'ACTIVE'))

  let previewTimer: ReturnType<typeof setTimeout> | null = null
  let previewSerial = 0

  function scheduleLivePreview(delay = 700) {
    if (!editorVisible.value) return
    if (previewTimer) clearTimeout(previewTimer)
    previewLoading.value = true
    previewTimer = setTimeout(() => {
      void refreshLivePreview()
    }, delay)
  }

  async function refreshLivePreview() {
    if (!editorVisible.value || !selectedTemplate.value || !currentVersion.value) return
    let layout: Record<string, unknown> | undefined
    if (canEditCurrentVersion.value) {
      try {
        layout = JSON.parse(layoutJsonText.value) as Record<string, unknown>
        previewError.value = ''
      } catch {
        previewError.value = 'The layout needs valid JSON before the preview can update.'
        previewLoading.value = false
        return
      }
    }

    const serial = ++previewSerial
    previewLoading.value = true
    try {
      const preview = await previewTemplateVersion(
        selectedTemplate.value.id,
        currentVersion.value.id,
        layout
      )
      if (serial !== previewSerial || !editorVisible.value) return
      previewPdfBase64.value = preview.pdf_base64
      previewFrameKey.value += 1
      previewError.value = ''
    } catch (err: unknown) {
      if (serial !== previewSerial) return
      previewError.value = err instanceof Error ? err.message : 'The preview could not be rendered.'
    } finally {
      if (serial === previewSerial) previewLoading.value = false
    }
  }

  function loadPreview() {
    scheduleLivePreview(0)
  }

  watch(layoutJsonText, () => {
    if (editorVisible.value) scheduleLivePreview()
  })

  const publishedVersionOptions = computed(() => {
    const list: Array<{
      id: number
      template_name: string
      version_number: number
      document_kind: string
    }> = []
    templates.value.forEach((t) => {
      const fromVersions = t.versions?.filter((v) => v.status === 'PUBLISHED') ?? []
      const published =
        fromVersions.length > 0 ? fromVersions : t.published_version ? [t.published_version] : []
      published.forEach((v) => {
        list.push({
          id: v.id,
          template_name: t.name,
          version_number: v.version_number,
          document_kind: t.document_kind
        })
      })
    })
    return list
  })

  function getKindTagType(kind: string): 'primary' | 'success' | 'warning' | 'info' | 'danger' {
    switch (kind) {
      case 'SERVICE':
        return 'primary'
      case 'SERVICE_NSCL':
        return 'success'
      case 'PPA':
        return 'warning'
      case 'COLLECTION_RECEIPT':
        return 'danger'
      case 'ACKNOWLEDGEMENT_RECEIPT':
        return 'warning'
      case 'ACCOUNT_STATEMENT':
        return 'info'
      case 'YELLOW_INVOICE':
        return 'warning'
      case 'WHITE_RECEIPT':
        return 'info'
      default:
        return 'info'
    }
  }

  function getStatusTagType(status: string): 'primary' | 'success' | 'warning' | 'info' | 'danger' {
    switch (status) {
      case 'PUBLISHED':
        return 'success'
      case 'VALIDATED':
        return 'primary'
      case 'DRAFT':
        return 'warning'
      case 'RETIRED':
        return 'info'
      default:
        return 'info'
    }
  }

  function formatDateTime(str?: string | null) {
    if (!str) return '—'
    return new Date(str).toLocaleString()
  }

  function validationSummaryFromError(err: unknown) {
    if (!(err instanceof HttpError) || !err.data || typeof err.data !== 'object') return null
    const body = err.data as { data?: DocumentTemplateVersion['validation_summary'] }
    return body.data ?? null
  }

  function formatBytes(bytes: number) {
    if (bytes < 1024) return `${bytes} B`
    return `${(bytes / 1024).toFixed(1)} KB`
  }

  async function loadData() {
    loading.value = true
    try {
      templates.value = await fetchTemplates()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to load templates')
    } finally {
      loading.value = false
    }

    loadActivations()
    loadAssets()
  }

  async function loadActivations() {
    loadingActivations.value = true
    try {
      activations.value = await fetchActivations()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to load activations')
    } finally {
      loadingActivations.value = false
    }
  }

  async function loadAssets() {
    loadingAssets.value = true
    try {
      assets.value = await fetchDocumentTemplateAssets()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to load branding assets')
    } finally {
      loadingAssets.value = false
    }
  }

  async function openEditor(template: DocumentTemplate) {
    loading.value = true
    try {
      const templateDetail = await fetchTemplate(template.id)
      selectedTemplate.value = templateDetail
      const versions = templateDetail.versions || []
      if (versions.length > 0) {
        selectedVersionId.value = versions[0].id
        currentVersion.value = versions[0]
        layoutJsonText.value = JSON.stringify(versions[0].layout_definition, null, 2)
        validationResult.value = versions[0].validation_summary
      }
      previewPdfBase64.value = ''
      previewError.value = ''
      editorVisible.value = true
      scheduleLivePreview(200)
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to load template details')
    } finally {
      loading.value = false
    }
  }

  function onVersionSelected(versionId: number) {
    const version = selectedTemplate.value?.versions?.find((v) => v.id === versionId)
    if (version) {
      currentVersion.value = version
      layoutJsonText.value = JSON.stringify(version.layout_definition, null, 2)
      validationResult.value = version.validation_summary
      previewPdfBase64.value = ''
      previewError.value = ''
    }
  }

  async function forkDraft(template: DocumentTemplate) {
    try {
      await forkDraftVersion(template.id)
      ElMessage.success('New draft version created.')
      loadData()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to fork draft version')
    }
  }

  async function saveDraftLayout() {
    if (!selectedTemplate.value || !currentVersion.value) return

    let parsed: any
    try {
      parsed = JSON.parse(layoutJsonText.value)
    } catch {
      ElMessage.error('Invalid JSON syntax.')
      return
    }

    saving.value = true
    try {
      currentVersion.value = await updateDraftVersion(
        selectedTemplate.value.id,
        currentVersion.value.id,
        parsed
      )
      ElMessage.success('Draft layout updated successfully.')
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to save layout')
    } finally {
      saving.value = false
    }
  }

  function openAssetUploadDialog() {
    assetUploadForm.value = { name: '', asset_type: 'LOGO' }
    assetUploadFile.value = null
    if (assetFileInput.value) assetFileInput.value.value = ''
    assetUploadDialogVisible.value = true
  }

  function onAssetFileSelected(event: Event) {
    const input = event.target as HTMLInputElement
    assetUploadFile.value = input.files?.[0] || null
  }

  async function submitAssetUpload() {
    if (!assetUploadForm.value.name.trim() || !assetUploadFile.value) {
      ElMessage.warning('Asset name and PNG or JPEG file are required.')
      return
    }
    uploadingAsset.value = true
    try {
      const formData = new FormData()
      formData.append('name', assetUploadForm.value.name.trim())
      formData.append('asset_type', assetUploadForm.value.asset_type)
      formData.append('file', assetUploadFile.value)
      await uploadDocumentTemplateAsset(formData)
      ElMessage.success('Private branding asset uploaded.')
      assetUploadDialogVisible.value = false
      await loadAssets()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to upload branding asset')
    } finally {
      uploadingAsset.value = false
    }
  }

  async function retireAsset(asset: DocumentTemplateAsset) {
    try {
      const { value } = await ElMessageBox.prompt(
        'Give the reason for retirement. This blocks new draft use but does not alter existing published layouts or issued PDFs.',
        `Retire ${asset.name}`,
        {
          confirmButtonText: 'Retire asset',
          cancelButtonText: 'Cancel',
          inputPlaceholder: 'e.g. Replaced by approved 2026 logo',
          inputValidator: (input) => input.trim().length > 0 || 'A retirement reason is required.'
        }
      )
      await retireDocumentTemplateAsset(asset.id, value.trim())
      ElMessage.success('Branding asset retired.')
      await loadAssets()
    } catch (err: any) {
      if (err === 'cancel' || err === 'close') return
      ElMessage.error(err?.message || 'Failed to retire branding asset')
    }
  }

  async function runValidation() {
    if (!selectedTemplate.value || !currentVersion.value) return

    validating.value = true
    try {
      const summary = await validateTemplateVersion(
        selectedTemplate.value.id,
        currentVersion.value.id
      )
      validationResult.value = summary
      if (summary.fiscal_valid) {
        ElMessage.success('Layout passed fiscal and structural validation.')
        currentVersion.value.status = 'VALIDATED'
      } else {
        ElMessage.warning('Layout has missing fiscal fields or statutory notices.')
      }
    } catch (err: unknown) {
      const summary = validationSummaryFromError(err)
      validationResult.value = summary
      if (!summary) {
        ElMessage.error(err instanceof Error ? err.message : 'Fiscal validation failed.')
      }
    } finally {
      validating.value = false
    }
  }

  async function previewTemplate(template: DocumentTemplate) {
    const versionId = template.published_version?.id || template.latest_version?.id
    if (!versionId) return

    try {
      await openEditor(template)
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to preview template')
    }
  }

  async function publishCurrentVersion() {
    if (!selectedTemplate.value || !currentVersion.value) return

    publishing.value = true
    try {
      currentVersion.value = await publishTemplateVersion(
        selectedTemplate.value.id,
        currentVersion.value.id
      )
      ElMessage.success('Version published immutably. Ready for route activation.')
      loadData()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to publish version')
    } finally {
      publishing.value = false
    }
  }

  async function retireCurrentVersion() {
    if (!selectedTemplate.value || !currentVersion.value) return

    try {
      const { value } = await ElMessageBox.prompt(
        'Give the reason for retirement. A version still routed for issuance must be replaced first. Historical layouts and issued PDFs are preserved.',
        `Retire ${selectedTemplate.value.code} v${currentVersion.value.version_number}`,
        {
          confirmButtonText: 'Retire version',
          cancelButtonText: 'Cancel',
          inputPlaceholder: 'e.g. Superseded by approved layout v2',
          inputValidator: (input) => input.trim().length > 0 || 'A retirement reason is required.'
        }
      )

      retiring.value = true
      currentVersion.value = await retireTemplateVersion(
        selectedTemplate.value.id,
        currentVersion.value.id,
        value.trim()
      )
      ElMessage.success(
        'Template version retired. Historical layout and artifacts remain available.'
      )
      await loadData()
    } catch (err: any) {
      if (err === 'cancel' || err === 'close') return
      ElMessage.error(err?.message || 'Failed to retire template version')
    } finally {
      retiring.value = false
    }
  }

  function downloadPdf() {
    if (!previewPdfBase64.value) return
    const link = document.createElement('a')
    link.href = `data:application/pdf;base64,${previewPdfBase64.value}`
    link.download = `preview-${selectedTemplate.value?.code || 'layout'}.pdf`
    link.click()
  }

  function openCreateDialog() {
    createForm.value = {
      document_kind: 'SERVICE',
      code: '',
      name: '',
      description: ''
    }
    createDialogVisible.value = true
  }

  async function submitCreateTemplate() {
    if (!createForm.value.code || !createForm.value.name) {
      ElMessage.warning('Code and Name are required.')
      return
    }

    creating.value = true
    try {
      await createTemplate(createForm.value)
      ElMessage.success('Document template created.')
      createDialogVisible.value = false
      loadData()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to create template')
    } finally {
      creating.value = false
    }
  }

  function openActivateDialog() {
    activateForm.value.template_version_id = null
    activateDialogVisible.value = true
  }

  async function submitActivation() {
    if (!activateForm.value.template_version_id) {
      ElMessage.warning('Please select a published template version.')
      return
    }

    activating.value = true
    try {
      await activateTemplateVersion({
        template_version_id: activateForm.value.template_version_id
      })
      ElMessage.success('Template version activated for route.')
      activateDialogVisible.value = false
      loadActivations()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to activate template')
    } finally {
      activating.value = false
    }
  }

  onMounted(() => {
    loadData()
  })

  onBeforeUnmount(() => {
    if (previewTimer) clearTimeout(previewTimer)
  })
</script>

<style scoped>
  .document-studio-page {
    min-height: calc(100vh - 120px);
  }

  .studio-tabs {
    background: var(--default-box-color);
    border: 1px solid var(--art-card-border);
  }

  .studio-editor :deep(.el-textarea__inner) {
    background-color: var(--default-box-color);
    color: var(--art-gray-800);
    box-shadow: 0 0 0 1px var(--art-card-border) inset;
  }
</style>
