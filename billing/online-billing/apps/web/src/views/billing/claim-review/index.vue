<template>
  <div class="bill-claim-review max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-sm"
    >
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Bill Claim Review</h1>
          <span
            class="bg-amber-500/20 text-amber-200 border border-amber-400/30 text-xs px-2.5 py-0.5 rounded-full font-medium"
            >Teller Workstation</span
          >
        </div>
        <p class="text-slate-300 text-sm max-w-2xl">
          Pending claims need identity verification. After you verify, the customer must still
          accept the invoice preview before it appears on My Bills. Switch to Approved for verified
          or accepted claims.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <ElButton
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="$router.push('/walk-in-billing')"
        >
          Walk-in Billing
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

    <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
      <ElCard shadow="never" v-loading="loading">
        <template #header>
          <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <span class="font-semibold">Claims</span>
            <div class="flex flex-wrap items-center gap-2">
              <ElRadioGroup v-model="statusFilter" size="small" @change="loadClaims">
                <ElRadioButton value="PENDING_TELLER_REVIEW">Pending</ElRadioButton>
                <ElRadioButton value="APPROVED">Approved</ElRadioButton>
                <ElRadioButton value="REJECTED">Rejected</ElRadioButton>
                <ElRadioButton value="ALL">All</ElRadioButton>
              </ElRadioGroup>
              <ElTag>{{ claims.length }}</ElTag>
            </div>
          </div>
        </template>
        <ElEmpty v-if="claims.length === 0" :description="emptyLabel" />
        <ElTable v-else :data="claims" stripe @row-click="openClaim">
          <ElTableColumn label="Invoice #" min-width="150">
            <template #default="{ row }">
              <span class="font-mono font-semibold">{{
                row.invoice_number || row.invoice?.invoice_number || `Claim #${row.id}`
              }}</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Status" width="130">
            <template #default="{ row }">
              <ElTag :type="statusTag(row.claim_status)" size="small">{{
                labelStatus(row.claim_status)
              }}</ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Customer" min-width="140">
            <template #default="{ row }">
              {{ row.customer?.name || row.user?.name || '—' }}
            </template>
          </ElTableColumn>
          <ElTableColumn label="Actions" width="100" fixed="right">
            <template #default="{ row }">
              <ElButton size="small" @click.stop="openClaim(row)">Open</ElButton>
            </template>
          </ElTableColumn>
        </ElTable>
      </ElCard>

      <ElCard shadow="never" v-loading="detailLoading">
        <template #header>
          <span class="font-semibold">{{ active ? `Claim #${active.id}` : 'Select a claim' }}</span>
        </template>
        <ElEmpty
          v-if="!active"
          description="Select a claim to review or look up the invoice number."
        />
        <div v-else class="space-y-4">
          <div class="flex flex-wrap gap-2">
            <ElTag :type="statusTag(active.claim_status)" size="small">{{
              labelStatus(active.claim_status)
            }}</ElTag>
            <ElTag effect="plain" size="small">{{ active.verification_route }}</ElTag>
          </div>
          <div class="rounded-xl border border-g-200 bg-g-100/40 p-4 space-y-2">
            <div class="text-xs text-g-500 uppercase tracking-wide">Invoice number</div>
            <div class="flex flex-wrap items-center gap-2">
              <div class="font-mono text-xl font-semibold tracking-wide">
                {{
                  active.invoice_number ||
                  active.invoice?.invoice_number ||
                  (active.invoice?.id ? `#${active.invoice.id}` : '—')
                }}
              </div>
              <ElButton
                v-if="active.invoice_number || active.invoice?.invoice_number"
                size="small"
                @click="
                  copyBillNumber(active.invoice_number || active.invoice?.invoice_number || '')
                "
              >
                Copy
              </ElButton>
            </div>
          </div>
          <div class="text-sm text-g-600 space-y-1">
            <div>Requester: {{ active.user?.name || active.user?.email || '—' }}</div>
            <div>Portal customer: {{ active.customer?.name || '—' }}</div>
            <div>Walk-in buyer: {{ active.invoice?.walk_in_customer?.buyer_name || '—' }}</div>
            <div>Amount: {{ active.invoice?.total_charge_amount || '—' }}</div>
          </div>

          <template v-if="active.claim_status === 'PENDING_TELLER_REVIEW'">
            <ElFormItem label="Decision notes">
              <ElInput v-model="notes" type="textarea" :rows="3" maxlength="1000" />
            </ElFormItem>

            <div class="flex flex-wrap gap-2">
              <ElButton type="success" :loading="saving" @click="decide('APPROVE')">
                Verify identity
              </ElButton>
              <ElButton type="danger" :loading="saving" @click="decide('REJECT')">Reject</ElButton>
            </div>
          </template>
          <template
            v-else-if="
              active.claim_status === 'PENDING_CUSTOMER_ACCEPTANCE' ||
              active.claim_status === 'APPROVED'
            "
          >
            <ElAlert
              type="info"
              :closable="false"
              show-icon
              :title="
                active.claim_status === 'PENDING_CUSTOMER_ACCEPTANCE'
                  ? 'Identity verified. Waiting for the customer to accept the invoice preview.'
                  : 'Customer accepted. Invoice is linked on My Bills.'
              "
            />
            <ElButton class="mt-3" @click="openClaimChat">Chat with customer</ElButton>
          </template>
          <ElAlert
            v-else
            type="info"
            :closable="false"
            show-icon
            title="This claim is no longer pending. Use the invoice number above, or open Walk-in Billing for the posted bill."
          />
        </div>
      </ElCard>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import { fetchGetUserInfo } from '@/api/auth'
  import { useAuthoritativeRealtimeRefresh } from '@/composables/useAuthoritativeRealtimeRefresh'
  import mittBus from '@/utils/sys/mittBus'
  import {
    decideTellerBillClaim,
    fetchTellerBillClaim,
    fetchTellerBillClaims,
    type BillClaimItem,
    type BillClaimStatus
  } from '@/api/billClaims'

  defineOptions({ name: 'TellerBillClaimReview' })

  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const claims = ref<BillClaimItem[]>([])
  const active = ref<BillClaimItem | null>(null)
  const notes = ref('')
  const statusFilter = ref<'PENDING_TELLER_REVIEW' | 'APPROVED' | 'REJECTED' | 'ALL'>(
    'PENDING_TELLER_REVIEW'
  )
  const realtime = useAuthoritativeRealtimeRefresh({
    scope: 'bill_claims',
    refresh: () => loadClaims(),
    isBusy: () => saving.value || detailLoading.value
  })

  const emptyLabel = computed(() => {
    if (statusFilter.value === 'PENDING_TELLER_REVIEW') return 'No claims waiting for review.'
    if (statusFilter.value === 'APPROVED') return 'No approved claims yet.'
    if (statusFilter.value === 'REJECTED') return 'No rejected claims yet.'
    return 'No claims found.'
  })

  function labelStatus(status: string) {
    return status.replaceAll('_', ' ')
  }

  function statusTag(status: BillClaimStatus | string) {
    if (status === 'APPROVED') return 'success'
    if (status === 'REJECTED' || status === 'EXPIRED' || status === 'CANCELLED') return 'danger'
    if (
      status === 'PENDING_TELLER_REVIEW' ||
      status === 'PENDING_VERIFICATION' ||
      status === 'PENDING_CUSTOMER_ACCEPTANCE'
    ) {
      return 'warning'
    }
    return 'info'
  }

  function openClaimChat() {
    if (!active.value?.id) return
    mittBus.emit('openChat', { billClaimId: active.value.id })
  }

  async function copyBillNumber(number: string) {
    if (!number) return
    try {
      await navigator.clipboard.writeText(number)
      ElMessage.success(`Copied ${number}`)
    } catch {
      ElMessage.info(number)
    }
  }

  async function loadClaims() {
    loading.value = true
    try {
      const list = await fetchTellerBillClaims({ status: statusFilter.value })
      claims.value = list.data || []
      if (active.value && !claims.value.some((c) => c.id === active.value!.id)) {
        active.value = null
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load claims.')
    } finally {
      loading.value = false
    }
  }

  async function openClaim(row: BillClaimItem) {
    detailLoading.value = true
    notes.value = ''
    try {
      active.value = await fetchTellerBillClaim(row.id)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to open claim.')
      active.value = null
    } finally {
      detailLoading.value = false
    }
  }

  async function decide(decision: 'APPROVE' | 'REJECT') {
    if (!active.value) return
    saving.value = true
    try {
      const result = await decideTellerBillClaim(active.value.id, {
        decision,
        notes: notes.value || undefined
      })
      ElMessage.success(result.message || 'Decision saved.')
      active.value = null
      await loadClaims()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to decide claim.')
    } finally {
      saving.value = false
    }
  }

  onMounted(async () => {
    const me = await fetchGetUserInfo()
    realtime.startForOrganization(
      Number((me as any).organization?.id || (me as any).organization_id)
    )
    await loadClaims()
  })
</script>
