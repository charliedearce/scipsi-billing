import request from '@/utils/http'
import type { PrivateFileItem } from '@/api/documentRequirements'

export type TaxEvidenceStatus =
  | 'PENDING_REVIEW'
  | 'APPROVED'
  | 'NEEDS_CORRECTION'
  | 'REJECTED'
  | 'EXPIRED'
  | 'REVOKED'

export type ExemptionType = 'VAT_EXEMPT' | 'ZERO_RATED'

export type TaxReviewDecision = 'APPROVED' | 'NEEDS_CORRECTION' | 'REJECTED'

export interface TaxEvidenceCustomer {
  id: number
  name?: string
  account_number?: string
}

export interface WithholdingCertificate {
  id: number
  organization_id: number
  customer_id: number
  certificate_no: string
  private_file_id: number
  payor_tin: string
  payor_name: string
  payee_tin?: string | null
  payee_name?: string | null
  period_from: string
  period_to: string
  atc_code: string
  income_payment_base: string
  withholding_rate: string | null
  certified_amount: string
  allocated_amount?: string
  remaining_amount?: string
  status: TaxEvidenceStatus
  customer_notes?: string | null
  decision_notes?: string | null
  rejection_reason?: string | null
  reviewed_at?: string | null
  created_at?: string
  updated_at?: string
  customer?: TaxEvidenceCustomer | null
  reviewer?: { id: number; name?: string } | null
  private_file?: PrivateFileItem | null
}

export interface TaxExemption {
  id: number
  organization_id: number
  customer_id: number
  exemption_type: ExemptionType
  legal_basis: string
  ruling_or_cert_no: string
  covered_services?: string[] | null
  valid_from: string
  valid_to?: string | null
  private_file_id: number
  status: TaxEvidenceStatus
  customer_notes?: string | null
  decision_notes?: string | null
  rejection_reason?: string | null
  reviewed_at?: string | null
  created_at?: string
  updated_at?: string
  customer?: TaxEvidenceCustomer | null
  reviewer?: { id: number; name?: string } | null
  private_file?: PrivateFileItem | null
}

export interface TaxEvidencePage<T> {
  data: T[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export interface SubmitWithholdingPayload {
  customer_id: number
  certificate_no: string
  private_file_id: number
  payor_tin: string
  payor_name: string
  payee_tin?: string
  payee_name?: string
  period_from: string
  period_to: string
  atc_code: string
  income_payment_base: string | number
  withholding_rate?: string | number
  certified_amount: string | number
  customer_notes?: string
}

export interface SubmitExemptionPayload {
  customer_id: number
  exemption_type: ExemptionType
  legal_basis: string
  ruling_or_cert_no: string
  covered_services?: string[]
  valid_from: string
  valid_to?: string | null
  private_file_id: number
  customer_notes?: string
}

export interface ReviewTaxEvidencePayload {
  decision: TaxReviewDecision
  decision_notes?: string
  rejection_reason?: string
}

function idempotencyHeaders() {
  const key =
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
      ? crypto.randomUUID()
      : `tax-${Date.now()}-${Math.random().toString(36).slice(2)}`
  return { 'X-Idempotency-Key': key }
}

export function fetchCustomerWithholding(params?: { page?: number }) {
  return request.get<TaxEvidencePage<WithholdingCertificate>>({
    url: '/api/v1/customer/tax-evidence/withholding',
    params
  })
}

export function submitCustomerWithholding(data: SubmitWithholdingPayload) {
  return request.post<{ message: string; certificate: WithholdingCertificate }>({
    url: '/api/v1/customer/tax-evidence/withholding',
    data,
    headers: idempotencyHeaders()
  })
}

export function fetchCustomerExemptions(params?: { page?: number }) {
  return request.get<TaxEvidencePage<TaxExemption>>({
    url: '/api/v1/customer/tax-evidence/exemptions',
    params
  })
}

export function submitCustomerExemption(data: SubmitExemptionPayload) {
  return request.post<{ message: string; tax_exemption: TaxExemption }>({
    url: '/api/v1/customer/tax-evidence/exemptions',
    data,
    headers: idempotencyHeaders()
  })
}

export function fetchAdminWithholding(params?: {
  status?: TaxEvidenceStatus | ''
  customer_id?: number
  business_date?: string
  page?: number
}) {
  return request.get<TaxEvidencePage<WithholdingCertificate>>({
    url: '/api/v1/admin/tax-evidence/withholding',
    params
  })
}

export function reviewAdminWithholding(id: number, data: ReviewTaxEvidencePayload) {
  return request.post<{ message: string; certificate: WithholdingCertificate }>({
    url: `/api/v1/admin/tax-evidence/withholding/${id}/review`,
    data,
    headers: idempotencyHeaders()
  })
}

export function revokeAdminWithholding(id: number, reason: string) {
  return request.post<{ message: string; certificate: WithholdingCertificate }>({
    url: `/api/v1/admin/tax-evidence/withholding/${id}/revoke`,
    data: { reason },
    headers: idempotencyHeaders()
  })
}

export function fetchAdminExemptions(params?: {
  status?: TaxEvidenceStatus | ''
  customer_id?: number
  business_date?: string
  exemption_type?: ExemptionType
  page?: number
}) {
  return request.get<TaxEvidencePage<TaxExemption>>({
    url: '/api/v1/admin/tax-evidence/exemptions',
    params
  })
}

export function reviewAdminExemption(id: number, data: ReviewTaxEvidencePayload) {
  return request.post<{ message: string; tax_exemption: TaxExemption }>({
    url: `/api/v1/admin/tax-evidence/exemptions/${id}/review`,
    data,
    headers: idempotencyHeaders()
  })
}

export function revokeAdminExemption(id: number, reason: string) {
  return request.post<{ message: string; tax_exemption: TaxExemption }>({
    url: `/api/v1/admin/tax-evidence/exemptions/${id}/revoke`,
    data: { reason },
    headers: idempotencyHeaders()
  })
}
