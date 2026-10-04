import request from '@/utils/http'

export type BillingRequestStatus =
  | 'DRAFT'
  | 'QUEUED'
  | 'IN_REVIEW'
  | 'NEEDS_CORRECTION'
  | 'BILLING_IN_PROGRESS'
  | 'BILL_READY'
  | 'CANCELLED'
  | 'CLOSED'

export interface BillingRequestDocument {
  id: number
  billing_request_id: number
  document_type_id: number
  document_requirement_id?: number | null
  private_file_id: number
  review_status: 'PENDING' | 'ACCEPTED' | 'NEEDS_CORRECTION'
  rejection_reason?: string | null
  reviewed_version_number?: number | null
  customer_notes?: string | null
  document_type?: {
    id: number
    code: string
    name: string
    allowed_mime_types?: string[]
    max_file_size_kb?: number
  } | null
  private_file?: {
    id: number
    current_version: number
    status: string
    latest_version?: {
      id: number
      version_number: number
      original_name: string
      mime_type: string
      file_size_bytes: number
      scan_status: 'PENDING' | 'CLEAN' | 'QUARANTINED'
    } | null
  } | null
}

export interface BillingRequestInvoiceRef {
  id: number
  invoice_number?: string | null
  status: string
  total_charge_amount?: string
  currency?: string
  posted_at?: string | null
  has_posted_settlement?: boolean
  settlement_state?: 'UNPAID' | 'PAID' | 'REPLACED' | string
  superseded_by_invoice_id?: number | null
  lock_version?: number
}

export type BillingRequestProgressStepKey =
  | 'submitted'
  | 'queued'
  | 'review'
  | 'billing'
  | 'bill_ready'
  | 'paid'

/** Server-derived, read-only view of who owns the request now and what happens next. */
export interface BillingRequestProgress {
  state: 'active' | 'attention' | 'complete' | 'cancelled'
  current_step: BillingRequestProgressStepKey | null
  owner: { type: 'customer' | 'teller' | 'none'; name: string | null; label: string }
  required_action: string
  next_step: string | null
  steps: Array<{
    key: BillingRequestProgressStepKey
    label: string
    status: 'complete' | 'current' | 'upcoming'
    at: string | null
  }>
}

export interface BillingRequestItem {
  id: number
  organization_id: number
  location_id: number
  customer_id: number
  created_by_user_id: number
  service_type: string
  transaction_no: string
  ticket_number?: number | null
  status: BillingRequestStatus
  initial_submitted_at?: string | null
  submitted_at?: string | null
  admitted_at?: string | null
  assigned_to_user_id?: number | null
  assigned_at?: string | null
  assignment_heartbeat_at?: string | null
  correction_rounds: number
  correction_notes?: string | null
  draft_invoice_id?: number | null
  invoice_id?: number | null
  invoice_count?: number
  invoices?: BillingRequestInvoiceRef[]
  requirement_snapshot?: Array<Record<string, unknown>> | null
  lock_version: number
  queue_position?: number | null
  queue_estimate?: {
    requests_ahead: number | null
    estimated_wait_minutes: number | null
    estimated_ready_minutes: number | null
    estimate_basis: 'recent_bills' | 'unavailable'
  } | null
  timeline?: {
    requested_at?: string | null
    bill_approved_at?: string | null
    paid_at?: string | null
  }
  catering_teller?: { id: number; name: string } | null
  progress?: BillingRequestProgress | null
  location?: { id: number; name: string; code?: string } | null
  customer?: { id: number; name: string; account_number?: string } | null
  assigned_teller?: { id: number; name: string; email?: string } | null
  assignedTeller?: { id: number; name: string; email?: string } | null
  documents?: BillingRequestDocument[]
  draft_invoice?: BillingRequestInvoiceRef | null
  draftInvoice?: BillingRequestInvoiceRef | null
  invoice?: BillingRequestInvoiceRef | null
  events?: Array<{
    id: number
    event_type: string
    from_status?: string | null
    to_status?: string | null
    notes?: string | null
    created_at: string
    actor?: { id: number; name: string } | null
  }>
  created_at?: string
  updated_at?: string
}

