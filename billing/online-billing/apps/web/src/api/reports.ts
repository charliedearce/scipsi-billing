import request from '@/utils/http'

export type BillingCollectionsDocumentType = 'INVOICE' | 'RECEIPT'

export type BillingCollectionsReceiptKind = 'OFFICIAL' | 'ACKNOWLEDGEMENT'

export interface BillingCollectionsRow {
  document_type: BillingCollectionsDocumentType
  document_id: number
  document_number: string
  receipt_kind?: BillingCollectionsReceiptKind | null
  counts_as_official_receipt?: boolean | null
  business_date: string
  status: string
  customer_id: number
  customer_account_number?: string | null
  customer_name?: string | null
  currency: string
  billed_amount: string
  gross_amount: string
  ppa_amount: string
  discount_amount: string
  tax_amount: string
  cash_received_amount: string
  withholding_received_amount: string
  applied_amount: string
  unapplied_amount: string
}

export interface BillingCollectionsCurrencyTotal {
  currency: string
  invoices: {
    count: number
    billed_amount: string
    gross_amount: string
    ppa_amount: string
    discount_amount: string
    tax_amount: string
  }
  receipts: {
    count: number
    cash_received_amount: string
    withholding_received_amount: string
    applied_amount: string
    unapplied_amount: string
  }
}

export interface BillingCollectionsReport {
  report: {
    code: 'BILLING_COLLECTIONS_REGISTER'
    title: string
    generated_at: string
    timezone: string
    filters: {
      date_from: string
      date_to: string
      location_id: number | null
      customer_id: number | null
      invoice_statuses: string[]
      receipt_statuses: string[]
    }
    scope_notice: string
    interpretation_notice: string
    row_limit: number
  }
  totals_by_currency: BillingCollectionsCurrencyTotal[]
  rows: BillingCollectionsRow[]
  pagination: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export interface BillingCollectionsReportParams {
  date_from?: string
  date_to?: string
  location_id?: number
  customer_id?: number
  invoice_statuses?: string[]
  receipt_statuses?: string[]
  page?: number
  per_page?: number
}

export function fetchBillingCollectionsReport(params: BillingCollectionsReportParams) {
  return request.get<BillingCollectionsReport>({
    url: '/api/v1/reports/billing-collections',
    params
  })
}

/** Authenticated CSV report export; callers must not expose a reusable URL. */
export function exportBillingCollectionsReport(params: BillingCollectionsReportParams) {
  return request.get<Blob>({
    url: '/api/v1/reports/billing-collections/export',
    params,
    responseType: 'blob',
    showErrorMessage: false
  })
}

export interface AdminDashboardMoney {
  count: number
  amount: string
}

export interface AdminDashboardDay {
  business_date: string
  bills: AdminDashboardMoney
  official_receipts: AdminDashboardMoney
  acknowledgements: AdminDashboardMoney
}

export interface AdminDashboardCurrency {
  currency: string
  bills_posted_today: AdminDashboardMoney
  official_receipts_posted_today: AdminDashboardMoney
  acknowledgements_posted_today: AdminDashboardMoney
  open_bills: AdminDashboardMoney
  last_7_days: AdminDashboardDay[]
}

export interface AdminDashboardTeller {
  user_id: number
  name: string
  bills_posted_today: AdminDashboardMoney
  official_receipts_posted_today: AdminDashboardMoney
  acknowledgements_posted_today: AdminDashboardMoney
  billing_requests_in_review: number
  payment_proofs_assigned: number
}

export interface AdminDashboard {
  business_date: string
  timezone: string
  notice: string
  currencies: AdminDashboardCurrency[]
  work_waiting: {
    billing_requests_queued: number
    payment_proofs_waiting: number
    document_corrections_pending: number
  }
  tellers: AdminDashboardTeller[]
}

export function fetchAdminDashboard() {
  return request.get<AdminDashboard>({
    url: '/api/v1/admin/dashboard'
  })
}
