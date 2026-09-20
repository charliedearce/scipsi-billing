<template>
  <div class="settings-page p-4">
    <ElAlert
      title="Security & Scope Notice"
      type="info"
      :closable="false"
      show-icon
      class="!mb-4"
    >
      <template #default>
        Web application settings manage allowlisted operational preferences. Financial tariff tables, calculation rules, and deployment secrets (database credentials, Redis passwords, SkySMS keys) are strictly prohibited from web storage.
      </template>
    </ElAlert>

    <ElCard shadow="never">
      <div class="flex items-center justify-between mb-4">
        <div>
          <h2 class="text-lg font-semibold text-gray-800">Application Settings</h2>
          <p class="text-sm text-gray-500">View and update allowlisted system and location configuration with optimistic locking.</p>
        </div>
        <ElButton @click="loadSettings">Refresh</ElButton>
      </div>

      <ElTable :data="settingsList" v-loading="loading" stripe style="width: 100%">
        <ElTableColumn prop="key" label="Setting Key" min-width="220">
          <template #default="{ row }">
            <span class="font-mono text-xs font-semibold text-gray-700">{{ row.key }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="description" label="Description" min-width="260" />
        <ElTableColumn prop="type" label="Type" width="100">
          <template #default="{ row }">
            <ElTag size="small" type="info">{{ row.type }}</ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="scope" label="Scope" width="120">
          <template #default="{ row }">
            <ElTag size="small" :type="row.scope === 'location' ? 'warning' : 'primary'">
              {{ row.scope }}
            </ElTag>
          </template>
        </ElTableColumn>
        <ElTableColumn label="Effective Value" min-width="200">
          <template #default="{ row }">
            <span v-if="row.type === 'boolean'">
              <ElTag size="small" :type="row.value ? 'success' : 'danger'">
                {{ row.value ? 'Enabled' : 'Disabled' }}
              </ElTag>
            </span>
            <span v-else class="font-mono text-xs">{{ String(row.value ?? '') || '—' }}</span>
          </template>
        </ElTableColumn>
        <ElTableColumn prop="lock_version" label="Ver." width="70" align="center" />
        <ElTableColumn label="Actions" width="120" fixed="right">
          <template #default="{ row }">
            <ElButton size="small" type="primary" plain @click="openEditDialog(row)">
              Edit
            </ElButton>
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <!-- Edit Setting Dialog -->
    <ElDialog
      v-model="dialogVisible"
      :title="`Edit Setting: ${activeSetting?.key}`"
      width="480px"
      destroy-on-close
    >
      <div v-if="activeSetting" class="space-y-4">
        <p class="text-sm text-gray-600">{{ activeSetting.description }}</p>

        <ElForm label-position="top">
          <ElFormItem label="Setting Value">
            <!-- Boolean Switch -->
            <ElSwitch
              v-if="activeSetting.type === 'boolean'"
              v-model="editValue"
              active-text="Enabled"
              inactive-text="Disabled"
            />
            <!-- Integer Input -->
            <ElInputNumber
              v-else-if="activeSetting.type === 'integer'"
              v-model="editValue"
              :min="1"
              :max="1000"
              class="w-full"
            />
            <!-- String Input -->
            <ElInput
              v-else
              v-model="editValue"
              placeholder="Enter value"
            />
          </ElFormItem>

          <ElFormItem label="Expected Concurrency Version">
            <ElInput :model-value="`v${activeSetting.lock_version}`" disabled />
          </ElFormItem>
        </ElForm>
      </div>

      <template #footer>
        <span class="dialog-footer">
          <ElButton @click="dialogVisible = false">Cancel</ElButton>
          <ElButton type="primary" :loading="saving" @click="handleSaveSetting">
            Save Setting
          </ElButton>
        </span>
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'
import { fetchSettingList, fetchUpdateSetting, AppSettingItem } from '@/api/settings'
import { ElMessage } from 'element-plus'

defineOptions({ name: 'ApplicationSettings' })

const settingsList = ref<AppSettingItem[]>([])
const loading = ref(false)
const saving = ref(false)
const dialogVisible = ref(false)
const activeSetting = ref<AppSettingItem | null>(null)
const editValue = ref<any>(null)

const loadSettings = async () => {
  loading.value = true
  try {
    const res: any = await fetchSettingList()
    settingsList.value = (res?.data || res) || []
  } catch (err: any) {
    ElMessage.error(err.message || 'Failed to load settings')
  } finally {
    loading.value = false
  }
}

const openEditDialog = (setting: AppSettingItem) => {
  activeSetting.value = setting
  editValue.value = setting.value
  dialogVisible.value = true
}

const handleSaveSetting = async () => {
  if (!activeSetting.value) return
  saving.value = true
  try {
    await fetchUpdateSetting(activeSetting.value.key, {
      value: editValue.value,
      lock_version: activeSetting.value.lock_version
    })
    ElMessage.success('Setting saved successfully')
    dialogVisible.value = false
    loadSettings()
  } catch (err: any) {
    ElMessage.error(err.message || 'Failed to update setting')
  } finally {
    saving.value = false
  }
}

onMounted(() => {
  loadSettings()
})
</script>