export interface BillingRequestListResponse {
  data: BillingRequestItem[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface TellerQueueSummary {
  queued_count: number
  in_review_count: number
  bill_ready_count?: number
  my_active_assignment: BillingRequestItem | null
  waiting_queue: BillingRequestItem[]
  completed_tracking?: BillingRequestItem[]
}

export function fetchCustomerBillingRequests(params?: {
  status?: BillingRequestStatus
  page?: number
}) {
  return request.get<BillingRequestListResponse>({
    url: '/api/v1/customer/billing-requests',
    params
  })
}

export function fetchCustomerBillingRequest(id: number) {
  return request.get<{ billing_request: BillingRequestItem }>({
    url: `/api/v1/customer/billing-requests/${id}`
  })
}

export function createCustomerBillingRequest(data: {
  customer_id: number
  location_id?: number | null
  service_type: string
  notes?: string
}) {
  return request.post<{ message: string; billing_request: BillingRequestItem }>({
    url: '/api/v1/customer/billing-requests',
    data: {
      customer_id: data.customer_id,
      service_type: data.service_type,
      notes: data.notes,
      ...(data.location_id ? { location_id: data.location_id } : {})
    }
  })
}

export function attachBillingRequestDocument(
  id: number,
  data: {
    document_type_id: number
    private_file_id: number
    document_requirement_id?: number | null
    customer_notes?: string
  }
) {
  return request.post<{ message: string; document: BillingRequestDocument }>({
    url: `/api/v1/customer/billing-requests/${id}/documents`,
    data
  })
}

export function removeBillingRequestDocument(requestId: number, documentId: number) {
  return request.del<{ message: string; billing_request: BillingRequestItem }>({
    url: `/api/v1/customer/billing-requests/${requestId}/documents/${documentId}`
  })
}

export function submitBillingRequest(id: number) {
  return request.post<{
    message: string
    billing_request: BillingRequestItem
    queue_position?: number | null
  }>({
    url: `/api/v1/customer/billing-requests/${id}/submit`
  })
}

export function resubmitBillingRequest(id: number, notes?: string) {
  return request.post<{
    message: string
    billing_request: BillingRequestItem
    queue_position?: number | null
  }>({
    url: `/api/v1/customer/billing-requests/${id}/resubmit`,
    data: notes ? { notes } : {}
  })
}

export function cancelBillingRequest(id: number, reason: string) {
  return request.post<{ message: string; billing_request: BillingRequestItem }>({
    url: `/api/v1/customer/billing-requests/${id}/cancel`,
    data: { reason }
  })
}

export function fetchTellerBillingQueue(params?: { location_id?: number }) {
  return request.get<TellerQueueSummary>({
    url: '/api/v1/teller/queue',
    params
  })
}

export function claimNextBillingRequest(data: { location_id: number; service_type?: string }) {
  return request.post<{ message: string; claimed: BillingRequestItem | null }>({
    url: '/api/v1/teller/queue/claim-next',
    data
  })
}

export function fetchTellerBillingRequest(id: number) {
  return request.get<{ billing_request: BillingRequestItem }>({
    url: `/api/v1/teller/billing-requests/${id}`
  })
}

export function heartbeatBillingRequest(id: number) {
  return request.post<{ message: string; assignment_heartbeat_at: string }>({
    url: `/api/v1/teller/billing-requests/${id}/heartbeat`
  })
}

export function requestBillingCorrection(
  id: number,
  data: {
    notes: string
    file_remarks?: Array<{ document_type_id: number; rejection_reason: string }>
  }
) {
  return request.post<{ message: string; billing_request: BillingRequestItem }>({
    url: `/api/v1/teller/billing-requests/${id}/request-correction`,
    data
  })
}

export function prepareBillingDraft(id: number) {
  return request.post<{
    message: string
    invoice: BillingRequestInvoiceRef
    billing_request: BillingRequestItem
  }>({
    url: `/api/v1/teller/billing-requests/${id}/prepare-draft`
  })
}

export function markBillingRequestBillReady(
  id: number,
  invoiceId: number,
  options?: { complete?: boolean }
) {
  return request.post<{ message: string; billing_request: BillingRequestItem }>({
    url: `/api/v1/teller/billing-requests/${id}/mark-bill-ready`,
    data: {
      invoice_id: invoiceId,
      complete: options?.complete ?? true
    }
  })
}

export function releaseBillingRequest(id: number, reason?: string) {
  return request.post<{ message: string; billing_request: BillingRequestItem }>({
    url: `/api/v1/teller/billing-requests/${id}/release`,
    data: reason ? { reason } : {}
  })
}

export function cancelTellerBillingRequest(id: number, reason: string) {
  return request.post<{ message: string; billing_request: BillingRequestItem }>({
    url: `/api/v1/teller/billing-requests/${id}/cancel`,
    data: { reason }
  })
}
