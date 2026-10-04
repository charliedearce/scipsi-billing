<template>
  <div class="tax-evidence-customer max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-sm"
    >
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Tax Evidence</h1>
          <span
            class="bg-emerald-500/20 text-emerald-100 border border-emerald-400/30 text-xs px-2.5 py-0.5 rounded-full font-medium"
            >Customer Portal</span
          >
        </div>
        <p class="text-slate-300 text-sm max-w-2xl">
          Upload BIR Form 2307 once for the period, or a Non-VAT / zero-rated ruling for Admin
          approval. Copy the tax withheld on the certificate. Approved certificates apply when you
          pay and do not change posted invoices.
        </p>
      </div>
      <ElButton
        :loading="loading"
        plain
        class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
        @click="loadAll"
      >
        Refresh
      </ElButton>
    </div>

    <ElAlert
      v-if="!activeCustomerId"
      type="warning"
      :closable="false"
      show-icon
      title="No active customer account is linked to this portal user. Complete registration or ask Admin to link your account before submitting tax evidence."
    />

    <ElAlert
      v-if="expiredAlerts.length"
      type="error"
      :closable="false"
      show-icon
      class="!items-start"
    >
      <template #title>Tax evidence expired — file a renewal</template>
      <div class="space-y-2 text-sm">
        <p>
          One or more approved documents have expired. Submit updated tax evidence with a new period
          or validity so Admin can re-approve. Posted invoices and receipts are not changed.
        </p>
        <ul class="list-disc pl-5 space-y-1">
          <li v-for="item in expiredAlerts" :key="item.key">{{ item.label }}</li>
        </ul>
        <div class="flex flex-wrap gap-2 pt-1">
          <ElButton
            size="small"
            type="danger"
            :disabled="!activeCustomerId"
            @click="renewWithholding"
          >
            Renew BIR 2307
          </ElButton>
          <ElButton
            size="small"
            type="danger"
            plain
            :disabled="!activeCustomerId"
            @click="renewExemption"
          >
            Renew exemption / ruling
          </ElButton>
        </div>
      </div>
    </ElAlert>

    <ElAlert
      v-else-if="approachingAlerts.length"
      type="warning"
      :closable="false"
      show-icon
      class="!items-start"
    >
      <template #title>Tax evidence expires in less than 30 days</template>
      <div class="space-y-2 text-sm">
        <p>
          Update your files now so validity can be refreshed before expiry. Use the same Tax
          Evidence submission flow — do not wait until the certificate lapses.
        </p>
        <ul class="list-disc pl-5 space-y-1">
          <li v-for="item in approachingAlerts" :key="item.key">{{ item.label }}</li>
        </ul>
        <div class="flex flex-wrap gap-2 pt-1">
          <ElButton
            size="small"
            type="warning"
            :disabled="!activeCustomerId"
            @click="renewWithholding"
          >
            Update withholding file
          </ElButton>
          <ElButton
            size="small"
            type="warning"
            plain
            :disabled="!activeCustomerId"
            @click="renewExemption"
          >
            Update exemption file
          </ElButton>
        </div>
      </div>
    </ElAlert>

    <ElTabs v-model="activeTab" @tab-change="onTabChange">
      <ElTabPane label="Withholding (BIR 2307)" name="withholding">
        <ElCard shadow="never" class="!rounded-xl" v-loading="loading">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
              One approved certificate can cover the bills in its period. Copy the tax withheld
              amount from the form. Admin reviews it before it can be used at payment.
            </p>
            <ElButton type="primary" :disabled="!activeCustomerId" @click="openWithholdingForm">
              Submit 2307
            </ElButton>
          </div>
          <ElTable :data="withholdingRows" stripe empty-text="No withholding certificates yet.">
            <ElTableColumn prop="certificate_no" label="Certificate no." min-width="140" />
            <ElTableColumn label="Period" min-width="200">
              <template #default="{ row }">{{
                formatDateRangeManila(row.period_from, row.period_to)
              }}</template>
            </ElTableColumn>
            <ElTableColumn prop="atc_code" label="ATC" width="90" />
            <ElTableColumn label="Certified" min-width="120">
              <template #default="{ row }">{{ money(row.certified_amount) }}</template>
            </ElTableColumn>
            <ElTableColumn label="Remaining" min-width="120">
              <template #default="{ row }">{{ money(row.remaining_amount) }}</template>
            </ElTableColumn>
            <ElTableColumn label="Status" min-width="140">
              <template #default="{ row }">
                <ElTag size="small" :type="statusTag(row.status)">{{ row.status }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Submitted" min-width="150">
              <template #default="{ row }">{{ formatDateTimeManila(row.created_at) }}</template>
            </ElTableColumn>
            <ElTableColumn label="" width="64" fixed="right">
              <template #default="{ row }">
                <ElTooltip content="View file" placement="top">
                  <ElButton
                    link
                    type="primary"
                    :icon="View"
                    :disabled="!row.private_file_id"
                    aria-label="View file"
                    @click="previewFile(row)"
                  />
                </ElTooltip>
              </template>
            </ElTableColumn>
          </ElTable>
        </ElCard>
      </ElTabPane>

      <ElTabPane label="Non-VAT / Zero-rated" name="exemptions">
        <ElCard shadow="never" class="!rounded-xl" v-loading="loading">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
              Non-VAT (VAT exempt) and zero-rated rulings are approved by Admin before they can
              override normally VATABLE tariffs on new drafts.
            </p>
            <ElButton type="primary" :disabled="!activeCustomerId" @click="openExemptionForm">
              Submit ruling
            </ElButton>
          </div>
          <ElTable :data="exemptionRows" stripe empty-text="No tax exemption rulings yet.">
            <ElTableColumn label="Type" min-width="130">
              <template #default="{ row }">
                <ElTag size="small" effect="plain">{{ exemptionLabel(row.exemption_type) }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="ruling_or_cert_no" label="Ruling / cert no." min-width="150" />
            <ElTableColumn
              prop="legal_basis"
              label="Legal basis"
              min-width="180"
              show-overflow-tooltip
            />
            <ElTableColumn label="Validity" min-width="200">
              <template #default="{ row }">{{
                formatDateRangeManila(row.valid_from, row.valid_to)
              }}</template>
            </ElTableColumn>
            <ElTableColumn label="Coverage" min-width="140">
              <template #default="{ row }">{{
                (row.covered_services || []).join(', ') || 'ALL'
              }}</template>
            </ElTableColumn>
            <ElTableColumn label="Status" min-width="140">
              <template #default="{ row }">
                <ElTag size="small" :type="statusTag(row.status)">{{ row.status }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Submitted" min-width="150">
              <template #default="{ row }">{{ formatDateTimeManila(row.created_at) }}</template>
            </ElTableColumn>
            <ElTableColumn label="" width="64" fixed="right">
              <template #default="{ row }">
                <ElTooltip content="View file" placement="top">
                  <ElButton
                    link
                    type="primary"
                    :icon="View"
                    :disabled="!row.private_file_id"
                    aria-label="View file"
                    @click="previewFile(row)"
                  />
                </ElTooltip>
              </template>
            </ElTableColumn>
          </ElTable>
        </ElCard>
      </ElTabPane>
    </ElTabs>

    <ElDrawer
      v-model="withholdingOpen"
      title="Submit BIR Form 2307"
      size="560px"
      destroy-on-close
      @closed="resetWithholding"
    >
      <ElForm label-position="top" @submit.prevent="submitWithholding">
        <ElFormItem label="Certificate number" required>
          <ElInput v-model="withholdingForm.certificate_no" maxlength="64" />
        </ElFormItem>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <ElFormItem label="Payor TIN" required>
            <ElInput v-model="withholdingForm.payor_tin" maxlength="32" />
          </ElFormItem>
          <ElFormItem label="Payor name" required>
            <ElInput v-model="withholdingForm.payor_name" maxlength="255" />
          </ElFormItem>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <ElFormItem label="Period from" required>
            <ElDatePicker
              v-model="withholdingForm.period_from"
              type="date"
              value-format="YYYY-MM-DD"
              class="!w-full"
            />
          </ElFormItem>
          <ElFormItem label="Period to" required>
            <ElDatePicker
              v-model="withholdingForm.period_to"
              type="date"
              value-format="YYYY-MM-DD"
              class="!w-full"
            />
          </ElFormItem>
        </div>
        <ElFormItem label="ATC code" required>
          <ElInput v-model="withholdingForm.atc_code" maxlength="16" placeholder="e.g. WC010" />
        </ElFormItem>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <ElFormItem label="Income payment on the 2307" required>
            <ElInput v-model="withholdingForm.income_payment_base" />
          </ElFormItem>
          <ElFormItem label="Tax withheld on the 2307" required>
            <ElInput v-model="withholdingForm.certified_amount" />
          </ElFormItem>
        </div>
        <p class="-mt-2 mb-3 text-xs text-slate-500">
          Use the tax withheld printed on the certificate. Admin reviews that amount. A rate is not
          required.
        </p>
        <ElFormItem label="Certificate file (PDF/JPEG/PNG)" required>
          <ElUpload
            :auto-upload="false"
            :limit="1"
            :accept="acceptFromMimeTypes(withholdingDocType?.allowed_mime_types)"
            :on-change="onWithholdingFile"
            :on-remove="() => (withholdingFile = null)"
          >
            <ElButton>Choose file</ElButton>
          </ElUpload>
          <p class="mt-1 text-xs text-slate-500">
            {{ withholdingDocType?.name || 'BIR 2307 document type' }} · max
            {{ formatMaxUploadSize(withholdingDocType?.max_file_size_kb) }}
          </p>
        </ElFormItem>
        <ElFormItem label="Notes for Admin">
          <ElInput
            v-model="withholdingForm.customer_notes"
            type="textarea"
            :rows="2"
            maxlength="1000"
          />
        </ElFormItem>
        <ElButton type="primary" class="w-full" :loading="saving" @click="submitWithholding">
          Submit for Admin approval
        </ElButton>
      </ElForm>
    </ElDrawer>

    <ElDrawer
      v-model="exemptionOpen"
      title="Submit Non-VAT / zero-rated ruling"
      size="560px"
      destroy-on-close
      @closed="resetExemption"
    >
      <ElForm label-position="top" @submit.prevent="submitExemption">
        <ElFormItem label="Treatment" required>
          <ElRadioGroup v-model="exemptionForm.exemption_type">
            <ElRadioButton value="VAT_EXEMPT">Non-VAT / VAT exempt</ElRadioButton>
            <ElRadioButton value="ZERO_RATED">Zero-rated</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem label="Ruling / certificate number" required>
          <ElInput v-model="exemptionForm.ruling_or_cert_no" maxlength="64" />
        </ElFormItem>
        <ElFormItem label="Legal basis" required>
          <ElInput
            v-model="exemptionForm.legal_basis"
            maxlength="255"
            placeholder="e.g. PEZA RA 7916 / NIRC Section 109"
          />
        </ElFormItem>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <ElFormItem label="Valid from" required>
            <ElDatePicker
              v-model="exemptionForm.valid_from"
              type="date"
              value-format="YYYY-MM-DD"
              class="!w-full"
            />
          </ElFormItem>
          <ElFormItem label="Valid to">
            <ElDatePicker
              v-model="exemptionForm.valid_to"
              type="date"
              value-format="YYYY-MM-DD"
              class="!w-full"
              placeholder="Open-ended"
            />
          </ElFormItem>
        </div>
        <ElFormItem label="Covered services">
          <ElSelect
            v-model="exemptionForm.covered_services"
            multiple
            filterable
            allow-create
            default-first-option
            class="w-full"
            placeholder="ALL or specific tariff/service codes"
          >
            <ElOption label="ALL services" value="ALL" />
            <ElOption label="STEV_DOM" value="STEV_DOM" />
            <ElOption label="STEVEDORING" value="STEVEDORING" />
            <ElOption label="ARR_DOM" value="ARR_DOM" />
            <ElOption label="ARR_FOR" value="ARR_FOR" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="Ruling file (PDF)" required>
          <ElUpload
            :auto-upload="false"
            :limit="1"
            :accept="acceptFromMimeTypes(exemptionDocType?.allowed_mime_types)"
            :on-change="onExemptionFile"
            :on-remove="() => (exemptionFile = null)"
          >
            <ElButton>Choose file</ElButton>
          </ElUpload>
          <p class="mt-1 text-xs text-slate-500">
            {{ exemptionDocType?.name || 'Exemption document type' }} · max
            {{ formatMaxUploadSize(exemptionDocType?.max_file_size_kb) }}
          </p>
        </ElFormItem>
        <ElFormItem label="Notes for Admin">
          <ElInput
            v-model="exemptionForm.customer_notes"
            type="textarea"
            :rows="2"
            maxlength="1000"
          />
        </ElFormItem>
        <ElButton type="primary" class="w-full" :loading="saving" @click="submitExemption">
          Submit for Admin approval
        </ElButton>
      </ElForm>
    </ElDrawer>

    <ElDialog
      v-model="previewOpen"
      :title="previewTitle"
      width="min(920px, 96vw)"
      destroy-on-close
      @closed="previewFileId = null"
    >
      <DocumentPreviewPane
        v-if="previewFileId"
        :active="previewOpen"
        :reload-key="previewFileId"
        :title="previewTitle"
        :fetcher="previewFetcher"
        min-height="70vh"
      />
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref } from 'vue'
  import { ElMessage, type UploadFile } from 'element-plus'
  import { View } from '@element-plus/icons-vue'
  import DocumentPreviewPane from '@/components/business/DocumentPreviewPane.vue'
  import {
    downloadPrivateFile,
    fetchDocumentTypes,
    uploadPrivateFile,
    type DocumentTypeItem
  } from '@/api/documentRequirements'
  import { fetchPortalProfile } from '@/api/registration'
  import { fetchGetUserInfo } from '@/api/auth'
  import { useAuthoritativeRealtimeRefresh } from '@/composables/useAuthoritativeRealtimeRefresh'
  import {
    fetchCustomerExemptions,
    fetchCustomerWithholding,
    submitCustomerExemption,
    submitCustomerWithholding,
    type ExemptionType,
    type TaxEvidenceStatus,
    type TaxExemption,
    type WithholdingCertificate
  } from '@/api/taxEvidence'
  import {
    formatDateManila,
    formatDateRangeManila,
    formatDateTimeManila
  } from '@/utils/date/formatDateTime'
  import {
    acceptFromMimeTypes,
    formatMaxUploadSize,
    validateUploadAgainstDocumentType
  } from '@/utils/uploads/documentUpload'

  const loading = ref(false)
  const saving = ref(false)
  const activeTab = ref('withholding')
  const activeCustomerId = ref<number | null>(null)

  const withholdingRows = ref<WithholdingCertificate[]>([])
  const exemptionRows = ref<TaxExemption[]>([])
  const withholdingDocType = ref<DocumentTypeItem | null>(null)
  const exemptionDocType = ref<DocumentTypeItem | null>(null)

  const withholdingOpen = ref(false)
  const exemptionOpen = ref(false)
  const withholdingFile = ref<File | null>(null)
  const exemptionFile = ref<File | null>(null)

  const withholdingForm = reactive({
    certificate_no: '',
    payor_tin: '',
    payor_name: '',
    period_from: '',
    period_to: '',
    atc_code: '',
    income_payment_base: '',
    certified_amount: '',
    customer_notes: ''
  })

  const exemptionForm = reactive<{
    exemption_type: ExemptionType
    ruling_or_cert_no: string
    legal_basis: string
    valid_from: string
    valid_to: string
    covered_services: string[]
    customer_notes: string
  }>({
    exemption_type: 'VAT_EXEMPT',
    ruling_or_cert_no: '',
    legal_basis: '',
    valid_from: '',
    valid_to: '',
    covered_services: ['ALL'],
    customer_notes: ''
  })

  const previewOpen = ref(false)
  const previewFileId = ref<number | null>(null)
  const previewTitle = ref('Tax evidence file')
  const realtime = useAuthoritativeRealtimeRefresh({
    scope: 'tax_evidence',
    refresh: () => loadAll(),
    isBusy: () => saving.value
  })

  const previewFetcher = computed(() => {
    const id = previewFileId.value
    if (!id) return null
    return async () => {
      const blob = await downloadPrivateFile(id)
      return { blob }
    }
  })

  function money(amount?: string | null) {
    if (amount == null || amount === '') return '—'
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' }).format(
      Number(amount)
    )
  }

  function statusTag(status: TaxEvidenceStatus) {
    if (status === 'APPROVED') return 'success'
    if (status === 'PENDING_REVIEW') return 'warning'
    if (status === 'NEEDS_CORRECTION') return 'info'
    if (status === 'REJECTED' || status === 'REVOKED' || status === 'EXPIRED') return 'danger'
    return 'info'
  }

  function manilaToday(): Date {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Manila',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit'
    }).formatToParts(new Date())
    const y = Number(parts.find((p) => p.type === 'year')?.value)
    const m = Number(parts.find((p) => p.type === 'month')?.value)
    const d = Number(parts.find((p) => p.type === 'day')?.value)
    return new Date(y, m - 1, d)
  }

  function calendarDatePart(dateStr: string): string {
    // API may return YYYY-MM-DD or an ISO datetime; compare on the calendar day only.
    return dateStr.slice(0, 10)
  }

  function daysUntil(dateStr?: string | null): number | null {
    if (!dateStr) return null
    const [y, m, d] = calendarDatePart(dateStr).split('-').map(Number)
    if (!y || !m || !d) return null
    const end = new Date(y, m - 1, d)
    const today = manilaToday()
    return Math.round((end.getTime() - today.getTime()) / 86400000)
  }

  type RenewAlert = { key: string; label: string; kind: 'withholding' | 'exemption' }

  const expiredAlerts = computed((): RenewAlert[] => {
    const items: RenewAlert[] = []
    for (const row of withholdingRows.value) {
      if (row.status === 'EXPIRED') {
        items.push({
          key: `w-exp-${row.id}`,
          kind: 'withholding',
          label: `BIR 2307 ${row.certificate_no} (period ended ${formatDateManila(row.period_to)})`
        })
      }
    }
    for (const row of exemptionRows.value) {
      if (row.status === 'EXPIRED') {
        items.push({
          key: `e-exp-${row.id}`,
          kind: 'exemption',
          label: `${exemptionLabel(row.exemption_type)} ${row.ruling_or_cert_no} (valid to ${formatDateManila(row.valid_to)})`
        })
      }
    }
    return items
  })

  const approachingAlerts = computed((): RenewAlert[] => {
    const items: RenewAlert[] = []
    for (const row of withholdingRows.value) {
      if (row.status !== 'APPROVED') continue
      const days = daysUntil(row.period_to)
      if (days !== null && days >= 0 && days < 30) {
        items.push({
          key: `w-soon-${row.id}`,
          kind: 'withholding',
          label: `BIR 2307 ${row.certificate_no} — ${days} day(s) left (ends ${formatDateManila(row.period_to)})`
        })
      }
    }
    for (const row of exemptionRows.value) {
      if (row.status !== 'APPROVED' || !row.valid_to) continue
      const days = daysUntil(row.valid_to)
      if (days !== null && days >= 0 && days < 30) {
        items.push({
          key: `e-soon-${row.id}`,
          kind: 'exemption',
          label: `${exemptionLabel(row.exemption_type)} ${row.ruling_or_cert_no} — ${days} day(s) left (ends ${formatDateManila(row.valid_to)})`
        })
      }
    }
    return items
  })

  function renewWithholding() {
    activeTab.value = 'withholding'
    openWithholdingForm()
  }

  function renewExemption() {
    activeTab.value = 'exemptions'
    openExemptionForm()
  }

  function exemptionLabel(type: ExemptionType) {
    return type === 'ZERO_RATED' ? 'Zero-rated' : 'Non-VAT / VAT exempt'
  }

  function onTabChange() {
    // lists already loaded together
  }

  function onWithholdingFile(uploadFile: UploadFile) {
    withholdingFile.value = uploadFile.raw || null
  }

  function onExemptionFile(uploadFile: UploadFile) {
    exemptionFile.value = uploadFile.raw || null
  }

  function openWithholdingForm() {
    withholdingOpen.value = true
  }

  function openExemptionForm() {
    exemptionOpen.value = true
  }

  function resetWithholding() {
    Object.assign(withholdingForm, {
      certificate_no: '',
      payor_tin: '',
      payor_name: '',
      period_from: '',
      period_to: '',
      atc_code: '',
      income_payment_base: '',
      certified_amount: '',
      customer_notes: ''
    })
    withholdingFile.value = null
  }

  function resetExemption() {
    Object.assign(exemptionForm, {
      exemption_type: 'VAT_EXEMPT',
      ruling_or_cert_no: '',
      legal_basis: '',
      valid_from: '',
      valid_to: '',
      covered_services: ['ALL'],
      customer_notes: ''
    })
    exemptionFile.value = null
  }

  function previewFile(row: { private_file_id: number; private_file?: PrivateFileLike | null }) {
    previewFileId.value = row.private_file_id
    previewTitle.value =
      row.private_file?.latest_version?.original_name || `File #${row.private_file_id}`
    previewOpen.value = true
  }

  interface PrivateFileLike {
    latest_version?: { original_name?: string } | null
  }

  async function uploadEvidenceFile(file: File, docType: DocumentTypeItem | null) {
    if (!docType) throw new Error('Required document type is not configured.')
    const validation = validateUploadAgainstDocumentType(file, docType)
    if (validation) throw new Error(validation)
    const form = new FormData()
    form.append('file', file)
    form.append('document_type_id', String(docType.id))
    const uploaded = await uploadPrivateFile(form)
    if (uploaded.latest_version?.scan_status && uploaded.latest_version.scan_status !== 'CLEAN') {
      throw new Error('Uploaded file is still scanning or quarantined. Try again shortly.')
    }
    return uploaded.id
  }

  async function submitWithholding() {
    if (!activeCustomerId.value) return
    if (!withholdingFile.value) {
      ElMessage.warning('Choose the BIR 2307 certificate file.')
      return
    }
    saving.value = true
    try {
      const privateFileId = await uploadEvidenceFile(
        withholdingFile.value,
        withholdingDocType.value
      )
      await submitCustomerWithholding({
        customer_id: activeCustomerId.value,
        certificate_no: withholdingForm.certificate_no.trim(),
        private_file_id: privateFileId,
        payor_tin: withholdingForm.payor_tin.trim(),
        payor_name: withholdingForm.payor_name.trim(),
        period_from: withholdingForm.period_from,
        period_to: withholdingForm.period_to,
        atc_code: withholdingForm.atc_code.trim(),
        income_payment_base: withholdingForm.income_payment_base,
        certified_amount: withholdingForm.certified_amount,
        customer_notes: withholdingForm.customer_notes.trim() || undefined
      })
      ElMessage.success('Withholding certificate submitted for Admin approval.')
      withholdingOpen.value = false
      await loadAll()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to submit withholding certificate.')
    } finally {
      saving.value = false
    }
  }

  async function submitExemption() {
    if (!activeCustomerId.value) return
    if (!exemptionFile.value) {
      ElMessage.warning('Choose the Non-VAT / zero-rated ruling file.')
      return
    }
    saving.value = true
    try {
      const privateFileId = await uploadEvidenceFile(exemptionFile.value, exemptionDocType.value)
      await submitCustomerExemption({
        customer_id: activeCustomerId.value,
        exemption_type: exemptionForm.exemption_type,
        legal_basis: exemptionForm.legal_basis.trim(),
        ruling_or_cert_no: exemptionForm.ruling_or_cert_no.trim(),
        covered_services:
          exemptionForm.covered_services.length > 0 ? exemptionForm.covered_services : ['ALL'],
        valid_from: exemptionForm.valid_from,
        valid_to: exemptionForm.valid_to || null,
        private_file_id: privateFileId,
        customer_notes: exemptionForm.customer_notes.trim() || undefined
      })
      ElMessage.success('Tax exemption ruling submitted for Admin approval.')
      exemptionOpen.value = false
      await loadAll()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to submit tax exemption ruling.')
    } finally {
      saving.value = false
    }
  }

  async function loadAll() {
    loading.value = true
    try {
      const [profile, me] = await Promise.all([fetchPortalProfile(), fetchGetUserInfo()])
      realtime.startForUser(Number((me as any).id || (me as any).userId))
      const link = profile.customer_links?.find((item: any) => item.is_active)
      activeCustomerId.value = link?.customer_id || null

      const [withholdingTypes, exemptionTypes] = await Promise.all([
        fetchDocumentTypes({ purpose: 'WITHHOLDING_CERTIFICATE', is_active: true }),
        fetchDocumentTypes({ purpose: 'EXEMPTION_EVIDENCE', is_active: true })
      ])
      withholdingDocType.value = withholdingTypes[0] || null
      exemptionDocType.value = exemptionTypes[0] || null

      if (!activeCustomerId.value) {
        withholdingRows.value = []
        exemptionRows.value = []
        return
      }

      const [withholding, exemptions] = await Promise.all([
        fetchCustomerWithholding(),
        fetchCustomerExemptions()
      ])
      withholdingRows.value = withholding.data || []
      exemptionRows.value = exemptions.data || []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load tax evidence.')
    } finally {
      loading.value = false
    }
  }

  onMounted(loadAll)
</script>
