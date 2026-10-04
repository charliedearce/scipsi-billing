<template>
  <div
    class="document-preview-pane flex flex-col h-full min-h-0 rounded-xl border border-slate-200/80 bg-slate-950/[0.03] overflow-hidden"
  >
    <div
      v-if="showToolbar"
      class="flex items-center justify-between gap-2 px-3 py-2 border-b border-slate-200/80 bg-white/70 dark:bg-slate-900/60"
    >
      <div class="min-w-0">
        <p class="text-sm font-medium truncate">{{ title || 'Document reference' }}</p>
        <p v-if="subtitle" class="text-[11px] text-slate-500 truncate">{{ subtitle }}</p>
      </div>
      <div class="flex items-center gap-1 shrink-0">
        <template v-if="!isPdf && blobUrl">
          <ElTooltip content="Zoom out" placement="top">
            <ElButton size="small" circle @click="adjustZoom(-0.2)">
              <ElIcon><ZoomOut /></ElIcon>
            </ElButton>
          </ElTooltip>
          <span class="text-xs text-slate-500 font-mono w-11 text-center"
            >{{ Math.round(zoom * 100) }}%</span
          >
          <ElTooltip content="Zoom in" placement="top">
            <ElButton size="small" circle @click="adjustZoom(0.2)">
              <ElIcon><ZoomIn /></ElIcon>
            </ElButton>
          </ElTooltip>
          <ElTooltip content="Rotate" placement="top">
            <ElButton size="small" circle @click="rotation = (rotation + 90) % 360">
              <ElIcon><RefreshRight /></ElIcon>
            </ElButton>
          </ElTooltip>
          <ElButton size="small" text @click="resetControls">Reset</ElButton>
        </template>
        <ElButton
          v-if="blobUrl && showDownload"
          size="small"
          plain
          :loading="downloading"
          @click="downloadFile"
        >
          <ElIcon class="mr-1"><Download /></ElIcon>
          Download
        </ElButton>
        <slot name="toolbar-extra" />
      </div>
    </div>

    <div
      class="relative flex-1 min-h-0 flex flex-col items-center justify-center overflow-hidden"
      :style="{ minHeight: minHeight }"
    >
      <div v-if="loading" class="flex flex-col items-center justify-center p-10 text-slate-500">
        <ElIcon class="is-loading text-3xl text-primary mb-3">
          <Loading />
        </ElIcon>
        <p class="text-sm font-medium">Loading document…</p>
      </div>

      <div v-else-if="error" class="flex flex-col items-center justify-center p-8 text-center">
        <ElIcon class="text-4xl text-rose-500 mb-2">
          <WarningFilled />
        </ElIcon>
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">
          Failed to load preview
        </p>
        <p class="text-xs text-slate-500 max-w-sm mb-4">{{ error }}</p>
        <ElButton size="small" @click="reload">Try again</ElButton>
      </div>

      <template v-else-if="blobUrl">
        <iframe
          v-if="isPdf"
          :src="blobUrl"
          class="w-full h-full min-h-[280px] border-0"
          title="Document preview"
        />

        <div
          v-else
          ref="viewportEl"
          class="relative w-full h-full min-h-[280px] overflow-hidden select-none touch-none"
          :class="zoom > 1 ? (dragging ? 'cursor-grabbing' : 'cursor-grab') : 'cursor-default'"
          @pointerdown="onPointerDown"
          @pointermove="onPointerMove"
          @pointerup="onPointerUp"
          @pointercancel="onPointerUp"
          @wheel.prevent="onWheel"
        >
          <img
            :src="blobUrl"
            :style="imageStyle"
            class="absolute left-1/2 top-1/2 max-w-full max-h-full object-contain shadow-sm pointer-events-none"
            alt="Document preview"
            draggable="false"
          />
          <p
            v-if="zoom > 1"
            class="absolute bottom-3 left-1/2 -translate-x-1/2 text-[11px] text-white/90 bg-slate-900/55 px-2.5 py-1 rounded-full pointer-events-none"
          >
            Drag to pan · scroll to zoom
          </p>
        </div>
      </template>

      <ElEmpty v-else :description="emptyText" />
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import {
    Download,
    Loading,
    RefreshRight,
    WarningFilled,
    ZoomIn,
    ZoomOut
  } from '@element-plus/icons-vue'

  interface Props {
    title?: string
    subtitle?: string
    fileName?: string
    mimeType?: string | null
    fetcher?: (() => Promise<{ blob: Blob; filename?: string; mimeType?: string }>) | null
    active?: boolean
    /** Change this when the underlying file changes so the pane reloads. */
    reloadKey?: string | number | null
    showToolbar?: boolean
    showDownload?: boolean
    minHeight?: string
    emptyText?: string
  }

  const props = withDefaults(defineProps<Props>(), {
    title: 'Document reference',
    subtitle: '',
    fileName: 'document',
    mimeType: null,
    fetcher: null,
    active: true,
    reloadKey: null,
    showToolbar: true,
    showDownload: true,
    minHeight: '320px',
    emptyText: 'Select a document to preview'
  })

  const loading = ref(false)
  const downloading = ref(false)
  const error = ref('')
  const blobUrl = ref('')
  const detectedMime = ref('')
  const zoom = ref(1)
  const rotation = ref(0)
  const offsetX = ref(0)
  const offsetY = ref(0)
  const dragging = ref(false)
  const dragOrigin = ref({ x: 0, y: 0, ox: 0, oy: 0 })
  const viewportEl = ref<HTMLElement | null>(null)

  const isPdf = computed(() => {
    const m = detectedMime.value || props.mimeType || ''
    return m.toLowerCase().includes('pdf')
  })

  const imageStyle = computed(() => ({
    transform: `translate(-50%, -50%) translate(${offsetX.value}px, ${offsetY.value}px) scale(${zoom.value}) rotate(${rotation.value}deg)`,
    transition: dragging.value ? 'none' : 'transform 0.15s ease-out'
  }))

  function clampZoom(value: number) {
    return Math.min(4, Math.max(0.4, Math.round(value * 100) / 100))
  }

  function adjustZoom(delta: number) {
    const next = clampZoom(zoom.value + delta)
    zoom.value = next
    if (next <= 1) {
      offsetX.value = 0
      offsetY.value = 0
    }
  }

  function resetControls() {
    zoom.value = 1
    rotation.value = 0
    offsetX.value = 0
    offsetY.value = 0
    dragging.value = false
  }

  function onPointerDown(event: PointerEvent) {
    if (zoom.value <= 1 || event.button !== 0) return
    dragging.value = true
    dragOrigin.value = {
      x: event.clientX,
      y: event.clientY,
      ox: offsetX.value,
      oy: offsetY.value
    }
    viewportEl.value?.setPointerCapture(event.pointerId)
  }

  function onPointerMove(event: PointerEvent) {
    if (!dragging.value) return
    offsetX.value = dragOrigin.value.ox + (event.clientX - dragOrigin.value.x)
    offsetY.value = dragOrigin.value.oy + (event.clientY - dragOrigin.value.y)
  }

  function onPointerUp(event: PointerEvent) {
    if (!dragging.value) return
    dragging.value = false
    try {
      viewportEl.value?.releasePointerCapture(event.pointerId)
    } catch {
      // ignore
    }
  }

  function onWheel(event: WheelEvent) {
    if (isPdf.value) return
    adjustZoom(event.deltaY < 0 ? 0.15 : -0.15)
  }

  function cleanup() {
    if (blobUrl.value) {
      URL.revokeObjectURL(blobUrl.value)
      blobUrl.value = ''
    }
    error.value = ''
    detectedMime.value = ''
    resetControls()
  }

  async function reload() {
    if (!props.fetcher || !props.active) return
    loading.value = true
    error.value = ''
    cleanup()

    try {
      const res = await props.fetcher()
      detectedMime.value = res.mimeType || res.blob.type || ''
      blobUrl.value = URL.createObjectURL(res.blob)
    } catch (err: any) {
      error.value = err?.message || 'Failed to download secure file.'
    } finally {
      loading.value = false
    }
  }

  function downloadFile() {
    if (!blobUrl.value) return
    downloading.value = true
    try {
      const a = document.createElement('a')
      a.href = blobUrl.value
      a.download = props.fileName || 'document'
      document.body.appendChild(a)
      a.click()
      document.body.removeChild(a)
    } finally {
      downloading.value = false
    }
  }

  watch(
    () => [props.active, props.reloadKey] as const,
    ([active]) => {
      if (active && props.fetcher) {
        reload()
      } else {
        cleanup()
      }
    },
    { immediate: true }
  )

  onBeforeUnmount(cleanup)

  defineExpose({ reload, cleanup })
</script>
