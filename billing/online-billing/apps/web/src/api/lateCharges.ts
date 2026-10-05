import request from '@/utils/http'

export interface LateChargeBand {
  id?: number
  days_from: number
  days_to?: number | null
  label: string
  fixed_amount_override?: string | null
  percentage_rate_override?: string | null
}

export interface LateChargePolicyVersion {
  id: number
  version_number: number
  currency: string
  enabled: boolean
  grace_days: number
  basis: 'FIXED' | 'PERCENTAGE'
  fixed_amount?: string | null
  percentage_rate?: string | null
  cadence: 'ONCE' | 'MONTHLY'
  minimum_amount?: string | null
  cap_amount?: string | null
  rounding_mode: string
  contract_reference?: string | null
  customer_notice?: string | null
  status: 'DRAFT' | 'PUBLISHED'
  effective_from: string
  effective_to?: string | null
  publication_reason?: string | null
  lock_version: number
  bands?: LateChargeBand[]
}

export interface LateChargeAssessment {
  id: number
  status: string
  assessed_amount: string
  principal_outstanding: string
  days_past_due: number
  as_of_date: string
  fiscal_mapping_status: string
  hold_reason?: string | null
  lock_version: number
  customer?: { id: number; name: string; account_number?: string }
  invoice?: { id: number; invoice_number: string }
  band?: { id: number; label: string }
}

export function fetchLateChargePolicies() {
  return request.get<LateChargePolicyVersion[]>({
    url: '/api/v1/admin/late-charge-policies'
  })
}

export function createLateChargePolicy(data: Record<string, unknown>) {
  return request.post<LateChargePolicyVersion>({
    url: '/api/v1/admin/late-charge-policies',
    data
  })
}

export function publishLateChargePolicy(id: number, expectedLockVersion: number, reason: string) {
  return request.post<LateChargePolicyVersion>({
    url: `/api/v1/admin/late-charge-policies/${id}/publish`,
    data: { expected_lock_version: expectedLockVersion, reason }
  })
}

export function deleteLateChargePolicyDraft(id: number, expectedLockVersion: number) {
  return request.del<{ message?: string }>({
    url: `/api/v1/admin/late-charge-policies/${id}`,
    params: { expected_lock_version: expectedLockVersion }
  })
}

export function fetchLateChargeAssessments(params?: { status?: string; customer_id?: number }) {
  return request.get({
    url: '/api/v1/admin/late-charge-assessments',
    params
  })
}

export function runLateChargeAssessments(asOf?: string) {
  return request.post<{
    as_of: string
    created: number
    reused: number
    held: number
    skipped: number
    assessment_ids: number[]
  }>({
    url: '/api/v1/admin/late-charge-assessments/run',
    data: asOf ? { as_of: asOf } : {}
  })
}

export function waiveLateChargeAssessment(id: number, expectedLockVersion: number, reason: string) {
  return request.post<LateChargeAssessment>({
    url: `/api/v1/admin/late-charge-assessments/${id}/waive`,
    data: { expected_lock_version: expectedLockVersion, reason }
  })
}
