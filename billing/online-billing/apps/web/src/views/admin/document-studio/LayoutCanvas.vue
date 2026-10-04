<template>
  <div class="flex min-h-0 flex-1 flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-2">
      <div>
        <span class="text-xs font-semibold uppercase tracking-wide text-g-600">Form designer</span>
        <p class="mt-1 text-xs text-g-500">
          Library on the left, the page in the middle, properties on the right. The preview updates
          from the server when you let go.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <ElButton
          size="small"
          link
          type="primary"
          @click="zoomMode = zoomMode === 'fit' ? 'actual' : 'fit'"
        >
          {{ zoomMode === 'fit' ? 'Actual size' : 'Fit page' }}
        </ElButton>
        <ElCheckbox v-if="editable" v-model="snapToGrid" size="small">Snap 5 mm</ElCheckbox>
        <ElButton size="small" link type="primary" @click="showJson = !showJson">
          {{ showJson ? 'Hide layout JSON' : 'Layout JSON' }}
        </ElButton>
      </div>
    </div>

    <div
      v-if="layout && !parseError"
      class="grid min-h-[560px] flex-1 grid-cols-1 gap-2 lg:grid-cols-[11.5rem_minmax(0,1fr)_14.5rem]"
    >
      <aside
        class="flex max-h-[720px] flex-col gap-3 overflow-auto rounded-lg border border-g-300 bg-g-200 p-2"
      >
        <div v-if="editable">
          <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-g-500"
            >Library</div
          >
          <button
            type="button"
            draggable="true"
            class="flex w-full cursor-grab items-center gap-2 rounded-md border border-g-300 bg-box px-2 py-1.5 text-left text-xs text-g-800"
            @dragstart="onPaletteDragStart($event, textPaletteItem)"
            @dragend="onPaletteDragEnd"
            @click="onPaletteClick(textPaletteItem)"
          >
            <ArtSvgIcon icon="ri:text" class="text-base text-theme" />
            Custom text
          </button>
          <p class="mt-1 text-[11px] leading-snug text-g-500">
            Drag this onto the page, then type your own wording in Properties.
          </p>
          <div class="mb-1 mt-3 text-[11px] font-semibold uppercase tracking-wide text-g-500"
            >Fields</div
          >
          <div class="max-h-40 space-y-1 overflow-auto">
            <button
              v-for="item in fieldPalette"
              :key="item.key"
              type="button"
              draggable="true"
              class="flex w-full cursor-grab items-center gap-2 rounded-md border border-dashed border-theme/40 bg-box px-2 py-1 text-left text-xs text-g-800"
              @dragstart="onPaletteDragStart($event, item)"
              @dragend="onPaletteDragEnd"
              @click="onPaletteClick(item)"
            >
              <ArtSvgIcon icon="ri:link" class="shrink-0 text-sm text-theme" />
              <span class="truncate">{{ item.label }}</span>
            </button>
          </div>
          <div class="mb-1 mt-3 text-[11px] font-semibold uppercase tracking-wide text-g-500"
            >Graphics</div
          >
          <div v-if="imagePalette.length > 0" class="space-y-1">
            <button
              v-for="item in imagePalette"
              :key="item.key"
              type="button"
              draggable="true"
              class="flex w-full cursor-grab items-center gap-2 rounded-md border border-g-300 bg-box px-2 py-1 text-left text-xs text-g-800"
              @dragstart="onPaletteDragStart($event, item)"
              @dragend="onPaletteDragEnd"
              @click="onPaletteClick(item)"
            >
              <img
                v-if="item.assetId && assetPreviewUrls[item.assetId]"
                :src="assetPreviewUrls[item.assetId]"
                alt=""
                class="h-4 w-4 object-contain"
                draggable="false"
              />
              <ArtSvgIcon v-else icon="ri:image-line" class="text-sm text-theme" />
              <span class="truncate">{{ item.label }}</span>
            </button>
          </div>
          <p v-else class="text-xs text-g-500"
            >Upload a logo on Branding Assets, then drag it here.</p
          >
        </div>
        <p v-else class="text-xs italic text-warning"
          >This version is locked. Fork a draft to move objects.</p
        >

        <div>
          <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-g-500"
            >Hierarchy</div
          >
          <div v-for="band in bandOrder" :key="band" class="mb-2">
            <div class="px-1 text-[11px] font-semibold text-g-600">{{ bandLabels[band] }}</div>
            <button
              v-for="(element, index) in bandElements(band)"
              :key="`${band}-${index}`"
              type="button"
              class="flex w-full items-center gap-1 rounded px-1 py-0.5 text-left text-xs text-g-800"
              :class="{ 'bg-theme/15 text-theme': isSelected(band, index) }"
              @click="selection = { band, index }"
            >
              <ArtSvgIcon :icon="hierarchyIcon(element)" class="shrink-0 text-sm" />
              <span class="truncate">{{ elementLabel(element) || element.type }}</span>
            </button>
            <p v-if="bandElements(band).length === 0" class="px-1 text-[11px] text-g-500">Empty</p>
          </div>
        </div>
      </aside>

      <div
        ref="viewportRef"
        class="min-h-[420px] overflow-auto rounded-lg border border-g-300 bg-g-200 p-3"
        tabindex="0"
        @keydown="onKeydown"
      >
        <div
          class="relative mx-auto"
          :style="{ width: `${sheetWidthPx}px`, height: `${sheetHeightPx}px` }"
        >
          <div
            class="studio-sheet absolute left-0 top-0"
            :style="{
              width: `${contentWidth * pxPerMmAt100}px`,
              transform: `scale(${zoom})`,
              transformOrigin: 'top left'
            }"
          >
            <section v-for="band in bandOrder" :key="band" class="studio-band">
              <div class="studio-band-label">{{ bandLabels[band] }}</div>
              <div class="studio-ruler-x" :style="{ width: `${contentWidth}mm` }">
                <span
                  v-for="tick in horizontalTicks"
                  :key="`x-${band}-${tick}`"
                  class="studio-tick"
                  :style="{ left: `${tick}mm` }"
                  >{{ tick }}</span
                >
              </div>
              <div class="flex">
                <div class="studio-ruler-y" :style="{ height: `${displayedBandHeight(band)}mm` }">
                  <span
                    v-for="tick in verticalTicks(displayedBandHeight(band))"
                    :key="`y-${band}-${tick}`"
                    class="studio-tick-y"
                    :style="{ top: `${tick}mm` }"
                    >{{ tick }}</span
                  >
                </div>
                <div
                  class="studio-band-surface"
                  :class="{ 'is-drop-target': dropBand === band }"
                  :style="{ width: `${contentWidth}mm`, height: `${displayedBandHeight(band)}mm` }"
                  :ref="(el) => setBandRef(band, el)"
                  @dragover.prevent="dropBand = band"
                  @dragleave="onBandDragLeave(band, $event)"
                  @drop="onDrop(band, $event)"
                  @pointerdown.self="selection = null"
                >
                  <p v-if="bandElements(band).length === 0" class="studio-band-empty"
                    >Drop text, a field, or a logo here</p
                  >
                  <div
                    v-for="(element, index) in bandElements(band)"
                    :key="`${band}-${index}`"
                    class="studio-element"
                    :class="elementClass(element, band, index)"
                    :style="elementStyle(element)"
                    @pointerdown="beginDrag($event, band, index, 'move')"
                  >
                    <img
                      v-if="
                        element.type === 'image' &&
                        element.asset_id &&
                        assetPreviewUrls[element.asset_id]
                      "
                      :src="assetPreviewUrls[element.asset_id]"
                      alt=""
                      class="studio-image"
                      draggable="false"
                    />
                    <span v-else class="studio-element-text">{{ elementLabel(element) }}</span>
                    <button
                      v-if="editable && isSelected(band, index) && canMove(element)"
                      type="button"
                      class="studio-resize"
                      aria-label="Resize"
                      @pointerdown="beginDrag($event, band, index, 'resize')"
                    />
                  </div>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div>

      <aside class="max-h-[720px] overflow-auto rounded-lg border border-g-300 bg-box p-3">
        <div v-if="selectedElement">
          <div class="mb-2 flex items-center justify-between gap-2">
            <span class="text-xs font-semibold uppercase tracking-wide text-g-600">Properties</span>
            <ElButton v-if="editable" size="small" type="danger" link @click="removeSelected"
              >Remove</ElButton
            >
          </div>
          <p class="mb-2 text-xs font-medium text-g-800">{{ selectedTypeLabel }}</p>

          <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
            <label
              v-if="selectedElement.type === 'static_text'"
              class="block text-xs text-g-600 sm:col-span-2"
            >
              Your text
              <ElInput
                v-model="selectedElement.text"
                type="textarea"
                :rows="4"
                resize="vertical"
                class="mt-1"
                placeholder="Type the wording that should appear on the document"
                :disabled="!editable"
                @input="commit"
              />
            </label>
            <label
              v-else-if="selectedElement.type === 'bound_text'"
              class="block text-xs text-g-600 sm:col-span-2"
            >
              Field
              <ElSelect
                v-model="selectedElement.field"
                size="small"
                class="mt-1 w-full"
                filterable
                allow-create
                default-first-option
                :disabled="!editable"
                @change="commit"
              >
                <ElOption
                  v-for="field in fieldOptions"
                  :key="field.value"
                  :label="field.label"
                  :value="field.value"
                />
              </ElSelect>
            </label>
            <p
              v-else-if="selectedElement.type === 'table'"
              class="text-xs text-g-500 sm:col-span-2"
            >
              Item tables stay in the details band. Change columns in Layout JSON.
            </p>
            <p
              v-else-if="selectedElement.type === 'image'"
              class="text-xs text-g-500 sm:col-span-2"
            >
              {{ imageAssetLabel(selectedElement.asset_id) }}
            </p>

            <template
              v-if="selectedElement.type === 'static_text' || selectedElement.type === 'bound_text'"
            >
              <label class="block text-xs text-g-600">
                Size (pt)
                <ElInput
                  :model-value="selectedElement.font_size_pt ?? 9"
                  size="small"
                  type="number"
                  class="mt-1"
                  :disabled="!editable"
                  @update:model-value="updateNumber('font_size_pt', $event)"
                />
              </label>
              <label class="block text-xs text-g-600">
                Align
                <ElSelect
                  :model-value="selectedElement.align || 'left'"
                  size="small"
                  class="mt-1 w-full"
                  :disabled="!editable"
                  @update:model-value="setAlign"
                >
                  <ElOption label="Left" value="left" />
                  <ElOption label="Center" value="center" />
                  <ElOption label="Right" value="right" />
                </ElSelect>
              </label>
              <label class="flex items-center gap-2 text-xs text-g-600 sm:col-span-2">
                <ElCheckbox
                  :model-value="selectedElement.font_weight === 'bold'"
                  :disabled="!editable"
                  @update:model-value="setBold"
                >
                  Bold
                </ElCheckbox>
              </label>
            </template>

            <label class="block text-xs text-g-600">
              X (mm)
              <ElInput
                :model-value="selectedElement.x_mm"
                size="small"
                type="number"
                class="mt-1"
                :disabled="!editable || !canMove(selectedElement)"
                @update:model-value="updateNumber('x_mm', $event)"
              />
            </label>
            <label class="block text-xs text-g-600">
              Y (mm)
              <ElInput
                :model-value="selectedElement.y_mm"
                size="small"
                type="number"
                class="mt-1"
                :disabled="!editable || !canMove(selectedElement)"
                @update:model-value="updateNumber('y_mm', $event)"
              />
            </label>
            <label class="block text-xs text-g-600">
              Width (mm)
              <ElInput
                :model-value="selectedElement.width_mm"
                size="small"
                type="number"
                class="mt-1"
                :disabled="!editable || !canMove(selectedElement)"
                @update:model-value="updateNumber('width_mm', $event)"
              />
            </label>
            <label class="block text-xs text-g-600">
              Height (mm)
              <ElInput
                :model-value="selectedElement.height_mm"
                size="small"
                type="number"
                class="mt-1"
                :disabled="!editable || !canMove(selectedElement)"
                @update:model-value="updateNumber('height_mm', $event)"
              />
            </label>
          </div>
        </div>
        <p v-else class="text-xs text-g-500">Select an object on the page or in the hierarchy.</p>
      </aside>
    </div>

    <p
      v-if="parseError"
      class="rounded-md border border-error/30 bg-error/12 px-3 py-2 text-xs text-error"
    >
      {{ parseError }}
    </p>

    <ElInput
      v-if="showJson"
      :model-value="modelValue"
      type="textarea"
      :rows="12"
      :readonly="!editable"
      class="font-mono text-xs"
      @update:model-value="onJsonEdit"
    />
  </div>
