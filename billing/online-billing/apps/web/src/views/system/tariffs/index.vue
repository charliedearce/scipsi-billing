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
            Maintain versioned tariff classifications, scheduled fuel-price bands, and transparent
            pricing rules. Published rules affect new calculations only; issued invoices retain
            their snapshots.
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
            <div class="flex flex-wrap items-center gap-3">
              <ElInput
                v-model="tariffSearch"
                clearable
                placeholder="Search code, name, service, route or rate"
                class="w-72"
              />
              <div class="text-sm text-g-500">
                {{ groupedTariffs.length }} cargo codes · {{ filteredTariffs.length }} rate cells
              </div>
            </div>
            <ElButton type="primary" @click="showTariffDialog = true">New Tariff</ElButton>
          </div>

          <ElTable
            :data="pagedGroups"
            v-loading="loadingTariffs"
            stripe
            border
            row-key="tariff_code"
            :expand-row-keys="expandedCodes"
            empty-text="No tariffs match this search."
            @expand-change="onGroupExpand"
          >
            <ElTableColumn type="expand" width="48">
              <template #default="{ row }">
                <div class="tariff-code-cells bg-g-100 p-3">
                  <ElTable
                    :data="row.cells"
                    size="small"
                    stripe
                    border
                    empty-text="No rate cells for this cargo code."
                    @row-click="openTariffDetail"
                  >
                    <ElTableColumn label="Service / Route" min-width="180">
                      <template #default="{ row: cell }">
                        {{ serviceLabel(cell.service_type) }} /
                        {{ routeLabel(cell.route_type) }}
                      </template>
                    </ElTableColumn>
                    <ElTableColumn label="Rate" width="140" align="right">
                      <template #default="{ row: cell }">
                        <span
                          v-if="currentVersion(cell)"
                          class="font-mono text-sm font-semibold text-g-800"
                        >
                          {{ money(currentVersion(cell)?.rate) }}
                        </span>
                        <span v-else class="text-g-400">No rate</span>
                      </template>
                    </ElTableColumn>
                    <ElTableColumn prop="unit_of_measure" label="Unit" width="110" />
                    <ElTableColumn label="Version" width="150">
                      <template #default="{ row: cell }">
                        <ElTag
                          v-if="currentVersion(cell)"
                          size="small"
                          :type="statusType(currentVersion(cell)!.status)"
                        >
                          v{{ currentVersion(cell)?.version_number }} ·
                          {{ currentVersion(cell)?.status }}
                        </ElTag>
                        <span v-else class="text-g-400">None</span>
                      </template>
                    </ElTableColumn>
                    <ElTableColumn label="Actions" width="200" fixed="right">
                      <template #default="{ row: cell }">
                        <ElButton
                          size="small"
                          type="primary"
                          link
                          @click.stop="openTariffDetail(cell)"
                        >
                          View rate
                        </ElButton>
                        <ElButton size="small" link @click.stop="openVersionDialog(cell)">
                          Add version
                        </ElButton>
                      </template>
                    </ElTableColumn>
                  </ElTable>
                </div>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="tariff_code" label="Code" width="130">
              <template #default="{ row }">
                <span class="font-mono text-xs font-semibold text-theme">{{
                  row.tariff_code
                }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="name" label="Tariff" min-width="200" />
            <ElTableColumn label="Rates" min-width="360">
              <template #default="{ row }">
                <div class="flex flex-wrap gap-1">
                  <ElTag
                    v-for="cell in row.cells"
                    :key="cell.id"
                    size="small"
                    effect="plain"
                    class="cursor-pointer"
                    @click.stop="openTariffDetail(cell)"
                  >
                    {{ serviceLabel(cell.service_type) }} /
                    {{ routeLabel(cell.route_type) }}
                    · {{ currentVersion(cell) ? money(currentVersion(cell)?.rate) : 'No rate' }}
                  </ElTag>
                </div>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Cells" width="90" align="right">
              <template #default="{ row }">{{ row.cells.length }}</template>
            </ElTableColumn>
          </ElTable>

          <div class="mt-3 flex justify-end">
            <ElPagination
              v-model:current-page="cataloguePage"
              v-model:page-size="cataloguePageSize"
              :total="groupedTariffs.length"
              :page-sizes="[10, 20, 50, 100]"
              layout="total, sizes, prev, pager, next"
              background
            />
          </div>
        </ElTabPane>

        <ElTabPane label="Fuel Prices & Schedules" name="fuel">
          <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
            <section>
              <div class="mb-3 flex items-center justify-between">
                <div>
                  <h3 class="text-sm font-semibold text-gray-700">Fuel Price Observations</h3>
                  <p class="text-xs text-gray-500"
                    >Effective observations are selected by business date and product grade.</p
                  >
                </div>
                <ElButton type="primary" size="small" @click="showObservationDialog = true"
                  >Record Price</ElButton
                >
              </div>
              <ElTable :data="observations" v-loading="loadingFuel" size="small" stripe border>
                <ElTableColumn prop="product_grade" label="Grade" width="100" />
                <ElTableColumn label="Price" width="120">
                  <template #default="{ row }"
                    >{{ row.price }} {{ row.currency }}/{{ row.unit_of_measure }}</template
                  >
                </ElTableColumn>
                <ElTableColumn prop="effective_at" label="Effective At" min-width="165" />
                <ElTableColumn label="Source" min-width="180">
                  <template #default="{ row }">
                    <span>{{
                      row.source_reference || row.source_evidence_ref || 'Not recorded'
                    }}</span>
                  </template>
                </ElTableColumn>
                <ElTableColumn prop="status" label="Status" width="100">
                  <template #default="{ row }"
                    ><ElTag size="small" :type="row.status === 'active' ? 'success' : 'info'">{{
                      row.status
                    }}</ElTag></template
                  >
                </ElTableColumn>
                <ElTableColumn label="Action" width="100">
                  <template #default="{ row }">
                    <ElButton
                      v-if="row.status === 'active'"
                      size="small"
                      type="warning"
                      plain
                      @click="retireObservation(row)"
                      >Retire</ElButton
                    >
                  </template>
                </ElTableColumn>
              </ElTable>
            </section>

            <section>
              <div class="mb-3 flex items-center justify-between">
                <div>
                  <h3 class="text-sm font-semibold text-gray-700">Surcharge Schedule Versions</h3>
                  <p class="text-xs text-gray-500"
                    >Bands are lower-inclusive and upper-exclusive; use null for an open upper
                    bound.</p
                  >
                </div>
                <ElButton type="primary" size="small" @click="showPolicyDialog = true"
                  >New Schedule</ElButton
                >
              </div>
              <ElTable :data="policies" v-loading="loadingFuel" size="small" stripe border>
                <ElTableColumn label="Version" width="90">
                  <template #default="{ row }">v{{ row.version_number }}</template>
                </ElTableColumn>
                <ElTableColumn prop="basis" label="Basis" min-width="150" />
                <ElTableColumn prop="effective_from" label="Effective From" min-width="160" />
                <ElTableColumn prop="status" label="Status" width="110">
                  <template #default="{ row }"
                    ><ElTag size="small" :type="statusType(row.status)">{{
                      row.status
                    }}</ElTag></template
                  >
                </ElTableColumn>
                <ElTableColumn label="Bands" min-width="220">
                  <template #default="{ row }">
                    <div class="flex flex-wrap gap-1">
                      <ElTag
                        v-for="band in row.bands"
                        :key="band.id || `${band.min_price}-${band.max_price}`"
                        size="small"
                        effect="plain"
                      >
                        [{{ band.min_price }}, {{ band.max_price || '∞' }}) ·
                        {{ percentLabel(band.surcharge_percent) }}
                      </ElTag>
                    </div>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Action" width="110">
                  <template #default="{ row }">
                    <ElButton
                      v-if="row.status === 'draft'"
                      size="small"
                      type="success"
                      plain
                      @click="publishPolicy(row)"
                      >Publish</ElButton
                    >
                  </template>
                </ElTableColumn>
              </ElTable>
            </section>
          </div>
        </ElTabPane>
      </ElTabs>
    </ElCard>

    <ElDrawer
      v-model="showDetailDrawer"
      :title="detailTitle"
      size="min(720px, 100vw)"
      destroy-on-close
    >
      <div v-if="selectedTariff" class="space-y-5">
        <div class="art-card-sm border border-g-200 p-4">
          <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
              <div class="font-mono text-sm font-semibold text-theme">{{
                selectedTariff.tariff_code
              }}</div>
              <div class="mt-1 text-base font-semibold text-g-800">{{ selectedTariff.name }}</div>
              <p class="mt-1 text-sm text-g-500">
                {{ selectedTariff.service_type }} / {{ selectedTariff.route_type }} ·
                {{ selectedTariff.unit_of_measure }}
              </p>
            </div>
            <ElTag
              v-if="selectedCurrentVersion"
              size="small"
              :type="statusType(selectedCurrentVersion.status)"
            >
              {{ selectedCurrentVersion.status }}
            </ElTag>
          </div>
          <div class="mt-4">
            <div class="text-xs uppercase tracking-wide text-g-500">Current rate</div>
            <div class="mt-1 font-mono text-2xl font-semibold text-g-900">
              {{
                selectedCurrentVersion ? money(selectedCurrentVersion.rate) : 'No published rate'
              }}
            </div>
          </div>
        </div>

        <ElDescriptions :column="1" border>
          <ElDescriptionsItem label="Version">
            {{ selectedCurrentVersion ? `v${selectedCurrentVersion.version_number}` : '—' }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Tax treatment">
            {{ selectedCurrentVersion?.tax_treatment_key || '—' }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="PPA share">
            <template v-if="selectedCurrentVersion?.ppa_share_applicability === 'APPLICABLE'">
              Applicable · {{ percentLabel(selectedCurrentVersion.ppa_share_rate) }}
            </template>
            <template v-else>Not applicable</template>
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Fuel surcharge">
            {{
              selectedCurrentVersion?.fuel_surcharge_applicability === 'APPLICABLE'
                ? 'Applicable'
                : 'Not applicable'
            }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Effective from">
            {{ formatDateTimeManila(selectedCurrentVersion?.effective_from) }}
          </ElDescriptionsItem>
          <ElDescriptionsItem label="Effective to">
            {{
              selectedCurrentVersion?.effective_to
                ? formatDateTimeManila(selectedCurrentVersion.effective_to)
                : 'Open ended'
            }}
          </ElDescriptionsItem>
          <ElDescriptionsItem v-if="selectedTariff.legacy_t_scode" label="Cargo code">
            {{ selectedTariff.legacy_t_scode }}
          </ElDescriptionsItem>
        </ElDescriptions>

        <div>
          <div class="mb-2 flex items-center justify-between gap-2">
            <h3 class="text-sm font-semibold text-g-800">Version history</h3>
            <ElButton size="small" type="primary" @click="openVersionDialog(selectedTariff)">
              Add version
            </ElButton>
          </div>
          <ElTable :data="selectedTariff.versions || []" size="small" stripe border>
            <ElTableColumn label="Version" width="90">
              <template #default="{ row }">v{{ row.version_number }}</template>
            </ElTableColumn>
            <ElTableColumn label="Rate" width="120" align="right">
              <template #default="{ row }">
                <span class="font-mono">{{ money(row.rate) }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="status" label="Status" width="120">
              <template #default="{ row }">
                <ElTag size="small" :type="statusType(row.status)">{{ row.status }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Effective from" min-width="160">
              <template #default="{ row }">{{ formatDateTimeManila(row.effective_from) }}</template>
            </ElTableColumn>
            <ElTableColumn label="Effective to" min-width="140">
              <template #default="{ row }">
                {{ row.effective_to ? formatDateTimeManila(row.effective_to) : 'Open ended' }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Tax / PPA / Fuel" min-width="210">
              <template #default="{ row }">
                {{ row.tax_treatment_key }} · PPA
                {{
                  row.ppa_share_applicability === 'APPLICABLE'
                    ? percentLabel(row.ppa_share_rate)
                    : 'No'
                }}
                · Fuel {{ row.fuel_surcharge_applicability === 'APPLICABLE' ? 'Yes' : 'No' }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Action" width="110">
              <template #default="{ row }">
                <ElButton
                  v-if="row.status === 'draft'"
                  size="small"
                  type="success"
                  plain
                  @click="publishVersion(selectedTariff!, row)"
                >
                  Publish
                </ElButton>
                <span v-else class="text-xs text-g-400">Read-only</span>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="showTariffDialog"
      title="Create Tariff Master"
      width="520px"
      destroy-on-close
    >
      <ElForm label-position="top">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <ElFormItem label="Tariff Code"
            ><ElInput v-model="tariffForm.tariff_code" placeholder="ARR_DOM"
          /></ElFormItem>
          <ElFormItem label="Name"
            ><ElInput v-model="tariffForm.name" placeholder="Arrastre Domestic"
          /></ElFormItem>
          <ElFormItem label="Service Type"
            ><ElSelect v-model="tariffForm.service_type" class="w-full"
              ><ElOption label="Arrastre" value="ARRASTRE" /><ElOption
                label="Stevedoring"
                value="STEVEDORING" /><ElOption label="Other" value="OTHER" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="Route Type"
            ><ElSelect v-model="tariffForm.route_type" class="w-full"
              ><ElOption label="Domestic" value="DOMESTIC" /><ElOption
                label="Foreign"
                value="FOREIGN" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="Unit of Measure"
            ><ElInput v-model="tariffForm.unit_of_measure" placeholder="REV_TON"
          /></ElFormItem>
        </div>
      </ElForm>
      <template #footer
        ><ElButton @click="showTariffDialog = false">Cancel</ElButton
        ><ElButton type="primary" :loading="saving" @click="saveTariff"
          >Create Tariff</ElButton
        ></template
      >
    </ElDialog>

    <ElDialog
      v-model="showVersionDialog"
      :title="`Add Version — ${selectedTariff?.tariff_code || ''}`"
      width="620px"
      destroy-on-close
    >
      <ElForm label-position="top">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <ElFormItem label="Rate"
            ><ElInput v-model="versionForm.rate" placeholder="125.5000"
          /></ElFormItem>
          <ElFormItem label="Tax Treatment"
            ><ElSelect v-model="versionForm.tax_treatment_key" class="w-full"
              ><ElOption
                v-for="value in taxTreatments"
                :key="value"
                :label="value"
                :value="value" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="PPA Share"
            ><ElSelect v-model="versionForm.ppa_share_applicability" class="w-full"
              ><ElOption label="Not applicable" value="NOT_APPLICABLE" /><ElOption
                label="Applicable"
                value="APPLICABLE" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="PPA Rate (decimal)"
            ><ElInput
              v-model="versionForm.ppa_share_rate"
              placeholder="0.1000"
              :disabled="versionForm.ppa_share_applicability !== 'APPLICABLE'"
          /></ElFormItem>
          <ElFormItem label="Fuel Surcharge"
            ><ElSelect v-model="versionForm.fuel_surcharge_applicability" class="w-full"
              ><ElOption label="Not applicable" value="NOT_APPLICABLE" /><ElOption
                label="Applicable"
                value="APPLICABLE" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="Initial Status"
            ><ElSelect v-model="versionForm.status" class="w-full"
              ><ElOption label="Draft" value="draft" /><ElOption
                label="Published / scheduled"
                value="published" /><ElOption label="Effective now" value="effective" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="Effective From"
            ><ElInput v-model="versionForm.effective_from" placeholder="2026-10-01 00:00:00"
          /></ElFormItem>
          <ElFormItem label="Effective To (optional)"
            ><ElInput v-model="versionForm.effective_to" placeholder="2026-12-31 23:59:59"
          /></ElFormItem>
        </div>
      </ElForm>
      <template #footer
        ><ElButton @click="showVersionDialog = false">Cancel</ElButton
        ><ElButton type="primary" :loading="saving" @click="saveVersion"
          >Save Version</ElButton
        ></template
      >
    </ElDialog>

    <ElDialog
      v-model="showObservationDialog"
      title="Record Fuel Price Observation"
      width="560px"
      destroy-on-close
    >
      <ElForm label-position="top">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <ElFormItem label="Product Grade"
            ><ElInput v-model="observationForm.product_grade"
          /></ElFormItem>
          <ElFormItem label="Price"
            ><ElInput v-model="observationForm.price" placeholder="67.2500"
          /></ElFormItem>
          <ElFormItem label="Currency"><ElInput v-model="observationForm.currency" /></ElFormItem>
          <ElFormItem label="Unit"
            ><ElInput v-model="observationForm.unit_of_measure"
          /></ElFormItem>
          <ElFormItem label="Observed At"
            ><ElInput v-model="observationForm.observed_at" placeholder="2026-09-19 08:00:00"
          /></ElFormItem>
          <ElFormItem label="Effective At"
            ><ElInput v-model="observationForm.effective_at" placeholder="2026-09-19 00:00:00"
          /></ElFormItem>
        </div>
        <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
          <ElFormItem label="Source Reference"
            ><ElInput
              v-model="observationForm.source_reference"
              placeholder="DOE bulletin / supplier quote"
          /></ElFormItem>
          <ElFormItem label="Evidence Reference"
            ><ElInput
              v-model="observationForm.source_evidence_ref"
              placeholder="Private file or review reference"
          /></ElFormItem>
        </div>
        <ElFormItem label="Source Notes"
          ><ElInput v-model="observationForm.notes" type="textarea" :rows="3"
        /></ElFormItem>
      </ElForm>
      <template #footer
        ><ElButton @click="showObservationDialog = false">Cancel</ElButton
        ><ElButton type="primary" :loading="saving" @click="saveObservation"
          >Record Observation</ElButton
        ></template
      >
    </ElDialog>

    <ElDialog
      v-model="showPolicyDialog"
      title="Create Fuel Surcharge Schedule"
      width="760px"
      destroy-on-close
    >
      <ElForm label-position="top">
        <div class="grid grid-cols-1 gap-3 md:grid-cols-3">
          <ElFormItem label="Calculation Basis"
            ><ElSelect v-model="policyForm.basis" class="w-full"
              ><ElOption label="Base tariff amount" value="BASE_TARIFF_AMOUNT" /></ElSelect
          ></ElFormItem>
          <ElFormItem label="Effective From"
            ><ElInput v-model="policyForm.effective_from" placeholder="2026-10-01 00:00:00"
          /></ElFormItem>
          <ElFormItem label="Effective To (optional)"
            ><ElInput v-model="policyForm.effective_to" placeholder="2026-12-31 23:59:59"
          /></ElFormItem>
        </div>
        <div class="mb-2 flex items-center justify-between"
          ><span class="text-sm font-semibold text-gray-700">Price Bands</span
          ><ElButton size="small" @click="addBand">Add Band</ElButton></div
        >
        <div
          v-for="(band, index) in policyForm.bands"
          :key="index"
          class="mb-3 grid grid-cols-1 items-end gap-2 rounded border border-gray-200 p-3 md:grid-cols-[1fr_1fr_1fr_2fr_auto]"
        >
          <ElFormItem label="Min (inclusive)"><ElInput v-model="band.min_price" /></ElFormItem>
          <ElFormItem label="Max (exclusive)"
            ><ElInput v-model="band.max_price" placeholder="blank = open"
          /></ElFormItem>
          <ElFormItem label="Rate (decimal)"
            ><ElInput v-model="band.surcharge_percent" placeholder="0.0500"
          /></ElFormItem>
          <ElFormItem label="Display Label"><ElInput v-model="band.label" /></ElFormItem>
          <ElButton
            v-if="policyForm.bands.length > 1"
            type="danger"
            plain
            @click="removeBand(index)"
            >Remove</ElButton
          >
        </div>
      </ElForm>
      <template #footer
        ><ElButton @click="showPolicyDialog = false">Cancel</ElButton
        ><ElButton type="primary" :loading="saving" @click="savePolicy"
          >Save Schedule</ElButton
        ></template
      >
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref, watch } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'
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

  type TariffCodeGroup = {
    tariff_code: string
    name: string
    unit_of_measure: string
    legacy_t_scode?: string | null
    cells: Tariff[]
  }

  const SERVICE_ORDER: Tariff['service_type'][] = ['ARRASTRE', 'STEVEDORING', 'OTHER']
  const ROUTE_ORDER: Tariff['route_type'][] = ['DOMESTIC', 'FOREIGN']

  const activeTab = ref('tariffs')
  const loadingTariffs = ref(false)
  const loadingFuel = ref(false)
  const saving = ref(false)
  const tariffs = ref<Tariff[]>([])
  const observations = ref<FuelPriceObservation[]>([])
  const policies = ref<FuelSurchargePolicy[]>([])

  const showTariffDialog = ref(false)
  const showVersionDialog = ref(false)
  const showDetailDrawer = ref(false)
  const showObservationDialog = ref(false)
  const showPolicyDialog = ref(false)
  const selectedTariff = ref<Tariff | null>(null)
  const tariffSearch = ref('')
  const cataloguePage = ref(1)
  const cataloguePageSize = ref(20)
  const expandedCodes = ref<string[]>([])

  const filteredTariffs = computed(() => {
    const query = tariffSearch.value.trim().toLowerCase()
    if (!query) {
      return tariffs.value
    }
    return tariffs.value.filter((tariff) => {
      const haystack = [
        tariff.tariff_code,
        tariff.name,
        tariff.service_type,
        tariff.route_type,
        tariff.legacy_t_scode,
        currentVersion(tariff)?.rate
      ]
        .filter(Boolean)
        .join(' ')
        .toLowerCase()
      return haystack.includes(query)
    })
  })

  const groupedTariffs = computed(() => groupTariffsByCode(filteredTariffs.value))

  const pagedGroups = computed(() => {
    const start = (cataloguePage.value - 1) * cataloguePageSize.value
    return groupedTariffs.value.slice(start, start + cataloguePageSize.value)
  })

  watch(tariffSearch, () => {
    cataloguePage.value = 1
  })

  watch(pagedGroups, (groups) => {
    if (tariffSearch.value.trim()) {
      expandedCodes.value = groups.map((group) => group.tariff_code)
    } else {
      expandedCodes.value = []
    }
  })

  const detailTitle = computed(() =>
    selectedTariff.value
      ? `Tariff rate — ${selectedTariff.value.tariff_code}`
      : 'Tariff rate details'
  )

  const selectedCurrentVersion = computed(() =>
    selectedTariff.value ? currentVersion(selectedTariff.value) : undefined
  )

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

  function groupTariffsByCode(rows: Tariff[]): TariffCodeGroup[] {
    const byCode = new Map<string, Tariff[]>()
    for (const row of rows) {
      const existing = byCode.get(row.tariff_code)
      if (existing) {
        existing.push(row)
      } else {
        byCode.set(row.tariff_code, [row])
      }
    }
    return [...byCode.entries()].map(([tariff_code, cells]) => {
      const sorted = [...cells].sort(compareTariffCells)
      return {
        tariff_code,
        name: sorted[0]?.name || tariff_code,
        unit_of_measure: sorted[0]?.unit_of_measure || '',
        legacy_t_scode: sorted[0]?.legacy_t_scode,
        cells: sorted
      }
    })
  }

  function compareTariffCells(left: Tariff, right: Tariff): number {
    const service =
      SERVICE_ORDER.indexOf(left.service_type) - SERVICE_ORDER.indexOf(right.service_type)
    if (service !== 0) {
      return service
    }
    return ROUTE_ORDER.indexOf(left.route_type) - ROUTE_ORDER.indexOf(right.route_type)
  }

  function serviceLabel(value: Tariff['service_type']): string {
    if (value === 'ARRASTRE') return 'Arrastre'
    if (value === 'STEVEDORING') return 'Stevedoring'
    return 'Other'
  }

  function routeLabel(value: Tariff['route_type']): string {
    return value === 'FOREIGN' ? 'Foreign' : 'Domestic'
  }

  function onGroupExpand(_row: TariffCodeGroup, expandedRows: TariffCodeGroup[]) {
    expandedCodes.value = expandedRows.map((group) => group.tariff_code)
  }

  function currentVersion(tariff: Tariff): TariffVersion | undefined {
    const versions = tariff.versions || []
    return (
      versions.find((version) => version.status === 'effective') ||
      versions.find((version) => version.status === 'published') ||
      versions[0]
    )
  }

  function money(value: string | null | undefined): string {
    const number = Number(value)
    if (!Number.isFinite(number)) {
      return value || '—'
    }
    return new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2,
      maximumFractionDigits: 4
    }).format(number)
  }

  function openTariffDetail(tariff: Tariff) {
    selectedTariff.value = tariff
    showDetailDrawer.value = true
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
      if (selectedTariff.value) {
        selectedTariff.value =
          tariffs.value.find((item) => item.id === selectedTariff.value?.id) || null
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load tariffs')
    } finally {
      loadingTariffs.value = false
    }
  }

  async function loadFuel() {
    loadingFuel.value = true
    try {
      const [observationList, policyList] = await Promise.all([
        fetchFuelObservations(),
        fetchFuelPolicies()
      ])
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
      Object.assign(tariffForm, {
        tariff_code: '',
        name: '',
        service_type: 'OTHER',
        route_type: 'DOMESTIC',
        unit_of_measure: 'REV_TON'
      })
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
        ppa_share_rate:
          versionForm.ppa_share_applicability === 'APPLICABLE'
            ? versionForm.ppa_share_rate
            : '0.0000',
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
      await ElMessageBox.confirm(
        'Publish this version? Existing issued invoices will not be repriced.',
        'Publish Tariff Version',
        { type: 'warning' }
      )
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
      await ElMessageBox.confirm(
        'Retire this observation? Historical snapshots remain unchanged.',
        'Retire Observation',
        { type: 'warning' }
      )
      await retireFuelObservation(observation.id)
      ElMessage.success('Fuel observation retired')
      await loadFuel()
    } catch {
      // Cancelled or rejected by the API.
    }
  }

  function addBand() {
    policyForm.bands.push({
      min_price: '',
      max_price: null,
      surcharge_percent: '0.0000',
      label: ''
    })
  }

  function removeBand(index: number) {
    policyForm.bands.splice(index, 1)
  }

  async function savePolicy() {
    if (
      !policyForm.effective_from ||
      policyForm.bands.some((band) => !band.min_price || !band.label || !band.surcharge_percent)
    ) {
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
      await ElMessageBox.confirm(
        'Publish this schedule? It will apply only to new calculations in its effective window.',
        'Publish Fuel Schedule',
        { type: 'warning' }
      )
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

  .tariff-code-cells :deep(.el-table) {
    --el-table-bg-color: transparent;
  }
</style>
