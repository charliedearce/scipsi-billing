<template>
  <div class="tax-evidence-admin p-4 sm:p-6 space-y-5">
    <ElCard shadow="never">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">
              Tax Evidence Review
            </h1>
            <ElTag type="info" effect="plain">P2-08 / W20</ElTag>
          </div>
          <p class="mt-1 max-w-3xl text-sm text-slate-500">
            Approve, request correction, reject, or revoke customer BIR 2307 withholding
            certificates and Non-VAT / zero-rated rulings. Decisions are audited and do not rewrite
            posted invoices.
          </p>
        </div>
        <ElButton :loading="loading" @click="loadActive">Refresh</ElButton>
      </div>
    </ElCard>

    <ElTabs v-model="activeTab" @tab-change="loadActive">
      <ElTabPane label="Withholding (2307)" name="withholding">
        <ElCard shadow="never" v-loading="loading">
          <div class="mb-4 flex flex-wrap items-center gap-3">
            <ElSelect
              v-model="withholdingStatus"
              clearable
              placeholder="All statuses"
              class="!w-52"
              @change="loadWithholding"
            >
              <ElOption v-for="status in statuses" :key="status" :label="status" :value="status" />
            </ElSelect>
            <span class="text-xs text-slate-500">{{ withholdingTotal }} certificate(s)</span>
          </div>
          <ElTable
            :data="withholdingRows"
            stripe
            empty-text="No withholding certificates match this filter."
          >
            <ElTableColumn prop="id" label="#" width="70" />
            <ElTableColumn label="Customer" min-width="170">
              <template #default="{ row }">{{
                row.customer?.name || `Customer #${row.customer_id}`
              }}</template>
            </ElTableColumn>
            <ElTableColumn prop="certificate_no" label="Certificate" min-width="140" />
            <ElTableColumn label="Period" min-width="200">
              <template #default="{ row }">{{
                formatDateRangeManila(row.period_from, row.period_to)
              }}</template>
            </ElTableColumn>
            <ElTableColumn label="Certified" min-width="110">
              <template #default="{ row }">{{ money(row.certified_amount) }}</template>
            </ElTableColumn>
            <ElTableColumn label="Status" min-width="140">
              <template #default="{ row }">
                <ElTag size="small" :type="statusTag(row.status)">{{ row.status }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Submitted" min-width="150">
              <template #default="{ row }">{{ formatDateTimeManila(row.created_at) }}</template>
            </ElTableColumn>
            <ElTableColumn label="Actions" width="140" fixed="right">
              <template #default="{ row }">
                <div class="flex items-center justify-end gap-0.5">
                  <ElTooltip content="View file" placement="top">
                    <ElButton
                      link
                      type="primary"
                      :icon="View"
                      aria-label="View file"
                      @click="openPreview(row)"
                    />
                  </ElTooltip>
                  <ElTooltip
                    v-if="row.status === 'PENDING_REVIEW' || row.status === 'NEEDS_CORRECTION'"
                    content="Review"
                    placement="top"
                  >
                    <ElButton
                      link
                      type="success"
                      :icon="CircleCheck"
                      aria-label="Review"
                      @click="openReview('withholding', row)"
                    />
                  </ElTooltip>
                  <ElTooltip v-if="row.status === 'APPROVED'" content="Revoke" placement="top">
                    <ElButton
                      link
                      type="danger"
                      :icon="CircleClose"
                      aria-label="Revoke"
                      @click="openRevoke('withholding', row)"
                    />
                  </ElTooltip>
                </div>
              </template>
            </ElTableColumn>
          </ElTable>
        </ElCard>
      </ElTabPane>

      <ElTabPane label="Non-VAT / Zero-rated" name="exemptions">
        <ElCard shadow="never" v-loading="loading">
          <div class="mb-4 flex flex-wrap items-center gap-3">
            <ElSelect
              v-model="exemptionStatus"
              clearable
              placeholder="All statuses"
              class="!w-52"
              @change="loadExemptions"
            >
              <ElOption v-for="status in statuses" :key="status" :label="status" :value="status" />
            </ElSelect>
            <span class="text-xs text-slate-500">{{ exemptionTotal }} ruling(s)</span>
          </div>
          <ElTable
            :data="exemptionRows"
            stripe
            empty-text="No tax exemption rulings match this filter."
          >
            <ElTableColumn prop="id" label="#" width="70" />
            <ElTableColumn label="Customer" min-width="170">
              <template #default="{ row }">{{
                row.customer?.name || `Customer #${row.customer_id}`
              }}</template>
            </ElTableColumn>
            <ElTableColumn label="Type" min-width="140">
              <template #default="{ row }">
                <ElTag size="small" effect="plain">{{
                  row.exemption_type === 'ZERO_RATED' ? 'Zero-rated' : 'Non-VAT / VAT exempt'
                }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="ruling_or_cert_no" label="Ruling no." min-width="140" />
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
            <ElTableColumn label="Status" min-width="140">
              <template #default="{ row }">
                <ElTag size="small" :type="statusTag(row.status)">{{ row.status }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Actions" width="140" fixed="right">
              <template #default="{ row }">
                <div class="flex items-center justify-end gap-0.5">
                  <ElTooltip content="View file" placement="top">
                    <ElButton
                      link
                      type="primary"
                      :icon="View"
                      aria-label="View file"
                      @click="openPreview(row)"
                    />
                  </ElTooltip>
                  <ElTooltip
                    v-if="row.status === 'PENDING_REVIEW' || row.status === 'NEEDS_CORRECTION'"
                    content="Review"
                    placement="top"
                  >
                    <ElButton
                      link
                      type="success"
                      :icon="CircleCheck"
                      aria-label="Review"
                      @click="openReview('exemption', row)"
                    />
                  </ElTooltip>
                  <ElTooltip v-if="row.status === 'APPROVED'" content="Revoke" placement="top">
                    <ElButton
                      link
                      type="danger"
                      :icon="CircleClose"
                      aria-label="Revoke"
                      @click="openRevoke('exemption', row)"
                    />
                  </ElTooltip>
                </div>
              </template>
            </ElTableColumn>
          </ElTable>
        </ElCard>
      </ElTabPane>
    </ElTabs>

    <ElDialog
      v-model="reviewOpen"
      :title="reviewKind === 'withholding' ? 'Review withholding certificate' : 'Review tax ruling'"
      width="min(560px, 94vw)"
      destroy-on-close
      @closed="resetReview"
    >
      <p v-if="reviewTarget" class="mb-4 text-sm text-slate-600">
        Customer:
        {{ reviewTarget.customer?.name || `#${reviewTarget.customer_id}` }}
        <span v-if="'certificate_no' in reviewTarget">
          · Certificate {{ reviewTarget.certificate_no }}
        </span>
        <span v-else-if="'ruling_or_cert_no' in reviewTarget">
          · Ruling {{ reviewTarget.ruling_or_cert_no }}
        </span>
      </p>
      <ElForm label-position="top">
        <ElFormItem label="Decision" required>
          <ElRadioGroup v-model="reviewForm.decision">
            <ElRadioButton value="APPROVED">Approve</ElRadioButton>
            <ElRadioButton value="NEEDS_CORRECTION">Needs correction</ElRadioButton>
            <ElRadioButton value="REJECTED">Reject</ElRadioButton>
          </ElRadioGroup>
        </ElFormItem>
        <ElFormItem v-if="reviewForm.decision !== 'APPROVED'" label="Reason for customer" required>
          <ElInput
            v-model="reviewForm.rejection_reason"
            type="textarea"
            :rows="3"
            maxlength="500"
            show-word-limit
          />
        </ElFormItem>
        <ElFormItem label="Decision notes">
          <ElInput
            v-model="reviewForm.decision_notes"
            type="textarea"
            :rows="3"
            maxlength="1000"
            show-word-limit
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="reviewOpen = false">Cancel</ElButton>
        <ElButton type="primary" :loading="saving" @click="submitReview">Save decision</ElButton>
      </template>
    </ElDialog>

    <ElDialog
      v-model="revokeOpen"
      title="Revoke approved tax evidence"
      width="min(480px, 94vw)"
      destroy-on-close
      @closed="resetRevoke"
    >
      <ElForm label-position="top">
        <ElFormItem label="Revocation reason" required>
          <ElInput
            v-model="revokeReason"
            type="textarea"
            :rows="4"
            maxlength="500"
            show-word-limit
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="revokeOpen = false">Cancel</ElButton>
        <ElButton type="danger" :loading="saving" @click="submitRevoke">Revoke</ElButton>
      </template>
    </ElDialog>

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
  import { ElMessage } from 'element-plus'
  import { CircleCheck, CircleClose, View } from '@element-plus/icons-vue'
  import DocumentPreviewPane from '@/components/business/DocumentPreviewPane.vue'
  import { downloadPrivateFile } from '@/api/documentRequirements'
  import { fetchGetUserInfo } from '@/api/auth'
  import { useAuthoritativeRealtimeRefresh } from '@/composables/useAuthoritativeRealtimeRefresh'
  import {
    fetchAdminExemptions,
    fetchAdminWithholding,
    reviewAdminExemption,
    reviewAdminWithholding,
    revokeAdminExemption,
    revokeAdminWithholding,
    type TaxEvidenceStatus,
    type TaxExemption,
    type TaxReviewDecision,
    type WithholdingCertificate
  } from '@/api/taxEvidence'
  import { formatDateRangeManila, formatDateTimeManila } from '@/utils/date/formatDateTime'

  const statuses: TaxEvidenceStatus[] = [
    'PENDING_REVIEW',
    'APPROVED',
    'NEEDS_CORRECTION',
    'REJECTED',
    'REVOKED',
    'EXPIRED'
  ]

  const loading = ref(false)
  const saving = ref(false)
  const activeTab = ref('withholding')

  const withholdingStatus = ref<TaxEvidenceStatus | ''>('PENDING_REVIEW')
  const exemptionStatus = ref<TaxEvidenceStatus | ''>('PENDING_REVIEW')
  const withholdingRows = ref<WithholdingCertificate[]>([])
  const exemptionRows = ref<TaxExemption[]>([])
  const withholdingTotal = ref(0)
  const exemptionTotal = ref(0)

  const reviewOpen = ref(false)
  const reviewKind = ref<'withholding' | 'exemption'>('withholding')
  const reviewTarget = ref<WithholdingCertificate | TaxExemption | null>(null)
  const reviewForm = reactive<{
    decision: TaxReviewDecision
    decision_notes: string
    rejection_reason: string
  }>({
    decision: 'APPROVED',
    decision_notes: '',
    rejection_reason: ''
  })

  const revokeOpen = ref(false)
  const revokeKind = ref<'withholding' | 'exemption'>('withholding')
  const revokeTarget = ref<WithholdingCertificate | TaxExemption | null>(null)
  const revokeReason = ref('')

  const previewOpen = ref(false)
  const previewFileId = ref<number | null>(null)
  const previewTitle = ref('Tax evidence file')
  const realtime = useAuthoritativeRealtimeRefresh({
    scope: 'tax_evidence',
    refresh: () => loadActive(),
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

  function openPreview(row: { private_file_id: number; private_file?: any }) {
    previewFileId.value = row.private_file_id
    previewTitle.value =
      row.private_file?.latest_version?.original_name || `File #${row.private_file_id}`
    previewOpen.value = true
  }

  function openReview(
    kind: 'withholding' | 'exemption',
    row: WithholdingCertificate | TaxExemption
  ) {
    reviewKind.value = kind
    reviewTarget.value = row
    reviewForm.decision = 'APPROVED'
    reviewForm.decision_notes = ''
    reviewForm.rejection_reason = ''
    reviewOpen.value = true
  }

  function resetReview() {
    reviewTarget.value = null
  }

  function openRevoke(
    kind: 'withholding' | 'exemption',
    row: WithholdingCertificate | TaxExemption
  ) {
    revokeKind.value = kind
    revokeTarget.value = row
    revokeReason.value = ''
    revokeOpen.value = true
  }

  function resetRevoke() {
    revokeTarget.value = null
    revokeReason.value = ''
  }

  async function submitReview() {
    if (!reviewTarget.value) return
    if (reviewForm.decision !== 'APPROVED' && !reviewForm.rejection_reason.trim()) {
      ElMessage.warning('Provide a reason for the customer.')
      return
    }
    saving.value = true
    try {
      const payload = {
        decision: reviewForm.decision,
        decision_notes: reviewForm.decision_notes.trim() || undefined,
        rejection_reason:
          reviewForm.decision === 'APPROVED' ? undefined : reviewForm.rejection_reason.trim()
      }
      if (reviewKind.value === 'withholding') {
        await reviewAdminWithholding(reviewTarget.value.id, payload)
      } else {
        await reviewAdminExemption(reviewTarget.value.id, payload)
      }
      ElMessage.success(`Decision saved: ${reviewForm.decision}.`)
      reviewOpen.value = false
      await loadActive()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to save review decision.')
    } finally {
      saving.value = false
    }
  }

  async function submitRevoke() {
    if (!revokeTarget.value) return
    if (!revokeReason.value.trim()) {
      ElMessage.warning('Provide a revocation reason.')
      return
    }
    saving.value = true
    try {
      if (revokeKind.value === 'withholding') {
        await revokeAdminWithholding(revokeTarget.value.id, revokeReason.value.trim())
      } else {
        await revokeAdminExemption(revokeTarget.value.id, revokeReason.value.trim())
      }
      ElMessage.success('Tax evidence revoked.')
      revokeOpen.value = false
      await loadActive()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to revoke tax evidence.')
    } finally {
      saving.value = false
    }
  }

  async function loadWithholding() {
    loading.value = true
    try {
      const page = await fetchAdminWithholding({
        status: withholdingStatus.value || undefined
      })
      withholdingRows.value = page.data || []
      withholdingTotal.value = page.total || withholdingRows.value.length
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load withholding certificates.')
    } finally {
      loading.value = false
    }
  }

  async function loadExemptions() {
    loading.value = true
    try {
      const page = await fetchAdminExemptions({
        status: exemptionStatus.value || undefined
      })
      exemptionRows.value = page.data || []
      exemptionTotal.value = page.total || exemptionRows.value.length
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load tax exemption rulings.')
    } finally {
      loading.value = false
    }
  }

  async function loadActive() {
    if (activeTab.value === 'exemptions') {
      await loadExemptions()
    } else {
      await loadWithholding()
    }
  }

  onMounted(async () => {
    const me = await fetchGetUserInfo()
    realtime.startForOrganization(
      Number((me as any).organization?.id || (me as any).organization_id)
    )
    await loadActive()
  })
</script>
