import request from '@/utils/http'

export interface PpaClearancePolicy {
  id: number
  version_number: number
  accept_qualifying_vip_credit: boolean
  status: 'DRAFT' | 'PUBLISHED'
  effective_from: string
  effective_to?: string | null
  lock_version: number
}

export interface PpaBillSettlement {
  invoice_number: string
  invoice_business_date?: string | null
  customer: { account_number?: string | null; name?: string | null }
  currency: string
  invoice_total_amount: string
  applied_amount: string
  outstanding_amount: string
  confirmed_receipt_count: number
  receipt_history_truncated: boolean
  receipts: Array<{
    receipt_number: string | null
    business_date: string | null
    applied_amount: string
  }>
  settlement_status: 'PAID' | 'PARTIALLY_PAID' | 'UNPAID'
  credit_status: 'ON_CREDIT' | 'CREDIT_SETTLED' | 'NOT_ON_CREDIT'
  credit_due_date?: string | null
  clearance_eligibility: 'FULLY_PAID' | 'ON_CREDIT_ACCEPTED' | 'NOT_ELIGIBLE'
  policy?: { version: number; accept_qualifying_vip_credit: boolean } | null
  formal_clearance_issued: false
  checked_at: string
  notice: string
}

export function verifyPpaBill(invoiceNumber: string) {
  return request.get<PpaBillSettlement>({
    url: `/api/v1/ppa/bills/${encodeURIComponent(invoiceNumber)}/settlement`
  })
}

export function fetchPpaClearancePolicies() {
  return request.get<PpaClearancePolicy[]>({ url: '/api/v1/admin/ppa-clearance-policies' })
}

export function createPpaClearancePolicy(data: {
  accept_qualifying_vip_credit: boolean
  effective_from: string
  effective_to?: string | null
}) {
  return request.post<PpaClearancePolicy>({ url: '/api/v1/admin/ppa-clearance-policies', data })
}

export function publishPpaClearancePolicy(id: number, expectedLockVersion: number, reason: string) {
  return request.post<PpaClearancePolicy>({
    url: `/api/v1/admin/ppa-clearance-policies/${id}/publish`,
    data: { expected_lock_version: expectedLockVersion, reason }
  })
}
