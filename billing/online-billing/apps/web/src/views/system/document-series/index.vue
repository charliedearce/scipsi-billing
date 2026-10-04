<template>
  <div class="page-content space-y-5 p-4 sm:p-6">
    <ElCard shadow="never" class="art-card">
      <template #header>
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <h1 class="text-lg font-semibold text-g-900">Document Numbering</h1>
            <p class="mt-1 text-sm text-g-500">
              Configure the prefix used for future invoice and receipt numbers.
            </p>
          </div>
          <ElButton :loading="loading" @click="load">Refresh</ElButton>
        </div>
      </template>
      <ElAlert
        type="warning"
        :closable="false"
        show-icon
        title="Changes affect future number allocations"
        description="Previously issued numbers and saved PDFs stay unchanged. Confirm the approved fiscal series format before changing a live invoice or Official Receipt series."
      />
    </ElCard>

    <ElCard shadow="never" class="art-card" v-loading="loading">
      <ElTable
        :data="series"
        stripe
        empty-text="No document series are configured for this organization."
        style="width: 100%"
      >
        <ElTableColumn prop="series_code" label="Series" min-width="180">
          <template #default="{ row }">
            <span class="font-mono font-medium text-g-900">{{ row.series_code }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Document type" min-width="190">
          <template #default="{ row }">{{ documentTypeLabel(row.document_type) }}</template>
        </ElTableColumn>
        <ElTableColumn label="Location" min-width="185">
          <template #default="{ row }">{{ row.location?.name || 'Organization-wide' }}</template>
        </ElTableColumn>
        <ElTableColumn label="Prefix" min-width="135">
          <template #default="{ row }">
            <ElTag size="small" effect="plain" type="info">{{ row.prefix || 'No prefix' }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Next number" min-width="190">
          <template #default="{ row }">
            <span class="font-mono text-g-700">{{ nextNumber(row) }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Sequence range" min-width="150">
          <template #default="{ row }">
            <span class="font-mono text-g-700">
              {{ row.start_number }}–{{ row.end_number ?? 'Open' }}
            </span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="current_number" label="Last sequence" min-width="130" />
        <ElTableColumn label="Status" width="105">
          <template #default="{ row }">
            <ElTag size="small" :type="row.is_active ? 'success' : 'info'">
              {{ row.is_active ? 'Active' : 'Inactive' }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Actions" width="145" fixed="right">
          <template #default="{ row }">
            <ElButton link type="primary" @click="openEdit(row)">Edit prefix</ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElDialog
      v-model="dialogVisible"
      title="Edit document number prefix"
      width="min(560px, 94vw)"
      destroy-on-close
    >
      <template v-if="activeSeries">
        <p class="mb-4 text-sm text-g-500">
          {{ activeSeries.series_code }} · {{ documentTypeLabel(activeSeries.document_type) }}
        </p>
        <ElAlert
          class="mb-4"
          type="info"
          :closable="false"
          show-icon
          title="The series counter will continue"
          description="This changes only the prefix on numbers allocated later. It will not renumber prior invoices, receipts, or PDFs."
        />
        <ElForm label-position="top">
          <ElFormItem label="Prefix">
            <ElInput
              v-model="form.prefix"
              :disabled="noPrefix"
              maxlength="32"
              show-word-limit
              placeholder="Example: SI- or OR-"
              autocomplete="off"
              @input="normalizePrefix"
            />
            <p class="mt-1 text-xs text-g-500">
              Use letters, numbers, period, underscore, slash, or hyphen.
            </p>
          </ElFormItem>
          <ElCheckbox v-model="noPrefix">Use no prefix</ElCheckbox>
          <div class="mt-3 rounded-lg bg-g-100 p-3 text-sm">
            <span class="text-g-500">Next number preview: </span>
            <span class="font-mono font-semibold text-g-900">{{ editedNextNumber }}</span>
          </div>
          <ElFormItem label="Reason for change" required class="mt-4">
            <ElInput
              v-model="form.reason"
              type="textarea"
              :rows="3"
              minlength="10"
              maxlength="1000"
              show-word-limit
              placeholder="Record why the future document number format is changing."
            />
          </ElFormItem>
          <p class="text-xs text-g-500">Series version: v{{ activeSeries.lock_version }}</p>
        </ElForm>
      </template>
      <template #footer>
        <ElButton @click="dialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="saving" @click="savePrefix">Save prefix</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import {
    fetchAdminDocumentSeries,
    updateDocumentSeriesPrefix,
    type AdminDocumentSeries,
    type DocumentSeriesKind
  } from '@/api/documentSeries'

  defineOptions({ name: 'DocumentNumbering' })

  const loading = ref(false)
  const saving = ref(false)
  const dialogVisible = ref(false)
  const series = ref<AdminDocumentSeries[]>([])
  const activeSeries = ref<AdminDocumentSeries | null>(null)
  const noPrefix = ref(false)
  const form = reactive({ prefix: '', reason: '' })

  const documentTypeLabel = (type: DocumentSeriesKind) => {
    const labels: Record<DocumentSeriesKind, string> = {
      SALES_INVOICE: 'Sales invoice',
      COLLECTION_RECEIPT: 'Collection receipt / Official Receipt',
      ACKNOWLEDGEMENT_RECEIPT: 'Acknowledgement receipt'
    }
    return labels[type] || type
  }

  const sequenceText = (row: AdminDocumentSeries) => {
    const next = Math.max(row.start_number, row.current_number + 1)
    if (row.end_number !== null && next > row.end_number) return null
    return String(next).padStart(row.padding_length, '0')
  }

  const nextNumber = (row: AdminDocumentSeries) => {
    const sequence = sequenceText(row)
    return sequence === null ? 'Series exhausted' : `${row.prefix}${sequence}` || sequence
  }

  const editedNextNumber = computed(() => {
    if (!activeSeries.value) return ''
    const sequence = sequenceText(activeSeries.value)
    if (sequence === null) return 'Series exhausted'
    const prefix = noPrefix.value ? '' : form.prefix.trim().toUpperCase()
    return `${prefix}${sequence}` || sequence
  })

  const load = async () => {
    loading.value = true
    try {
      series.value = await fetchAdminDocumentSeries()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load document series')
    } finally {
      loading.value = false
    }
  }

  const openEdit = (row: AdminDocumentSeries) => {
    activeSeries.value = row
    form.prefix = row.prefix
    form.reason = ''
    noPrefix.value = row.prefix === ''
    dialogVisible.value = true
  }

  const normalizePrefix = (value: string) => {
    form.prefix = value.toUpperCase()
  }

  const savePrefix = async () => {
    if (!activeSeries.value) return
    const prefix = noPrefix.value ? '' : form.prefix.trim().toUpperCase()
    if (!noPrefix.value && !prefix) {
      ElMessage.warning('Enter a prefix or select “Use no prefix.”')
      return
    }
    const reason = form.reason.trim()
    if (reason.length < 10) {
      ElMessage.warning('Provide at least 10 characters explaining this change.')
      return
    }

    saving.value = true
    try {
      await updateDocumentSeriesPrefix(activeSeries.value.id, {
        prefix,
        expected_lock_version: activeSeries.value.lock_version,
        reason
      })
      ElMessage.success('Document number prefix updated for future allocations')
      dialogVisible.value = false
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Could not update this series prefix')
    } finally {
      saving.value = false
    }
  }

  onMounted(load)
</script>
