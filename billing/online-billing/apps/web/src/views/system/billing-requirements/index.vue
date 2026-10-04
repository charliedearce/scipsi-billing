<template>
  <div class="billing-requirements-page p-4 sm:p-6 space-y-5">
    <ElCard shadow="never">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100">
              Billing Document Requirements
            </h1>
            <ElTag type="info" effect="plain">W21 / P1-07</ElTag>
          </div>
          <p class="mt-1 max-w-3xl text-sm text-slate-500">
            Configure named document types (PDF/JPEG/PNG and size limits) and which files customers
            must upload before a billing request can enter the teller queue.
          </p>
        </div>
        <ElButton :loading="loading" @click="loadAll">Refresh</ElButton>
      </div>
    </ElCard>

    <ElTabs v-model="activeTab">
      <ElTabPane label="Document types" name="types">
        <ElCard shadow="never" v-loading="loading">
          <ElTable :data="documentTypes" stripe>
            <ElTableColumn prop="code" label="Code" min-width="150" />
            <ElTableColumn prop="name" label="Name" min-width="200" />
            <ElTableColumn prop="purpose" label="Purpose" min-width="170">
              <template #default="{ row }">
                <ElTag size="small" effect="plain">{{ row.purpose }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Allowed formats" min-width="180">
              <template #default="{ row }">
                {{ (row.allowed_mime_types || []).join(', ') || '—' }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Max size" width="120">
              <template #default="{ row }">
                {{ formatMaxUploadSize(row.max_file_size_kb) }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Max files" width="100" prop="max_files" />
            <ElTableColumn label="Active" width="90">
              <template #default="{ row }">
                <ElTag size="small" :type="row.is_active ? 'success' : 'info'">
                  {{ row.is_active ? 'Yes' : 'No' }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Actions" width="110" fixed="right">
              <template #default="{ row }">
                <ElButton size="small" type="primary" plain @click="openTypeEditor(row)">
                  Edit
                </ElButton>
              </template>
            </ElTableColumn>
          </ElTable>
        </ElCard>
      </ElTabPane>

      <ElTabPane label="Service requirements" name="requirements">
        <ElCard shadow="never" v-loading="loading">
          <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-slate-500">
              Required entries are enforced on customer submit for the matching service type.
            </p>
            <ElButton type="primary" @click="openRequirementCreate">Add requirement</ElButton>
          </div>
          <ElTable :data="requirements" stripe>
            <ElTableColumn prop="service_type" label="Service type" min-width="140" />
            <ElTableColumn label="Document type" min-width="200">
              <template #default="{ row }">
                {{ row.document_type?.name || `Type #${row.document_type_id}` }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Location" width="120">
              <template #default="{ row }">
                {{ row.location_id ? `#${row.location_id}` : 'All' }}
              </template>
            </ElTableColumn>
            <ElTableColumn label="Required" width="100">
              <template #default="{ row }">
                <ElTag size="small" :type="row.is_required ? 'danger' : 'info'">
                  {{ row.is_required ? 'Required' : 'Optional' }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Version" width="90">
              <template #default="{ row }">v{{ row.version }} / L{{ row.lock_version }}</template>
            </ElTableColumn>
            <ElTableColumn label="Actions" width="110" fixed="right">
              <template #default="{ row }">
                <ElButton size="small" type="primary" plain @click="openRequirementEditor(row)">
                  Edit
                </ElButton>
              </template>
            </ElTableColumn>
          </ElTable>
        </ElCard>
      </ElTabPane>
    </ElTabs>

    <ElDialog
      v-model="typeDialogVisible"
      :title="`Edit document type: ${editingType?.code || ''}`"
      width="520px"
      destroy-on-close
    >
      <ElForm v-if="editingType" label-position="top">
        <ElFormItem label="Display name">
          <ElInput v-model="typeForm.name" />
        </ElFormItem>
        <ElFormItem label="Description">
          <ElInput v-model="typeForm.description" type="textarea" :rows="2" />
        </ElFormItem>
        <ElFormItem label="Allowed formats">
          <ElCheckboxGroup v-model="typeForm.allowed_mime_types">
            <ElCheckbox label="application/pdf">PDF</ElCheckbox>
            <ElCheckbox label="image/jpeg">JPEG</ElCheckbox>
            <ElCheckbox label="image/png">PNG</ElCheckbox>
          </ElCheckboxGroup>
        </ElFormItem>
        <ElFormItem label="Maximum file size (KB)">
          <ElInputNumber
            v-model="typeForm.max_file_size_kb"
            :min="100"
            :max="25600"
            class="w-full"
          />
          <p class="mt-1 text-xs text-slate-500">
            Customers see this as {{ formatMaxUploadSize(typeForm.max_file_size_kb) }}. Range
            100–25600 KB.
          </p>
        </ElFormItem>
        <ElFormItem label="Maximum files per request">
          <ElInputNumber v-model="typeForm.max_files" :min="1" :max="10" class="w-full" />
        </ElFormItem>
        <ElFormItem label="Active">
          <ElSwitch v-model="typeForm.is_active" />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="typeDialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="saving" @click="saveType">Save</ElButton>
      </template>
    </ElDialog>

    <ElDialog
      v-model="requirementDialogVisible"
      :title="editingRequirement ? 'Edit service requirement' : 'Add service requirement'"
      width="520px"
      destroy-on-close
    >
      <ElForm label-position="top">
        <ElFormItem v-if="!editingRequirement" label="Service type">
          <ElSelect v-model="requirementForm.service_type" filterable allow-create class="w-full">
            <ElOption label="GENERAL" value="GENERAL" />
            <ElOption label="CARGO_HANDLING" value="CARGO_HANDLING" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem v-if="!editingRequirement" label="Document type">
          <ElSelect v-model="requirementForm.document_type_id" class="w-full" filterable>
            <ElOption
              v-for="type in documentTypes"
              :key="type.id"
              :label="`${type.name} (${type.code})`"
              :value="type.id"
            />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="Required for teller-queue admission">
          <ElSwitch v-model="requirementForm.is_required" />
        </ElFormItem>
        <ElFormItem label="Effective from">
          <ElDatePicker
            v-model="requirementForm.effective_from"
            type="date"
            value-format="YYYY-MM-DD"
            class="w-full"
          />
        </ElFormItem>
        <ElFormItem label="Effective to">
          <ElDatePicker
            v-model="requirementForm.effective_to"
            type="date"
            value-format="YYYY-MM-DD"
            class="w-full"
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="requirementDialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="saving" @click="saveRequirement">Save</ElButton>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { ElMessage } from 'element-plus'
  import {
    fetchCreateDocumentRequirement,
    fetchDocumentRequirements,
    fetchDocumentTypes,
    fetchUpdateDocumentRequirement,
    fetchUpdateDocumentType,
    type DocumentRequirementItem,
    type DocumentTypeItem
  } from '@/api/documentRequirements'
  import { formatMaxUploadSize } from '@/utils/uploads/documentUpload'

  defineOptions({ name: 'BillingRequirementsAdmin' })

  const loading = ref(false)
  const saving = ref(false)
  const activeTab = ref('types')
  const documentTypes = ref<DocumentTypeItem[]>([])
  const requirements = ref<DocumentRequirementItem[]>([])

  const typeDialogVisible = ref(false)
  const editingType = ref<DocumentTypeItem | null>(null)
  const typeForm = reactive({
    name: '',
    description: '',
    allowed_mime_types: [] as string[],
    max_file_size_kb: 5120,
    max_files: 1,
    is_active: true
  })

  const requirementDialogVisible = ref(false)
  const editingRequirement = ref<DocumentRequirementItem | null>(null)
  const requirementForm = reactive({
    service_type: 'GENERAL',
    document_type_id: null as number | null,
    is_required: true,
    effective_from: null as string | null,
    effective_to: null as string | null
  })

  async function loadAll() {
    loading.value = true
    try {
      const [types, reqs] = await Promise.all([
        fetchDocumentTypes(),
        fetchDocumentRequirements()
      ])
      documentTypes.value = types
      requirements.value = reqs
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load billing requirements.')
    } finally {
      loading.value = false
    }
  }

  function openTypeEditor(row: DocumentTypeItem) {
    editingType.value = row
    typeForm.name = row.name
    typeForm.description = row.description || ''
    typeForm.allowed_mime_types = [...(row.allowed_mime_types || [])]
    typeForm.max_file_size_kb = row.max_file_size_kb
    typeForm.max_files = row.max_files
    typeForm.is_active = row.is_active
    typeDialogVisible.value = true
  }

  async function saveType() {
    if (!editingType.value) return
    saving.value = true
    try {
      await fetchUpdateDocumentType(editingType.value.id, {
        name: typeForm.name,
        description: typeForm.description,
        allowed_mime_types: typeForm.allowed_mime_types,
        max_file_size_kb: typeForm.max_file_size_kb,
        max_files: typeForm.max_files,
        is_active: typeForm.is_active
      })
      ElMessage.success('Document type updated. New uploads use this size and MIME policy.')
      typeDialogVisible.value = false
      await loadAll()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to update document type.')
    } finally {
      saving.value = false
    }
  }

  function openRequirementCreate() {
    editingRequirement.value = null
    requirementForm.service_type = 'GENERAL'
    requirementForm.document_type_id = documentTypes.value[0]?.id ?? null
    requirementForm.is_required = true
    requirementForm.effective_from = null
    requirementForm.effective_to = null
    requirementDialogVisible.value = true
  }

  function openRequirementEditor(row: DocumentRequirementItem) {
    editingRequirement.value = row
    requirementForm.service_type = row.service_type
    requirementForm.document_type_id = row.document_type_id
    requirementForm.is_required = row.is_required
    requirementForm.effective_from = row.effective_from || null
    requirementForm.effective_to = row.effective_to || null
    requirementDialogVisible.value = true
  }

  async function saveRequirement() {
    saving.value = true
    try {
      if (editingRequirement.value) {
        await fetchUpdateDocumentRequirement(editingRequirement.value.id, {
          is_required: requirementForm.is_required,
          effective_from: requirementForm.effective_from,
          effective_to: requirementForm.effective_to,
          lock_version: editingRequirement.value.lock_version
        })
        ElMessage.success('Service requirement updated.')
      } else {
        if (!requirementForm.document_type_id) {
          ElMessage.warning('Choose a document type.')
          return
        }
        await fetchCreateDocumentRequirement({
          service_type: requirementForm.service_type,
          document_type_id: requirementForm.document_type_id,
          is_required: requirementForm.is_required,
          effective_from: requirementForm.effective_from,
          effective_to: requirementForm.effective_to
        })
        ElMessage.success('Service requirement created.')
      }
      requirementDialogVisible.value = false
      await loadAll()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to save requirement.')
    } finally {
      saving.value = false
    }
  }

  onMounted(loadAll)
</script>
