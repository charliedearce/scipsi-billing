<template>
  <ElDialog
    v-model="visible"
    :title="title"
    width="850px"
    destroy-on-close
    append-to-body
    class="proof-viewer-dialog rounded-xl overflow-hidden"
    :before-close="handleClose"
  >
    <div class="relative min-h-[420px] max-h-[75vh] flex flex-col items-center justify-center bg-slate-900/5 rounded-lg border border-slate-200 dark:border-slate-800 overflow-hidden">
      <!-- Loading state -->
      <div v-if="loading" class="flex flex-col items-center justify-center p-12 text-slate-500">
        <ElIcon class="is-loading text-3xl text-primary mb-3">
          <Loading />
        </ElIcon>
        <p class="text-sm font-medium">Fetching authenticated file...</p>
      </div>

      <!-- Error state -->
      <div v-else-if="error" class="flex flex-col items-center justify-center p-8 text-center">
        <ElIcon class="text-4xl text-rose-500 mb-2">
          <WarningFilled />
        </ElIcon>
        <p class="text-sm font-medium text-slate-700 dark:text-slate-200 mb-1">Failed to load file preview</p>
        <p class="text-xs text-slate-500 max-w-sm mb-4">{{ error }}</p>
        <ElButton size="small" @click="fetchProof">Try Again</ElButton>
      </div>

      <!-- Content view -->
      <template v-else-if="blobUrl">
        <!-- PDF preview -->
        <iframe
          v-if="isPdf"
          :src="blobUrl"
          class="w-full h-[65vh] border-0 rounded"
          title="Document Preview"
        />

        <!-- Image preview with controls -->
        <div v-else class="relative w-full h-[65vh] flex items-center justify-center overflow-auto p-4 select-none">
          <img
            :src="blobUrl"
            :style="{
              transform: `scale(${zoom}) rotate(${rotation}deg)`,
              transition: 'transform 0.2s ease-out'
            }"
            class="max-w-full max-h-full object-contain rounded shadow-sm"
            alt="Payment Proof"
          />
        </div>
      </template>

      <ElEmpty v-else description="No preview available" />
    </div>

    <template #footer>
      <div class="flex items-center justify-between gap-3 pt-2">
        <!-- Image manipulation controls -->
        <div v-if="!isPdf && blobUrl" class="flex items-center gap-1">
          <ElTooltip content="Zoom Out" placement="top">
            <ElButton size="small" circle @click="zoom = Math.max(0.4, zoom - 0.2)">
              <ElIcon><ZoomOut /></ElIcon>
            </ElButton>
          </ElTooltip>
          <span class="text-xs text-slate-500 font-mono px-1 w-12 text-center">{{ Math.round(zoom * 100) }}%</span>
          <ElTooltip content="Zoom In" placement="top">
            <ElButton size="small" circle @click="zoom = Math.min(3.0, zoom + 0.2)">
              <ElIcon><ZoomIn /></ElIcon>
            </ElButton>
          </ElTooltip>
          <ElTooltip content="Rotate" placement="top">
            <ElButton size="small" circle @click="rotation = (rotation + 90) % 360">
              <ElIcon><RefreshRight /></ElIcon>
            </ElButton>
          </ElTooltip>
          <ElTooltip content="Reset" placement="top">
            <ElButton size="small" text @click="resetControls">Reset</ElButton>
          </ElTooltip>
        </div>
        <div v-else />

        <!-- Actions -->
        <div class="flex items-center gap-2">
          <ElButton v-if="blobUrl" :loading="downloading" type="primary" plain @click="downloadFile">
            <ElIcon class="mr-1"><Download /></ElIcon> Download File
          </ElButton>
          <ElButton @click="handleClose">Close</ElButton>
        </div>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue'
import {
  Loading,
  WarningFilled,
  ZoomIn,
  ZoomOut,
  RefreshRight,
  Download
} from '@element-plus/icons-vue'

interface Props {
  modelValue: boolean
  title?: string
  fileId?: number | null
  mimeType?: string | null
  fileName?: string
  fetcher?: () => Promise<{ blob: Blob; filename?: string; mimeType?: string }>
}

const props = withDefaults(defineProps<Props>(), {
  title: 'Payment Proof Preview',
  mimeType: null,
  fileName: 'payment-proof'
})

const emit = defineEmits<{
  (e: 'update:modelValue', val: boolean): void
}>()

const visible = computed({
  get: () => props.modelValue,
  set: (val) => emit('update:modelValue', val)
})

const loading = ref(false)
const downloading = ref(false)
const error = ref('')
const blobUrl = ref('')
const detectedMime = ref('')
const zoom = ref(1)
const rotation = ref(0)

const isPdf = computed(() => {
  const m = detectedMime.value || props.mimeType || ''
  return m.toLowerCase().includes('pdf')
})

const resetControls = () => {
  zoom.value = 1
  rotation.value = 0
}

const cleanup = () => {
  if (blobUrl.value) {
    URL.revokeObjectURL(blobUrl.value)
    blobUrl.value = ''
  }
  error.value = ''
  detectedMime.value = ''
  resetControls()
}

const fetchProof = async () => {
  if (!props.fetcher) return
  loading.value = true
  error.value = ''
  cleanup()

  try {
    const res = await props.fetcher()
    detectedMime.value = res.mimeType || res.blob.type || ''
    blobUrl.value = URL.createObjectURL(res.blob)
  } catch (err: any) {
    error.value = err?.message || 'Failed to download secure proof file.'
  } finally {
    loading.value = false
  }
}

const downloadFile = () => {
  if (!blobUrl.value) return
  const a = document.createElement('a')
  a.href = blobUrl.value
  a.download = props.fileName || 'proof-document'
  document.body.appendChild(a)
  a.click()
  document.body.removeChild(a)
}

const handleClose = () => {
  visible.value = false
  cleanup()
}

watch(
  () => props.modelValue,
  (open) => {
    if (open && props.fetcher) {
      fetchProof()
    } else {
      cleanup()
    }
  }
)
</script>

<style scoped>
:deep(.el-dialog__body) {
  padding-top: 10px;
  padding-bottom: 10px;
}
</style>
