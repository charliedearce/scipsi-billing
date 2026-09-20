import request from '@/utils/http'

export type AgingBucket = 'CURRENT' | '1_30' | '31_60' | '61_90' | '91_PLUS' | 'UNCLASSIFIED'

export interface CreditAgingItem {
  credit_charge_id: number
  invoice_id: number
  invoice_number: string
  charged_amount: string
  applied_as_of_amount: string
  outstanding_as_of_amount: string
  charged_business_date?: string | null
  charged_recorded_at?: string | null
  due_date?: string | null
  due_date_classification: 'CLASSIFIED' | 'UNCLASSIFIED_NEEDS_TERMS_REVIEW'
  days_past_due: number
  bucket: AgingBucket
  payment_terms_days: number
  due_date_basis: 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE'
}

export interface VipCreditAging {
  as_of_date: string
  timezone: string
  cutoff_semantics: string
  reason?: string
  account?: {
    id: number
    customer_id: number
    customer_name: string
    account_number: string
  }
  currencies: Array<{
    currency: string
    buckets: Record<AgingBucket, string>
    outstanding_amount: string
    items: CreditAgingItem[]
  }>
}

export function fetchPortalCreditAging(customerId: number, asOf?: string) {
  return request.get<VipCreditAging>({
    url: '/api/v1/portal/credit-aging',
    params: { customer_id: customerId, as_of: asOf }
  })
}

export function fetchStaffCreditAging(accountId: number, asOf?: string) {
  return request.get<VipCreditAging>({
    url: `/api/v1/credit-accounts/${accountId}/aging`,
    params: { as_of: asOf }
  })
}

export function fetchStaffCreditAgingIndex(asOf?: string) {
  return request.get<VipCreditAging[]>({
    url: '/api/v1/credit-accounts/aging',
    params: { as_of: asOf }
  })
}

/** Administrator-only, organization-scoped principal-aging CSV. Late charges are excluded. */
export function exportVipPrincipalAging(asOf?: string) {
  return request.get<Blob>({
    url: '/api/v1/reports/vip-credit-aging/export',
    params: { as_of: asOf },
    responseType: 'blob',
    showErrorMessage: false
  })
}
