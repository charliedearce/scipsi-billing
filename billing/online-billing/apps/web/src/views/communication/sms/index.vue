<template>
  <div class="sms-workspace-page p-4">
    <!-- Header -->
    <ElCard shadow="never" class="mb-4">
      <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h2 class="text-lg font-semibold text-gray-800">Transactional SMS Communications</h2>
            <ElTag type="info" size="small" effect="plain">Decision W31</ElTag>
          </div>
          <p class="text-sm text-gray-500 mt-1">
            Manage SkySMS gateway status, immutable versioned templates, delivery reconciliation,
            and recipient contact safeguards.
          </p>
        </div>
        <div class="flex items-center gap-2">
          <ElTag :type="providerHealth?.status === 'healthy' ? 'success' : 'warning'" size="small">
            Provider: {{ providerHealth?.providerName || 'SkySMS' }} ({{
              providerHealth?.status || 'Unknown'
            }})
          </ElTag>
          <ElButton @click="refreshAll">Refresh All</ElButton>
        </div>
      </div>

      <ElAlert
        title="W31 Boundary Guarantee: SMS delivery observations never modify invoices, receipts, financial balances, or VIP credit limits."
        type="info"
        :closable="false"
        show-icon
        class="mt-3"
      />
    </ElCard>

    <!-- Main Workspace Tabs -->
    <ElCard shadow="never">
      <ElTabs v-model="activeTab" class="sms-tabs">
        <!-- TAB 1: Provider Health -->
        <ElTabPane label="Provider Health" name="health">
          <div class="py-2" v-loading="loadingHealth">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
              <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="text-xs text-gray-500 uppercase font-semibold">Gateway Status</div>
                <div class="mt-2 flex items-center gap-2">
                  <span
                    class="w-3 h-3 rounded-full"
                    :class="
                      providerHealth?.status === 'healthy' ? 'bg-emerald-500' : 'bg-amber-500'
                    "
                  ></span>
                  <span class="text-base font-bold text-gray-800 capitalize">{{
                    providerHealth?.status || 'Active'
                  }}</span>
                </div>
                <div class="text-xs text-gray-500 mt-1"
                  >Environment: {{ providerHealth?.accountEnvironment || 'sandbox' }}</div
                >
              </div>

              <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="text-xs text-gray-500 uppercase font-semibold"
                  >Credential Reference</div
                >
                <div class="mt-2 text-base font-mono font-medium text-gray-700">
                  {{ providerHealth?.keyReferenceMasked || 'Managed Secret Reference' }}
                </div>
                <div class="text-xs text-gray-500 mt-1"
                  >Raw API keys are write-only and never exposed</div
                >
              </div>

              <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="text-xs text-gray-500 uppercase font-semibold">Throttling Rules</div>
                <div class="mt-2 text-base font-bold text-gray-800">
                  {{ providerHealth?.rateLimitObservations?.requests_per_minute_limit || 30 }}
                  req/min
                </div>
                <div class="text-xs text-gray-500 mt-1">
                  Burst limit: {{ providerHealth?.rateLimitObservations?.burst_limit || 3 }} req/sec
                </div>
              </div>
            </div>

            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-800">
              <div class="font-semibold mb-1">SkySMS Adapter Dispatch Policy</div>
              <ul class="list-disc list-inside space-y-1 text-xs">
                <li
                  >SMS intents are dispatched strictly <strong>post-commit</strong> by background
                  queue workers.</li
                >
                <li
                  >Normal send endpoint lacks idempotency parameter: timeouts transition to
                  <code>UNKNOWN_RECONCILIATION_REQUIRED</code> without blind retries.</li
                >
                <li
                  ><code>PROVIDER_SENT</code> is displayed as provider-reported and never labeled as
                  proof of customer receipt.</li
                >
              </ul>
            </div>
          </div>
        </ElTabPane>

        <!-- TAB 2: Templates & Policies -->
        <ElTabPane label="Templates & Policies" name="templates">
          <div class="py-2" v-loading="loadingTemplates">
            <div class="flex items-center justify-between mb-4">
              <h3 class="text-sm font-semibold text-gray-700">Approved Transactional Templates</h3>
              <ElButton type="primary" size="small" @click="showNewTemplateDialog = true">
                Create New Template Draft
              </ElButton>
            </div>

            <ElTable :data="templates" stripe border class="w-full">
              <ElTableColumn prop="code" label="Event Code" width="220">
                <template #default="{ row }">
                  <span class="font-mono font-medium text-blue-600 text-xs">{{ row.code }}</span>
                </template>
              </ElTableColumn>
              <ElTableColumn prop="name" label="Template Name" min-width="180" />
              <ElTableColumn prop="template_class" label="Class" width="200">
                <template #default="{ row }">
                  <ElTag
                    :type="
                      row.template_class === 'CONTRACTUAL_TRANSACTIONAL' ? 'primary' : 'warning'
                    "
                    size="small"
                  >
                    {{ row.template_class }}
                  </ElTag>
                </template>
              </ElTableColumn>
              <ElTableColumn label="Active Version" width="120">
                <template #default="{ row }">
                  <ElTag type="success" size="small">v{{ row.current_version }}</ElTag>
                </template>
              </ElTableColumn>
              <ElTableColumn label="Active Body" min-width="260">
                <template #default="{ row }">
                  <span class="text-xs text-gray-600 line-clamp-2">
                    {{ row.active_version?.body_template || 'No active version' }}
                  </span>
                </template>
              </ElTableColumn>
              <ElTableColumn label="Actions" width="220" fixed="right">
                <template #default="{ row }">
                  <div class="flex items-center gap-2">
                    <ElButton size="small" @click="handlePreviewTemplate(row)">Preview</ElButton>
                    <ElButton size="small" type="primary" plain @click="handleViewVersions(row)">
                      Versions ({{ row.versions?.length || 1 }})
                    </ElButton>
                  </div>
                </template>
              </ElTableColumn>
            </ElTable>

            <!-- Notification Policies Section -->
            <div class="mt-8">
              <h3 class="text-sm font-semibold text-gray-700 mb-3"
                >Event Dispatch Policies & Quiet Hours</h3
              >
              <ElTable :data="policies" stripe border class="w-full">
                <ElTableColumn prop="event_key" label="Event Key" width="240">
                  <template #default="{ row }">
                    <span class="font-mono text-xs">{{ row.event_key }}</span>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Priority" width="120">
                  <template #default="{ row }">
                    <ElTag :type="row.priority === 'high' ? 'danger' : 'info'" size="small">
                      {{ row.priority }}
                    </ElTag>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Quiet Hours (21:00 - 07:00)" width="200">
                  <template #default="{ row }">
                    <ElTag
                      :type="row.quiet_hours_policy?.enforce ? 'warning' : 'info'"
                      size="small"
                    >
                      {{ row.quiet_hours_policy?.enforce ? 'Enforced' : 'Exempt (Transaction)' }}
                    </ElTag>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Dispatch Status" width="140">
                  <template #default="{ row }">
                    <ElSwitch
                      v-model="row.is_enabled"
                      size="small"
                      @change="(val: any) => handleTogglePolicy(row, Boolean(val))"
                    />
                    <span class="ml-2 text-xs">{{ row.is_enabled ? 'Enabled' : 'Disabled' }}</span>
                  </template>
                </ElTableColumn>
              </ElTable>
            </div>
          </div>
        </ElTabPane>

        <!-- TAB 3: Delivery Dashboard -->
        <ElTabPane label="Delivery Outbox Dashboard" name="deliveries">
          <div class="py-2" v-loading="loadingDeliveries">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
              <div class="flex items-center gap-2">
                <ElSelect
                  v-model="filterStatus"
                  placeholder="Filter Status"
                  clearable
                  class="!w-44"
                  @change="loadDeliveries"
                >
                  <ElOption label="All Statuses" value="" />
                  <ElOption label="Queued Local" value="queued_local" />
                  <ElOption label="Provider Pending" value="provider_pending" />
                  <ElOption label="Provider Sent" value="provider_sent" />
                  <ElOption label="Provider Failed" value="provider_failed" />
                  <ElOption
                    label="Reconciliation Required"
                    value="unknown_reconciliation_required"
                  />
                  <ElOption label="Suppressed" value="suppressed" />
                </ElSelect>
                <ElButton @click="loadDeliveries">Filter</ElButton>
              </div>
              <div class="text-xs text-gray-500">
                Total Deliveries: {{ deliveriesPagination.total }}
              </div>
            </div>

            <ElTable :data="deliveries" stripe border class="w-full">
              <ElTableColumn prop="id" label="ID" width="80" />
              <ElTableColumn label="Event" width="180">
                <template #default="{ row }">
                  <span class="font-mono text-xs font-semibold">{{
                    row.event?.event_key || 'EVENT'
                  }}</span>
                </template>
              </ElTableColumn>
              <ElTableColumn label="Recipient (Masked)" width="160">
                <template #default="{ row }">
                  <span class="font-mono text-xs">{{ row.recipient_phone_masked }}</span>
                </template>
              </ElTableColumn>
              <ElTableColumn label="Status" width="180">
                <template #default="{ row }">
                  <ElTag :type="getStatusTagType(row.status)" size="small">
                    {{ formatStatus(row.status) }}
                  </ElTag>
                </template>
              </ElTableColumn>
              <ElTableColumn label="Message Preview" min-width="240">
                <template #default="{ row }">
                  <span
                    v-if="row.message_content_available !== false"
                    class="text-xs text-gray-600 line-clamp-1"
                  >
                    {{ row.rendered_body }}
                  </span>
                  <span v-else class="text-xs text-gray-400"
                    >Restricted to authorized communications staff</span
                  >
                </template>
              </ElTableColumn>
              <ElTableColumn label="Attempts" width="90" align="center">
                <template #default="{ row }">
                  <span class="text-xs font-bold">{{ row.attempt_count }}</span>
                </template>
              </ElTableColumn>
              <ElTableColumn label="Actions" width="200" fixed="right">
                <template #default="{ row }">
                  <div class="flex items-center gap-2">
                    <ElButton size="small" @click="handleViewDelivery(row)">Details</ElButton>
                    <ElButton
                      v-if="
                        row.status === 'unknown_reconciliation_required' ||
                        row.status === 'provider_pending'
                      "
                      size="small"
                      type="warning"
                      plain
                      @click="handleReconcileDelivery(row)"
                    >
                      Reconcile
                    </ElButton>
                    <ElButton
                      v-if="row.status === 'provider_failed'"
                      size="small"
                      type="danger"
                      plain
                      @click="handleResendDelivery(row)"
                    >
                      Resend
                    </ElButton>
                  </div>
                </template>
              </ElTableColumn>
            </ElTable>

            <div class="mt-4 flex justify-end">
              <ElPagination
                v-model:current-page="deliveriesPagination.page"
                :page-size="deliveriesPagination.per_page"
                :total="deliveriesPagination.total"
                layout="prev, pager, next"
                @current-change="loadDeliveries"
              />
            </div>
          </div>
        </ElTabPane>

        <!-- TAB 4: Contact Governance -->
        <ElTabPane label="Contact & Preference Governance" name="governance">
          <div class="py-2 space-y-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h4 class="text-sm font-semibold text-gray-800 mb-2"
                  >Verified Contact Requirement</h4
                >
                <p class="text-xs text-gray-600">
                  Transactional SMS dispatches require a verified mobile contact belonging to the
                  affected customer account. Unverified phone numbers, opted-out contacts, or stale
                  records are automatically suppressed with an immutable suppression record.
                </p>
              </div>

              <div class="p-4 bg-gray-50 rounded-lg border border-gray-200">
                <h4 class="text-sm font-semibold text-gray-800 mb-2"
                  >Customer Preference Sovereignty</h4
                >
                <p class="text-xs text-gray-600">
                  Customers can configure their notification preferences directly in the online
                  portal. Operational reminders respect customer opt-outs and quiet hours.
                  Contractual notices require explicit legitimate business basis.
                </p>
              </div>
            </div>

            <div class="bg-gray-50 border border-gray-200 rounded-lg p-4">
              <h4 class="text-sm font-semibold text-gray-800 mb-2"
                >Safe Variable Catalog Whitelist</h4
              >
              <div class="flex flex-wrap gap-2">
                <ElTag v-for="varName in allowedVariables" :key="varName" type="info" size="small">
                  {{ formatVar(varName) }}
                </ElTag>
              </div>
              <p class="text-xs text-gray-500 mt-2">
                URLs, bank account numbers, tax exemption figures, or authentication OTPs are
                strictly prohibited in transactional SMS templates.
              </p>
            </div>
          </div>
        </ElTabPane>
      </ElTabs>
    </ElCard>

    <!-- Preview Modal -->
    <ElDialog v-model="showPreviewDialog" title="Template Synthetic Preview" width="500px">
      <div v-if="previewResult" class="space-y-3">
        <div
          class="p-3 bg-gray-100 rounded border border-gray-300 font-mono text-sm whitespace-pre-wrap"
        >
          {{ previewResult.rendered_preview }}
        </div>
        <div class="flex items-center justify-between text-xs text-gray-500">
          <span>Characters: {{ previewResult.character_count }} / 1000</span>
          <ElTag :type="previewResult.is_within_single_sms ? 'success' : 'warning'" size="small">
            {{ previewResult.is_within_single_sms ? 'Single SMS (<= 160)' : 'Multi-part SMS' }}
          </ElTag>
        </div>
        <div v-if="previewResult.warnings.length > 0" class="text-xs text-amber-600">
          <div v-for="(w, idx) in previewResult.warnings" :key="idx">• {{ w }}</div>
        </div>
      </div>
    </ElDialog>

    <!-- Version History Drawer / Modal -->
    <ElDialog
      v-model="showVersionsDialog"
      :title="`Version History: ${selectedTemplate?.code}`"
      width="700px"
    >
      <div v-if="selectedTemplate" class="space-y-4">
        <div class="flex items-center justify-between">
          <span class="text-sm font-semibold text-gray-700">Template Versions</span>
          <ElButton size="small" type="primary" @click="showNewVersionForm = true">
            Draft New Version
          </ElButton>
        </div>

        <ElTable :data="selectedTemplate.versions || []" border stripe size="small">
          <ElTableColumn prop="version" label="Ver" width="60" align="center" />
          <ElTableColumn label="Status" width="100">
            <template #default="{ row }">
              <ElTag :type="getVersionTagType(row.status)" size="small">{{ row.status }}</ElTag>
            </template>
          </ElTableColumn>
          <ElTableColumn prop="body_template" label="Template Text" min-width="250" />
          <ElTableColumn
            label="Actions"
            width="180"
            class-name="mobile-table-actions"
            label-class-name="mobile-table-actions"
          >
            <template #default="{ row }">
              <div class="flex items-center gap-1">
                <ElButton
                  v-if="row.status === 'draft'"
                  size="small"
                  type="success"
                  plain
                  @click="handlePublishVersion(row)"
                >
                  Publish
                </ElButton>
                <ElButton
                  v-if="row.status === 'published'"
                  size="small"
                  type="primary"
                  plain
                  @click="handleActivateVersion(row)"
                >
                  Activate
                </ElButton>
                <ElButton v-if="row.status === 'active'" size="small" type="info" disabled>
                  Current
                </ElButton>
              </div>
            </template>
          </ElTableColumn>
        </ElTable>
      </div>
    </ElDialog>

    <!-- Delivery Detail Dialog -->
    <ElDialog v-model="showDeliveryDetailDialog" title="Delivery Outbox Record" width="650px">
      <div v-if="selectedDelivery" class="space-y-4 text-sm">
        <div class="grid grid-cols-2 gap-3 p-3 bg-gray-50 rounded border">
          <div><strong class="text-gray-500">ID:</strong> #{{ selectedDelivery.id }}</div>
          <div
            ><strong class="text-gray-500">Event:</strong>
            {{ selectedDelivery.event?.event_key }}</div
          >
          <div
            ><strong class="text-gray-500">Recipient:</strong>
            {{ selectedDelivery.recipient_phone_masked }}</div
          >
          <div>
            <strong class="text-gray-500">Status:</strong>
            <ElTag :type="getStatusTagType(selectedDelivery.status)" size="small" class="ml-1">
              {{ formatStatus(selectedDelivery.status) }}
            </ElTag>
          </div>
          <div
            ><strong class="text-gray-500">Attempts:</strong>
            {{ selectedDelivery.attempt_count }}</div
          >
          <div><strong class="text-gray-500">Channel:</strong> {{ selectedDelivery.channel }}</div>
        </div>

        <div
          v-if="selectedDelivery.suppression_reason"
          class="p-3 bg-amber-50 border border-amber-200 rounded text-amber-800 text-xs"
        >
          <strong>Suppression Reason:</strong> {{ selectedDelivery.suppression_reason }}
        </div>

        <div v-if="selectedDelivery.message_content_available !== false">
          <h4 class="font-semibold text-gray-700 text-xs uppercase mb-1">Rendered Message Body</h4>
          <div class="p-3 bg-gray-100 rounded font-mono text-xs whitespace-pre-wrap">
            {{ selectedDelivery.rendered_body }}
          </div>
          <div class="mt-1 text-xs text-gray-400 font-mono">
            SHA-256: {{ selectedDelivery.rendered_body_hash }}
          </div>
        </div>
        <ElAlert
          v-else
          title="Message content is restricted"
          type="info"
          :closable="false"
          show-icon
          description="You can view the delivery state for this assigned location, but not the customer message body or provider identifiers."
        />

        <div>
          <h4 class="font-semibold text-gray-700 text-xs uppercase mb-1">Dispatch Attempts</h4>
          <ElTable :data="selectedDelivery.attempts || []" border size="small">
            <ElTableColumn prop="attempt_number" label="#" width="50" align="center" />
            <ElTableColumn prop="status" label="Status" width="110" />
            <ElTableColumn prop="provider_queue_id" label="Provider Queue ID" min-width="160">
              <template #default="{ row }">
                <span class="font-mono text-xs">{{ row.provider_queue_id || 'N/A' }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn prop="dispatched_at" label="Dispatched At" width="160" />
          </ElTable>
        </div>
      </div>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { ref, onMounted } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    fetchSmsTemplates,
    fetchSmsPolicies,
    fetchSmsDeliveries,
    fetchSmsDelivery,
    fetchSmsProviderHealth,
    previewSmsTemplate,
    publishSmsTemplateVersion,
    activateSmsTemplateVersion,
    reconcileSmsDelivery,
    resendSmsDelivery,
    updateSmsPolicy,
    type NotificationTemplate,
    type NotificationPolicy,
    type NotificationDelivery,
    type SmsProviderHealth
  } from '@/api/sms'

  const activeTab = ref('health')

  // State
  const loadingHealth = ref(false)
  const loadingTemplates = ref(false)
  const loadingDeliveries = ref(false)

  const providerHealth = ref<SmsProviderHealth | null>(null)
  const templates = ref<NotificationTemplate[]>([])
  const policies = ref<NotificationPolicy[]>([])
  const deliveries = ref<NotificationDelivery[]>([])
  const allowedVariables = ref<string[]>([
    'org_name',
    'recipient_name',
    'reference_no',
    'queue_ticket',
    'action_label',
    'date_formatted',
    'support_contact'
  ])

  const filterStatus = ref('')
  const deliveriesPagination = ref({
    page: 1,
    per_page: 15,
    total: 0
  })

  // Dialog states
  const showPreviewDialog = ref(false)
  const previewResult = ref<any>(null)
  const showVersionsDialog = ref(false)
  const selectedTemplate = ref<NotificationTemplate | null>(null)
  const showNewTemplateDialog = ref(false)
  const showNewVersionForm = ref(false)
  const showDeliveryDetailDialog = ref(false)
  const selectedDelivery = ref<NotificationDelivery | null>(null)

  // Loaders
  async function loadHealth() {
    loadingHealth.value = true
    try {
      const res = await fetchSmsProviderHealth()
      providerHealth.value = res.data
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to load SMS provider health')
    } finally {
      loadingHealth.value = false
    }
  }

  async function loadTemplates() {
    loadingTemplates.value = true
    try {
      const [tplRes, polRes] = await Promise.all([fetchSmsTemplates(), fetchSmsPolicies()])
      templates.value = tplRes.data
      policies.value = polRes.data
      if (tplRes.allowed_variables) {
        allowedVariables.value = tplRes.allowed_variables
      }
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to load templates')
    } finally {
      loadingTemplates.value = false
    }
  }

  async function loadDeliveries() {
    loadingDeliveries.value = true
    try {
      const res = await fetchSmsDeliveries({
        page: deliveriesPagination.value.page,
        per_page: deliveriesPagination.value.per_page,
        status: filterStatus.value || undefined
      })
      deliveries.value = res.data
      deliveriesPagination.value.total = res.total
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to load deliveries')
    } finally {
      loadingDeliveries.value = false
    }
  }

  function refreshAll() {
    loadHealth()
    loadTemplates()
    loadDeliveries()
  }

  // Template Actions
  async function handlePreviewTemplate(template: NotificationTemplate) {
    const body =
      template.active_version?.body_template || template.latest_version?.body_template || ''
    if (!body) {
      ElMessage.warning('Template has no body to preview')
      return
    }

    try {
      const res = await previewSmsTemplate({ body_template: body })
      previewResult.value = res.data
      showPreviewDialog.value = true
    } catch (err: any) {
      ElMessage.error(err?.message || 'Preview failed')
    }
  }

  function handleViewVersions(template: NotificationTemplate) {
    selectedTemplate.value = template
    showVersionsDialog.value = true
  }

  async function handlePublishVersion(version: any) {
    const templateId = selectedTemplate.value?.id || version.template_id
    try {
      await publishSmsTemplateVersion(templateId, version.id)
      ElMessage.success('Version published.')
      loadTemplates()
      showVersionsDialog.value = false
    } catch (err: any) {
      ElMessage.error(err?.message || 'Publish failed')
    }
  }

  async function handleActivateVersion(version: any) {
    const templateId = selectedTemplate.value?.id || version.template_id
    try {
      await activateSmsTemplateVersion(templateId, version.id)
      ElMessage.success('Version activated.')
      loadTemplates()
      showVersionsDialog.value = false
    } catch (err: any) {
      ElMessage.error(err?.message || 'Activation failed')
    }
  }

  async function handleTogglePolicy(policy: NotificationPolicy, enabled: boolean) {
    try {
      await updateSmsPolicy(policy.id, {
        is_enabled: enabled,
        priority: policy.priority
      })
      ElMessage.success('Policy updated.')
    } catch (err: any) {
      policy.is_enabled = !enabled
      ElMessage.error(err?.message || 'Policy update failed')
    }
  }

  // Delivery Actions
  async function handleViewDelivery(delivery: NotificationDelivery) {
    try {
      const res = await fetchSmsDelivery(delivery.id)
      selectedDelivery.value = res.data
      showDeliveryDetailDialog.value = true
    } catch (err: any) {
      ElMessage.error(err?.message || 'Failed to fetch delivery details')
    }
  }

  async function handleReconcileDelivery(delivery: NotificationDelivery) {
    try {
      await reconcileSmsDelivery(delivery.id)
      ElMessage.success('Reconciliation completed.')
      loadDeliveries()
    } catch (err: any) {
      ElMessage.error(err?.message || 'Reconciliation failed')
    }
  }

  async function handleResendDelivery(delivery: NotificationDelivery) {
    try {
      const { value: reason } = await ElMessageBox.prompt(
        'Enter authorization reason for resending this failed SMS:',
        'Resend Transactional SMS',
        {
          confirmButtonText: 'Resend',
          cancelButtonText: 'Cancel',
          inputPattern: /^.{3,255}$/,
          inputErrorMessage: 'Reason must be between 3 and 255 characters'
        }
      )

      if (reason) {
        await resendSmsDelivery(delivery.id, { reason })
        ElMessage.success('Resend dispatched.')
        loadDeliveries()
      }
    } catch {
      // cancelled
    }
  }

  // Helpers
  function getStatusTagType(status: string) {
    switch (status) {
      case 'provider_sent':
        return 'success'
      case 'provider_pending':
      case 'provider_queued':
      case 'dispatching':
      case 'queued_local':
        return 'info'
      case 'provider_failed':
        return 'danger'
      case 'unknown_reconciliation_required':
        return 'warning'
      case 'suppressed':
        return 'info'
      default:
        return 'info'
    }
  }

  function getVersionTagType(status: string) {
    switch (status) {
      case 'active':
        return 'success'
      case 'published':
        return 'primary'
      case 'draft':
        return 'info'
      case 'retired':
        return 'warning'
      default:
        return 'info'
    }
  }

  function formatStatus(status: string) {
    return status.replace(/_/g, ' ').toUpperCase()
  }

  function formatVar(name: string): string {
    return '{{ ' + name + ' }}'
  }

  onMounted(() => {
    loadHealth()
    loadTemplates()
    loadDeliveries()
  })
</script>

<style scoped>
  .sms-workspace-page {
    min-height: 100%;
  }
</style>
