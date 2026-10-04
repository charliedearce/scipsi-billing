<template>
  <div class="walk-in-billing max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <div
      class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-gradient-to-r from-slate-900 to-slate-800 text-white p-6 rounded-2xl shadow-sm"
    >
      <div class="space-y-1">
        <div class="flex items-center gap-2.5">
          <h1 class="text-xl sm:text-2xl font-bold tracking-tight">Walk-in Billing</h1>
          <span
            class="bg-amber-500/20 text-amber-200 border border-amber-400/30 text-xs px-2.5 py-0.5 rounded-full font-medium"
            >Teller Workstation</span
          >
        </div>
        <p class="text-slate-300 text-sm max-w-2xl">
          Quick counter bill: enter a display name and optional notes, post the invoice, then hand
          the customer the invoice number to claim in the portal. TIN and address are optional.
          Portal file-upload requests stay on Billing Request Queue.
        </p>
      </div>
      <div class="flex items-center gap-3">
        <ElSelect
          v-if="locations.length > 1"
          v-model="locationId"
          placeholder="Location"
          class="!w-48"
          @change="loadWalkIns"
        >
          <ElOption
            v-for="loc in locations"
            :key="loc.id"
            :label="loc.name || loc.code || `Location #${loc.id}`"
            :value="loc.id"
          />
        </ElSelect>
        <ElButton
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="$router.push('/billing-request-queue')"
        >
          Request Queue
        </ElButton>
        <ElButton
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="$router.push('/bill-claim-review')"
        >
          Claim Review
        </ElButton>
        <ElButton
          :loading="loading"
          plain
          class="!bg-white/10 !text-white !border-white/20 hover:!bg-white/20"
          @click="loadWalkIns"
        >
          Refresh
        </ElButton>
        <ElButton
          type="primary"
          :disabled="!locationId"
          class="!bg-amber-500 hover:!bg-amber-400 !border-none !text-slate-950 font-semibold"
          @click="createOpen = true"
        >
          New walk-in
        </ElButton>
      </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6">
      <ElCard shadow="never" v-loading="loading" class="xl:col-span-4">
        <template #header>
          <div class="flex items-center justify-between">
            <span class="font-semibold">Recent walk-ins</span>
            <ElTag>{{ walkIns.length }} shown</ElTag>
          </div>
        </template>
        <ElEmpty v-if="walkIns.length === 0" description="No walk-in buyers yet." />
        <ElTable v-else :data="walkIns" stripe @row-click="openWalkIn">
          <ElTableColumn prop="buyer_name" label="Buyer" min-width="140" />
          <ElTableColumn label="Bill #" min-width="150">
            <template #default="{ row }">
              <span v-if="latestPostedNumber(row)" class="font-mono font-semibold">{{
                latestPostedNumber(row)
              }}</span>
              <span v-else class="text-g-500">—</span>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Contact" min-width="120">
            <template #default="{ row }">
              {{ row.contact_mobile || row.contact_email || '—' }}
            </template>
          </ElTableColumn>
          <ElTableColumn label="Linked" width="100">
            <template #default="{ row }">
              <ElTag :type="row.customer_id ? 'success' : 'info'" size="small">
                {{ row.customer_id ? 'Portal' : 'Shell' }}
              </ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn label="Actions" width="100" fixed="right">
            <template #default="{ row }">
              <ElButton size="small" @click.stop="openWalkIn(row)">Open</ElButton>
            </template>
          </ElTableColumn>
        </ElTable>
      </ElCard>

      <ElCard shadow="never" v-loading="detailLoading" class="xl:col-span-8">
        <template #header>
          <span class="font-semibold">{{
            active ? `Bill for ${active.buyer_name}` : 'Select a walk-in'
          }}</span>
        </template>
        <ElEmpty
          v-if="!active"
          description="Select a walk-in buyer to create or continue a draft."
        />
        <div v-else class="space-y-4">
          <div class="text-sm text-g-600 space-y-1">
            <div>Name on bill: {{ active.buyer_name }}</div>
            <div v-if="active.buyer_tin" class="flex flex-wrap items-center gap-2">
              <span>TIN: {{ active.buyer_tin }}</span>
              <ElTag v-if="incompleteTin" type="danger" size="small">Incomplete</ElTag>
              <ElButton
                v-if="incompleteTin && !hasPostedInvoice"
                size="small"
                type="warning"
                plain
                :loading="saving"
                @click="clearIncompleteTin"
              >
                Clear TIN
              </ElButton>
            </div>
            <div v-if="active.buyer_address">Address: {{ active.buyer_address }}</div>
            <div v-if="active.contact_mobile">Mobile: {{ active.contact_mobile }}</div>
            <div v-if="active.contact_email">Email: {{ active.contact_email }}</div>
            <ElAlert
              v-if="incompleteTin"
              type="warning"
              :closable="false"
              show-icon
              class="!mt-2"
              title="Incomplete TIN blocks Post invoice. Clear it or enter at least 9 digits, then post. After posting, give the customer the invoice number for Claim Bill."
            />
            <div v-else-if="!active.buyer_tin && !active.buyer_address" class="text-g-500">
              No TIN or address captured — fine for counter posting. After post, customer uses Claim
              Bill with the invoice number (links ownership; does not rewrite this bill).
            </div>
          </div>

          <div class="art-card-sm space-y-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
              <div>
                <p class="text-xs text-g-500">Bill #</p>
                <p class="font-mono text-lg font-semibold tracking-wide text-g-900">{{
                  postedNumber ||
                  draftPreview?.invoice_number ||
                  (draftPreview ? `Draft #${draftPreview.id}` : 'New bill')
                }}</p>
              </div>
              <div class="text-sm text-g-700">
                <p class="text-xs text-g-500">Account</p>
                <p>{{ active.buyer_name }}</p>
                <CustomerTaxBadges
                  :customer-id="active.customer_id"
                  :business-date="businessDate"
                  class="mt-1"
                />
                <p v-if="active.buyer_tin" class="text-xs text-g-500">TIN {{ active.buyer_tin }}</p>
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
          </div>

          <div v-if="!draftPreview" class="space-y-3">
            <div class="flex flex-wrap gap-2">
              <ElDatePicker
                v-model="businessDate"
                type="date"
                value-format="YYYY-MM-DD"
                placeholder="Business date"
              />
              <ElButton
                type="primary"
                :loading="saving"
                :disabled="!businessDate"
                @click="startDraft"
              >
                Create invoice draft
              </ElButton>
            </div>
          </div>

          <div v-if="draftPreview" class="space-y-3">
            <InvoiceBillingItemsGrid
              v-model:lines="draftLines"
              v-model:surcharge-mode="surchargeMode"
              v-model:dangerous-cargo-percent="dangerousCargoPercent"
              :tariffs="tariffs"
              :route-type="routeType"
              :draft="draftPreview"
              :customer-id="draftPreview?.customer_id"
              :business-date="businessDate"
            />
            <div class="flex flex-col sm:flex-row sm:flex-wrap gap-2">
              <ElButton type="success" class="w-full sm:w-auto" :loading="saving" @click="postDraft"
                >Post invoice</ElButton
              >
            </div>
          </div>

          <ElAlert
            v-if="postedNumber"
            type="success"
            :closable="false"
            show-icon
            :title="`Posted invoice ${postedNumber}. Give this number to the customer for Claim Bill.`"
          />

          <div
            v-if="postedInvoices.length"
            class="space-y-2 border border-g-200 rounded-xl p-4 bg-g-100/40"
          >
            <h3 class="font-semibold text-sm">Posted bill numbers (for Claim Bill)</h3>
            <div
              v-for="inv in postedInvoices"
              :key="inv.id"
              class="flex flex-wrap items-center justify-between gap-2"
            >
              <div class="font-mono text-base font-semibold tracking-wide">
                {{ inv.invoice_number || `Invoice #${inv.id}` }}
              </div>
              <div class="flex items-center gap-2">
                <ElTag size="small" type="success">POSTED</ElTag>
                <span class="text-xs text-g-500">{{ inv.business_date || '' }}</span>
                <ElButton
                  v-if="inv.invoice_number"
                  size="small"
                  @click="copyBillNumber(inv.invoice_number)"
                >
                  Copy
                </ElButton>
              </div>
            </div>
            <p class="text-xs text-g-500">
              Customer path: log in → Claim Bill → paste this number.
            </p>
          </div>
        </div>
      </ElCard>
    </div>

    <ElDrawer v-model="createOpen" title="Quick walk-in" size="440px" destroy-on-close>
      <ElForm label-position="top" @submit.prevent="createWalkIn">
        <p class="text-sm text-g-600 mb-4">
          Only a name is required. Vessel, voyage, type and route are captured on the invoice draft.
          Leave TIN and address blank unless the customer has them ready.
        </p>
        <ElFormItem label="Name on bill" required>
          <ElInput
            v-model="createForm.buyer_name"
            maxlength="255"
            placeholder="How the buyer should appear on the invoice"
          />
        </ElFormItem>
        <ElFormItem label="Notes (optional)">
          <ElInput
            v-model="createForm.notes"
            type="textarea"
            :rows="3"
            maxlength="150"
            show-word-limit
            placeholder="Alphanumeric notes copied onto the bill if valid"
          />
        </ElFormItem>

        <ElCollapse v-model="createMoreOpen" class="mb-4">
          <ElCollapseItem title="More details (optional)" name="more">
            <ElFormItem label="TIN">
              <ElInput
                v-model="createForm.buyer_tin"
                maxlength="32"
                placeholder="Leave blank if not available"
              />
              <p class="mt-1 text-xs text-g-500"> Optional. If entered, use at least 9 digits. </p>
            </ElFormItem>
            <ElFormItem label="Branch code">
              <ElInput v-model="createForm.buyer_branch_code" maxlength="10" />
            </ElFormItem>
            <ElFormItem label="Address">
              <ElInput
                v-model="createForm.buyer_address"
                type="textarea"
                :rows="2"
                maxlength="1024"
              />
            </ElFormItem>
            <ElFormItem label="Mobile">
              <ElInput v-model="createForm.contact_mobile" maxlength="32" />
            </ElFormItem>
            <ElFormItem label="Email">
              <ElInput v-model="createForm.contact_email" maxlength="255" />
            </ElFormItem>
          </ElCollapseItem>
        </ElCollapse>

        <ElButton
          type="primary"
          class="w-full"
          :loading="saving"
          :disabled="!locationId || !createForm.buyer_name.trim()"
          @click="createWalkIn"
        >
          Create walk-in
        </ElButton>
      </ElForm>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref, watch } from 'vue'
  import { ElMessage } from 'element-plus'
  import InvoiceShipmentFields from '@/components/business/InvoiceShipmentFields.vue'
  import InvoiceBillingItemsGrid from '@/components/business/InvoiceBillingItemsGrid.vue'
  import CustomerTaxBadges from '@/components/business/CustomerTaxBadges.vue'
  import { fetchGetUserInfo } from '@/api/auth'
  import {
    createWalkInCustomer,
    createWalkInInvoiceDraft,
    fetchWalkInCustomer,
    fetchWalkInCustomers,
    updateWalkInCustomer,
    type WalkInCustomerItem
  } from '@/api/walkInBilling'
  import {
    fetchEffectiveTariffs,
    fetchInvoiceDraft,
    invoiceShipmentPayload,
    NOTES_PATTERN,
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

  defineOptions({ name: 'TellerWalkInBilling' })

  const loading = ref(false)
  const detailLoading = ref(false)
  const saving = ref(false)
  const createOpen = ref(false)
  const createMoreOpen = ref<string[]>([])
  const locations = ref<Array<{ id: number; name?: string; code?: string }>>([])
  const locationId = ref<number | null>(null)
  const walkIns = ref<WalkInCustomerItem[]>([])
  const active = ref<WalkInCustomerItem | null>(null)
  const businessDate = ref(manilaBusinessDate())
  const vesselId = ref<number | null>(null)
  const vesselName = ref('')
  const voyage = ref('')
  const shipmentNotes = ref('')
  const movementType = ref<MovementType | ''>('')
  const routeType = ref<RouteType | ''>('')
  const tariffs = ref<Tariff[]>([])
  const draftLines = ref<BillingDraftLine[]>([emptyBillingLine()])
  const draftPreview = ref<InvoiceDraft | null>(null)
  const postedNumber = ref<string | null>(null)
  const surchargeMode = ref<SurchargeMode>('FUEL')
  const dangerousCargoPercent = ref<string | null>(null)

  const createForm = reactive({
    buyer_name: '',
    notes: '',
    buyer_tin: '',
    buyer_branch_code: '',
    buyer_address: '',
    contact_mobile: '',
    contact_email: ''
  })

  function resetCreateForm() {
    Object.assign(createForm, {
      buyer_name: '',
      notes: '',
      buyer_tin: '',
      buyer_branch_code: '',
      buyer_address: '',
      contact_mobile: '',
      contact_email: ''
    })
    createMoreOpen.value = []
  }

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

  function warnShipment() {
    ElMessage.warning(
      validateInvoiceShipment({
        vessel_id: vesselId.value,
        voyage: voyage.value,
        notes: shipmentNotes.value,
        movement_type: movementType.value,
        route_type: routeType.value
      }) || 'Complete vessel, voyage, notes, type and route.'
    )
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

  const incompleteTin = computed(() => {
    const tin = (active.value?.buyer_tin || '').trim()
    if (!tin) return false
    return tin.replace(/\D/g, '').length < 9
  })

  const hasPostedInvoice = computed(() =>
    (active.value?.invoices || []).some((inv) => inv.status === 'POSTED')
  )

  const postedInvoices = computed(() =>
    (active.value?.invoices || []).filter((inv) => inv.status === 'POSTED')
  )

  function latestPostedNumber(row: WalkInCustomerItem): string | null {
    const posted = (row.invoices || []).filter((inv) => inv.status === 'POSTED')
    if (!posted.length) return null
    const latest = [...posted].sort((a, b) => b.id - a.id)[0]
    return latest.invoice_number || null
  }

  async function clearIncompleteTin() {
    if (!active.value || !incompleteTin.value) return
    saving.value = true
    try {
      active.value = await updateWalkInCustomer(active.value.id, { buyer_tin: null })
      ElMessage.success('TIN cleared. You can post the invoice now.')
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to clear TIN.')
    } finally {
      saving.value = false
    }
  }

  async function copyBillNumber(number: string) {
    try {
      await navigator.clipboard.writeText(number)
      ElMessage.success(`Copied ${number}`)
    } catch {
      ElMessage.info(number)
    }
  }

  function buildItems() {
    return draftLines.value
      .filter((line) => line.tariff_version_id && line.quantity)
      .map((line) => ({
        tariff_version_id: line.tariff_version_id,
        tariff_code: line.tariff_code || undefined,
        service_type: line.service_type || undefined,
        quantity: line.quantity
      }))
  }

  async function bootstrap() {
    const me = await fetchGetUserInfo()
    locations.value = ((me as any).locations || []) as Array<{
      id: number
      name?: string
      code?: string
    }>
    const primary =
      locations.value.find((loc) => (loc as any).pivot?.is_primary) || locations.value[0] || null
    locationId.value = primary?.id ?? null
    tariffs.value = await fetchEffectiveTariffs()
    await loadWalkIns()
  }

  async function loadWalkIns() {
    loading.value = true
    try {
      const list = await fetchWalkInCustomers({
        location_id: locationId.value || undefined
      })
      walkIns.value = list.data || []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load walk-ins.')
    } finally {
      loading.value = false
    }
  }

  async function openWalkIn(row: WalkInCustomerItem, preferredNotes?: string) {
    detailLoading.value = true
    postedNumber.value = null
    draftPreview.value = null
    draftLines.value = [emptyBillingLine()]
    resetShipment()
    if (preferredNotes && NOTES_PATTERN.test(preferredNotes.trim())) {
      shipmentNotes.value = preferredNotes.trim()
    }
    try {
      active.value = await fetchWalkInCustomer(row.id)
      const draft = (active.value.invoices || []).find((inv) => inv.status === 'DRAFT')
      if (draft) {
        const loaded = await fetchInvoiceDraft(draft.id)
        draftPreview.value = loaded
        applyShipmentFromDraft(loaded)
        if (loaded.items?.length) {
          draftLines.value = loaded.items.map(billingLineFromDraftItem)
        }
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to open walk-in.')
      active.value = null
    } finally {
      detailLoading.value = false
    }
  }

  async function createWalkIn() {
    if (!locationId.value || !createForm.buyer_name.trim()) return
    const tinDigits = createForm.buyer_tin.replace(/\D/g, '')
    if (createForm.buyer_tin.trim() && tinDigits.length < 9) {
      ElMessage.warning('TIN is optional. Leave it blank or enter at least 9 digits.')
      return
    }
    const notes = createForm.notes.trim()
    saving.value = true
    try {
      const created = await createWalkInCustomer({
        location_id: locationId.value,
        buyer_name: createForm.buyer_name.trim(),
        buyer_tin: tinDigits ? createForm.buyer_tin.trim() : undefined,
        buyer_branch_code: createForm.buyer_branch_code.trim() || undefined,
        buyer_address: createForm.buyer_address.trim() || undefined,
        contact_mobile: createForm.contact_mobile.trim() || undefined,
        contact_email: createForm.contact_email.trim() || undefined
      })
      ElMessage.success('Walk-in created. Add lines and post when ready.')
      createOpen.value = false
      resetCreateForm()
      await loadWalkIns()
      await openWalkIn(created, notes)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to create walk-in.')
    } finally {
      saving.value = false
    }
  }

  async function startDraft() {
    if (!active.value || !businessDate.value) return
    const shipment = currentShipment()
    if (!shipment) {
      warnShipment()
      return
    }
    saving.value = true
    try {
      const draft = await createWalkInInvoiceDraft(active.value.id, {
        business_date: businessDate.value,
        ...shipment
      })
      draftPreview.value = draft
      ElMessage.success('Invoice draft created.')
      await openWalkIn(active.value, shipment.notes)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to create draft.')
    } finally {
      saving.value = false
    }
  }

  async function postDraft() {
    if (!draftPreview.value) return
    const items = buildItems()
    if (!items.length) {
      ElMessage.warning('Add at least one tariff line.')
      return
    }
    const shipment = currentShipment()
    if (!shipment) {
      warnShipment()
      return
    }
    saving.value = true
    try {
      let draft = await updateInvoiceDraft(draftPreview.value.id, {
        expected_version: draftPreview.value.lock_version,
        business_date: businessDate.value || manilaBusinessDate(),
        ...shipment,
        surcharge_mode: surchargeMode.value,
        dangerous_cargo_percent:
          surchargeMode.value === 'DANGEROUS_CARGO' ? dangerousCargoPercent.value : null,
        items
      })
      draft = await postInvoiceDraft(draft.id, { expected_version: draft.lock_version })
      postedNumber.value = draft.invoice_number || String(draft.id)
      draftPreview.value = null
      ElMessage.success(`Invoice ${postedNumber.value} posted.`)
      if (active.value) await openWalkIn(active.value)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to post invoice.')
    } finally {
      saving.value = false
    }
  }

  onMounted(bootstrap)
</script>
