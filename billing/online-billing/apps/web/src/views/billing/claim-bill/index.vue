<template>
  <div class="claim-bill-page max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-sm"
    >
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Claim Bill</h1>
          <span
            class="bg-emerald-500/20 text-emerald-200 border border-emerald-400/30 text-xs px-2.5 py-0.5 rounded-full font-medium"
            >Customer Portal</span
          >
        </div>
        <p class="text-slate-300 text-sm max-w-2xl">
          Enter the invoice number from a walk-in or counter bill to link it to your portal account.
          After verification, review the invoice before accepting it into My Bills.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <ElButton
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="$router.push('/my-billing-requests')"
        >
          Request Billing
        </ElButton>
        <ElButton
          :loading="loading"
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="loadClaims"
        >
          Refresh
        </ElButton>
      </div>
    </div>

    <ElAlert
      v-if="!activeCustomerId"
      type="warning"
      :closable="false"
      show-icon
      title="No active customer account is linked to this portal user. Complete registration or ask Admin to link your account before claiming a bill."
    />

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <ElCard shadow="never">
        <template #header>
          <span class="font-semibold">Claim by invoice number</span>
        </template>
        <ElForm label-position="top" @submit.prevent="submitClaim">
          <ElFormItem label="Invoice number" required>
            <ElInput
              v-model="invoiceNumber"
              placeholder="e.g. 0000001234"
              maxlength="64"
              :disabled="!activeCustomerId || saving"
            />
          </ElFormItem>
          <ElButton
            type="primary"
            class="w-full"
            :loading="saving"
            :disabled="!activeCustomerId || !invoiceNumber.trim()"
            @click="submitClaim"
          >
            Start claim
          </ElButton>
          <p class="mt-3 text-xs text-g-500">
            Responses never confirm whether an invoice exists. If a verification code is sent, enter
            it below; otherwise a teller will review the claim.
          </p>
        </ElForm>
      </ElCard>

      <ElCard shadow="never" v-loading="loading">
        <template #header>
          <div class="flex items-center justify-between">
            <span class="font-semibold">Your claims</span>
            <ElTag>{{ claims.length }} shown</ElTag>
          </div>
        </template>
        <ElEmpty v-if="claims.length === 0" description="No bill claims yet." />
        <div v-else class="space-y-3">
          <div
            v-for="claim in claims"
            :key="claim.id"
            class="border border-g-300 rounded-xl p-4 space-y-3"
            :class="{ 'ring-1 ring-theme/40': selectedClaimId === claim.id }"
          >
            <div class="flex flex-wrap items-center gap-2">
              <ElTag :type="statusTag(claim.claim_status)" size="small">{{
                labelStatus(claim.claim_status)
              }}</ElTag>
              <ElTag effect="plain" size="small">{{ claim.verification_route }}</ElTag>
              <span class="text-sm text-g-600 truncate">
                {{ claim.invoice_number || `Claim #${claim.id}` }}
              </span>
            </div>

            <div
              v-if="claim.claim_status === 'PENDING_VERIFICATION'"
              class="flex flex-col sm:flex-row gap-2"
            >
              <ElInput
                v-model="verifyCodes[claim.id]"
                placeholder="Verification code"
                maxlength="10"
                class="sm:flex-1"
              />
              <ElButton
                type="primary"
                :loading="verifyingId === claim.id"
                @click="verifyClaim(claim)"
              >
                Verify
              </ElButton>
              <ElButton :loading="cancellingId === claim.id" @click="cancelClaim(claim)">
                Cancel
              </ElButton>
            </div>

            <div
              v-else-if="claim.claim_status === 'PENDING_TELLER_REVIEW'"
              class="flex items-center justify-between gap-2"
            >
              <p class="text-xs text-g-500">Waiting for teller review.</p>
              <ElButton
                size="small"
                :loading="cancellingId === claim.id"
                @click="cancelClaim(claim)"
              >
                Cancel
              </ElButton>
            </div>

            <div
              v-else-if="claim.claim_status === 'PENDING_CUSTOMER_ACCEPTANCE'"
              class="flex flex-wrap gap-2"
            >
              <ElButton type="primary" size="small" @click="openPreview(claim)">
                Review invoice
              </ElButton>
              <ElButton size="small" @click="openClaimChat(claim)">Chat teller</ElButton>
            </div>

            <p v-else-if="claim.claim_status === 'APPROVED'" class="text-xs text-success">
              Linked. Open My Bills &amp; Payments to view or pay.
              <ElButton link type="success" size="small" @click="$router.push('/my-bills')"
                >Go to My Bills</ElButton
              >
            </p>
            <p v-else-if="claim.rejection_reason || claim.staff_notes" class="text-xs text-g-500">
              {{ claim.rejection_reason || claim.staff_notes }}
            </p>
          </div>
        </div>
      </ElCard>
    </div>

    <ElCard v-if="preview" shadow="never" v-loading="previewLoading">
      <template #header>
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="font-semibold">Invoice preview</span>
          <ElTag :type="preview.can_accept ? 'warning' : 'success'" size="small">
            {{ preview.can_accept ? 'Awaiting your acceptance' : 'Already accepted' }}
          </ElTag>
        </div>
      </template>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div class="space-y-1">
          <div>
            <span class="text-g-500">Invoice #</span>
            <span class="ml-2 font-medium">{{ preview.invoice.invoice_number || '—' }}</span>
          </div>
          <div>
            <span class="text-g-500">Date</span>
            <span class="ml-2">{{ preview.invoice.business_date || '—' }}</span>
          </div>
          <div>
            <span class="text-g-500">Buyer</span>
            <span class="ml-2">{{ preview.invoice.buyer_name || '—' }}</span>
          </div>
          <div>
            <span class="text-g-500">TIN</span>
            <span class="ml-2">{{ preview.invoice.buyer_tin || '—' }}</span>
          </div>
          <div>
            <span class="text-g-500">Address</span>
            <span class="ml-2">{{ preview.invoice.buyer_address || '—' }}</span>
          </div>
        </div>
        <div class="space-y-1">
          <div>
            <span class="text-g-500">Gross</span>
            <span class="ml-2">{{ preview.invoice.gross_amount || '—' }}</span>
          </div>
          <div>
            <span class="text-g-500">Tax</span>
            <span class="ml-2">{{ preview.invoice.tax_amount || '—' }}</span>
          </div>
          <div>
            <span class="text-g-500">Total</span>
            <span class="ml-2 font-semibold">{{ preview.invoice.total_charge_amount || '—' }}</span>
          </div>
          <div>
            <span class="text-g-500">Creating teller</span>
            <span class="ml-2">{{ preview.creating_teller?.name || '—' }}</span>
          </div>
        </div>
      </div>

      <div class="mt-4 overflow-x-auto">
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b border-g-300 text-left text-g-500">
              <th class="py-2 pr-2 font-medium">Description</th>
              <th class="py-2 pr-2 font-medium">Qty</th>
              <th class="py-2 pr-2 font-medium">Rate</th>
              <th class="py-2 font-medium">Line total</th>
            </tr>
          </thead>
          <tbody>
            <tr
              v-for="(line, idx) in preview.invoice.lines"
              :key="idx"
              class="border-b border-g-200"
            >
              <td class="py-2 pr-2">{{ line.description }}</td>
              <td class="py-2 pr-2">{{ line.quantity }}</td>
              <td class="py-2 pr-2">{{ line.unit_rate }}</td>
              <td class="py-2">{{ line.line_total }}</td>
            </tr>
          </tbody>
        </table>
        <p v-if="!preview.invoice.lines?.length" class="text-xs text-g-500 mt-2">No line items.</p>
      </div>

      <div v-if="preview.can_accept" class="mt-5 flex flex-wrap gap-2">
        <ElButton type="primary" :loading="accepting" @click="acceptPreview">
          Accept → My Bills
        </ElButton>
        <ElButton :loading="declining" @click="declinePreview">Decline</ElButton>
        <ElButton @click="openClaimChatById(preview.claim_id)">Chat teller about this</ElButton>
      </div>
      <div v-else class="mt-5">
        <ElButton type="success" @click="$router.push('/my-bills')">Go to My Bills</ElButton>
      </div>
    </ElCard>
  </div>
