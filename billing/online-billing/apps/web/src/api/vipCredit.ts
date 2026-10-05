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
    currency?: string
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
  review_target_hours_snapshot: number
  review_due_at: string | null
  review_overdue: boolean
  rejection_reason?: string | null
  resubmission_rounds?: number
  lock_version: number
  proof_file?: {
    id: number
    current_version?: number
    latest_version?: { version_number?: number; original_name?: string; mime_type?: string } | null
  } | null
  receipt?: {
    id: number
    receipt_number: string
    receipt_kind?: 'OFFICIAL' | 'ACKNOWLEDGEMENT' | string | null
    status: string
    applied_amount: string
    canonical_artifact?: { id?: number; status?: string } | null
  } | null
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
  review_target_hours: number
  due_date_basis: 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE'
  overdue_restriction: 'ALLOW' | 'WARN' | 'BLOCK'
  overdue_grace_days: number
  allow_customer_overrides: boolean
  status: 'DRAFT' | 'PUBLISHED'
  effective_from: string
  effective_to?: string | null
  publication_reason?: string | null
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

export interface VipCreditRepaymentPage {
  data: VipCreditRepayment[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export function fetchVipCreditRepayments(customerId: number, page = 1) {
  return request.get<VipCreditRepaymentPage>({
    url: '/api/v1/portal/credit-repayments',
    params: { customer_id: customerId, page }
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

export function deleteCreditPolicyDraft(id: number, expectedLockVersion: number) {
  return request.del<{ message?: string }>({
    url: `/api/v1/admin/credit-policies/${id}`,
    params: { expected_lock_version: expectedLockVersion }
  })
}

export type VipCreditProfileStatus = 'ACTIVE' | 'HELD' | 'DISABLED'

export interface AdminCreditAccountVersion {
  id: number
  version_number: number
  status: VipCreditProfileStatus
  credit_limit_mode_override?: 'CAPPED' | 'UNLIMITED' | null
  credit_limit_amount_override?: string | null
  payment_terms_days_override?: number | null
  due_date_basis_override?: 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE' | null
  overdue_restriction_override?: 'ALLOW' | 'WARN' | 'BLOCK' | null
  overdue_grace_days_override?: number | null
  overdue_amount_threshold_override?: string | null
  effective_from: string
  effective_to?: string | null
  reason?: string
  lock_version?: number
}

export interface AdminCreditAccount {
  id: number
  customer_id: number
  lock_version: number
  customer?: {
    id: number
    name: string
    account_number?: string
    customer_type?: string
    status?: string
  }
  versions?: AdminCreditAccountVersion[]
}

export interface AdminCreditAccountList {
  data: AdminCreditAccount[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface VipCreditAccountProfileInput {
  expected_account_lock_version?: number
  status: VipCreditProfileStatus
  effective_from: string
  effective_to?: string | null
  reason: string
  credit_limit_mode_override?: 'CAPPED' | 'UNLIMITED' | null
  credit_limit_amount_override?: string | null
  payment_terms_days_override?: number | null
  due_date_basis_override?: 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE' | null
  overdue_restriction_override?: 'ALLOW' | 'WARN' | 'BLOCK' | null
  overdue_grace_days_override?: number | null
  overdue_amount_threshold_override?: string | null
}

export function fetchAdminCreditAccounts(page = 1) {
  return request.get<AdminCreditAccountList>({
    url: '/api/v1/admin/credit-accounts',
    params: { page }
  })
}

export function configureVipCreditAccount(customerId: number, body: VipCreditAccountProfileInput) {
  return request.post<AdminCreditAccount>({
    url: `/api/v1/admin/credit-accounts/${customerId}/versions`,
    data: body
  })
}

export function fetchStaffCreditAccountSummary(accountId: number) {
  return request.get<VipCreditSummary>({
    url: `/api/v1/credit-accounts/${accountId}`
  })
}
