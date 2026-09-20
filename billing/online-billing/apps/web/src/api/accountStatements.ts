import request from '@/utils/http'

export interface AccountStatementItem {
  id: number
  invoice_id: number
  invoice_number: string
  business_date: string
  invoice_amount: string
  payment_amount: string
  outstanding_amount: string
}

export interface AccountStatement {
  id: number
  statement_number: string
  as_of_date: string
  currency: string
  invoice_total: string
  payment_total: string
  outstanding_total: string
  status: 'GENERATED' | 'VOID'
  customer?: { id: number; account_number: string; name: string }
  customer_snapshot?: { account_number: string; name: string }
  generated_at: string
  items?: AccountStatementItem[]
}

export interface AccountStatementPage {
  data: AccountStatement[]
  total: number
}

export function fetchAccountStatements(params?: { customer_id?: number; as_of_date?: string }) {
  return request.get<AccountStatementPage>({ url: '/api/v1/account-statements', params })
}

export function fetchAccountStatement(id: number) {
  return request.get<AccountStatement>({ url: `/api/v1/account-statements/${id}` })
}

export function generateAccountStatement(data: { customer_id: number; as_of_date: string }) {
  return request.post<AccountStatement>({ url: '/api/v1/account-statements', data })
}

/** Authenticated download of the canonical PDF rendered from the frozen statement snapshot. */
export function downloadAccountStatementArtifact(id: number) {
  return request.get<Blob>({
    url: `/api/v1/account-statements/${id}/artifact/download`,
    responseType: 'blob',
    showErrorMessage: false
  })
}