</template>

<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { fetchGetUserInfo } from '@/api/auth'
  import { fetchPortalProfile } from '@/api/registration'
  import { useAuthoritativeRealtimeRefresh } from '@/composables/useAuthoritativeRealtimeRefresh'
  import mittBus from '@/utils/sys/mittBus'
  import {
    acceptPortalBillClaim,
    cancelPortalBillClaim,
    createPortalBillClaim,
    declinePortalBillClaim,
    fetchPortalBillClaims,
    previewPortalBillClaim,
    verifyPortalBillClaim,
    type BillClaimItem,
    type BillClaimPreview
  } from '@/api/billClaims'

  defineOptions({ name: 'CustomerClaimBill' })

  const router = useRouter()
  const loading = ref(false)
  const saving = ref(false)
  const verifyingId = ref<number | null>(null)
  const cancellingId = ref<number | null>(null)
  const accepting = ref(false)
  const declining = ref(false)
  const previewLoading = ref(false)
  const activeCustomerId = ref<number | null>(null)
  const invoiceNumber = ref('')
  const claims = ref<BillClaimItem[]>([])
  const verifyCodes = reactive<Record<number, string>>({})
  const selectedClaimId = ref<number | null>(null)
  const preview = ref<BillClaimPreview | null>(null)
  const realtime = useAuthoritativeRealtimeRefresh({
    scope: 'bill_claims',
    refresh: () => loadClaims(),
    isBusy: () =>
      saving.value ||
      verifyingId.value !== null ||
      cancellingId.value !== null ||
      accepting.value ||
      declining.value
  })

  function labelStatus(status: string) {
    return status.replaceAll('_', ' ')
  }

  function statusTag(status: string) {
    if (status === 'APPROVED') return 'success'
    if (status === 'REJECTED' || status === 'CANCELLED' || status === 'EXPIRED') return 'danger'
    if (
      status === 'PENDING_VERIFICATION' ||
      status === 'PENDING_TELLER_REVIEW' ||
      status === 'PENDING_CUSTOMER_ACCEPTANCE'
    ) {
      return 'warning'
    }
    return 'info'
  }

  async function loadClaims() {
    loading.value = true
    try {
      const [profile, me] = await Promise.all([fetchPortalProfile(), fetchGetUserInfo()])
      realtime.startForUser(Number((me as any).id || (me as any).userId))
      const link = (profile as any).customer_links?.find((item: any) => item.is_active)
      activeCustomerId.value = link?.customer_id || null
      if (!activeCustomerId.value) {
        claims.value = []
        return
      }
      const list = await fetchPortalBillClaims()
      claims.value = list.data || []

      const pending = claims.value.find((c) => c.claim_status === 'PENDING_CUSTOMER_ACCEPTANCE')
      if (pending && (!preview.value || preview.value.claim_id !== pending.id)) {
        await openPreview(pending)
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load bill claims.')
    } finally {
      loading.value = false
    }
  }

  async function submitClaim() {
    if (!activeCustomerId.value || !invoiceNumber.value.trim()) return
    saving.value = true
    try {
      const result = await createPortalBillClaim(invoiceNumber.value.trim())
      ElMessage.success(result.message || 'Claim started.')
      invoiceNumber.value = ''
      await loadClaims()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to start claim.')
    } finally {
      saving.value = false
    }
  }

  async function verifyClaim(claim: BillClaimItem) {
    const code = (verifyCodes[claim.id] || '').trim()
    if (!code) {
      ElMessage.warning('Enter the verification code.')
      return
    }
    verifyingId.value = claim.id
    try {
      const result = await verifyPortalBillClaim(claim.id, code)
      ElMessage.success(result.message || 'Verified. Review the invoice to accept.')
      delete verifyCodes[claim.id]
      await loadClaims()
      await openPreview({ ...claim, claim_status: 'PENDING_CUSTOMER_ACCEPTANCE' })
    } catch (error: any) {
      ElMessage.error(error?.message || 'Verification failed.')
    } finally {
      verifyingId.value = null
    }
  }

  async function cancelClaim(claim: BillClaimItem) {
    cancellingId.value = claim.id
    try {
      await cancelPortalBillClaim(claim.id)
      ElMessage.success('Claim cancelled.')
      if (preview.value?.claim_id === claim.id) preview.value = null
      await loadClaims()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to cancel claim.')
    } finally {
      cancellingId.value = null
    }
  }

  async function openPreview(claim: BillClaimItem) {
    selectedClaimId.value = claim.id
    previewLoading.value = true
    try {
      preview.value = await previewPortalBillClaim(claim.id)
    } catch (error: any) {
      preview.value = null
      ElMessage.error(error?.message || 'Unable to load invoice preview.')
    } finally {
      previewLoading.value = false
    }
  }

  async function acceptPreview() {
    if (!preview.value?.can_accept) return
    accepting.value = true
    try {
      const result = await acceptPortalBillClaim(preview.value.claim_id)
      ElMessage.success(result.message || 'Invoice added to My Bills.')
      preview.value = null
      await loadClaims()
      await router.push('/my-bills')
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to accept claim.')
    } finally {
      accepting.value = false
    }
  }

  async function declinePreview() {
    if (!preview.value?.can_accept) return
    try {
      const { value } = await ElMessageBox.prompt(
        'Optional note for the teller (invoice will not be added to My Bills).',
        'Decline invoice',
        {
          confirmButtonText: 'Decline',
          cancelButtonText: 'Keep reviewing',
          inputPlaceholder: 'Reason (optional)',
          inputType: 'textarea'
        }
      )
      declining.value = true
      const result = await declinePortalBillClaim(preview.value.claim_id, value || undefined)
      ElMessage.success(result.message || 'Claim declined.')
      preview.value = null
      await loadClaims()
    } catch (error: any) {
      if (error === 'cancel' || error === 'close') return
      ElMessage.error(error?.message || 'Unable to decline claim.')
    } finally {
      declining.value = false
    }
  }

  function openClaimChat(claim: BillClaimItem) {
    openClaimChatById(claim.id)
  }

  function openClaimChatById(billClaimId: number) {
    mittBus.emit('openChat', { billClaimId })
  }

  onMounted(loadClaims)
</script>
