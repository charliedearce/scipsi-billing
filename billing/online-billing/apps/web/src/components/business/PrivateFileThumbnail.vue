<template>
  <div
    class="relative flex h-16 w-16 shrink-0 items-center justify-center overflow-hidden rounded-lg border border-slate-200 bg-slate-50"
    :title="fileName || 'Attachment'"
  >
    <img
      v-if="blobUrl"
      :src="blobUrl"
      :alt="fileName || 'Attachment thumbnail'"
      class="h-full w-full object-contain"
    />
    <ElIcon v-else-if="loading" class="is-loading text-xl text-slate-400"><Loading /></ElIcon>
    <div v-else-if="isPdf" class="text-center leading-none">
      <ElIcon class="text-2xl text-rose-500"><DocumentIcon /></ElIcon>
      <div class="mt-1 text-[10px] font-bold text-rose-600">PDF</div>
    </div>
    <div v-else class="text-center leading-none">
      <ElIcon class="text-2xl text-slate-400"><Picture /></ElIcon>
      <div class="mt-1 max-w-[54px] truncate text-[9px] uppercase text-slate-500">
        {{ extension || 'FILE' }}
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, onBeforeUnmount, ref, watch } from 'vue'
  import { Document as DocumentIcon, Loading, Picture } from '@element-plus/icons-vue'
  import { downloadPrivateFile } from '@/api/documentRequirements'

  const props = defineProps<{
    fileId: number
    fileName?: string | null
    mimeType?: string | null
    reloadKey?: number | string | null
  }>()

  const blobUrl = ref('')
  const loading = ref(false)

  const normalizedMime = computed(() => (props.mimeType || '').toLowerCase())
  const extension = computed(() => props.fileName?.split('.').pop()?.toUpperCase() || '')
  const isImage = computed(
    () =>
      normalizedMime.value.startsWith('image/') ||
      ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'BMP'].includes(extension.value)
  )
  const isPdf = computed(() => normalizedMime.value.includes('pdf') || extension.value === 'PDF')

  function cleanup() {
    if (blobUrl.value) {
      URL.revokeObjectURL(blobUrl.value)
      blobUrl.value = ''
    }
  }

  async function loadThumbnail() {
    cleanup()
    if (!props.fileId || !isImage.value) return

    loading.value = true
    try {
      const blob = await downloadPrivateFile(props.fileId)
      if (blob.type.toLowerCase().startsWith('image/')) {
        blobUrl.value = URL.createObjectURL(blob)
      }
    } catch {
      // Keep the neutral file fallback. The full viewer surfaces download errors.
    } finally {
      loading.value = false
    }
  }

  watch(
    () => [props.fileId, props.reloadKey, props.mimeType, props.fileName] as const,
    loadThumbnail,
    { immediate: true }
  )

  onBeforeUnmount(cleanup)
</script>
