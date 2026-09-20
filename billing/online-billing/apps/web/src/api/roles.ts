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
