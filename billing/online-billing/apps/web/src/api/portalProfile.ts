import request from '@/utils/http'

export interface PortalAvatar {
  private_file_id: number
  download_path?: string
  mime_type?: string | null
  original_name?: string | null
  status?: string
}

export interface BuyerVersionSummary {
  id?: number
  version: number
  registered_name: string
  trade_name?: string | null
  tin?: string | null
  tax_identification_number?: string | null
  branch_code?: string | null
  tax_classification?: string | null
  billing_address?: Record<string, any> | string | null
  registered_address?: string | null
  status: string
  effective_from?: string | null
}

export interface PortalCustomerLink {
  id: number
  customer_id: number
  account_number?: string
  name?: string
  customer_type?: string
  authority_role?: string
  is_active: boolean
  buyer_profile?: {
    id: number
    current_version: number
    active_version?: BuyerVersionSummary | null
    pending_version?: BuyerVersionSummary | null
  } | null
}

export interface PortalContactPoint {
  id: number
  type: string
  value: string
  is_verified: boolean
  verified_at?: string | null
  status?: string
}

export interface PortalProfileResponse {
  user: {
    id: number
    name: string
    email: string
    phone?: string | null
    status?: string
    avatar?: PortalAvatar | null
  }
  customer_links: PortalCustomerLink[]
  contact_points: PortalContactPoint[]
}

function idempotencyHeaders() {
  const key =
    typeof crypto !== 'undefined' && 'randomUUID' in crypto
      ? crypto.randomUUID()
      : `portal-${Date.now()}-${Math.random().toString(36).slice(2)}`
  return { 'X-Idempotency-Key': key }
}

export function fetchPortalProfile() {
  return request.get<PortalProfileResponse>({
    url: '/api/v1/portal/profile'
  })
}

export function updatePortalProfile(data: {
  name?: string
  customer_id?: number
  registered_name?: string
  tin?: string
  tax_identification_number?: string
  branch_code?: string
  registered_address?: string
  billing_address?: Record<string, any> | string
}) {
  return request.put<{
    success: boolean
    message: string
    user: any
    buyer_version?: BuyerVersionSummary | null
  }>({
    url: '/api/v1/portal/profile',
    data,
    headers: idempotencyHeaders()
  })
}

export function changePortalPassword(data: {
  current_password: string
  password: string
  password_confirmation: string
}) {
  return request.post<{ success: boolean; message: string }>({
    url: '/api/v1/portal/profile/password',
    data,
    headers: idempotencyHeaders()
  })
}

export function uploadPortalAvatar(file: File) {
  const form = new FormData()
  form.append('file', file)
  return request.post<{
    success: boolean
    message: string
    avatar: PortalAvatar
  }>({
    url: '/api/v1/portal/profile/avatar',
    data: form,
    headers: {
      ...idempotencyHeaders(),
      'Content-Type': 'multipart/form-data'
    }
  })
}
