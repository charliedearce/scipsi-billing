import request from '@/utils/http'
import type { InvoiceDraft, InvoiceShipmentInput } from '@/api/invoices'

export interface WalkInCustomerItem {
  id: number
  organization_id: number
  location_id: number
  shell_customer_id?: number | null
  customer_id?: number | null
  buyer_name: string
  buyer_tin?: string | null
  buyer_branch_code?: string | null
  buyer_address?: string | null
  contact_mobile?: string | null
  contact_email?: string | null
  created_by_user_id?: number | null
  created_at?: string
  updated_at?: string
  location?: { id: number; name?: string; code?: string } | null
  creator?: { id: number; name?: string } | null
  portal_customer?: { id: number; name?: string; account_number?: string } | null
  shell_customer?: { id: number; name?: string; account_number?: string } | null
  invoices?: Array<{
    id: number
    invoice_number?: string | null
    status: string
    total_charge_amount?: string
    business_date?: string
  }>
}

export interface WalkInCustomerListResponse {
  data: WalkInCustomerItem[]
  current_page: number
  last_page: number
  per_page: number
  total: number
}

export function fetchWalkInCustomers(params?: {
  location_id?: number
  linked?: boolean
  page?: number
}) {
  return request.get<WalkInCustomerListResponse>({
    url: '/api/v1/teller/walk-in/customers',
    params
  })
}

export function fetchWalkInCustomer(id: number) {
  return request.get<WalkInCustomerItem>({
    url: `/api/v1/teller/walk-in/customers/${id}`
  })
}

export function createWalkInCustomer(data: {
  location_id: number
  buyer_name: string
  buyer_tin?: string
  buyer_branch_code?: string
  buyer_address?: string
  contact_mobile?: string
  contact_email?: string
}) {
  return request.post<WalkInCustomerItem>({
    url: '/api/v1/teller/walk-in/customers',
    data
  })
}

export function updateWalkInCustomer(
  id: number,
  data: {
    buyer_name?: string
    buyer_tin?: string | null
    buyer_branch_code?: string | null
    buyer_address?: string | null
    contact_mobile?: string | null
    contact_email?: string | null
  }
) {
  return request.put<WalkInCustomerItem>({
    url: `/api/v1/teller/walk-in/customers/${id}`,
    data
  })
}

export function createWalkInInvoiceDraft(
  id: number,
  data: InvoiceShipmentInput & { business_date: string }
) {
  return request.post<InvoiceDraft & { walk_in_customer?: WalkInCustomerItem }>({
    url: `/api/v1/teller/walk-in/customers/${id}/invoice-draft`,
    data
  })
}

export function linkWalkInToPortalCustomer(
  id: number,
  data: { customer_id: number; reason?: string }
) {
  return request.post<{ message: string }>({
    url: `/api/v1/teller/walk-in/customers/${id}/link`,
    data
  })
}
