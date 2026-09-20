import request from '@/utils/http'

export interface RegisterCustomerParams {
  full_name: string
  company_name: string
  email: string
  mobile: string
  password: string
  password_confirmation: string
}

export interface RegisterResponseData {
  registration_status: string
  message: string
  challenge_id: number | null
  user_id: number | null
  mobile_masked: string
}

export interface VerifyOtpParams {
  challenge_id: number
  code: string
}

export interface VerifyOtpResponseData {
  success: boolean
  message: string
  token?: string
  user?: {
    id: number
    name: string
    email: string
    status: string
  }
}

export function registerCustomer(data: RegisterCustomerParams) {
  return request.post<{ success: boolean; data: RegisterResponseData }>({
    url: '/api/v1/auth/register',
    data
  })
}

export function resendRegistrationOtp(challengeId: number) {
  return request.post<{ success: boolean; data: { challenge_id: number; expires_at: string; message: string } }>({
    url: '/api/v1/auth/contact-verifications/mobile/send',
    data: { challenge_id: challengeId }
  })
}

export function verifyMobileOtp(params: VerifyOtpParams) {
  return request.post<{ success: boolean; data: VerifyOtpResponseData }>({
    url: '/api/v1/auth/contact-verifications/mobile/verify',
    data: params
  })
}

export function fetchPortalProfile() {
  return request.get<{
    user: any
    customer_links: any[]
    contact_points: any[]
  }>({
    url: '/api/v1/portal/profile'
  })
}

export function updatePortalProfile(data: {
  name?: string
  customer_id?: number
  registered_name?: string
  tax_identification_number?: string
  branch_code?: string
  registered_address?: string
}) {
  return request.put<{ success: boolean; message: string; user: any }>({
    url: '/api/v1/portal/profile',
    data
  })
}

export function fetchAdminCustomers(params?: {
  page?: number
  per_page?: number
  search?: string
  status?: string
  customer_type?: string
}) {
  return request.get<any>({
    url: '/api/v1/admin/customers',
    params
  })
}

export function fetchAdminCustomerDetail(id: number) {
  return request.get<any>({
    url: `/api/v1/admin/customers/${id}`
  })
}

export function updateAdminCustomerStatus(id: number, status: string, notes?: string) {
  return request.put<{ success: boolean; message: string; customer: any }>({
    url: `/api/v1/admin/customers/${id}/status`,
    data: { status, notes }
  })
}

export function fetchAdminBuyerProfiles(customerId: number) {
  return request.get<{ buyer_profile: any; versions: any[] }>({
    url: `/api/v1/admin/customers/${customerId}/buyer-profiles`
  })
}

export function reviewAdminBuyerProfile(
  customerId: number,
  versionId: number,
  action: 'approve' | 'reject',
  reviewNotes?: string
) {
  return request.post<{ success: boolean; message: string; version: any }>({
    url: `/api/v1/admin/customers/${customerId}/buyer-profiles/${versionId}/review`,
    data: { action, review_notes: reviewNotes }
  })
}
