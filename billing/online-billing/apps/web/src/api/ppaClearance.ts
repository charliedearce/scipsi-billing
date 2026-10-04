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

export interface PpaReceiptSummary {
  receipt_number: string | null
  business_date: string | null
  applied_amount: string
  receipt_status: 'POSTED' | 'REVERSED'
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
  receipts: PpaReceiptSummary[]
  reversed_receipt_count: number
  reversed_receipt_history_truncated: boolean
  reversed_receipts: PpaReceiptSummary[]
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

export interface PpaDocumentMatch {
  document_kind: 'INVOICE' | 'OFFICIAL_RECEIPT' | 'ACKNOWLEDGEMENT_RECEIPT'
  invoice_number?: string | null
  receipt_number?: string | null
  receipt_status?: 'POSTED' | 'REVERSED' | null
  linked_invoices: Array<{
    invoice_number: string
    applied_amount: string
    currency: string
  }>
}

export function resolvePpaDocument(number: string) {
  return request.get<PpaDocumentMatch>({
    url: `/api/v1/ppa/documents/${encodeURIComponent(number)}`
  })
}

export function downloadPpaBillLayout(invoiceNumber: string) {
  return request.get<Blob>({
    url: `/api/v1/ppa/bills/${encodeURIComponent(invoiceNumber)}/layout`,
    responseType: 'blob',
    showErrorMessage: false
  })
}

export function downloadPpaReceiptLayout(receiptNumber: string) {
  return request.get<Blob>({
    url: `/api/v1/ppa/receipts/${encodeURIComponent(receiptNumber)}/layout`,
    responseType: 'blob',
    showErrorMessage: false
  })
}

export interface PpaShareReceipt {
  receipt_number: string | null
  business_date: string | null
  applied_amount: string
}

export interface PpaShareRow {
  invoice_number: string
  business_date: string | null
  customer_name?: string | null
  customer_account_number?: string | null
  currency: string
  invoice_total: string
  ppa_share: string
  applied_amount: string
  receipts: PpaShareReceipt[]
}

export interface PpaShareReport {
  report: {
    title: string
    notice: string
    filters: { date_from: string; date_to: string }
  }
  totals_by_currency: Array<{
    currency: string
    paid_bill_count: number
    invoice_total: string
    ppa_share: string
  }>
  rows: PpaShareRow[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export function fetchPpaShareReport(params: { date_from: string; date_to: string; page?: number }) {
  return request.get<PpaShareReport>({ url: '/api/v1/ppa/share-report', params })
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
