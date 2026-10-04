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
    <DocumentPreviewPane
      :active="visible"
      :reload-key="visible ? `${fileId ?? ''}:${title}` : null"
      :title="title"
      :file-name="fileName"
      :mime-type="mimeType"
      :fetcher="visible ? fetcher || null : null"
      min-height="65vh"
      empty-text="No preview available"
    />
    <template #footer>
      <div class="flex justify-end">
        <ElButton @click="handleClose">Close</ElButton>
      </div>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import DocumentPreviewPane from '@/components/business/DocumentPreviewPane.vue'

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

  const handleClose = () => {
    visible.value = false
  }
</script>

<style scoped>
  :deep(.el-dialog__body) {
    padding-top: 10px;
    padding-bottom: 10px;
  }
</style>
