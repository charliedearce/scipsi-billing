<template>
  <div class="space-y-4">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <h3 class="font-medium text-g-900">Billing items</h3>
      <p class="text-xs" :class="calcError ? 'text-error' : 'text-g-500'">{{ helperText }}</p>
    </div>

    <div
      class="flex flex-col gap-3 rounded-lg border border-g-200 bg-g-100/40 p-3 sm:flex-row sm:flex-wrap sm:items-end"
    >
      <div class="min-w-[10rem]">
        <div class="mb-1 text-xs text-g-500">Fuel surcharge</div>
        <ElSwitch
          :model-value="fuelEnabled"
          :disabled="disabled"
          inline-prompt
          active-text="On"
          inactive-text="Off"
          @change="onFuelToggle"
        />
      </div>
      <div class="min-w-[10rem]">
        <div class="mb-1 text-xs text-g-500">Dangerous cargo</div>
        <ElSwitch
          :model-value="dangerEnabled"
          :disabled="disabled"
          inline-prompt
          active-text="On"
          inactive-text="Off"
          @change="onDangerToggle"
        />
      </div>
      <div v-if="dangerEnabled" class="min-w-[9rem] max-w-[12rem]">
        <div class="mb-1 text-xs text-g-500">Dangerous cargo %</div>
        <ElInput
          v-model="dangerousPercentDisplay"
          inputmode="decimal"
          size="small"
          :disabled="disabled"
          placeholder="e.g. 100"
          @input="syncPercentFromDisplay"
          @change="syncPercentFromDisplay"
        >
          <template #append>%</template>
        </ElInput>
      </div>
      <p class="text-xs text-g-500 sm:ml-auto sm:max-w-xs">
        Only one surcharge may be on per bill. Fuel uses the published price band, rounds GROSS to a
        whole peso, and turns off PPA for the bill. Dangerous cargo sets RATE to that % of the
        tariff rate (100 = same rate, 150 = 1.5×).
      </p>
    </div>

    <p v-if="!lines.length" class="text-sm text-g-500">Add a service line.</p>
    <div v-else class="space-y-3">
      <article
        v-for="(row, index) in lines"
        :key="row.client_key"
        class="rounded-lg border border-g-200 bg-g-100/40 p-3"
      >
        <div class="mb-3 flex items-center justify-between gap-2">
          <p class="text-xs font-medium text-g-500">Item {{ index + 1 }}</p>
          <ElButton
            v-if="!disabled && lines.length > 1"
            text
            type="danger"
            size="small"
            aria-label="Remove line"
            @click="removeLine(index)"
          >
            <ArtSvgIcon icon="ri:delete-bin-line" class="mr-1 text-base" />
            Remove
          </ElButton>
        </div>

        <div class="grid grid-cols-[repeat(auto-fit,minmax(14rem,1fr))] gap-3">
          <div class="min-w-0">
            <div class="mb-1 text-xs text-g-500">Service</div>
            <ElSelect
              :key="`${row.client_key}-service`"
              v-model="row.service_type"
              class="w-full"
              :placeholder="routeType ? 'Type' : 'Select route first'"
              :disabled="disabled || !routeType"
              @change="onServiceChange(row)"
            >
              <ElOption
                v-for="service in serviceOptions"
                :key="service"
                :label="serviceLabel(service)"
                :value="service"
              />
            </ElSelect>
          </div>
          <div class="min-w-0">
            <div class="mb-1 text-xs text-g-500">Cargo</div>
            <ElSelect
              :key="`${row.client_key}-cargo`"
              v-model="row.tariff_version_id"
              class="w-full"
              filterable
              :placeholder="cargoPlaceholder(row)"
              :disabled="disabled || !routeType || !row.service_type"
              @change="onCargoChange(row)"
            >
              <ElOption
                v-for="opt in cargoOptions(row)"
                :key="opt.version.id"
                :label="opt.label"
                :value="opt.version.id"
              />
            </ElSelect>
          </div>
        </div>

        <div class="mt-3 grid grid-cols-[repeat(auto-fit,minmax(6.75rem,1fr))] gap-3">
          <div class="min-w-0">
            <div class="mb-1 text-xs text-g-500">Qty</div>
            <ElInput
              v-model="row.quantity"
              inputmode="decimal"
              :disabled="disabled"
              placeholder="0"
            />
          </div>
          <div class="min-w-0">
            <div class="mb-1 text-xs text-g-500">Unit</div>
            <p class="flex h-8 items-center text-sm text-g-800">{{ unitFor(row) }}</p>
          </div>
          <div class="min-w-0">
            <div class="mb-1 text-xs text-g-500">Rate</div>
            <p class="flex h-8 items-center font-mono text-sm text-g-800">{{
              money(rateFor(row))
            }}</p>
          </div>
          <div class="min-w-0">
            <div class="mb-1 text-xs text-g-500">Disc.</div>
            <p class="flex h-8 items-center font-mono text-sm text-g-800">{{ money('0.00') }}</p>
          </div>
          <div class="min-w-0">
            <div class="mb-1 text-xs text-g-500">Gross</div>
            <p class="flex h-8 items-center font-mono text-sm font-semibold text-g-900">{{
              money(liveItem(index)?.gross_amount)
            }}</p>
          </div>
          <div class="min-w-0">
            <div class="mb-1 text-xs text-g-500">Tax</div>
            <p class="flex h-8 items-center text-sm text-g-800">{{ taxLabel(liveItem(index)) }}</p>
          </div>
        </div>
      </article>
    </div>
    <ElButton v-if="!disabled" @click="addLine">Add item</ElButton>

    <div class="art-card-xs p-4">
      <h3 class="mb-3 font-medium text-g-900">Computations</h3>
      <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm sm:grid-cols-3">
        <div>
          <dt class="text-xs text-g-500">TOTAL</dt>
          <dd class="font-mono text-g-800">{{ money(displayDraft?.gross_amount) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-g-500">DISC</dt>
          <dd class="font-mono text-g-800">{{ money(displayDraft?.discount_amount) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-g-500">VAT</dt>
          <dd class="font-mono text-g-800">{{ money(displayDraft?.tax_amount) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-g-500">LESS PPA SHARE</dt>
          <dd class="font-mono text-g-800">{{ money(displayDraft?.ppa_amount) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-g-500">NET</dt>
          <dd class="font-mono text-g-800">{{ money(displayDraft?.net_amount) }}</dd>
        </div>
        <div>
          <dt class="text-xs text-g-500">DUE TO SCIPSI</dt>
          <dd class="font-mono font-semibold text-theme">{{
            money(displayDraft?.total_charge_amount)
          }}</dd>
        </div>
      </dl>
      <p class="mt-2 text-xs text-g-500">
        Approved Non-VAT or zero-rated treatment appears in each line's TAX classification and VAT
        total. Approved BIR 2307 withholding is credited during payment and receipt allocation; it
        does not reduce this invoice.
      </p>
      <p v-if="showSurchargeNote" class="mt-2 text-xs text-g-500">
        <template v-if="surchargeMode === 'DANGEROUS_CARGO'">
          RATE is {{ dangerousPercentDisplay || '—' }}% of the tariff rate. GROSS uses the full
          adjusted rate before centavo rounding.
        </template>
        <template v-else>
          Fuel surcharge included in totals:
          {{ money(displayDraft?.fuel_surcharge_amount) }}
        </template>
      </p>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import { watchDebounced } from '@vueuse/core'
  import {
    calculateInvoiceDraft,
    emptyBillingLine,
    newBillingLineKey,
    type BillingDraftLine,
    type BillingServiceType,
    type InvoiceCalculationPreview,
    type InvoiceDraft,
    type InvoiceDraftItem,
    type RouteType,
    type SurchargeMode
  } from '@/api/invoices'
  import type { Tariff } from '@/api/pricing'

  const SERVICE_TYPES: BillingServiceType[] = ['ARRASTRE', 'STEVEDORING', 'OTHER']

  const lines = defineModel<BillingDraftLine[]>('lines', { required: true })
  const surchargeMode = defineModel<SurchargeMode>('surchargeMode', { default: 'FUEL' })
  const dangerousCargoPercent = defineModel<string | null>('dangerousCargoPercent', {
    default: null
  })

  const props = defineProps<{
    tariffs: Tariff[]
    routeType?: string
    draft?: InvoiceDraft | null
    customerId?: number | null
    businessDate?: string
    disabled?: boolean
  }>()

  const preview = ref<InvoiceCalculationPreview | null>(null)
  const previewKey = ref('')
  const computing = ref(false)
  const calcError = ref('')
  const dangerousPercentDisplay = ref('')
  let calcSeq = 0
  let syncingMode = false

  const fuelEnabled = computed(() => surchargeMode.value === 'FUEL')
  const dangerEnabled = computed(() => surchargeMode.value === 'DANGEROUS_CARGO')

  function fractionFromDisplay(display: string): string | null {
    const trimmed = display.trim()
    if (!trimmed) return null
    const n = Number(trimmed)
    if (!Number.isFinite(n) || n <= 0) return null
    return (n / 100).toFixed(4)
  }

  function pointsFromFraction(fraction: string): string {
    return (Number(fraction) * 100).toFixed(2).replace(/\.?0+$/, '')
  }

  function resolvedDangerPercent(): string | null {
    if (surchargeMode.value !== 'DANGEROUS_CARGO') return null
    return (
      fractionFromDisplay(dangerousPercentDisplay.value) ||
      (dangerousCargoPercent.value && Number(dangerousCargoPercent.value) > 0
        ? String(dangerousCargoPercent.value)
        : null)
    )
  }

  function syncPercentFromDisplay() {
    if (surchargeMode.value !== 'DANGEROUS_CARGO') return
    const next = fractionFromDisplay(dangerousPercentDisplay.value)
    if (dangerousCargoPercent.value !== next) {
      dangerousCargoPercent.value = next
    }
  }

  watch(
    () => [surchargeMode.value, dangerousCargoPercent.value] as const,
    ([mode, fraction]) => {
      if (mode !== 'DANGEROUS_CARGO') {
        if (!fraction && dangerousPercentDisplay.value !== '') {
          dangerousPercentDisplay.value = ''
        }
        return
      }
      if (!fraction) return
      const points = pointsFromFraction(fraction)
      if (dangerousPercentDisplay.value !== points) {
        dangerousPercentDisplay.value = points
      }
    },
    { immediate: true }
  )

  function onFuelToggle(value: string | number | boolean) {
    if (syncingMode) return
    if (value) {
      syncingMode = true
      dangerousCargoPercent.value = null
      dangerousPercentDisplay.value = ''
      surchargeMode.value = 'FUEL'
      syncingMode = false
      return
    }
    if (surchargeMode.value === 'FUEL') {
      surchargeMode.value = 'NONE'
    }
  }

  function onDangerToggle(value: string | number | boolean) {
    if (syncingMode) return
    if (value) {
      syncingMode = true
      if (!dangerousPercentDisplay.value.trim()) {
        dangerousPercentDisplay.value = '100'
      }
      const fraction = fractionFromDisplay(dangerousPercentDisplay.value) || '1.0000'
      dangerousCargoPercent.value = fraction
      dangerousPercentDisplay.value = pointsFromFraction(fraction)
      surchargeMode.value = 'DANGEROUS_CARGO'
      syncingMode = false
      return
    }
    if (surchargeMode.value === 'DANGEROUS_CARGO') {
      surchargeMode.value = 'NONE'
      dangerousCargoPercent.value = null
      dangerousPercentDisplay.value = ''
    }
  }

  const routeTariffs = computed(() =>
    props.tariffs.filter((tariff) => !props.routeType || tariff.route_type === props.routeType)
  )

  const serviceOptions = computed(() =>
    SERVICE_TYPES.filter((service) =>
      routeTariffs.value.some(
        (tariff) =>
          tariff.service_type === service &&
          (tariff.versions || []).some((version) => version.status === 'effective')
      )
    )
  )

  function payableLines() {
    return lines.value.filter((line) => line.tariff_version_id && Number(line.quantity) > 0)
  }

  function lineKey() {
    return payableLines()
      .map((line) => `${line.tariff_version_id}:${Number(line.quantity)}`)
      .join('|')
  }

  function surchargeKey() {
    return `${surchargeMode.value}:${resolvedDangerPercent() || ''}`
  }

  function calculationKey() {
    return `${props.customerId || ''}|${props.businessDate || ''}|${props.routeType || ''}|${lineKey()}|${surchargeKey()}`
  }

  function draftMatchesLines(draft?: InvoiceDraft | null) {
    const items = draft?.items || []
    const payable = payableLines()
    if (!payable.length || items.length !== payable.length) return false
    const modeMatches = (draft?.surcharge_mode || 'FUEL') === surchargeMode.value
    if (
      draft?.customer_id !== props.customerId ||
      draft?.business_date !== props.businessDate ||
      draft?.route_type !== props.routeType
    )
      return false
    const dangerMatches =
      String(draft?.dangerous_cargo_percent || '') === String(resolvedDangerPercent() || '')
    if (!modeMatches || !dangerMatches) return false
    return payable.every(
      (line, index) =>
        items[index]?.tariff_version_id === line.tariff_version_id &&
        Number(items[index]?.quantity) === Number(line.quantity)
    )
  }

  const displayDraft = computed(() => {
    if (preview.value && previewKey.value === calculationKey()) {
      return {
        ...(props.draft || ({} as InvoiceDraft)),
        ...preview.value.totals,
        surcharge_mode: preview.value.surcharge_mode,
        dangerous_cargo_percent: preview.value.dangerous_cargo_percent,
        items: preview.value.items
      }
    }
    if (draftMatchesLines(props.draft)) return props.draft
    return null
  })

  const showSurchargeNote = computed(() => {
    if (surchargeMode.value === 'DANGEROUS_CARGO') {
      return !!resolvedDangerPercent()
    }
    const amount = displayDraft.value?.fuel_surcharge_amount
    return !!amount && amount !== '0.00' && amount !== '0.0000'
  })

  const helperText = computed(() => {
    if (calcError.value) return calcError.value
    if (computing.value) return 'Computing totals…'
    return 'RATE and DISC. come from the tariff. Totals update as you encode.'
  })

  function serviceLabel(service: BillingServiceType) {
    if (service === 'ARRASTRE') return 'Arrastre'
    if (service === 'STEVEDORING') return 'Stevedoring'
    return 'Other'
  }

  function cargoPlaceholder(row: BillingDraftLine) {
    if (!props.routeType) return 'Select route first'
    if (!row.service_type) return 'Select service first'
    return 'Search cargo / tscode'
  }

  function cargoOptions(row: BillingDraftLine) {
    const options: Array<{ tariff: Tariff; version: Tariff['versions'][number]; label: string }> =
      []
    for (const tariff of routeTariffs.value) {
      if (row.service_type && tariff.service_type !== row.service_type) continue
      const version = (tariff.versions || []).find((entry) => entry.status === 'effective')
      if (!version) continue
      options.push({ tariff, version, label: cargoLabel(tariff) })
    }
    return options
  }

  function cargoLabel(tariff: Tariff) {
    const scode = tariff.legacy_t_scode ? ` · ${tariff.legacy_t_scode}` : ''
    return `${tariff.tariff_code} — ${tariff.name}${scode}`
  }

  function matchFor(row: BillingDraftLine) {
    if (!row.service_type) return null
    if (row.tariff_version_id) {
      return (
        cargoOptions(row).find((option) => option.version?.id === row.tariff_version_id) ?? null
      )
    }
    if (!row.tariff_code) return null
    return cargoOptions(row).find((option) => option.tariff.tariff_code === row.tariff_code) ?? null
  }

  function unitFor(row: BillingDraftLine) {
    return matchFor(row)?.tariff.unit_of_measure || '—'
  }

  function rateFor(row: BillingDraftLine) {
    const idx = lines.value.indexOf(row)
    const live = idx >= 0 ? liveItem(idx) : null
    if (live?.unit_rate) return live.unit_rate
    return matchFor(row)?.version?.rate || row.unit_rate || ''
  }

  function applyTariff(row: BillingDraftLine, option: ReturnType<typeof matchFor>) {
    row.tariff_version_id = option?.version?.id ?? null
    row.tariff_code = option?.tariff.tariff_code || ''
    row.unit_rate = option?.version?.rate || ''
    row.discount_amount = '0.00'
    row.description = ''
  }

  function onServiceChange(row: BillingDraftLine) {
    const stillValid = cargoOptions(row).some(
      (option) => option.version?.id === row.tariff_version_id
    )
    if (!stillValid) {
      applyTariff(row, null)
      return
    }
    applyTariff(row, matchFor(row))
  }

  function onCargoChange(row: BillingDraftLine) {
    applyTariff(row, matchFor(row))
  }

  function ensureLineKeys() {
    for (const line of lines.value) {
      if (!line.client_key) {
        line.client_key = newBillingLineKey()
      }
    }
  }

  function liveItem(index: number) {
    const line = lines.value[index]
    if (!line?.tariff_version_id || Number(line.quantity) <= 0) return null
    const payableIndex =
      lines.value
        .slice(0, index + 1)
        .filter((entry) => entry.tariff_version_id && Number(entry.quantity) > 0).length - 1
    const saved = displayDraft.value?.items?.[payableIndex]
    if (!saved || saved.tariff_version_id !== line.tariff_version_id) return null
    return saved
  }

  function taxLabel(item: InvoiceDraftItem | null) {
    const key = item?.snapshot?.tax_treatment_key || item?.pricing_snapshot?.tax_treatment_key
    if (key === 'EXEMPT' || key === 'NON_VAT') return 'Non-VAT'
    if (key === 'ZERO_RATED') return 'Zero-rated'
    if (key === 'VATABLE') return 'VATable'
    return '—'
  }

  function money(value: string | null | undefined) {
    if (value === null || value === undefined || value === '') return '—'
    const [whole, fraction = '00'] = value.split('.')
    return `${whole.replace(/\B(?=(\d{3})+(?!\d))/g, ',')}.${fraction.padEnd(2, '0').slice(0, 2)}`
  }

  function addLine() {
    lines.value.push(emptyBillingLine())
  }

  function removeLine(index: number) {
    if (lines.value.length < 2) return
    lines.value.splice(index, 1)
  }

  watch(
    lines,
    () => {
      ensureLineKeys()
    },
    { immediate: true, deep: false }
  )

  async function refreshTotals() {
    if (props.disabled) return
    const payable = payableLines()
    const key = calculationKey()
    if (!props.customerId || !props.routeType || !payable.length) {
      preview.value = null
      previewKey.value = ''
      computing.value = false
      calcError.value = ''
      return
    }
    const dangerPercent = resolvedDangerPercent()
    if (surchargeMode.value === 'DANGEROUS_CARGO' && !dangerPercent) {
      preview.value = null
      previewKey.value = ''
      computing.value = false
      calcError.value = 'Enter a dangerous cargo percentage greater than 0.'
      return
    }
    const seq = ++calcSeq
    computing.value = true
    calcError.value = ''
    try {
      if (surchargeMode.value === 'DANGEROUS_CARGO' && dangerPercent) {
        dangerousCargoPercent.value = dangerPercent
      }
      const result = await calculateInvoiceDraft({
        customer_id: props.customerId,
        business_date: props.businessDate,
        route_type: props.routeType as RouteType,
        surcharge_mode: surchargeMode.value,
        dangerous_cargo_percent: surchargeMode.value === 'DANGEROUS_CARGO' ? dangerPercent : null,
        items: payable.map((line) => ({
          tariff_version_id: line.tariff_version_id,
          tariff_code: line.tariff_code || undefined,
          service_type: line.service_type || undefined,
          quantity: line.quantity
        }))
      })
      if (seq !== calcSeq) return
      preview.value = result
      previewKey.value = key
    } catch (error: unknown) {
      if (seq !== calcSeq) return
      preview.value = null
      previewKey.value = ''
      calcError.value =
        error instanceof Error && error.message
          ? error.message
          : 'Unable to compute totals from the server.'
    } finally {
      if (seq === calcSeq) computing.value = false
    }
  }

  watchDebounced(
    () => [
      props.customerId,
      props.businessDate,
      props.routeType,
      props.disabled,
      lineKey(),
      surchargeKey()
    ],
    () => {
      void refreshTotals()
    },
    { debounce: 400, maxWait: 1200, immediate: true }
  )
</script>
