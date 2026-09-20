import request from '@/utils/http'

export interface CreditCharge {
  credit_charge_id: number
  invoice_id: number
  invoice_number: string
  currency: string
  charged_amount: string
  outstanding_amount: string
  due_date?: string | null
  due_date_classification?: 'CLASSIFIED' | 'UNCLASSIFIED_NEEDS_TERMS_REVIEW'
  payment_terms_days: number
  due_date_basis: 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE'
  is_overdue: boolean
  invoice_lock_version: number
}

export interface VipCreditSummary {
  eligible: boolean
  reason?: string | null
  account?: { id: number; customer_id: number; customer_name: string; lock_version: number }
  profile?: { version: number; status: 'ACTIVE' | 'HELD' | 'DISABLED'; effective_from: string }
  policy?: { version: number; currency: string; effective_from: string }
  terms?: {
    credit_limit_mode: 'CAPPED' | 'UNLIMITED'
    credit_limit_amount?: string | null
    payment_terms_days: number
    due_date_basis: 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE'
    overdue_restriction: 'ALLOW' | 'WARN' | 'BLOCK'
    overdue_grace_days: number
  }
  exposure_amount?: string
  overdue_amount?: string
  available_credit_amount?: string | null
  charges?: CreditCharge[]
}

export interface VipCreditRepayment {
  id: number
  status: 'SUBMITTED' | 'IN_REVIEW' | 'REJECTED' | 'APPROVED'
  customer_id: number
  proof_file_id: number
  receipt_id?: number | null
  currency: string
  requested_amount: string
  declared_reference?: string | null
  confirmed_reference?: string | null
  initial_submitted_at: string
  rejection_reason?: string | null
  lock_version: number
  receipt?: { id: number; receipt_number: string; status: string; applied_amount: string } | null
  customer?: { id: number; name: string } | null
  allocations: Array<{
    invoice_id: number
    requested_amount: string
    expected_invoice_lock_version: number
    invoice?: { invoice_number: string }
  }>
}

export interface CreditPolicyVersion {
  id: number
  version_number: number
  currency: string
  default_credit_limit_mode: 'CAPPED' | 'UNLIMITED'
  default_credit_limit_amount?: string | null
  payment_terms_days: number
  due_date_basis: 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE'
  overdue_restriction: 'ALLOW' | 'WARN' | 'BLOCK'
  overdue_grace_days: number
  allow_customer_overrides: boolean
  status: 'DRAFT' | 'PUBLISHED'
  effective_from: string
  lock_version: number
}

export function fetchVipCreditSummary(customerId: number) {
  return request.get<VipCreditSummary>({
    url: '/api/v1/portal/credit-account',
    params: { customer_id: customerId }
  })
}

export function chargeBillsToVipCredit(data: {
  customer_id: number
  allocations: Array<{
    invoice_id: number
    expected_invoice_lock_version: number
    requested_amount: string
  }>
}) {
  return request.post<VipCreditSummary>({ url: '/api/v1/portal/credit-charges', data })
}

export function fetchVipCreditRepayments(customerId: number) {
  return request.get<{ data: VipCreditRepayment[] }>({
    url: '/api/v1/portal/credit-repayments',
    params: { customer_id: customerId }
  })
}

export function submitVipCreditRepayment(data: {
  customer_id: number
  proof_file_id: number
  declared_reference?: string
  allocations: Array<{
    invoice_id: number
    expected_invoice_lock_version: number
    requested_amount: string
  }>
}) {
  return request.post<VipCreditRepayment>({ url: '/api/v1/portal/credit-repayments', data })
}

export function resubmitVipCreditRepayment(id: number, proofFileId: number) {
  return request.post<VipCreditRepayment>({
    url: `/api/v1/portal/credit-repayments/${id}/resubmit`,
    data: { proof_file_id: proofFileId }
  })
}

export function fetchTellerVipCreditRepayments(status?: VipCreditRepayment['status']) {
  return request.get<{ data: VipCreditRepayment[] }>({
    url: '/api/v1/teller/credit-repayments',
    params: status ? { status } : undefined
  })
}

export function claimNextVipCreditRepayment() {
  return request.post<VipCreditRepayment | null>({
    url: '/api/v1/teller/credit-repayments/claim-next'
  })
}

export function approveVipCreditRepayment(
  id: number,
  data: {
    expected_version: number
    confirmed_reference: string
    allocations: Array<{ invoice_id: number; cash_amount: string }>
  }
) {
  return request.post<VipCreditRepayment>({
    url: `/api/v1/teller/credit-repayments/${id}/approve`,
    data
  })
}

export function rejectVipCreditRepayment(id: number, expectedVersion: number, reason: string) {
  return request.post<VipCreditRepayment>({
    url: `/api/v1/teller/credit-repayments/${id}/reject`,
    data: { expected_version: expectedVersion, reason }
  })
}

export function fetchCreditPolicies() {
  return request.get<CreditPolicyVersion[]>({ url: '/api/v1/admin/credit-policies' })
}

export function createCreditPolicy(
  data: Omit<CreditPolicyVersion, 'id' | 'version_number' | 'status' | 'lock_version'>
) {
  return request.post<CreditPolicyVersion>({ url: '/api/v1/admin/credit-policies', data })
}

export function publishCreditPolicy(id: number, expectedLockVersion: number, reason: string) {
  return request.post<CreditPolicyVersion>({
    url: `/api/v1/admin/credit-policies/${id}/publish`,
    data: { expected_lock_version: expectedLockVersion, reason }
  })
}
