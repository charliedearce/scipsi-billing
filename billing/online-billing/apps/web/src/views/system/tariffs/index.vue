<template>
  <div class="pricing-workspace-page p-4">
    <ElCard shadow="never" class="mb-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-800">Tariffs &amp; Fuel Surcharges</h2>
            <ElTag type="info" size="small" effect="plain">P2-10 / W29</ElTag>
          </div>
          <p class="mt-1 text-sm text-gray-500">
            Maintain versioned tariff classifications, scheduled fuel-price bands, and transparent pricing rules.
            Published rules affect new calculations only; issued invoices retain their snapshots.
          </p>
        </div>
        <ElButton @click="refreshAll">Refresh</ElButton>
      </div>
      <ElAlert
        class="mt-3"
        title="Pricing safeguards: tax, PPA, and fuel applicability are resolved per tariff line on the server. Overlapping effective windows and fuel bands are rejected."
        type="info"
        :closable="false"
        show-icon
      />
    </ElCard>

    <ElCard shadow="never">
      <ElTabs v-model="activeTab">
        <ElTabPane label="Tariff Catalogue" name="tariffs">
          <div class="flex flex-wrap items-center justify-between gap-3 py-2">
            <div class="text-sm text-gray-500">{{ tariffs.length }} tariff master records</div>
            <ElButton type="primary" @click="showTariffDialog = true">New Tariff</ElButton>
          </div>

          <ElTable :data="tariffs" v-loading="loadingTariffs" stripe border>
            <ElTableColumn prop="tariff_code" label="Code" width="150">
              <template #default="{ row }">
                <span class="font-mono text-xs font-semibold text-blue-700">{{ row.tariff_code }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="name" label="Tariff" min-width="210" />
            <ElTableColumn label="Service / Route" min-width="180">
              <template #default="{ row }">{{ row.service_type }} / {{ row.route_type }}</template>
            </ElTableColumn>
            <ElTableColumn prop="unit_of_measure" label="Unit" width="120" />
            <ElTableColumn label="Current Version" width="150">
              <template #default="{ row }">
                <template v-if="currentVersion(row)">
                  v{{ currentVersion(row)?.version_number }} · {{ currentVersion(row)?.rate }}
                </template>
                <span v-else class="text-gray-400">No effective version</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Classifications" min-width="260">
              <template #default="{ row }">
                <div v-if="currentVersion(row)" class="flex flex-wrap gap-1">
                  <ElTag size="small" type="info">{{ currentVersion(row)?.tax_treatment_key }}</ElTag>
                  <ElTag size="small" :type="currentVersion(row)?.ppa_share_applicability === 'APPLICABLE' ? 'warning' : 'info'">
                    PPA {{ currentVersion(row)?.ppa_share_applicability === 'APPLICABLE' ? 'Yes' : 'No' }}
                  </ElTag>
                  <ElTag size="small" :type="currentVersion(row)?.fuel_surcharge_applicability === 'APPLICABLE' ? 'warning' : 'info'">
                    Fuel {{ currentVersion(row)?.fuel_surcharge_applicability === 'APPLICABLE' ? 'Yes' : 'No' }}
                  </ElTag>
                </div>
                <span v-else class="text-gray-400">—</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Actions" width="180" fixed="right">
              <template #default="{ row }">
                <ElButton size="small" type="primary" plain @click="openVersionDialog(row)">
                  Add Version
                </ElButton>
              </template>
            </ElTableColumn>
          </ElTable>

          <ElDivider />
          <h3 class="mb-3 text-sm font-semibold text-gray-700">Version History</h3>
          <ElTable
            v-for="tariff in tariffs"
            :key="tariff.id"
            :data="tariff.versions"
            size="small"
            stripe
            class="mb-4"
          >
            <ElTableColumn :label="`${tariff.tariff_code} — ${tariff.name}`" min-width="190">
              <template #default="{ row }">v{{ row.version_number }} · {{ row.rate }}</template>
            </ElTableColumn>
            <ElTableColumn prop="effective_from" label="Effective From" min-width="180" />
            <ElTableColumn prop="effective_to" label="Effective To" min-width="180">
              <template #default="{ row }">{{ row.effective_to || 'Open ended' }}</template>
            </ElTableColumn>
            <ElTableColumn prop="status" label="Status" width="120">
              <template #default="{ row }">
                <ElTag size="small" :type="statusType(row.status)">{{ row.status }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Action" width="130">
              <template #default="{ row }">
                <ElButton
                  v-if="row.status === 'draft'"
                  size="small"
                  type="success"
                  plain
                  @click="publishVersion(tariff, row)"
                >
                  Publish
                </ElButton>
              </template>
            </ElTableColumn>
          </ElTable>
        </ElTabPane>

        <ElTabPane label="Fuel Prices & Schedules" name="fuel">
          <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section>
              <div class="mb-3 flex items-center justify-between">
                <div>
                  <h3 class="text-sm font-semibold text-gray-700">Fuel Price Observations</h3>
                  <p class="text-xs text-gray-500">Effective observations are selected by business date and product grade.</p>
                </div>
                <ElButton type="primary" size="small" @click="showObservationDialog = true">Record Price</ElButton>
              </div>
              <ElTable :data="observations" v-loading="loadingFuel" size="small" stripe border>
                <ElTableColumn prop="product_grade" label="Grade" width="100" />
                <ElTableColumn label="Price" width="120">
                  <template #default="{ row }">{{ row.price }} {{ row.currency }}/{{ row.unit_of_measure }}</template>
                </ElTableColumn>
                <ElTableColumn prop="effective_at" label="Effective At" min-width="165" />
                <ElTableColumn label="Source" min-width="180">
                  <template #default="{ row }">
                    <span>{{ row.source_reference || row.source_evidence_ref || 'Not recorded' }}</span>
                  </template>
                </ElTableColumn>
                <ElTableColumn prop="status" label="Status" width="100">
                  <template #default="{ row }"><ElTag size="small" :type="row.status === 'active' ? 'success' : 'info'">{{ row.status }}</ElTag></template>
                </ElTableColumn>
                <ElTableColumn label="Action" width="100">
                  <template #default="{ row }">
                    <ElButton v-if="row.status === 'active'" size="small" type="warning" plain @click="retireObservation(row)">Retire</ElButton>
                  </template>
                </ElTableColumn>
              </ElTable>
            </section>

            <section>
              <div class="mb-3 flex items-center justify-between">
                <div>
                  <h3 class="text-sm font-semibold text-gray-700">Surcharge Schedule Versions</h3>
                  <p class="text-xs text-gray-500">Bands are lower-inclusive and upper-exclusive; use null for an open upper bound.</p>
                </div>
                <ElButton type="primary" size="small" @click="showPolicyDialog = true">New Schedule</ElButton>
              </div>
              <ElTable :data="policies" v-loading="loadingFuel" size="small" stripe border>
                <ElTableColumn label="Version" width="90">
                  <template #default="{ row }">v{{ row.version_number }}</template>
                </ElTableColumn>
                <ElTableColumn prop="basis" label="Basis" min-width="150" />
                <ElTableColumn prop="effective_from" label="Effective From" min-width="160" />
                <ElTableColumn prop="status" label="Status" width="110">
                  <template #default="{ row }"><ElTag size="small" :type="statusType(row.status)">{{ row.status }}</ElTag></template>
                </ElTableColumn>
                <ElTableColumn label="Bands" min-width="220">
                  <template #default="{ row }">
                    <div class="flex flex-wrap gap-1">
                      <ElTag v-for="band in row.bands" :key="band.id || `${band.min_price}-${band.max_price}`" size="small" effect="plain">
                        [{{ band.min_price }}, {{ band.max_price || '∞' }}) · {{ percentLabel(band.surcharge_percent) }}
                      </ElTag>
                    </div>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Action" width="110">
                  <template #default="{ row }">
                    <ElButton v-if="row.status === 'draft'" size="small" type="success" plain @click="publishPolicy(row)">Publish</ElButton>
                  </template>
                </ElTableColumn>
              </ElTable>
            </section>
          </div>
        </ElTabPane>
      </ElTabs>
    </ElCard>

    <ElDialog v-model="showTariffDialog" title="Create Tariff Master" width="520px" destroy-on-close>
      <ElForm label-position="top">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <ElFormItem label="Tariff Code"><ElInput v-model="tariffForm.tariff_code" placeholder="ARR_DOM" /></ElFormItem>
          <ElFormItem label="Name"><ElInput v-model="tariffForm.name" placeholder="Arrastre Domestic" /></ElFormItem>
          <ElFormItem label="Service Type"><ElSelect v-model="tariffForm.service_type" class="w-full"><ElOption label="Arrastre" value="ARRASTRE" /><ElOption label="Stevedoring" value="STEVEDORING" /><ElOption label="Other" value="OTHER" /></ElSelect></ElFormItem>
          <ElFormItem label="Route Type"><ElSelect v-model="tariffForm.route_type" class="w-full"><ElOption label="Domestic" value="DOMESTIC" /><ElOption label="Foreign" value="FOREIGN" /></ElSelect></ElFormItem>
          <ElFormItem label="Unit of Measure"><ElInput v-model="tariffForm.unit_of_measure" placeholder="REV_TON" /></ElFormItem>
        </div>
      </ElForm>
      <template #footer><ElButton @click="showTariffDialog = false">Cancel</ElButton><ElButton type="primary" :loading="saving" @click="saveTariff">Create Tariff</ElButton></template>
    </ElDialog>

    <ElDialog v-model="showVersionDialog" :title="`Add Version — ${selectedTariff?.tariff_code || ''}`" width="620px" destroy-on-close>
      <ElForm label-position="top">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <ElFormItem label="Rate"><ElInput v-model="versionForm.rate" placeholder="125.5000" /></ElFormItem>
          <ElFormItem label="Tax Treatment"><ElSelect v-model="versionForm.tax_treatment_key" class="w-full"><ElOption v-for="value in taxTreatments" :key="value" :label="value" :value="value" /></ElSelect></ElFormItem>
          <ElFormItem label="PPA Share"><ElSelect v-model="versionForm.ppa_share_applicability" class="w-full"><ElOption label="Not applicable" value="NOT_APPLICABLE" /><ElOption label="Applicable" value="APPLICABLE" /></ElSelect></ElFormItem>
          <ElFormItem label="PPA Rate (decimal)"><ElInput v-model="versionForm.ppa_share_rate" placeholder="0.1000" :disabled="versionForm.ppa_share_applicability !== 'APPLICABLE'" /></ElFormItem>
          <ElFormItem label="Fuel Surcharge"><ElSelect v-model="versionForm.fuel_surcharge_applicability" class="w-full"><ElOption label="Not applicable" value="NOT_APPLICABLE" /><ElOption label="Applicable" value="APPLICABLE" /></ElSelect></ElFormItem>
          <ElFormItem label="Initial Status"><ElSelect v-model="versionForm.status" class="w-full"><ElOption label="Draft" value="draft" /><ElOption label="Published / scheduled" value="published" /><ElOption label="Effective now" value="effective" /></ElSelect></ElFormItem>
          <ElFormItem label="Effective From"><ElInput v-model="versionForm.effective_from" placeholder="2026-10-01 00:00:00" /></ElFormItem>
          <ElFormItem label="Effective To (optional)"><ElInput v-model="versionForm.effective_to" placeholder="2026-12-31 23:59:59" /></ElFormItem>
        </div>
      </ElForm>
      <template #footer><ElButton @click="showVersionDialog = false">Cancel</ElButton><ElButton type="primary" :loading="saving" @click="saveVersion">Save Version</ElButton></template>
    </ElDialog>

    <ElDialog v-model="showObservationDialog" title="Record Fuel Price Observation" width="560px" destroy-on-close>
      <ElForm label-position="top">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <ElFormItem label="Product Grade"><ElInput v-model="observationForm.product_grade" /></ElFormItem>
          <ElFormItem label="Price"><ElInput v-model="observationForm.price" placeholder="67.2500" /></ElFormItem>
          <ElFormItem label="Currency"><ElInput v-model="observationForm.currency" /></ElFormItem>
          <ElFormItem label="Unit"><ElInput v-model="observationForm.unit_of_measure" /></ElFormItem>
          <ElFormItem label="Observed At"><ElInput v-model="observationForm.observed_at" placeholder="2026-09-19 08:00:00" /></ElFormItem>
          <ElFormItem label="Effective At"><ElInput v-model="observationForm.effective_at" placeholder="2026-09-19 00:00:00" /></ElFormItem>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <ElFormItem label="Source Reference"><ElInput v-model="observationForm.source_reference" placeholder="DOE bulletin / supplier quote" /></ElFormItem>
          <ElFormItem label="Evidence Reference"><ElInput v-model="observationForm.source_evidence_ref" placeholder="Private file or review reference" /></ElFormItem>
        </div>
        <ElFormItem label="Source Notes"><ElInput v-model="observationForm.notes" type="textarea" :rows="3" /></ElFormItem>
      </ElForm>
      <template #footer><ElButton @click="showObservationDialog = false">Cancel</ElButton><ElButton type="primary" :loading="saving" @click="saveObservation">Record Observation</ElButton></template>
    </ElDialog>

    <ElDialog v-model="showPolicyDialog" title="Create Fuel Surcharge Schedule" width="760px" destroy-on-close>
      <ElForm label-position="top">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <ElFormItem label="Calculation Basis"><ElSelect v-model="policyForm.basis" class="w-full"><ElOption label="Base tariff amount" value="BASE_TARIFF_AMOUNT" /></ElSelect></ElFormItem>
          <ElFormItem label="Effective From"><ElInput v-model="policyForm.effective_from" placeholder="2026-10-01 00:00:00" /></ElFormItem>
          <ElFormItem label="Effective To (optional)"><ElInput v-model="policyForm.effective_to" placeholder="2026-12-31 23:59:59" /></ElFormItem>
        </div>
        <div class="mb-2 flex items-center justify-between"><span class="text-sm font-semibold text-gray-700">Price Bands</span><ElButton size="small" @click="addBand">Add Band</ElButton></div>
        <div v-for="(band, index) in policyForm.bands" :key="index" class="mb-3 grid grid-cols-1 items-end gap-2 rounded border border-gray-200 p-3 md:grid-cols-[1fr_1fr_1fr_2fr_auto]">
          <ElFormItem label="Min (inclusive)"><ElInput v-model="band.min_price" /></ElFormItem>
          <ElFormItem label="Max (exclusive)"><ElInput v-model="band.max_price" placeholder="blank = open" /></ElFormItem>
          <ElFormItem label="Rate (decimal)"><ElInput v-model="band.surcharge_percent" placeholder="0.0500" /></ElFormItem>
          <ElFormItem label="Display Label"><ElInput v-model="band.label" /></ElFormItem>
          <ElButton v-if="policyForm.bands.length > 1" type="danger" plain @click="removeBand(index)">Remove</ElButton>
        </div>
      </ElForm>
      <template #footer><ElButton @click="showPolicyDialog = false">Cancel</ElButton><ElButton type="primary" :loading="saving" @click="savePolicy">Save Schedule</ElButton></template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue'
import { ElMessage, ElMessageBox } from 'element-plus'
import {
  createFuelObservation,
  createFuelPolicy,
  createTariff,
  createTariffVersion,
  fetchAdminTariffs,
  fetchFuelObservations,
  fetchFuelPolicies,
  publishFuelPolicy,
  publishTariffVersion,
  retireFuelObservation,
  type Applicability,
  type FuelObservationPayload,
  type FuelPriceObservation,
  type FuelSurchargePolicy,
  type FuelPolicyPayload,
  type PricingStatus,
  type Tariff,
  type TariffCreatePayload,
  type TariffVersion,
  type TariffVersionPayload,
  type TaxTreatment
} from '@/api/pricing'

const activeTab = ref('tariffs')
const loadingTariffs = ref(false)
const loadingFuel = ref(false)
const saving = ref(false)
const tariffs = ref<Tariff[]>([])
const observations = ref<FuelPriceObservation[]>([])
const policies = ref<FuelSurchargePolicy[]>([])

const showTariffDialog = ref(false)
const showVersionDialog = ref(false)
const showObservationDialog = ref(false)
const showPolicyDialog = ref(false)
const selectedTariff = ref<Tariff | null>(null)

const tariffForm = reactive<TariffCreatePayload>({
  tariff_code: '',
  name: '',
  service_type: 'OTHER',
  route_type: 'DOMESTIC',
  unit_of_measure: 'REV_TON'
})

const versionForm = reactive<TariffVersionPayload>({
  rate: '0.0000',
  tax_treatment_key: 'VATABLE',
  ppa_share_applicability: 'NOT_APPLICABLE',
  ppa_share_rate: '0.0000',
  fuel_surcharge_applicability: 'NOT_APPLICABLE',
  effective_from: '',
  effective_to: null,
  status: 'draft'
})

const observationForm = reactive<FuelObservationPayload>({
  product_grade: 'DIESEL',
  price: '',
  currency: 'PHP',
  unit_of_measure: 'LITER',
  observed_at: '',
  effective_at: '',
  notes: '',
  source_reference: '',
  source_evidence_ref: ''
})

const policyForm = reactive<FuelPolicyPayload>({
  basis: 'BASE_TARIFF_AMOUNT',
  effective_from: '',
  effective_to: null,
  status: 'draft',
  bands: [
    { min_price: '0.0000', max_price: null, surcharge_percent: '0.0000', label: 'No surcharge' }
  ]
})

const taxTreatments: TaxTreatment[] = ['VATABLE', 'EXEMPT', 'ZERO_RATED', 'NON_VAT']

function currentVersion(tariff: Tariff): TariffVersion | undefined {
  return tariff.versions?.find((version) => version.status === 'effective')
}

function statusType(status: PricingStatus) {
  if (status === 'effective') return 'success'
  if (status === 'published') return 'warning'
  if (status === 'retired') return 'info'
  return 'primary'
}

function percentLabel(value: string): string {
  const number = Number(value)
  return Number.isFinite(number) ? `${(number * 100).toFixed(2)}%` : value
}

async function loadTariffs() {
  loadingTariffs.value = true
  try {
    tariffs.value = await fetchAdminTariffs()
  } catch (error: any) {
    ElMessage.error(error?.message || 'Failed to load tariffs')
  } finally {
    loadingTariffs.value = false
  }
}

async function loadFuel() {
  loadingFuel.value = true
  try {
    const [observationList, policyList] = await Promise.all([fetchFuelObservations(), fetchFuelPolicies()])
    observations.value = observationList
    policies.value = policyList
  } catch (error: any) {
    ElMessage.error(error?.message || 'Failed to load fuel pricing')
  } finally {
    loadingFuel.value = false
  }
}

function refreshAll() {
  void loadTariffs()
  void loadFuel()
}

function openVersionDialog(tariff: Tariff) {
  selectedTariff.value = tariff
  Object.assign(versionForm, {
    rate: '0.0000',
    tax_treatment_key: 'VATABLE',
    ppa_share_applicability: 'NOT_APPLICABLE',
    ppa_share_rate: '0.0000',
    fuel_surcharge_applicability: 'NOT_APPLICABLE',
    effective_from: '',
    effective_to: null,
    status: 'draft'
  })
  showVersionDialog.value = true
}

async function saveTariff() {
  if (!tariffForm.tariff_code || !tariffForm.name) {
    ElMessage.warning('Tariff code and name are required')
    return
  }
  saving.value = true
  try {
    await createTariff({ ...tariffForm })
    ElMessage.success('Tariff created')
    showTariffDialog.value = false
    Object.assign(tariffForm, { tariff_code: '', name: '', service_type: 'OTHER', route_type: 'DOMESTIC', unit_of_measure: 'REV_TON' })
    await loadTariffs()
  } catch (error: any) {
    ElMessage.error(error?.message || 'Failed to create tariff')
  } finally {
    saving.value = false
  }
}

async function saveVersion() {
  if (!selectedTariff.value || !versionForm.rate || !versionForm.effective_from) {
    ElMessage.warning('Tariff rate and effective-from date are required')
    return
  }
  saving.value = true
  try {
    await createTariffVersion(selectedTariff.value.id, {
      ...versionForm,
      ppa_share_rate: versionForm.ppa_share_applicability === 'APPLICABLE' ? versionForm.ppa_share_rate : '0.0000',
      effective_to: versionForm.effective_to || null
    })
    ElMessage.success('Tariff version saved')
    showVersionDialog.value = false
    await loadTariffs()
  } catch (error: any) {
    ElMessage.error(error?.message || 'Failed to save tariff version')
  } finally {
    saving.value = false
  }
}

async function publishVersion(tariff: Tariff, version: TariffVersion) {
  try {
    await ElMessageBox.confirm('Publish this version? Existing issued invoices will not be repriced.', 'Publish Tariff Version', { type: 'warning' })
    await publishTariffVersion(tariff.id, version.id)
    ElMessage.success('Tariff version published')
    await loadTariffs()
  } catch {
    // Cancelled or rejected by the API.
  }
}

async function saveObservation() {
  if (!observationForm.price || !observationForm.observed_at || !observationForm.effective_at) {
    ElMessage.warning('Price, observed-at, and effective-at values are required')
    return
  }
  saving.value = true
  try {
    await createFuelObservation({ ...observationForm })
    ElMessage.success('Fuel price recorded')
    showObservationDialog.value = false
    await loadFuel()
  } catch (error: any) {
    ElMessage.error(error?.message || 'Failed to record fuel price')
  } finally {
    saving.value = false
  }
}

async function retireObservation(observation: FuelPriceObservation) {
  try {
    await ElMessageBox.confirm('Retire this observation? Historical snapshots remain unchanged.', 'Retire Observation', { type: 'warning' })
    await retireFuelObservation(observation.id)
    ElMessage.success('Fuel observation retired')
    await loadFuel()
  } catch {
    // Cancelled or rejected by the API.
  }
}

function addBand() {
  policyForm.bands.push({ min_price: '', max_price: null, surcharge_percent: '0.0000', label: '' })
}

function removeBand(index: number) {
  policyForm.bands.splice(index, 1)
}

async function savePolicy() {
  if (!policyForm.effective_from || policyForm.bands.some((band) => !band.min_price || !band.label || !band.surcharge_percent)) {
    ElMessage.warning('Effective-from and complete band values are required')
    return
  }
  saving.value = true
  try {
    await createFuelPolicy({ ...policyForm, effective_to: policyForm.effective_to || null })
    ElMessage.success('Fuel surcharge schedule saved')
    showPolicyDialog.value = false
    await loadFuel()
  } catch (error: any) {
    ElMessage.error(error?.message || 'Failed to save fuel surcharge schedule')
  } finally {
    saving.value = false
  }
}

async function publishPolicy(policy: FuelSurchargePolicy) {
  try {
    await ElMessageBox.confirm('Publish this schedule? It will apply only to new calculations in its effective window.', 'Publish Fuel Schedule', { type: 'warning' })
    await publishFuelPolicy(policy.id)
    ElMessage.success('Fuel surcharge schedule published')
    await loadFuel()
  } catch {
    // Cancelled or rejected by the API.
  }
}

onMounted(refreshAll)
</script>

<style scoped>
.pricing-workspace-page {
  min-height: 100%;
}
</style>
