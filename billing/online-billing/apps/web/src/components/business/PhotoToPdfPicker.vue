<template>
  <div v-if="canCreatePhotoPdf(allowedMimeTypes)" class="mt-2">
    <ElButton plain :disabled="disabled" @click="open = true"
      >Use camera · combine photos to PDF</ElButton
    >
    <ElDialog
      v-model="open"
      title="Make one PDF from photos"
      width="min(520px, 94vw)"
      @closed="clear"
    >
      <p class="text-sm text-g-600 mb-3"
        >Take each page in order, or choose several photos. You can move or remove pages before
        creating the PDF.</p
      >
      <div class="flex flex-wrap gap-2 mb-4">
        <ElButton @click="cameraInput?.click()">Take photo</ElButton>
        <ElButton @click="galleryInput?.click()">Choose photos</ElButton>
      </div>
      <input
        ref="cameraInput"
        class="hidden"
        type="file"
        accept="image/*"
        capture="environment"
        @change="addPhotos"
      />
      <input
        ref="galleryInput"
        class="hidden"
        type="file"
        accept="image/*"
        multiple
        @change="addPhotos"
      />
      <div v-if="photos.length" class="space-y-2 max-h-[45vh] overflow-auto">
        <div
          v-for="(photo, index) in photos"
          :key="photo.url"
          class="flex items-center gap-3 rounded-lg border border-g-200 p-2"
        >
          <img
            :src="photo.url"
            alt="Document page preview"
            class="h-16 w-12 object-cover rounded"
          />
          <span class="min-w-0 flex-1 truncate text-sm"
            >Page {{ index + 1 }} · {{ photo.file.name }}</span
          >
          <ElButton link :disabled="index === 0" aria-label="Move page up" @click="move(index, -1)"
            >↑</ElButton
          >
          <ElButton
            link
            :disabled="index === photos.length - 1"
            aria-label="Move page down"
            @click="move(index, 1)"
            >↓</ElButton
          >
          <ElButton link type="danger" aria-label="Remove page" @click="remove(index)"
            >Remove</ElButton
          >
        </div>
      </div>
      <template #footer>
        <ElButton @click="open = false">Cancel</ElButton>
        <ElButton type="primary" :loading="building" :disabled="!photos.length" @click="createPdf"
          >Create PDF</ElButton
        >
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, onBeforeUnmount } from 'vue'
  import { ElMessage } from 'element-plus'
  import { canCreatePhotoPdf, photoFilesToPdf } from '@/utils/uploads/photoPdf'

  const props = defineProps<{
    allowedMimeTypes?: string[] | null
    maxFileSizeKb?: number | null
    disabled?: boolean
  }>()
  const emit = defineEmits<{ created: [file: File] }>()
  const open = ref(false)
  const building = ref(false)
  const cameraInput = ref<HTMLInputElement | null>(null)
  const galleryInput = ref<HTMLInputElement | null>(null)
  const photos = ref<{ file: File; url: string }[]>([])

  function addPhotos(event: Event) {
    const input = event.target as HTMLInputElement
    const files = Array.from(input.files || [])
    if (photos.value.length + files.length > 20) {
      ElMessage.warning('Choose up to 20 pages per PDF.')
    } else {
      photos.value.push(...files.map((file) => ({ file, url: URL.createObjectURL(file) })))
    }
    input.value = ''
  }
  function move(index: number, direction: number) {
    const [photo] = photos.value.splice(index, 1)
    photos.value.splice(index + direction, 0, photo)
  }
  function remove(index: number) {
    const [photo] = photos.value.splice(index, 1)
    URL.revokeObjectURL(photo.url)
  }
  function clear() {
    photos.value.forEach((photo) => URL.revokeObjectURL(photo.url))
    photos.value = []
  }
  onBeforeUnmount(clear)
  async function createPdf() {
    building.value = true
    try {
      const file = await photoFilesToPdf(photos.value.map((photo) => photo.file))
      if (props.maxFileSizeKb && file.size > props.maxFileSizeKb * 1024) {
        ElMessage.warning(
          'The PDF is too large for this document type. Remove pages or use fewer photos.'
        )
        return
      }
      emit('created', file)
      open.value = false
    } catch (error: any) {
      ElMessage.error(error?.message || 'Could not create the PDF from these photos.')
    } finally {
      building.value = false
    }
  }
</script>
