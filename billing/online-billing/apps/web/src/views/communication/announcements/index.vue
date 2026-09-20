<template>
  <div class="announcements-page p-4">
    <!-- Header Card -->
    <ElCard shadow="never" class="mb-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-800">In-App Announcements</h2>
            <ElTag type="success" size="small" effect="plain">Decision W34</ElTag>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Durable, organization-scoped notices for scheduled maintenance and critical operational communications.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <ElButton type="primary" @click="openCreateDialog">
            + New Announcement
          </ElButton>
          <ElButton @click="loadData">Refresh</ElButton>
        </div>
      </div>

      <ElAlert
        title="W34 Boundary Guarantee: In-app notices never modify invoice, receipt, queue, PPA clearance, or payment balances."
        type="info"
        :closable="false"
        show-icon
        class="mt-3"
      />
    </ElCard>

    <!-- Main Content Card -->
    <ElCard shadow="never">
      <!-- Filter Bar -->
      <div class="flex flex-wrap items-center justify-between gap-4 mb-4">
        <ElRadioGroup v-model="filterStatus" @change="loadData" size="default">
          <ElRadioButton label="">All</ElRadioButton>
          <ElRadioButton label="published">Published</ElRadioButton>
          <ElRadioButton label="scheduled">Scheduled</ElRadioButton>
          <ElRadioButton label="draft">Drafts</ElRadioButton>
          <ElRadioButton label="retired">Retired</ElRadioButton>
        </ElRadioGroup>

        <div class="flex items-center gap-2">
          <ElSelect
            v-model="filterSeverity"
            placeholder="Filter Severity"
            clearable
            style="width: 160px"
            @change="loadData"
          >
            <ElOption label="INFO" value="INFO" />
            <ElOption label="MAINTENANCE" value="MAINTENANCE" />
            <ElOption label="IMPORTANT" value="IMPORTANT" />
            <ElOption label="CRITICAL" value="CRITICAL" />
          </ElSelect>
        </div>
      </div>

      <!-- Announcements Table -->
      <ElTable :data="announcements" v-loading="loading" stripe style="width: 100%">
        <ElTableColumn prop="id" label="ID" width="70" />

        <ElTableColumn label="Severity" width="130">
          <template #default="{ row }">
            <ElTag
              :type="getSeverityTagType(row.current_version?.severity)"
              effect="dark"
              size="small"
              class="font-bold"
            >
              {{ row.current_version?.severity || 'INFO' }}
            </ElTag>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Title & Content" min-width="260">
          <template #default="{ row }">
            <div class="font-semibold text-gray-900 flex items-center gap-2">
              <span>{{ row.current_version?.title || '(Untitled)' }}</span>
              <ElTag size="small" type="info" effect="plain">
                v{{ row.current_version?.version_number || 1 }}
              </ElTag>
              <ElTag
                v-if="!row.current_version?.is_dismissible"
                size="small"
                type="danger"
                effect="plain"
              >
                Locked
              </ElTag>
            </div>
            <p class="text-xs text-gray-500 mt-1 line-clamp-2">
              {{ row.current_version?.body }}
            </p>
            <div v-if="row.current_version?.change_reason" class="text-xs text-indigo-600 mt-1 italic">
              Revision: {{ row.current_version.change_reason }}
            </div>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Audience" width="180">
          <template #default="{ row }">
            <div v-if="row.current_version?.audience_type === 'all'">
              <ElTag size="small" type="success">All Users</ElTag>
            </div>
            <div v-else class="flex flex-col gap-1">
              <ElTag size="small" type="warning">Targeted</ElTag>
              <div v-if="row.current_version?.roles?.length" class="text-xs text-gray-600">
                Roles: {{ row.current_version.roles.map((r: any) => r.name).join(', ') }}
              </div>
              <div v-if="row.current_version?.locations?.length" class="text-xs text-gray-600">
                Loc: {{ row.current_version.locations.map((l: any) => l.code).join(', ') }}
              </div>
            </div>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Status" width="120">
          <template #default="{ row }">
            <ElTag :type="getStatusTagType(row.status)">
              {{ row.effective_status || row.status }}
            </ElTag>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Interactions" width="170">
          <template #default="{ row }">
            <div class="text-xs space-y-0.5">
              <div>Seen: <strong>{{ row.metrics?.seen_count || 0 }}</strong></div>
              <div>Acknowledged: <strong>{{ row.metrics?.acknowledged_count || 0 }}</strong></div>
              <div>Dismissed: <strong>{{ row.metrics?.dismissed_count || 0 }}</strong></div>
            </div>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Actions" width="220" fixed="right">
          <template #default="{ row }">
            <div class="flex flex-wrap gap-1">
              <!-- If draft: Edit & Publish -->
              <ElButton
                v-if="row.status === 'draft'"
                size="small"
                type="primary"
                link
                @click="openEditDraftDialog(row)"
              >
                Edit
              </ElButton>
              <ElButton
                v-if="row.status === 'draft'"
                size="small"
                type="success"
                link
                @click="handlePublishDraft(row)"
              >
                Publish
              </ElButton>

              <!-- If published: New Revision & Retire -->
              <ElButton
                v-if="row.status === 'published' || row.status === 'scheduled'"
                size="small"
                type="warning"
                link
                @click="openRevisionDialog(row)"
              >
                Revise
              </ElButton>
              <ElButton
                v-if="row.status === 'published' || row.status === 'scheduled'"
                size="small"
                type="danger"
                link
                @click="openRetireDialog(row)"
              >
                Retire
              </ElButton>

              <!-- History View -->
              <ElButton size="small" type="info" link @click="openHistoryDialog(row)">
                History
              </ElButton>
            </div>
          </template>
        </ElTableColumn>
      </ElTable>

      <!-- Pagination -->
      <div class="flex justify-end mt-4">
        <ElPagination
          v-model:current-page="currentPage"
          v-model:page-size="pageSize"
          :total="totalCount"
          :page-sizes="[10, 15, 25, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="loadData"
          @current-change="loadData"
        />
      </div>
    </ElCard>

    <!-- Authoring Dialog (Create / Edit Draft / Publish Revision) -->
    <ElDialog
      v-model="dialogVisible"
      :title="dialogTitle"
      width="640px"
      :close-on-click-modal="false"
    >
      <ElForm ref="formRef" :model="formData" :rules="formRules" label-width="140px" status-icon>
        <ElFormItem label="Title" prop="title">
          <ElInput v-model="formData.title" placeholder="e.g. Scheduled System Maintenance" />
        </ElFormItem>

        <ElFormItem label="Severity" prop="severity">
          <ElSelect v-model="formData.severity" style="width: 100%" @change="onSeverityChange">
            <ElOption label="INFO — General operational notice" value="INFO" />
            <ElOption label="MAINTENANCE — Planned downtime window" value="MAINTENANCE" />
            <ElOption label="IMPORTANT — Policy or urgent reminder" value="IMPORTANT" />
            <ElOption label="CRITICAL — Urgent operational emergency" value="CRITICAL" />
          </ElSelect>
        </ElFormItem>

        <ElFormItem label="Content Body" prop="body">
          <ElInput
            v-model="formData.body"
            type="textarea"
            :rows="4"
            placeholder="Plain text or safe Markdown. HTML tags, script execution, and external URLs are strictly rejected."
          />
        </ElFormItem>

        <ElFormItem label="Audience" prop="audience_type">
          <ElRadioGroup v-model="formData.audience_type">
            <ElRadio label="all">All Active Users</ElRadio>
            <ElRadio label="targeted">Targeted Scope</ElRadio>
          </ElRadioGroup>
        </ElFormItem>

        <template v-if="formData.audience_type === 'targeted'">
          <ElFormItem label="Target Roles">
            <ElSelect
              v-model="formData.role_ids"
              multiple
              placeholder="Select roles (OR logic)"
              style="width: 100%"
            >
              <ElOption
                v-for="role in availableRoles"
                :key="role.id"
                :label="role.name"
                :value="role.id"
              />
            </ElSelect>
          </ElFormItem>

          <ElFormItem label="Target Locations">
            <ElSelect
              v-model="formData.location_ids"
              multiple
              placeholder="Select locations (OR logic)"
              style="width: 100%"
            >
              <ElOption
                v-for="loc in availableLocations"
                :key="loc.id"
                :label="`${loc.name} (${loc.code})`"
                :value="loc.id"
              />
            </ElSelect>
          </ElFormItem>
        </template>

        <ElFormItem label="Effective Start">
          <ElDatePicker
            v-model="formData.effective_start_at"
            type="datetime"
            placeholder="Start date and time (Asia/Manila)"
            style="width: 100%"
            value-format="YYYY-MM-DD HH:mm:ss"
          />
        </ElFormItem>

        <ElFormItem label="Effective Expiry">
          <ElDatePicker
            v-model="formData.effective_end_at"
            type="datetime"
            placeholder="Optional expiry date/time"
            style="width: 100%"
            value-format="YYYY-MM-DD HH:mm:ss"
          />
        </ElFormItem>

        <ElFormItem label="Dismissible?">
          <div class="flex items-center gap-3">
            <ElSwitch
              v-model="formData.is_dismissible"
              :disabled="formData.severity !== 'CRITICAL'"
            />
            <span class="text-xs text-gray-500">
              {{ formData.severity === 'CRITICAL'
                ? 'Only CRITICAL notices may be locked (non-dismissible).'
                : 'Non-critical notices are always dismissible.' }}
            </span>
          </div>
        </ElFormItem>

        <!-- Revision Change Reason -->
        <ElFormItem
          v-if="dialogMode === 'revision'"
          label="Change Reason"
          prop="change_reason"
          required
        >
          <ElInput
            v-model="formData.change_reason"
            type="textarea"
            :rows="2"
            placeholder="Mandatory explanation for this published correction/update."
          />
        </ElFormItem>

        <ElFormItem v-if="dialogMode === 'create'" label="Publish Now?">
          <ElSwitch v-model="formData.publish_now" />
        </ElFormItem>
      </ElForm>

      <template #footer>
        <div class="flex justify-end gap-2">
          <ElButton @click="dialogVisible = false">Cancel</ElButton>
          <ElButton type="primary" :loading="submitting" @click="handleSubmit">
            {{ dialogMode === 'revision' ? 'Publish Revision' : (formData.publish_now ? 'Publish' : 'Save Draft') }}
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <!-- Retire Dialog -->
    <ElDialog v-model="retireDialogVisible" title="Retire Announcement" width="500px">
      <p class="text-sm text-gray-600 mb-3">
        Retiring an announcement immediately stops its active display while preserving complete delivery and audit history. An authorized retirement reason is required.
      </p>
      <ElForm label-position="top">
        <ElFormItem label="Retirement Reason" required>
          <ElInput
            v-model="retireReason"
            type="textarea"
            :rows="3"
            placeholder="State the reason for retiring this notice."
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <div class="flex justify-end gap-2">
          <ElButton @click="retireDialogVisible = false">Cancel</ElButton>
          <ElButton type="danger" :loading="retiring" @click="handleRetire">
            Confirm Retirement
          </ElButton>
        </div>
      </template>
    </ElDialog>

    <!-- History Drawer -->
    <ElDrawer v-model="historyDrawerVisible" title="Announcement Revision History" size="550px">
      <div v-loading="loadingHistory" class="space-y-6">
        <div
          v-for="v in historyData?.versions"
          :key="v.id"
          class="p-4 border rounded-lg bg-gray-50 space-y-2"
        >
          <div class="flex items-center justify-between">
            <span class="font-bold text-gray-900">Version {{ v.version_number }}</span>
            <ElTag :type="getSeverityTagType(v.severity)" size="small">
              {{ v.severity }}
            </ElTag>
          </div>
          <h4 class="font-semibold text-sm">{{ v.title }}</h4>
          <p class="text-xs text-gray-600">{{ v.body }}</p>

          <div v-if="v.change_reason" class="text-xs text-indigo-700 bg-indigo-50 p-2 rounded">
            <strong>Change Reason:</strong> {{ v.change_reason }}
          </div>

          <div class="text-xs text-gray-500 font-mono">
            SHA-256 Digest: {{ v.content_hash.substring(0, 16) }}...
          </div>

          <div class="text-xs text-gray-500 flex justify-between border-t pt-2">
            <span>Author: {{ v.author?.name || 'System' }}</span>
            <span>Published: {{ v.published_at || 'Draft' }}</span>
          </div>

          <div class="text-xs text-gray-700 flex gap-4 pt-1">
            <span>Seen: <strong>{{ v.metrics?.seen_count || 0 }}</strong></span>
            <span>Acknowledged: <strong>{{ v.metrics?.acknowledged_count || 0 }}</strong></span>
            <span>Dismissed: <strong>{{ v.metrics?.dismissed_count || 0 }}</strong></span>
          </div>
        </div>
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { ref, onMounted } from 'vue'
  import {
    AnnouncementAdminItem,
    AnnouncementSeverity,
    AnnouncementAudienceType,
    fetchAdminAnnouncements,
    createAnnouncement,
    updateDraftAnnouncement,
    publishAnnouncement,
    retireAnnouncement,
    fetchAnnouncementHistory
  } from '@/api/announcements'
  import { ElMessage } from 'element-plus'
  import request from '@/utils/http'

  defineOptions({ name: 'AnnouncementsManagement' })

  const announcements = ref<AnnouncementAdminItem[]>([])
  const loading = ref(false)
  const submitting = ref(false)
  const retiring = ref(false)
  const loadingHistory = ref(false)

  const filterStatus = ref('')
  const filterSeverity = ref('')
  const currentPage = ref(1)
  const pageSize = ref(15)
  const totalCount = ref(0)

  // Dialog state
  const dialogVisible = ref(false)
  const dialogTitle = ref('Create Announcement')
  const dialogMode = ref<'create' | 'edit' | 'revision'>('create')
  const targetAnnouncementId = ref<number | null>(null)

  const retireDialogVisible = ref(false)
  const retireReason = ref('')
  const targetRetireId = ref<number | null>(null)

  const historyDrawerVisible = ref(false)
  const historyData = ref<any>(null)

  // Options
  const availableRoles = ref<any[]>([])
  const availableLocations = ref<any[]>([])

  const formData = ref<{
    title: string
    body: string
    severity: AnnouncementSeverity
    audience_type: AnnouncementAudienceType
    role_ids: number[]
    location_ids: number[]
    effective_start_at: string | null
    effective_end_at: string | null
    is_dismissible: boolean
    change_reason: string
    publish_now: boolean
  }>({
    title: '',
    body: '',
    severity: 'INFO',
    audience_type: 'all',
    role_ids: [],
    location_ids: [],
    effective_start_at: null,
    effective_end_at: null,
    is_dismissible: true,
    change_reason: '',
    publish_now: false
  })

  const formRules = {
    title: [{ required: true, message: 'Title is required', trigger: 'blur' }],
    severity: [{ required: true, message: 'Severity is required', trigger: 'change' }],
    body: [{ required: true, message: 'Body is required', trigger: 'blur' }]
  }

  type AnnouncementTagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

  type TagType = 'primary' | 'success' | 'warning' | 'info' | 'danger'

  function getSeverityTagType(severity?: string): TagType {
    switch (severity) {
      case 'CRITICAL':
        return 'danger'
      case 'MAINTENANCE':
        return 'warning'
      case 'IMPORTANT':
        return 'primary'
      default:
        return 'info'
    }
  }

  function getStatusTagType(status?: string): TagType {
    switch (status) {
      case 'published':
        return 'success'
      case 'scheduled':
        return 'warning'
      case 'draft':
        return 'info'
      case 'retired':
        return 'danger'
      default:
        return 'info'
    }
  }

  function onSeverityChange(val: AnnouncementSeverity) {
    if (val !== 'CRITICAL') {
      formData.value.is_dismissible = true
    }
  }

  async function loadData() {
    loading.value = true
    try {
      const res = await fetchAdminAnnouncements({
        page: currentPage.value,
        per_page: pageSize.value,
        status: filterStatus.value || undefined,
        severity: filterSeverity.value || undefined
      })
      if (res && res.data) {
        announcements.value = res.data
        totalCount.value = res.meta.total
      }
    } catch {
      ElMessage.error('Failed to load announcements')
    } finally {
      loading.value = false
    }
  }

  async function loadRolesAndLocations() {
    try {
      const rolesRes = await request.get<{ data: any[] }>({ url: '/api/v1/roles' })
      if (rolesRes && rolesRes.data) {
        availableRoles.value = rolesRes.data
      }
    } catch {}

    // Mock/default Makar Wharf location
    availableLocations.value = [
      { id: 1, name: 'Makar Wharf, General Santos City', code: 'GENSAN' }
    ]
  }

  function resetForm() {
    formData.value = {
      title: '',
      body: '',
      severity: 'INFO',
      audience_type: 'all',
      role_ids: [],
      location_ids: [],
      effective_start_at: null,
      effective_end_at: null,
      is_dismissible: true,
      change_reason: '',
      publish_now: false
    }
  }

  function openCreateDialog() {
    resetForm()
    dialogTitle.value = 'New Announcement'
    dialogMode.value = 'create'
    targetAnnouncementId.value = null
    dialogVisible.value = true
  }

  function openEditDraftDialog(row: AnnouncementAdminItem) {
    resetForm()
    dialogTitle.value = `Edit Draft Announcement #${row.id}`
    dialogMode.value = 'edit'
    targetAnnouncementId.value = row.id

    const v = row.current_version
    if (v) {
      formData.value.title = v.title
      formData.value.body = v.body
      formData.value.severity = v.severity
      formData.value.audience_type = v.audience_type
      formData.value.role_ids = v.roles?.map((r: any) => r.id) || []
      formData.value.location_ids = v.locations?.map((l: any) => l.id) || []
      formData.value.effective_start_at = v.effective_start_at
      formData.value.effective_end_at = v.effective_end_at || null
      formData.value.is_dismissible = v.is_dismissible
    }

    dialogVisible.value = true
  }

  function openRevisionDialog(row: AnnouncementAdminItem) {
    resetForm()
    dialogTitle.value = `Publish Revision for Announcement #${row.id}`
    dialogMode.value = 'revision'
    targetAnnouncementId.value = row.id

    const v = row.current_version
    if (v) {
      formData.value.title = v.title
      formData.value.body = v.body
      formData.value.severity = v.severity
      formData.value.audience_type = v.audience_type
      formData.value.role_ids = v.roles?.map((r: any) => r.id) || []
      formData.value.location_ids = v.locations?.map((l: any) => l.id) || []
      formData.value.effective_start_at = v.effective_start_at
      formData.value.effective_end_at = v.effective_end_at || null
      formData.value.is_dismissible = v.is_dismissible
      formData.value.change_reason = ''
    }

    dialogVisible.value = true
  }

  async function handlePublishDraft(row: AnnouncementAdminItem) {
    try {
      await publishAnnouncement(row.id)
      ElMessage.success('Announcement published successfully')
      loadData()
    } catch {
      ElMessage.error('Failed to publish announcement')
    }
  }

  function openRetireDialog(row: AnnouncementAdminItem) {
    targetRetireId.value = row.id
    retireReason.value = ''
    retireDialogVisible.value = true
  }

  async function handleRetire() {
    if (!retireReason.value.trim()) {
      ElMessage.warning('Retirement reason is required')
      return
    }

    if (!targetRetireId.value) return

    retiring.value = true
    try {
      await retireAnnouncement(targetRetireId.value, {
        retirement_reason: retireReason.value.trim()
      })
      ElMessage.success('Announcement retired successfully')
      retireDialogVisible.value = false
      loadData()
    } catch {
      ElMessage.error('Failed to retire announcement')
    } finally {
      retiring.value = false
    }
  }

  async function openHistoryDialog(row: AnnouncementAdminItem) {
    historyDrawerVisible.value = true
    loadingHistory.value = true
    try {
      const res = await fetchAnnouncementHistory(row.id)
      if (res && res.data) {
        historyData.value = res.data
      }
    } catch {
      ElMessage.error('Failed to load revision history')
    } finally {
      loadingHistory.value = false
    }
  }

  async function handleSubmit() {
    if (!formData.value.title || !formData.value.body) {
      ElMessage.warning('Title and content body are required')
      return
    }

    submitting.value = true
    try {
      if (dialogMode.value === 'create') {
        await createAnnouncement({
          title: formData.value.title,
          body: formData.value.body,
          severity: formData.value.severity,
          audience_type: formData.value.audience_type,
          role_ids: formData.value.role_ids,
          location_ids: formData.value.location_ids,
          effective_start_at: formData.value.effective_start_at,
          effective_end_at: formData.value.effective_end_at,
          is_dismissible: formData.value.is_dismissible,
          publish_now: formData.value.publish_now
        })
        ElMessage.success('Announcement created successfully')
      } else if (dialogMode.value === 'edit' && targetAnnouncementId.value) {
        await updateDraftAnnouncement(targetAnnouncementId.value, {
          title: formData.value.title,
          body: formData.value.body,
          severity: formData.value.severity,
          audience_type: formData.value.audience_type,
          role_ids: formData.value.role_ids,
          location_ids: formData.value.location_ids,
          effective_start_at: formData.value.effective_start_at,
          effective_end_at: formData.value.effective_end_at,
          is_dismissible: formData.value.is_dismissible
        })
        ElMessage.success('Draft announcement updated')
      } else if (dialogMode.value === 'revision' && targetAnnouncementId.value) {
        if (!formData.value.change_reason.trim()) {
          ElMessage.warning('A change reason is mandatory when revising a published notice')
          submitting.value = false
          return
        }

        await publishAnnouncement(targetAnnouncementId.value, {
          change_reason: formData.value.change_reason.trim(),
          title: formData.value.title,
          body: formData.value.body,
          severity: formData.value.severity,
          audience_type: formData.value.audience_type,
          role_ids: formData.value.role_ids,
          location_ids: formData.value.location_ids,
          effective_start_at: formData.value.effective_start_at,
          effective_end_at: formData.value.effective_end_at,
          is_dismissible: formData.value.is_dismissible
        })
        ElMessage.success('New revision published successfully')
      }

      dialogVisible.value = false
      loadData()
    } catch (err: any) {
      ElMessage.error(err?.response?.data?.message || 'Operation failed')
    } finally {
      submitting.value = false
    }
  }

  onMounted(() => {
    loadData()
    loadRolesAndLocations()
  })
</script>

<style scoped>
  .announcements-page {
    max-width: 1400px;
    margin: 0 auto;
  }
</style>
