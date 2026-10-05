import request from '@/utils/http'

export interface PermissionItem {
  id: number
  name: string
  category: string
  description?: string | null
}

export interface RoleItem {
  id: number
  organization_id?: number | null
  name: string
  label: string
  is_system: boolean
  lock_version: number
  permissions: PermissionItem[]
}

export function fetchRoleList() {
  return request.get<RoleItem[]>({
    url: '/api/v1/roles'
  })
}

export function fetchPermissionList() {
  return request.get<Record<string, PermissionItem[]>>({
    url: '/api/v1/permissions'
  })
}

export interface RolePayload {
  name: string
  label: string
  permission_ids: number[]
  lock_version?: number
}

export function createRole(data: RolePayload) {
  return request.post<RoleItem>({ url: '/api/v1/roles', data })
}

export function updateRole(id: number, data: RolePayload) {
  return request.put<RoleItem>({ url: `/api/v1/roles/${id}`, data })
}

export function deleteRole(id: number, lockVersion: number) {
  return request.del<{ message: string }>({
    url: `/api/v1/roles/${id}`,
    data: { lock_version: lockVersion }
  })
}