</template>

<script setup lang="ts">
  import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
  import { downloadDocumentTemplateAsset } from '@/api/documentStudio'

  type LayoutElement = {
    type: string
    x_mm: number
    y_mm: number
    width_mm: number
    height_mm: number
    text?: string
    field?: string
    font_size_pt?: number
    font_weight?: string
    align?: string
    asset_id?: number
  }

  type LayoutBand = {
    height_mm?: number
    elements?: LayoutElement[]
  }

  type LayoutDoc = {
    page?: {
      paper_size?: string
      orientation?: string
      width_mm?: number
      height_mm?: number
      margins?: { top?: number; right?: number; bottom?: number; left?: number }
    }
    bands?: Record<string, LayoutBand>
  }

  type PaletteItem = {
    key: string
    kind: 'static_text' | 'bound_text' | 'image'
    label: string
    field?: string
    assetId?: number
  }

  type StudioAsset = {
    id: number
    name: string
    asset_type: string
  }

  type DragState = {
    band: string
    index: number
    mode: 'move' | 'resize'
    startX: number
    startY: number
    originX: number
    originY: number
    originW: number
    originH: number
  }

  const props = withDefaults(
    defineProps<{
      modelValue: string
      editable: boolean
      documentKind: string
      assets?: StudioAsset[]
    }>(),
    { assets: () => [] }
  )

  const emit = defineEmits<{
    'update:modelValue': [value: string]
  }>()

  const bandOrder = ['header', 'details', 'summary', 'footer'] as const
  const bandLabels: Record<string, string> = {
    header: 'Header',
    details: 'Details',
    summary: 'Summary',
    footer: 'Footer'
  }
  const defaultBandHeight: Record<string, number> = {
    header: 50,
    details: 40,
    summary: 40,
    footer: 25
  }
  const pxPerMmAt100 = 96 / 25.4

  const FIELD_CATALOG: Record<string, Array<[string, string]>> = {
    invoice: [
      ['Issuer name', 'issuer.registered_name'],
      ['Issuer address', 'issuer.address'],
      ['Issuer TIN', 'issuer.tin'],
      ['BIR permit', 'issuer.bir_permit'],
      ['Invoice number', 'invoice.invoice_number'],
      ['Invoice date', 'invoice.invoice_date'],
      ['Buyer name', 'buyer.registered_name'],
      ['Buyer TIN', 'buyer.tin'],
      ['Buyer address', 'buyer.address'],
      ['Vatable sales', 'totals.vatable_sales'],
      ['VAT amount', 'totals.vat_amount'],
      ['Amount due', 'totals.total_amount_due']
    ],
    receipt: [
      ['Issuer name', 'issuer.registered_name'],
      ['Issuer address', 'issuer.address'],
      ['Issuer TIN', 'issuer.tin'],
      ['Receipt number', 'receipt.receipt_number'],
      ['Receipt date', 'receipt.receipt_date'],
      ['Payer name', 'payer.registered_name'],
      ['Payer TIN', 'payer.tin'],
      ['Cash received', 'totals.cash_received'],
      ['Withholding', 'totals.withholding_received'],
      ['Amount applied', 'totals.applied_amount']
    ],
    statement: [
      ['Organization', 'organization.name'],
      ['Statement number', 'statement.statement_number'],
      ['As of date', 'statement.as_of_date'],
      ['Account', 'customer.name'],
      ['Currency', 'statement.currency'],
      ['Outstanding total', 'totals.outstanding_total']
    ],
    transmittal: [
      ['Organization', 'organization.name'],
      ['Transmittal number', 'transmittal.transmittal_number'],
      ['As of date', 'transmittal.as_of_date'],
      ['Type', 'transmittal.kind_label'],
      ['Currency', 'transmittal.currency'],
      ['Primary total', 'summary.primary_total']
    ]
  }

  const layout = ref<LayoutDoc | null>(null)
  const parseError = ref('')
  const selection = ref<{ band: string; index: number } | null>(null)
  const showJson = ref(false)
  const zoomMode = ref<'fit' | 'actual'>('fit')
  const snapToGrid = ref(true)
  const fitZoom = ref(1)
  const dropBand = ref('')
  const viewportRef = ref<HTMLElement | null>(null)
  const bandRefs = new Map<string, HTMLElement>()
  let lastWritten = ''
  let suppressPaletteClick = false
  let dragState: DragState | null = null
  const assetPreviewUrls = ref<Record<number, string>>({})
  let assetPreviewSerial = 0
  let resizeObserver: ResizeObserver | null = null

  const catalogKey = computed(() => {
    if (['COLLECTION_RECEIPT', 'ACKNOWLEDGEMENT_RECEIPT'].includes(props.documentKind))
      return 'receipt'
    if (props.documentKind === 'ACCOUNT_STATEMENT') return 'statement'
    if (props.documentKind === 'YELLOW_INVOICE' || props.documentKind === 'WHITE_RECEIPT')
      return 'transmittal'
    return 'invoice'
  })

  const textPaletteItem: PaletteItem = {
    key: 'static-text',
    kind: 'static_text',
    label: 'Custom text'
  }

  const fieldPalette = computed<PaletteItem[]>(() =>
    FIELD_CATALOG[catalogKey.value].map(([label, field]) => ({
      key: field,
      kind: 'bound_text' as const,
      label,
      field
    }))
  )

  const imagePalette = computed<PaletteItem[]>(() =>
    props.assets.map((asset) => ({
      key: `asset-${asset.id}`,
      kind: 'image' as const,
      label: asset.name,
      assetId: asset.id
    }))
  )

  const fieldOptions = computed(() => {
    const options = FIELD_CATALOG[catalogKey.value].map(([label, value]) => ({
      label: `${label} (${value})`,
      value
    }))
    const current = selectedElement.value?.field
    if (current && !options.some((option) => option.value === current)) {
      options.unshift({ label: current, value: current })
    }
    return options
  })

  const contentWidth = computed(() => {
    const page = layout.value?.page
    const paper = page?.paper_size || 'LETTER'
    const landscape = String(page?.orientation || '').toUpperCase() === 'LANDSCAPE'
    let width = 215.9
    let height = 279.4
    if (paper === 'A4') {
      width = 210
      height = 297
    } else if (paper === 'CUSTOM') {
      width = Number(page?.width_mm) || 215.9
      height = Number(page?.height_mm) || 279.4
    }
    if (landscape) {
      const swap = width
      width = height
      height = swap
    }
    const margins = page?.margins || {}
    return Math.max(40, width - Number(margins.left || 0) - Number(margins.right || 0))
  })

  const horizontalTicks = computed(() => {
    const ticks: number[] = []
    for (let mm = 0; mm <= contentWidth.value; mm += 10) ticks.push(mm)
    return ticks
  })

  function verticalTicks(heightMm: number) {
    const ticks: number[] = []
    for (let mm = 0; mm <= heightMm; mm += 10) ticks.push(mm)
    return ticks
  }

  const zoom = computed(() => (zoomMode.value === 'actual' ? 1 : fitZoom.value))
  const sheetWidthPx = computed(() => contentWidth.value * pxPerMmAt100 * zoom.value)
  const sheetHeightPx = computed(() => {
    const millimeters = bandOrder.reduce((sum, band) => sum + displayedBandHeight(band) + 3, 0)
    const labels = bandOrder.length * 16
    return (millimeters * pxPerMmAt100 + labels) * zoom.value
  })

  const selectedElement = computed(() => {
    const current = selection.value
    if (!current || !layout.value?.bands?.[current.band]) return null
    return layout.value.bands[current.band].elements?.[current.index] ?? null
  })

  const selectedTypeLabel = computed(() => {
    const type = selectedElement.value?.type
    if (type === 'static_text') return 'Custom text'
    if (type === 'bound_text') return 'Field'
    if (type === 'table') return 'Table'
    if (type === 'image') return 'Image'
    if (type === 'line') return 'Line'
    if (type === 'rectangle') return 'Rectangle'
    return 'Element'
  })

  watch(
    () => props.modelValue,
    (value) => {
      if (value === lastWritten) return
      lastWritten = value
      selection.value = null
      applyJson(value)
    },
    { immediate: true }
  )

  watch(parseError, (error) => {
    if (error) showJson.value = true
  })

  onMounted(() => {
    nextTick(updateFit)
    window.addEventListener('pointermove', onPointerMove)
    window.addEventListener('pointerup', endDrag)
  })

  onBeforeUnmount(() => {
    resizeObserver?.disconnect()
    window.removeEventListener('pointermove', onPointerMove)
    window.removeEventListener('pointerup', endDrag)
    revokeAssetPreviews()
  })

  watch(
    () => props.assets.map((asset) => asset.id).join(','),
    () => {
      void loadAssetPreviews()
    },
    { immediate: true }
  )

  watch(viewportRef, (node) => {
    resizeObserver?.disconnect()
    if (!node || typeof ResizeObserver === 'undefined') return
    resizeObserver = new ResizeObserver(() => updateFit())
    resizeObserver.observe(node)
    updateFit()
  })

  function revokeAssetPreviews() {
    Object.values(assetPreviewUrls.value).forEach((url) => URL.revokeObjectURL(url))
    assetPreviewUrls.value = {}
  }

  async function loadAssetPreviews() {
    const serial = ++assetPreviewSerial
    const nextUrls: Record<number, string> = {}
    await Promise.all(
      props.assets.map(async (asset) => {
        try {
          const blob = await downloadDocumentTemplateAsset(asset.id)
          if (!(blob instanceof Blob) || blob.size === 0) return
          nextUrls[asset.id] = URL.createObjectURL(blob)
        } catch {
          // The chip still shows the asset name when the private file cannot be previewed.
        }
      })
    )
    if (serial !== assetPreviewSerial) {
      Object.values(nextUrls).forEach((url) => URL.revokeObjectURL(url))
      return
    }
    revokeAssetPreviews()
    assetPreviewUrls.value = nextUrls
  }

  function imageAssetLabel(assetId?: number) {
    const asset = props.assets.find((item) => item.id === assetId)
    if (!asset) return 'Logo'
    if (asset.asset_type === 'SIGNATURE') return `Signature: ${asset.name}`
    if (asset.asset_type === 'WATERMARK') return `Watermark: ${asset.name}`
    return `Logo: ${asset.name}`
  }

  function applyJson(value: string) {
    try {
      layout.value = value ? (JSON.parse(value) as LayoutDoc) : null
      parseError.value = ''
    } catch {
      layout.value = null
      parseError.value = 'Layout JSON is invalid. Fix it below before using the page.'
    }
  }

  function commit() {
    if (!layout.value || parseError.value) return
    const json = JSON.stringify(layout.value, null, 2)
    if (json === lastWritten) return
    lastWritten = json
    emit('update:modelValue', json)
  }

  function onJsonEdit(value: string) {
    lastWritten = value
    emit('update:modelValue', value)
    selection.value = null
    applyJson(value)
  }

  function updateFit() {
    const node = viewportRef.value
    if (!node || contentWidth.value <= 0) return
    const available = Math.max(120, node.clientWidth - 24)
    const natural = contentWidth.value * pxPerMmAt100
    fitZoom.value = Math.min(1, available / natural)
  }

  function displayedBandHeight(band: string, ignoreIndex = -1) {
    const stored = layout.value?.bands?.[band]
    if (stored?.height_mm && Number(stored.height_mm) > 0) return Number(stored.height_mm)
    const elements = (stored?.elements || []).filter((_, index) => index !== ignoreIndex)
    if (elements.length === 0) return defaultBandHeight[band]
    const bottom = Math.max(
      ...elements.map((element) => Number(element.y_mm) + Number(element.height_mm))
    )
    return Math.max(defaultBandHeight[band], bottom + 2)
  }

  function bandElements(band: string) {
    return layout.value?.bands?.[band]?.elements || []
  }

  function setBandRef(band: string, element: unknown) {
    if (element instanceof HTMLElement) bandRefs.set(band, element)
    else bandRefs.delete(band)
  }

  function roundMm(value: number) {
    return Math.round(value * 10) / 10
  }

  function pxToMm(band: string, px: number) {
    const node = bandRefs.get(band)
    if (!node || contentWidth.value <= 0) return px / pxPerMmAt100
    return px / (node.getBoundingClientRect().width / contentWidth.value)
  }

  function ensureBand(band: string) {
    if (!layout.value) return
    if (!layout.value.bands) layout.value.bands = {}
    if (!layout.value.bands[band]) {
      layout.value.bands[band] = { height_mm: defaultBandHeight[band], elements: [] }
    }
    if (!Array.isArray(layout.value.bands[band].elements)) {
      layout.value.bands[band].elements = []
    }
  }

  function canMove(element: LayoutElement) {
    return element.type !== 'table'
  }

  function hierarchyIcon(element: LayoutElement) {
    if (element.type === 'static_text') return 'ri:text'
    if (element.type === 'bound_text') return 'ri:link'
    if (element.type === 'image') return 'ri:image-line'
    if (element.type === 'table') return 'ri:table-line'
    if (element.type === 'line') return 'ri:subtract-line'
    return 'ri:shape-line'
  }

  function elementLabel(element: LayoutElement) {
    if (element.type === 'static_text') return element.text || 'Text'
    if (element.type === 'bound_text') return element.field || 'Field'
    if (element.type === 'table') return 'Items table'
    if (element.type === 'image') return imageAssetLabel(element.asset_id)
    if (element.type === 'qrcode') return 'QR code'
    return ''
  }

  function elementStyle(element: LayoutElement) {
    return {
      left: `${Number(element.x_mm) || 0}mm`,
      top: `${Number(element.y_mm) || 0}mm`,
      width: `${Number(element.width_mm) || 20}mm`,
      height: `${Number(element.height_mm) || 6}mm`,
      fontSize: element.font_size_pt ? `${element.font_size_pt}pt` : undefined,
      fontWeight: element.font_weight || undefined,
      textAlign: (element.align as 'left' | 'center' | 'right' | undefined) || undefined
    }
  }

  function elementClass(element: LayoutElement, band: string, index: number) {
    return [
      `is-${element.type}`,
      {
        'is-selected': isSelected(band, index),
        'is-locked': !canMove(element)
      }
    ]
  }

  function isSelected(band: string, index: number) {
    return selection.value?.band === band && selection.value.index === index
  }

  function onPaletteDragStart(event: DragEvent, item: PaletteItem) {
    suppressPaletteClick = true
    event.dataTransfer?.setData('application/json', JSON.stringify(item))
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'copy'
  }

  function onPaletteDragEnd() {
    dropBand.value = ''
    window.setTimeout(() => {
      suppressPaletteClick = false
    }, 0)
  }

  function onPaletteClick(item: PaletteItem) {
    if (suppressPaletteClick || !props.editable) return
    const band = selection.value?.band || 'header'
    const stack = bandElements(band).length * 7
    addElement(band, item, 4, 4 + stack)
  }

  function onBandDragLeave(band: string, event: DragEvent) {
    const current = event.currentTarget
    const related = event.relatedTarget
    if (current instanceof HTMLElement && related instanceof Node && current.contains(related))
      return
    if (dropBand.value === band) dropBand.value = ''
  }

  function onDrop(band: string, event: DragEvent) {
    event.preventDefault()
    dropBand.value = ''
    if (!props.editable || parseError.value) return
    const raw = event.dataTransfer?.getData('application/json')
    if (!raw) return
    let item: PaletteItem
    try {
      item = JSON.parse(raw) as PaletteItem
    } catch {
      return
    }
    const surface = event.currentTarget
    if (!(surface instanceof HTMLElement) || contentWidth.value <= 0) return
    const rect = surface.getBoundingClientRect()
    const x = ((event.clientX - rect.left) / rect.width) * contentWidth.value
    const y = ((event.clientY - rect.top) / rect.height) * displayedBandHeight(band)
    addElement(band, item, x, y)
  }

  function addElement(band: string, item: PaletteItem, x: number, y: number) {
    if (!props.editable || !layout.value || parseError.value) return
    ensureBand(band)
    const elements = layout.value.bands?.[band]?.elements
    if (!elements) return
    if (item.kind === 'image' && !item.assetId) return
    const element: LayoutElement = {
      type: item.kind,
      x_mm: 0,
      y_mm: 0,
      width_mm: item.kind === 'image' ? 36 : 70,
      height_mm: item.kind === 'image' ? 18 : item.kind === 'static_text' ? 12 : 6
    }
    if (item.kind !== 'image') element.font_size_pt = 9
    if (item.kind === 'static_text') element.text = 'Custom text'
    if (item.kind === 'bound_text') element.field = item.field
    if (item.kind === 'image' && item.assetId) element.asset_id = item.assetId
    placeElement(band, element, x, y)
    elements.push(element)
    selection.value = { band, index: elements.length - 1 }
    commit()
  }

  function snapMm(value: number) {
    if (!snapToGrid.value) return roundMm(value)
    return Math.round(value / 5) * 5
  }

  function placeElement(
    band: string,
    element: LayoutElement,
    x: number,
    y: number,
    ignoreIndex = -1
  ) {
    const maxX = Math.max(0, contentWidth.value - element.width_mm)
    const maxY = Math.max(0, displayedBandHeight(band, ignoreIndex) - element.height_mm)
    element.x_mm = snapMm(Math.min(Math.max(0, x), maxX))
    element.y_mm = snapMm(Math.min(Math.max(0, y), maxY))
  }

  function beginDrag(event: PointerEvent, band: string, index: number, mode: 'move' | 'resize') {
    const element = bandElements(band)[index]
    if (!element) return
    selection.value = { band, index }
    if (!props.editable || parseError.value || !canMove(element)) return
    if (
      mode === 'move' &&
      event.target instanceof HTMLElement &&
      event.target.classList.contains('studio-resize')
    ) {
      return
    }
    event.preventDefault()
    event.stopPropagation()
    dragState = {
      band,
      index,
      mode,
      startX: event.clientX,
      startY: event.clientY,
      originX: Number(element.x_mm) || 0,
      originY: Number(element.y_mm) || 0,
      originW: Number(element.width_mm) || 20,
      originH: Number(element.height_mm) || 6
    }
  }

  function onPointerMove(event: PointerEvent) {
    if (!dragState || !layout.value) return
    const element = layout.value.bands?.[dragState.band]?.elements?.[dragState.index]
    if (!element) return
    const dx = pxToMm(dragState.band, event.clientX - dragState.startX)
    const dy = pxToMm(dragState.band, event.clientY - dragState.startY)
    if (dragState.mode === 'move') {
      placeElement(
        dragState.band,
        element,
        dragState.originX + dx,
        dragState.originY + dy,
        dragState.index
      )
      return
    }
    element.width_mm = snapMm(Math.max(8, dragState.originW + dx))
    element.height_mm = snapMm(Math.max(3, dragState.originH + dy))
    const maxWidth = Math.max(8, contentWidth.value - element.x_mm)
    const maxHeight = Math.max(
      3,
      displayedBandHeight(dragState.band, dragState.index) - element.y_mm
    )
    element.width_mm = Math.min(element.width_mm, maxWidth)
    element.height_mm = Math.min(element.height_mm, maxHeight)
  }

  function endDrag() {
    if (!dragState) return
    dragState = null
    commit()
  }

  function updateNumber(
    key: 'x_mm' | 'y_mm' | 'width_mm' | 'height_mm' | 'font_size_pt',
    value: string | number
  ) {
    const element = selectedElement.value
    const current = selection.value
    if (!element || !current || !props.editable) return
    const numeric = Number(value)
    if (!Number.isFinite(numeric)) return
    if (key === 'font_size_pt') {
      element.font_size_pt = roundMm(Math.min(48, Math.max(6, numeric)))
    } else if (key === 'width_mm') {
      element.width_mm = roundMm(Math.max(8, numeric))
    } else if (key === 'height_mm') {
      element.height_mm = roundMm(Math.max(3, numeric))
    } else if (key === 'x_mm') {
      element.x_mm = roundMm(Math.max(0, numeric))
    } else {
      element.y_mm = roundMm(Math.max(0, numeric))
    }
    commit()
  }

  function setAlign(value: string) {
    if (!selectedElement.value || !props.editable) return
    selectedElement.value.align = value
    commit()
  }

  function setBold(value: string | number | boolean) {
    if (!selectedElement.value || !props.editable) return
    selectedElement.value.font_weight = value ? 'bold' : 'normal'
    commit()
  }

  function removeSelected() {
    const current = selection.value
    const elements = current ? layout.value?.bands?.[current.band]?.elements : undefined
    if (!current || !elements || !props.editable) return
    elements.splice(current.index, 1)
    selection.value = null
    commit()
  }

  function onKeydown(event: KeyboardEvent) {
    if (!props.editable) return
    if (event.target instanceof HTMLInputElement || event.target instanceof HTMLTextAreaElement)
      return
    if (event.key !== 'Delete' && event.key !== 'Backspace') return
    event.preventDefault()
    removeSelected()
  }
