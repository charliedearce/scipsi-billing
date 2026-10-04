import request from '@/utils/http'

export interface VesselOption {
  id: number
  name: string
  vessel_type?: string | null
  typical_route?: string | null
  shipping_line?: string | null
}

export function fetchVessels(q = '') {
  return request.get<VesselOption[]>({
    url: '/api/v1/vessels',
    params: q.trim() ? { q: q.trim() } : undefined
  })
}
