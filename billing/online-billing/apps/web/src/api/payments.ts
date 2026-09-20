import request from '@/utils/http'

export interface PortalBill {
  id: number
  invoice_number: string
  business_date: string
  currency: string
  total_charge_amount: string
  applied_amount: string
  outstanding_amount: string
  lock_version: number
  status: 'POSTED'
  receipt_history: Array<{
    receipt_id: number
    receipt_number: string
    business_date: string
    applied_amount: string
  }>
}

export interface ManualPaymentSubmission {
  id: number
  customer_id: number
  payment_group_id?: number | null
  proof_file_id: number
  receipt_id?: number | null
  status: 'SUBMITTED' | 'IN_REVIEW' | 'REJECTED' | 'APPROVED'
  currency: string
  requested_amount: string
  declared_reference?: string | null
  confirmed_reference?: string | null
  initial_submitted_at: string
  submitted_at: string
  rejection_reason?: string | null
  lock_version: number
  resubmission_rounds: number
  items: Array<{
    invoice_id: number
    expected_invoice_lock_version?: number
    requested_amount?: string
    invoice?: PortalBill
  }>
  customer?: { id: number; name: string } | null
  assigned_to_user_id?: number | null
  assigned_teller?: { id: number; name: string } | null
  proof_file?: { id: number; current_version?: number } | null
  receipt?: {
    id: number
    receipt_number: string
    status: string
    applied_amount: string
    canonical_artifact?: { id: number } | null
  } | null
  payment_group?: PaymentGroup | null
}

export interface PaymentSubmissionPayload {
  payment_group_id: number
  proof_file_id: number
  declared_reference?: string
}

export interface PaymentGroupAllocation {
  invoice_id: number
  expected_invoice_lock_version: number
  requested_amount: string
}

export interface PaymentGroup {
  id: number
  customer_id: number
  payment_policy_version_id: number
  payment_policy_version_number: number
  route: 'MANUAL_BANK'
  payment_method: 'BANK_TRANSFER' | 'CHECK_DEPOSIT'
  check_clearance_status: 'NOT_APPLICABLE' | 'PENDING' | 'CLEARED' | 'DISHONORED'
  status:
    | 'MANUAL_INSTRUCTION_ISSUED'
    | 'PROOF_SUBMITTED'
    | 'IN_REVIEW'
    | 'PROOF_REJECTED'
    | 'EXPIRED'
    | 'SETTLED'
  currency: string
  gross_selected_amount: string
  gateway_threshold_snapshot?: string | null
  manual_instructions_snapshot: string
  manual_deadline_hours_snapshot: number
  review_target_hours_snapshot?: number | null
  clearance_target_hours_snapshot?: number | null
  correction_window_hours_snapshot?: number | null
  instruction_issued_at: string
  payment_deadline_at: string
  review_due_at?: string | null
  clearance_due_at?: string | null
  correction_due_at?: string | null
  first_proof_submitted_at?: string | null
  first_proof_was_timely?: boolean | null
  lock_version: number
  items: Array<{
    invoice_id: number
    expected_invoice_lock_version: number
    requested_amount: string
    invoice?: PortalBill
  }>
}

export interface PaymentPolicyVersion {
  id: number
  version_number: number
  currency: string
  gateway_enabled: boolean
  gateway_threshold_amount?: string | null
  manual_instructions: string
  manual_deadline_hours: number
  review_target_hours?: number | null
  clearance_target_hours?: number | null
  correction_window_hours?: number | null
  status: 'DRAFT' | 'PUBLISHED'
  effective_from: string
  effective_to?: string | null
  lock_version: number
  publication_reason?: string | null
}

export interface PaymentPolicyPayload {
  currency?: string
  gateway_enabled?: boolean
  gateway_threshold_amount?: string
  manual_instructions: string
  manual_deadline_hours: number
  review_target_hours?: number
  clearance_target_hours?: number
  correction_window_hours?: number
  effective_from: string
  effective_to?: string
}