</script>

<style scoped>
  .studio-sheet {
    color: #1c1c1c;
    font-family: Helvetica, Arial, sans-serif;
  }

  .studio-band + .studio-band {
    margin-top: 2mm;
  }

  .studio-band-label {
    margin-bottom: 1mm;
    color: var(--art-gray-600);
    font-size: 9px;
    font-weight: 600;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .studio-ruler-x,
  .studio-ruler-y {
    position: relative;
    flex: none;
    overflow: hidden;
    background: var(--default-box-color);
    color: var(--art-gray-600);
    font-size: 8px;
  }

  .studio-ruler-x {
    height: 14px;
    margin-left: 16px;
    border-bottom: 1px solid var(--art-card-border);
  }

  .studio-ruler-y {
    width: 16px;
    border-right: 1px solid var(--art-card-border);
  }

  .studio-tick,
  .studio-tick-y {
    position: absolute;
    line-height: 1;
  }

  .studio-tick {
    top: 2px;
  }

  .studio-tick-y {
    right: 1px;
  }

  .studio-band-surface {
    position: relative;
    overflow: hidden;
    background-color: #fff;
    background-image:
      linear-gradient(to right, rgb(28 28 28 / 8%) 1px, transparent 1px),
      linear-gradient(to bottom, rgb(28 28 28 / 8%) 1px, transparent 1px);
    background-size: 5mm 5mm;
    box-shadow: 0 1px 3px rgb(0 0 0 / 18%);
  }

  .studio-band-surface.is-drop-target {
    outline: 2px solid var(--el-color-primary);
    outline-offset: -2px;
  }

  .studio-band-empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    color: #9a9aa6;
    font-size: 11px;
    pointer-events: none;
  }

  .studio-element {
    position: absolute;
    box-sizing: border-box;
    overflow: hidden;
    line-height: 1.15;
    cursor: grab;
    user-select: none;
  }

  .studio-element.is-locked {
    cursor: default;
  }

  .studio-element.is-static_text:hover,
  .studio-element.is-bound_text:hover,
  .studio-element.is-image:hover,
  .studio-element.is-selected {
    outline: 1px solid var(--el-color-primary);
    outline-offset: -1px;
    z-index: 2;
  }

  .studio-element.is-bound_text {
    border: 1px dashed var(--el-color-primary);
    background: color-mix(in srgb, var(--el-color-primary) 12%, #fff);
  }

  .studio-element.is-line {
    border-bottom: 1px solid #111;
  }

  .studio-element.is-rectangle,
  .studio-element.is-table,
  .studio-element.is-image,
  .studio-element.is-qrcode {
    border: 1px solid #333;
    background: #f6f6f6;
  }

  .studio-element-text {
    display: block;
    overflow: hidden;
    white-space: pre-wrap;
    word-break: break-word;
  }

  .studio-image {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: contain;
    pointer-events: none;
  }

  .studio-resize {
    position: absolute;
    right: 0;
    bottom: 0;
    width: 8px;
    height: 8px;
    padding: 0;
    border: 0;
    background: var(--el-color-primary);
    cursor: nwse-resize;
  }
</style>
