import request from '@/utils/http'

export type AccountingPeriodStatus = 'OPEN' | 'CLOSED'
export type BackdateStatus = 'PENDING' | 'APPROVED' | 'REJECTED' | 'CONSUMED' | 'EXPIRED'
export type BackdateDocumentType = 'INVOICE' | 'RECEIPT'

export interface AccountingPeriod {
  id: number
  period_code: string
  starts_on: string
  ends_on: string
  status: AccountingPeriodStatus
  closed_at?: string | null
  notes?: string | null
}

export interface BackdateAuthorization {
  id: number
  document_type: BackdateDocumentType
  business_date: string
  status: BackdateStatus
  reason: string
  decision_notes?: string | null
  expires_at?: string | null
  consumed_at?: string | null
  period?: Pick<AccountingPeriod, 'id' | 'period_code' | 'status'> | null
  requested_by?: { id: number; name: string } | null
  reviewed_by?: { id: number; name: string } | null
}

export interface BackdateAuthorizationPage {
  data: BackdateAuthorization[]
  total: number
}

export function fetchAccountingPeriods(status?: AccountingPeriodStatus) {
  return request.get<AccountingPeriod[]>({
    url: '/api/v1/admin/accounting-periods',
    params: status ? { status } : undefined
  })
}

export function createAccountingPeriod(
  data: Pick<AccountingPeriod, 'period_code' | 'starts_on' | 'ends_on'> & { notes?: string }
) {
  return request.post<AccountingPeriod>({ url: '/api/v1/admin/accounting-periods', data })
}

export function closeAccountingPeriod(id: number, reason: string) {
  return request.post<AccountingPeriod>({
    url: `/api/v1/admin/accounting-periods/${id}/close`,
    data: { reason }
  })
}

export function fetchBackdateAuthorizations(status?: BackdateStatus) {
  return request.get<BackdateAuthorizationPage>({
    url: '/api/v1/backdate-authorizations',
    params: status ? { status } : undefined
  })
}

export function requestBackdateAuthorization(data: {
  document_type: BackdateDocumentType
  business_date: string
  reason: string
}) {
  return request.post<BackdateAuthorization>({ url: '/api/v1/backdate-authorizations', data })
}

export function approveBackdateAuthorization(
  id: number,
  data: { decision_notes: string; expires_at: string }
) {
  return request.post<BackdateAuthorization>({
    url: `/api/v1/backdate-authorizations/${id}/approve`,
    data
  })
}

export function rejectBackdateAuthorization(id: number, decisionNotes: string) {
  return request.post<BackdateAuthorization>({
    url: `/api/v1/backdate-authorizations/${id}/reject`,
    data: { decision_notes: decisionNotes }
  })
}
