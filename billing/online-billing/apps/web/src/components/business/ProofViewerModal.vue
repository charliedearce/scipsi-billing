<template>
  <ElDialog
    v-model="visible"
    :title="title"
    width="min(850px, calc(100vw - 24px))"
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
      min-height="min(65vh, 600px)"
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

<style>
  .proof-viewer-dialog .el-dialog__body {
    padding-top: 10px;
    padding-bottom: 10px;
  }

  @media (width <= 639px) {
    .proof-viewer-dialog {
      display: flex;
      flex-direction: column;
      width: 100vw !important;
      max-width: 100vw;
      height: 100dvh;
      max-height: 100dvh;
      margin: 0;
      border-radius: 0 !important;
    }

    .proof-viewer-dialog .el-dialog__header {
      padding-right: 48px;
    }

    .proof-viewer-dialog .el-dialog__title {
      display: block;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .proof-viewer-dialog .el-dialog__body {
      flex: 1;
      min-height: 0;
      padding: 8px !important;
      overflow: auto;
    }

    .proof-viewer-dialog .el-dialog__footer {
      padding-bottom: max(12px, env(safe-area-inset-bottom));
    }
  }
</style>
