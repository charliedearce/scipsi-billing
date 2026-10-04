import request from '@/utils/http'

export type BillClaimStatus =
  | 'PENDING_VERIFICATION'
  | 'PENDING_TELLER_REVIEW'
  | 'PENDING_CUSTOMER_ACCEPTANCE'
  | 'APPROVED'
  | 'REJECTED'
  | 'EXPIRED'
  | 'CANCELLED'

export interface BillClaimPreview {
  claim_id: number
  claim_status: BillClaimStatus
  can_accept: boolean
  invoice: {
    id: number
    invoice_number?: string | null
    business_date?: string | null
    currency?: string | null
    gross_amount?: string
    tax_amount?: string
    total_charge_amount?: string
    buyer_name?: string | null
    buyer_tin?: string | null
    buyer_address?: string | null
    lines: Array<{
      description: string
      quantity: string
      unit_rate: string
      line_total: string
    }>
  }
  creating_teller?: { id: number; name?: string } | null
}

export type BillClaimRoute = 'CLAIM_CODE' | 'TELLER_REVIEW'

export interface BillClaimEvent {
  id: number
  event_type: string
  notes?: string | null
  created_at: string
  actor?: { id: number; name: string } | null
}

export interface BillClaimItem {
  id: number
  organization_id: number
  user_id: number
  customer_id: number
  invoice_id?: number | null
  invoice_number?: string | null
  claim_status: BillClaimStatus
  verification_route: BillClaimRoute
  attempt_count?: number
  max_attempts?: number
  code_expires_at?: string | null
  resolved_at?: string | null
  rejection_reason?: string | null
  staff_notes?: string | null
  created_at?: string
  updated_at?: string
  events?: BillClaimEvent[]
  user?: { id: number; name?: string; email?: string } | null
  customer?: { id: number; name?: string; account_number?: string } | null
  invoice?: {
    id: number
    invoice_number?: string | null
    status?: string
    total_charge_amount?: string
    walk_in_customer?: { id: number; buyer_name?: string } | null
  } | null
}

export interface BillClaimListResponse {
  data: BillClaimItem[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export function fetchPortalBillClaims(params?: { page?: number }) {
  return request.get<BillClaimListResponse>({
    url: '/api/v1/portal/bill-claims',
    params
  })
}

export function createPortalBillClaim(invoiceNumber: string) {
  return request.post<{ message: string; claim: BillClaimItem }>({
    url: '/api/v1/portal/bill-claims',
    data: { invoice_number: invoiceNumber }
  })
}

export function verifyPortalBillClaim(id: number, code: string) {
  return request.post<{ message: string; claim: BillClaimItem }>({
    url: `/api/v1/portal/bill-claims/${id}/verify`,
    data: { code }
  })
}

export function previewPortalBillClaim(id: number) {
  return request.get<BillClaimPreview>({
    url: `/api/v1/portal/bill-claims/${id}/preview`
  })
}

export function acceptPortalBillClaim(id: number) {
  return request.post<{ message: string; claim: BillClaimItem }>({
    url: `/api/v1/portal/bill-claims/${id}/accept`
  })
}

export function declinePortalBillClaim(id: number, reason?: string) {
  return request.post<{ message: string; claim: BillClaimItem }>({
    url: `/api/v1/portal/bill-claims/${id}/decline`,
    data: reason ? { reason } : {}
  })
}

export function cancelPortalBillClaim(id: number) {
  return request.post<{ message: string }>({
    url: `/api/v1/portal/bill-claims/${id}/cancel`
  })
}

export function fetchTellerBillClaims(params?: {
  page?: number
  status?:
    | 'PENDING_TELLER_REVIEW'
    | 'PENDING_CUSTOMER_ACCEPTANCE'
    | 'APPROVED'
    | 'REJECTED'
    | 'ALL'
    | string
}) {
  return request.get<BillClaimListResponse>({
    url: '/api/v1/teller/bill-claims',
    params
  })
}

export function fetchTellerBillClaim(id: number) {
  return request.get<BillClaimItem>({
    url: `/api/v1/teller/bill-claims/${id}`
  })
}

export function decideTellerBillClaim(
  id: number,
  data: { decision: 'APPROVE' | 'REJECT'; notes?: string }
) {
  return request.post<{ message: string; claim: BillClaimItem }>({
    url: `/api/v1/teller/bill-claims/${id}/decide`,
    data
  })
}
