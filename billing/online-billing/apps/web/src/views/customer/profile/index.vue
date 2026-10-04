<!-- Customer Profile Settings (W32 / W20) -->
<template>
  <div class="page-content max-w-6xl mx-auto space-y-5">
    <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-3">
      <div>
        <h1 class="text-xl font-semibold text-g-900">Profile Settings</h1>
        <p class="mt-1 text-sm text-g-600">
          Manage your portal account, company buyer details, password, and tax verification
          requests. Company and tax changes never rewrite issued invoices or receipts.
        </p>
      </div>
      <ElButton :loading="loading" @click="loadAll">Refresh</ElButton>
    </div>

    <ElAlert
      v-if="!activeCustomerId"
      type="warning"
      :closable="false"
      show-icon
      title="No active customer account is linked. Ask Admin to link your portal user before updating company or tax evidence."
    />

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">
      <!-- Left summary card -->
      <div class="art-card-sm p-6 text-center space-y-4">
        <div class="relative mx-auto w-24 h-24">
          <ElAvatar :size="96" :src="avatarBlobUrl || undefined" class="!bg-theme/20 text-2xl">
            {{ (accountForm.name || '?').charAt(0) }}
          </ElAvatar>
        </div>
        <div>
          <div class="text-lg font-medium">{{ accountForm.name || '—' }}</div>
          <div class="text-xs text-g-500 mt-1">{{ accountForm.email }}</div>
          <div class="text-xs text-g-500">{{ accountForm.phone || 'No mobile on file' }}</div>
        </div>
        <div class="flex flex-wrap justify-center gap-2">
          <ElTag v-for="cp in verifiedContacts" :key="cp.id" size="small" type="success">
            {{ cp.type }} verified
          </ElTag>
        </div>
        <ElUpload
          :auto-upload="false"
          :show-file-list="false"
          accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
          :on-change="onAvatarPick"
        >
          <ElButton :loading="avatarSaving" size="small" type="primary" plain>
            Change picture
          </ElButton>
        </ElUpload>
        <p class="text-[11px] text-g-500">JPEG / PNG / WebP · max 2 MB · private storage</p>
      </div>

      <div class="lg:col-span-2 space-y-5">
        <!-- Account -->
        <div class="art-card-sm">
          <h2 class="p-4 text-base font-medium border-b border-g-300">Portal account</h2>
          <div class="p-4 space-y-4">
            <ElForm label-position="top" @submit.prevent="saveAccount">
              <ElFormItem label="Full name">
                <ElInput v-model="accountForm.name" maxlength="255" />
              </ElFormItem>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <ElFormItem label="Email (read-only)">
                  <ElInput v-model="accountForm.email" disabled />
                </ElFormItem>
                <ElFormItem label="Mobile (read-only)">
                  <ElInput v-model="accountForm.phone" disabled />
                </ElFormItem>
              </div>
              <ElAlert
                type="info"
                :closable="false"
                show-icon
                class="!mb-3"
                title="Email and mobile changes require a verified OTP flow and are not available on this page yet."
              />
              <div class="flex justify-end">
                <ElButton type="primary" :loading="accountSaving" @click="saveAccount">
                  Save name
                </ElButton>
              </div>
            </ElForm>
          </div>
        </div>

        <!-- Company / buyer -->
        <div class="art-card-sm">
          <div class="p-4 border-b border-g-300 flex-cb gap-3">
            <h2 class="text-base font-medium">Company / registered buyer</h2>
            <ElTag v-if="pendingBuyer" size="small" type="warning">
              Pending review v{{ pendingBuyer.version }}
            </ElTag>
            <ElTag v-else-if="activeBuyer" size="small" type="success">
              Active v{{ activeBuyer.version }}
            </ElTag>
          </div>
          <div class="p-4 space-y-4">
            <ElAlert
              v-if="pendingBuyer"
              type="warning"
              :closable="false"
              show-icon
              :title="`A buyer profile update (v${pendingBuyer.version}) is awaiting Admin review. Active billing still uses v${activeBuyer?.version || '—'} until approved.`"
            />
            <ElForm label-position="top" @submit.prevent="saveBuyer">
              <ElFormItem label="Registered company / buyer name" required>
                <ElInput
                  v-model="buyerForm.registered_name"
                  maxlength="255"
                  :disabled="!activeCustomerId"
                />
              </ElFormItem>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <ElFormItem label="TIN (optional)">
                  <ElInput v-model="buyerForm.tin" maxlength="32" :disabled="!activeCustomerId" />
                </ElFormItem>
                <ElFormItem label="Branch code">
                  <ElInput
                    v-model="buyerForm.branch_code"
                    maxlength="16"
                    :disabled="!activeCustomerId"
                  />
                </ElFormItem>
              </div>
              <ElFormItem label="Billing / registered address">
                <ElInput
                  v-model="buyerForm.registered_address"
                  type="textarea"
                  :rows="2"
                  maxlength="500"
                  :disabled="!activeCustomerId"
                />
              </ElFormItem>
              <div class="flex justify-end">
                <ElButton
                  type="primary"
                  :loading="buyerSaving"
                  :disabled="!activeCustomerId"
                  @click="saveBuyer"
                >
                  Submit for review
                </ElButton>
              </div>
            </ElForm>
          </div>
        </div>

        <!-- Password -->
        <div class="art-card-sm">
          <h2 class="p-4 text-base font-medium border-b border-g-300">Change password</h2>
          <div class="p-4">
            <ElForm label-position="top" @submit.prevent="savePassword">
              <ElFormItem label="Current password" required>
                <ElInput v-model="pwdForm.current_password" type="password" show-password />
              </ElFormItem>
              <ElFormItem label="New password" required>
                <ElInput v-model="pwdForm.password" type="password" show-password />
              </ElFormItem>
              <ElFormItem label="Confirm new password" required>
                <ElInput v-model="pwdForm.password_confirmation" type="password" show-password />
              </ElFormItem>
              <p class="text-xs text-g-500 mb-3">Minimum 10 characters with letters and numbers.</p>
              <div class="flex justify-end">
                <ElButton type="primary" :loading="pwdSaving" @click="savePassword">
                  Update password
                </ElButton>
              </div>
            </ElForm>
          </div>
        </div>

        <!-- Tax verification -->
        <div class="art-card-sm">
          <div class="p-4 border-b border-g-300 flex-cb gap-3">
            <div>
              <h2 class="text-base font-medium">Tax verification requests</h2>
              <p class="text-xs text-g-500 mt-1">
                Request Admin approval for withholding (BIR 2307) or Non-VAT / zero-rated treatment.
              </p>
            </div>
            <ElButton text type="primary" @click="router.push('/my-tax-evidence')">
              Full tax evidence
            </ElButton>
          </div>
          <div class="p-4 space-y-4">
            <ElAlert
              v-if="profileExpiredAlerts.length"
              type="error"
              :closable="false"
              show-icon
              title="Tax evidence expired — renew on Tax Evidence"
              description="Submit updated withholding or exemption files with a new validity period. Issued invoices and receipts are not rewritten."
            />
            <ElAlert
              v-else-if="profileApproachingAlerts.length"
              type="warning"
              :closable="false"
              show-icon
              title="Tax evidence expires in less than 30 days"
              description="Update your files soon so Admin can refresh validity before expiry."
            />
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <div class="rounded-lg border border-g-300 p-4 space-y-2">
                <div class="text-sm font-medium">Withholding (BIR 2307)</div>
                <ElTag size="small" :type="statusTag(latestWithholding?.status)">
                  {{ latestWithholding?.status || 'None submitted' }}
                </ElTag>
                <p class="text-xs text-g-500">
                  {{
                    latestWithholding
                      ? `${latestWithholding.certificate_no} · ${latestWithholding.period_from} → ${latestWithholding.period_to}`
                      : 'Upload Form 2307 for Admin review before settlement credit.'
                  }}
                </p>
                <ElButton
                  size="small"
                  type="primary"
                  :disabled="!activeCustomerId"
                  @click="withholdingOpen = true"
                >
                  Request withholding verification
                </ElButton>
              </div>
              <div class="rounded-lg border border-g-300 p-4 space-y-2">
                <div class="text-sm font-medium">VAT exempt / Non-VAT / zero-rated</div>
                <ElTag size="small" :type="statusTag(latestExemption?.status)">
                  {{ latestExemption?.status || 'None submitted' }}
                </ElTag>
                <p class="text-xs text-g-500">
                  {{
                    latestExemption
                      ? `${latestExemption.exemption_type} · ${latestExemption.ruling_or_cert_no}`
                      : 'Submit a ruling or certificate for Admin tax-treatment review.'
                  }}
                </p>
                <ElButton
                  size="small"
                  type="primary"
                  :disabled="!activeCustomerId"
                  @click="exemptionOpen = true"
                >
                  Request VAT / exemption verification
                </ElButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Withholding drawer -->
    <ElDrawer
      v-model="withholdingOpen"
      title="Request withholding verification (BIR 2307)"
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
          <ElInput v-model="withholdingForm.atc_code" maxlength="16" />
        </ElFormItem>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <ElFormItem label="Income payment on the 2307" required>
            <ElInput v-model="withholdingForm.income_payment_base" />
          </ElFormItem>
          <ElFormItem label="Tax withheld on the 2307" required>
            <ElInput v-model="withholdingForm.certified_amount" />
          </ElFormItem>
        </div>
        <p class="-mt-2 mb-3 text-xs text-g-500">
          Use the tax withheld printed on the certificate. Admin reviews that amount before it can
          be applied at payment.
        </p>
        <ElFormItem label="Certificate file" required>
          <ElUpload
            :auto-upload="false"
            :limit="1"
            :accept="acceptFromMimeTypes(withholdingDocType?.allowed_mime_types)"
            :on-change="onWithholdingFile"
            :on-remove="() => (withholdingFile = null)"
          >
            <ElButton>Choose file</ElButton>
          </ElUpload>
        </ElFormItem>
        <ElFormItem label="Notes for Admin">
          <ElInput v-model="withholdingForm.customer_notes" type="textarea" :rows="2" />
        </ElFormItem>
        <ElButton type="primary" class="w-full" :loading="taxSaving" @click="submitWithholding">
          Submit for Admin approval
        </ElButton>
      </ElForm>
    </ElDrawer>

    <!-- Exemption drawer -->
    <ElDrawer
      v-model="exemptionOpen"
      title="Request VAT exempt / zero-rated verification"
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
          <ElInput v-model="exemptionForm.legal_basis" maxlength="255" />
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
            />
          </ElFormItem>
        </div>
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
        </ElFormItem>
        <ElFormItem label="Notes for Admin">
          <ElInput v-model="exemptionForm.customer_notes" type="textarea" :rows="2" />
        </ElFormItem>
        <ElButton type="primary" class="w-full" :loading="taxSaving" @click="submitExemption">
          Submit for Admin approval
        </ElButton>
      </ElForm>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { useRouter } from 'vue-router'
  import { ElMessage, type UploadFile } from 'element-plus'
  import {
    changePortalPassword,
    fetchPortalProfile,
    updatePortalProfile,
    uploadPortalAvatar,
    type BuyerVersionSummary
  } from '@/api/portalProfile'
  import {
    downloadPrivateFile,
    fetchDocumentTypes,
    uploadPrivateFile,
    type DocumentTypeItem
  } from '@/api/documentRequirements'
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
  import { useUserStore } from '@/store/modules/user'
  import {
    acceptFromMimeTypes,
    validateUploadAgainstDocumentType
  } from '@/utils/uploads/documentUpload'

  defineOptions({ name: 'CustomerProfileSettings' })

  const router = useRouter()
  const userStore = useUserStore()

  const loading = ref(false)
  const accountSaving = ref(false)
  const buyerSaving = ref(false)
  const pwdSaving = ref(false)
  const avatarSaving = ref(false)
  const taxSaving = ref(false)

  const activeCustomerId = ref<number | null>(null)
  const activeBuyer = ref<BuyerVersionSummary | null>(null)
  const pendingBuyer = ref<BuyerVersionSummary | null>(null)
  const contactPoints = ref<Array<{ id: number; type: string; is_verified: boolean }>>([])
  const avatarBlobUrl = ref('')
  const avatarFileId = ref<number | null>(null)

  const withholdingRows = ref<WithholdingCertificate[]>([])
  const exemptionRows = ref<TaxExemption[]>([])
  const withholdingDocType = ref<DocumentTypeItem | null>(null)
  const exemptionDocType = ref<DocumentTypeItem | null>(null)
  const withholdingOpen = ref(false)
  const exemptionOpen = ref(false)
  const withholdingFile = ref<File | null>(null)
  const exemptionFile = ref<File | null>(null)

  const accountForm = reactive({
    name: '',
    email: '',
    phone: ''
  })

  const buyerForm = reactive({
    registered_name: '',
    tin: '',
    branch_code: '00000',
    registered_address: ''
  })

  const pwdForm = reactive({
    current_password: '',
    password: '',
    password_confirmation: ''
  })

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
    customer_notes: string
  }>({
    exemption_type: 'VAT_EXEMPT',
    ruling_or_cert_no: '',
    legal_basis: '',
    valid_from: '',
    valid_to: '',
    customer_notes: ''
  })

  const verifiedContacts = computed(() => contactPoints.value.filter((c) => c.is_verified))
  const latestWithholding = computed(() => withholdingRows.value[0] || null)
  const latestExemption = computed(() => exemptionRows.value[0] || null)

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

  const profileExpiredAlerts = computed(() => {
    const rows = [
      ...withholdingRows.value.filter((r) => r.status === 'EXPIRED'),
      ...exemptionRows.value.filter((r) => r.status === 'EXPIRED')
    ]
    return rows
  })

  const profileApproachingAlerts = computed(() => {
    const items: Array<WithholdingCertificate | TaxExemption> = []
    for (const row of withholdingRows.value) {
      if (row.status !== 'APPROVED') continue
      const days = daysUntil(row.period_to)
      if (days !== null && days >= 0 && days < 30) items.push(row)
    }
    for (const row of exemptionRows.value) {
      if (row.status !== 'APPROVED' || !row.valid_to) continue
      const days = daysUntil(row.valid_to)
      if (days !== null && days >= 0 && days < 30) items.push(row)
    }
    return items
  })

  function statusTag(status?: TaxEvidenceStatus | string | null) {
    switch (status) {
      case 'APPROVED':
        return 'success'
      case 'PENDING_REVIEW':
        return 'warning'
      case 'NEEDS_CORRECTION':
      case 'REJECTED':
      case 'REVOKED':
      case 'EXPIRED':
        return 'danger'
      default:
        return 'info'
    }
  }

  function addressLine(version?: BuyerVersionSummary | null): string {
    if (!version) return ''
    if (version.registered_address) return String(version.registered_address)
    const addr = version.billing_address
    if (!addr) return ''
    if (typeof addr === 'string') return addr
    return addr.line1 || JSON.stringify(addr)
  }

  function cleanupAvatarBlob() {
    if (avatarBlobUrl.value) {
      URL.revokeObjectURL(avatarBlobUrl.value)
      avatarBlobUrl.value = ''
    }
  }

  async function loadAvatarPreview(fileId?: number | null, mime?: string | null) {
    cleanupAvatarBlob()
    avatarFileId.value = fileId || null
    if (!fileId) return
    try {
      const blob = await downloadPrivateFile(fileId)
      if (blob.type.startsWith('image/') || (mime || '').startsWith('image/')) {
        avatarBlobUrl.value = URL.createObjectURL(blob)
      }
    } catch {
      // Keep initials fallback
    }
  }

  async function loadAll() {
    loading.value = true
    try {
      const profile = await fetchPortalProfile()

      accountForm.name = profile.user?.name || ''
      accountForm.email = profile.user?.email || ''
      accountForm.phone = profile.user?.phone || ''
      contactPoints.value = profile.contact_points || []

      const link = (profile.customer_links || []).find((item) => item.is_active)
      activeCustomerId.value = link?.customer_id || null
      activeBuyer.value = link?.buyer_profile?.active_version || null
      pendingBuyer.value = link?.buyer_profile?.pending_version || null

      const source = pendingBuyer.value || activeBuyer.value
      buyerForm.registered_name = source?.registered_name || link?.name || ''
      buyerForm.tin = source?.tin || source?.tax_identification_number || ''
      buyerForm.branch_code = source?.branch_code || '00000'
      buyerForm.registered_address = addressLine(source)

      await loadAvatarPreview(
        profile.user?.avatar?.private_file_id,
        profile.user?.avatar?.mime_type
      )

      const [withholdingTypes, exemptionTypes] = await Promise.all([
        fetchDocumentTypes({ purpose: 'WITHHOLDING_CERTIFICATE', is_active: true }).catch(() => []),
        fetchDocumentTypes({ purpose: 'EXEMPTION_EVIDENCE', is_active: true }).catch(() => [])
      ])
      withholdingDocType.value = withholdingTypes[0] || null
      exemptionDocType.value = exemptionTypes[0] || null

      if (!activeCustomerId.value) {
        withholdingRows.value = []
        exemptionRows.value = []
        return
      }

      const [withholding, exemptions] = await Promise.all([
        fetchCustomerWithholding({ page: 1 }),
        fetchCustomerExemptions({ page: 1 })
      ])
      withholdingRows.value = withholding.data || []
      exemptionRows.value = exemptions.data || []
    } catch (err: any) {
      ElMessage.error(err.message || 'Failed to load profile.')
    } finally {
      loading.value = false
    }
  }

  async function saveAccount() {
    if (!accountForm.name.trim()) {
      ElMessage.warning('Full name is required.')
      return
    }
    accountSaving.value = true
    try {
      await updatePortalProfile({ name: accountForm.name.trim() })
      const info = userStore.info as any
      if (info) {
        info.userName = accountForm.name.trim()
        info.name = accountForm.name.trim()
      }
      ElMessage.success('Name saved.')
    } catch (err: any) {
      ElMessage.error(err.message || 'Could not save name.')
    } finally {
      accountSaving.value = false
    }
  }

  async function saveBuyer() {
    if (!activeCustomerId.value) return
    if (!buyerForm.registered_name.trim()) {
      ElMessage.warning('Registered company / buyer name is required.')
      return
    }
    buyerSaving.value = true
    try {
      await updatePortalProfile({
        customer_id: activeCustomerId.value,
        registered_name: buyerForm.registered_name.trim(),
        tin: buyerForm.tin.trim() || undefined,
        branch_code: buyerForm.branch_code.trim() || '00000',
        registered_address: buyerForm.registered_address.trim() || undefined
      })
      ElMessage.success('Buyer profile submitted for Admin review.')
      await loadAll()
    } catch (err: any) {
      ElMessage.error(err.message || 'Could not submit buyer profile.')
    } finally {
      buyerSaving.value = false
    }
  }

  async function savePassword() {
    if (!pwdForm.current_password || !pwdForm.password) {
      ElMessage.warning('Enter current and new passwords.')
      return
    }
    if (pwdForm.password !== pwdForm.password_confirmation) {
      ElMessage.warning('New password confirmation does not match.')
      return
    }
    pwdSaving.value = true
    try {
      await changePortalPassword({ ...pwdForm })
      ElMessage.success('Password updated.')
      pwdForm.current_password = ''
      pwdForm.password = ''
      pwdForm.password_confirmation = ''
    } catch (err: any) {
      ElMessage.error(err.message || 'Could not change password.')
    } finally {
      pwdSaving.value = false
    }
  }

  async function onAvatarPick(uploadFile: UploadFile) {
    const file = uploadFile.raw
    if (!file) return
    if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
      ElMessage.error('Use JPEG, PNG, or WebP images only.')
      return
    }
    if (file.size > 2048 * 1024) {
      ElMessage.error('Profile picture must be 2 MB or smaller.')
      return
    }
    avatarSaving.value = true
    try {
      const res = await uploadPortalAvatar(file)
      ElMessage.success('Profile picture updated.')
      await loadAvatarPreview(res.avatar?.private_file_id, res.avatar?.mime_type)
    } catch (err: any) {
      ElMessage.error(err.message || 'Could not upload picture.')
    } finally {
      avatarSaving.value = false
    }
  }

  function onWithholdingFile(uploadFile: UploadFile) {
    withholdingFile.value = uploadFile.raw || null
  }

  function onExemptionFile(uploadFile: UploadFile) {
    exemptionFile.value = uploadFile.raw || null
  }

  function resetWithholding() {
    withholdingFile.value = null
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
  }

  function resetExemption() {
    exemptionFile.value = null
    Object.assign(exemptionForm, {
      exemption_type: 'VAT_EXEMPT',
      ruling_or_cert_no: '',
      legal_basis: '',
      valid_from: '',
      valid_to: '',
      customer_notes: ''
    })
  }

  async function uploadEvidenceFile(file: File, docType: DocumentTypeItem | null) {
    if (!docType) throw new Error('Document type is not configured.')
    const validationError = validateUploadAgainstDocumentType(file, docType)
    if (validationError) throw new Error(validationError)
    const form = new FormData()
    form.append('file', file)
    form.append('document_type_id', String(docType.id))
    const uploaded = await uploadPrivateFile(form)
    return uploaded.id
  }

  async function submitWithholding() {
    if (!activeCustomerId.value || !withholdingFile.value) {
      ElMessage.warning('Complete the form and attach the certificate file.')
      return
    }
    taxSaving.value = true
    try {
      const privateFileId = await uploadEvidenceFile(
        withholdingFile.value,
        withholdingDocType.value
      )
      await submitCustomerWithholding({
        customer_id: activeCustomerId.value,
        certificate_no: withholdingForm.certificate_no,
        private_file_id: privateFileId,
        payor_tin: withholdingForm.payor_tin,
        payor_name: withholdingForm.payor_name,
        period_from: withholdingForm.period_from,
        period_to: withholdingForm.period_to,
        atc_code: withholdingForm.atc_code,
        income_payment_base: withholdingForm.income_payment_base,
        certified_amount: withholdingForm.certified_amount,
        customer_notes: withholdingForm.customer_notes || undefined
      })
      ElMessage.success('Withholding certificate submitted for Admin approval.')
      withholdingOpen.value = false
      await loadAll()
    } catch (err: any) {
      ElMessage.error(err.message || 'Could not submit withholding evidence.')
    } finally {
      taxSaving.value = false
    }
  }

  async function submitExemption() {
    if (!activeCustomerId.value || !exemptionFile.value) {
      ElMessage.warning('Complete the form and attach the ruling file.')
      return
    }
    taxSaving.value = true
    try {
      const privateFileId = await uploadEvidenceFile(exemptionFile.value, exemptionDocType.value)
      await submitCustomerExemption({
        customer_id: activeCustomerId.value,
        exemption_type: exemptionForm.exemption_type,
        legal_basis: exemptionForm.legal_basis,
        ruling_or_cert_no: exemptionForm.ruling_or_cert_no,
        valid_from: exemptionForm.valid_from,
        valid_to: exemptionForm.valid_to || null,
        private_file_id: privateFileId,
        customer_notes: exemptionForm.customer_notes || undefined
      })
      ElMessage.success('Tax exemption request submitted for Admin approval.')
      exemptionOpen.value = false
      await loadAll()
    } catch (err: any) {
      ElMessage.error(err.message || 'Could not submit exemption evidence.')
    } finally {
      taxSaving.value = false
    }
  }

  onMounted(loadAll)
  onBeforeUnmount(cleanupAvatarBlob)
</script>
