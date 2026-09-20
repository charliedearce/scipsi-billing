import request from '@/utils/http'

export interface AppSettingItem {
  key: string
  value: any
  type: 'string' | 'integer' | 'boolean' | 'decimal' | 'json'
  scope: 'organization' | 'location'
  description: string
  lock_version: number
  updated_at?: string | null
}

export function fetchSettingList(locationId?: number) {
  return request.get<AppSettingItem[]>({
    url: '/api/v1/settings',
    params: locationId ? { location_id: locationId } : {}
  })
}

export function fetchSettingDetail(key: string, locationId?: number) {
  return request.get<AppSettingItem>({
    url: `/api/v1/settings/${key}`,
    params: locationId ? { location_id: locationId } : {}
  })
}

export function fetchUpdateSetting(key: string, data: {
  value: any
  location_id?: number
  lock_version?: number
}) {
  return request.put<AppSettingItem>({
    url: `/api/v1/settings/${key}`,
    data
  })
}
