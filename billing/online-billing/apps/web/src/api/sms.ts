import request from '@/utils/http'

export interface NotificationTemplateVersion {
  id: number
  template_id: number
  version: number
  body_template: string
  allowed_variables?: string[] | null
  status: 'draft' | 'published' | 'active' | 'retired'
  published_at?: string | null
  activated_at?: string | null
  retired_at?: string | null
  created_at: string
}

export interface NotificationTemplate {
  id: number
  organization_id: number
  code: string
  name: string
  channel: string
  template_class: 'CONTRACTUAL_TRANSACTIONAL' | 'OPERATIONAL_REMINDER'
  current_version: number
  is_active: boolean
  active_version?: NotificationTemplateVersion | null
  latest_version?: NotificationTemplateVersion | null
  versions?: NotificationTemplateVersion[]
  created_at: string
}

export interface NotificationPolicy {
  id: number
  organization_id: number
  event_key: string
  version: number
  template_id?: number | null
  template_version_id?: number | null
  is_enabled: boolean
  priority: 'normal' | 'high'
  allowed_channel: string
  quiet_hours_policy?: {
    start?: string
    end?: string
    enforce?: boolean
  } | null
  effective_from: string
  template?: NotificationTemplate | null
  template_version?: NotificationTemplateVersion | null
}

export interface SmsDeliveryAttempt {
  id: number
  delivery_id: number
  attempt_number: number
  provider: string
  provider_queue_id?: string | null
  provider_message_id?: string | null
  status: string
  error_message?: string | null
  response_payload_redacted?: Record<string, any> | null
  dispatched_at: string
  response_received_at?: string | null
}

export interface SmsProviderStatusObservation {
  id: number
  delivery_id: number
  provider: string
  provider_raw_status: string
  normalized_status: string
  observation_source: string
  observed_at: string
  details_redacted?: Record<string, any> | null
}

export interface NotificationDelivery {
  id: number
  organization_id: number
  event_id: number
  contact_point_id?: number | null
  recipient_phone_masked: string
  template_version_id: number
  policy_version_id?: number | null
  channel: string
  rendered_body?: string
  rendered_body_hash?: string
  local_effect_key?: string
  message_content_available?: boolean
  status:
    | 'suppressed'
    | 'queued_local'
    | 'dispatching'
    | 'provider_pending'
    | 'provider_queued'
    | 'provider_sent'
    | 'provider_failed'
    | 'unknown_reconciliation_required'
  suppression_reason?: string | null
  attempt_count: number
  last_attempted_at?: string | null
  finalized_at?: string | null
  created_at: string
  event?: {
    id: number
    event_key: string
    occurred_at: string
  }
  template_version?: {
    id: number
    version: number
    template_id: number
    template?: {
      id: number
      code: string
      name: string
    }
  }
  attempts?: SmsDeliveryAttempt[]
  observations?: SmsProviderStatusObservation[]
}

export interface SmsProviderHealth {
  providerName: string
  isConfigured: boolean
  isEnabled: boolean
  status: string
  accountEnvironment: string
  keyReferenceMasked?: string | null
  lastSyncAt?: string | null
  rateLimitObservations: {
    requests_per_minute_limit?: number
    burst_limit?: number
    current_minute_usage?: number
  }
  creditObservations: Record<string, any>
}

// Templates API
export function fetchSmsTemplates(params?: { channel?: string; is_active?: boolean }) {
  return request.get<{ data: NotificationTemplate[]; allowed_variables: string[] }>({
    url: '/api/v1/sms/templates',
    params
  })
}

export function fetchSmsTemplate(id: number) {
  return request.get<{ data: NotificationTemplate; allowed_variables: string[] }>({
    url: `/api/v1/sms/templates/${id}`
  })
}

export function createSmsTemplate(data: {
  code: string
  name: string
  template_class: string
  body_template: string
  allowed_variables?: string[]
}) {
  return request.post<{ message: string; data: NotificationTemplate }>({
    url: '/api/v1/sms/templates',
    data
  })
}

export function createSmsTemplateVersion(
  templateId: number,
  data: { body_template: string; allowed_variables?: string[] }
) {
  return request.post<{ message: string; data: NotificationTemplateVersion }>({
    url: `/api/v1/sms/templates/${templateId}/versions`,
    data
  })
}

export function previewSmsTemplate(data: {
  body_template: string
  sample_data?: Record<string, any>
}) {
  return request.post<{
    data: {
      rendered_preview: string
      character_count: number
      is_within_single_sms: boolean
      warnings: string[]
    }
  }>({
    url: '/api/v1/sms/templates/preview',
    data
  })
}

export function publishSmsTemplateVersion(templateId: number, versionId: number) {
  return request.post<{ message: string; data: NotificationTemplateVersion }>({
    url: `/api/v1/sms/templates/${templateId}/versions/${versionId}/publish`
  })
}

export function activateSmsTemplateVersion(templateId: number, versionId: number) {
  return request.post<{ message: string; data: NotificationTemplateVersion }>({
    url: `/api/v1/sms/templates/${templateId}/versions/${versionId}/activate`
  })
}

export function retireSmsTemplateVersion(templateId: number, versionId: number) {
  return request.post<{ message: string; data: NotificationTemplateVersion }>({
    url: `/api/v1/sms/templates/${templateId}/versions/${versionId}/retire`
  })
}

// Policies API
export function fetchSmsPolicies() {
  return request.get<{ data: NotificationPolicy[] }>({
    url: '/api/v1/sms/policies'
  })
}

export function updateSmsPolicy(
  id: number,
  data: {
    is_enabled: boolean
    priority: 'normal' | 'high'
    template_id?: number | null
    template_version_id?: number | null
    quiet_hours_policy?: Record<string, any> | null
  }
) {
  return request.put<{ message: string; data: NotificationPolicy }>({
    url: `/api/v1/sms/policies/${id}`,
    data
  })
}

// Deliveries API
export function fetchSmsDeliveries(params?: {
  page?: number
  per_page?: number
  status?: string
  event_key?: string
  channel?: string
}) {
  return request.get<{
    data: NotificationDelivery[]
    current_page: number
    last_page: number
    total: number
    per_page: number
  }>({
    url: '/api/v1/sms/deliveries',
    params
  })
}

export function fetchSmsDelivery(id: number) {
  return request.get<{ data: NotificationDelivery }>({
    url: `/api/v1/sms/deliveries/${id}`
  })
}

export function reconcileSmsDelivery(id: number) {
  return request.post<{ message: string; data: NotificationDelivery }>({
    url: `/api/v1/sms/deliveries/${id}/reconcile`
  })
}

export function resendSmsDelivery(id: number, data: { reason: string }) {
  return request.post<{ message: string; data: NotificationDelivery }>({
    url: `/api/v1/sms/deliveries/${id}/resend`,
    data
  })
}

// Provider Health API
export function fetchSmsProviderHealth() {
  return request.get<{ data: SmsProviderHealth }>({
    url: '/api/v1/sms/provider/health'
  })
}
