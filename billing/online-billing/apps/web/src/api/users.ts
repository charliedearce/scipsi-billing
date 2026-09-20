import request from '@/utils/http'

export interface UserItem {
  id: number
  organization_id: number
  name: string
  email: string
  phone?: string | null
  status: 'active' | 'suspended' | 'pending_activation'
  lock_version: number
  roles: Array<{ id: number; name: string; label: string } | string>
  locations?: Array<{ id: number; name: string; code: string }>
  created_at?: string
  updated_at?: string
}

export interface UserListParams {
  page?: number
  per_page?: number
  search?: string
  role?: string
  status?: string
}

export interface PaginatedResponse<T> {
  current_page: number
  data: T[]
  total: number
  per_page: number
  last_page: number
}

export function fetchUserList(params?: UserListParams) {
  return request.get<PaginatedResponse<UserItem>>({
    url: '/api/v1/users',
    params
  })
}

export function fetchUserDetail(id: number) {
  return request.get<UserItem>({
    url: `/api/v1/users/${id}`
  })
}

export function fetchCreateUser(data: {
  name: string
  email: string
  password: string
  phone?: string
  status?: string
  role_ids?: number[]
  location_ids?: number[]
}) {
  return request.post<UserItem>({
    url: '/api/v1/users',
    data
  })
}

export function fetchUpdateUser(id: number, data: {
  name?: string
  email?: string
  password?: string
  phone?: string
  status?: string
  role_ids?: number[]
  location_ids?: number[]
  lock_version?: number
}) {
  return request.put<UserItem>({
    url: `/api/v1/users/${id}`,
    data
  })
}

export function fetchSuspendUser(id: number, lock_version?: number) {
  return request.post<{ message: string; user: UserItem }>({
    url: `/api/v1/users/${id}/suspend`,
    data: { lock_version }
  })
}

export function fetchActivateUser(id: number, lock_version?: number) {
  return request.post<{ message: string; user: UserItem }>({
    url: `/api/v1/users/${id}/activate`,
    data: { lock_version }
  })
}