export interface PaymentInstructionPayload {
  customer_id: number
  payment_method?: 'BANK_TRANSFER' | 'CHECK_DEPOSIT'
  allocations: Array<{
    invoice_id: number
    expected_invoice_lock_version: number
    requested_amount: string
  }>
}

export function fetchPortalBills(customerId: number) {
  return request.get<PortalBill[]>({
    url: '/api/v1/portal/bills',
    params: { customer_id: customerId }
  })
}

export function fetchPaymentSubmissions(customerId: number) {
  return request.get<{ data: ManualPaymentSubmission[]; total: number }>({
    url: '/api/v1/portal/payment-submissions',
    params: { customer_id: customerId }
  })
}

export function submitPaymentProof(data: PaymentSubmissionPayload) {
  return request.post<ManualPaymentSubmission>({ url: '/api/v1/portal/payment-submissions', data })
}

export function fetchPortalPaymentGroups(customerId: number) {
  return request.get<PaymentGroup[]>({
    url: '/api/v1/portal/payment-groups',
    params: { customer_id: customerId }
  })
}

export function issueManualPaymentInstruction(data: PaymentInstructionPayload) {
  return request.post<PaymentGroup>({
    url: '/api/v1/portal/payment-groups/manual-instruction',
    data
  })
}

export function fetchPaymentPolicies() {
  return request.get<PaymentPolicyVersion[]>({ url: '/api/v1/admin/payment-policies' })
}

export function createPaymentPolicy(data: PaymentPolicyPayload) {
  return request.post<PaymentPolicyVersion>({ url: '/api/v1/admin/payment-policies', data })
}

export function publishPaymentPolicy(id: number, expectedLockVersion: number, reason: string) {
  return request.post<PaymentPolicyVersion>({
    url: `/api/v1/admin/payment-policies/${id}/publish`,
    data: { expected_lock_version: expectedLockVersion, reason }
  })
}

export function resubmitPaymentProof(id: number, proofFileId: number) {
  return request.post<ManualPaymentSubmission>({
    url: `/api/v1/portal/payment-submissions/${id}/resubmit`,
    data: { proof_file_id: proofFileId }
  })
}

export function fetchTellerPaymentSubmissions(status?: ManualPaymentSubmission['status']) {
  return request.get<{ data: ManualPaymentSubmission[]; total: number }>({
    url: '/api/v1/teller/payment-submissions',
    params: status ? { status } : undefined
  })
}

export function claimNextPaymentSubmission() {
  return request.post<ManualPaymentSubmission | null>({
    url: '/api/v1/teller/payment-submissions/claim-next'
  })
}

export function fetchTellerPaymentSubmission(id: number) {
  return request.get<ManualPaymentSubmission>({ url: `/api/v1/teller/payment-submissions/${id}` })
}

export function approvePaymentSubmission(
  id: number,
  data: {
    expected_version: number
    confirmed_reference?: string
    allocations: Array<{
      invoice_id: number
      cash_amount: string
      withholding_applications?: Array<{ certificate_id: number; amount: string }>
    }>
  }
) {
  return request.post<ManualPaymentSubmission>({
    url: `/api/v1/teller/payment-submissions/${id}/approve`,
    data
  })
}

export function rejectPaymentSubmission(id: number, expectedVersion: number, reason: string) {
  return request.post<ManualPaymentSubmission>({
    url: `/api/v1/teller/payment-submissions/${id}/reject`,
    data: { expected_version: expectedVersion, reason }
  })
}

export function recordPaymentCheckClearance(
  id: number,
  data: {
    expected_submission_version: number
    expected_payment_group_version: number
    clearance_status: 'CLEARED' | 'DISHONORED'
    notes: string
  }
) {
  return request.post<ManualPaymentSubmission>({
    url: `/api/v1/teller/payment-submissions/${id}/check-clearance`,
    data
  })
}
