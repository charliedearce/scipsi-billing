import request from '@/utils/http'

export type PricingStatus = 'draft' | 'published' | 'effective' | 'retired'
export type TaxTreatment = 'VATABLE' | 'EXEMPT' | 'ZERO_RATED' | 'NON_VAT'
export type Applicability = 'APPLICABLE' | 'NOT_APPLICABLE'

export interface TariffVersion {
  id: number
  tariff_id: number
  version_number: number
  rate: string
  tax_treatment_key: TaxTreatment
  ppa_share_applicability: Applicability
  ppa_share_rate: string
  fuel_surcharge_applicability: Applicability
  effective_from: string
  effective_to?: string | null
  status: PricingStatus
  created_at?: string
}

export interface Tariff {
  id: number
  organization_id: number
  tariff_code: string
  name: string
  service_type: 'ARRASTRE' | 'STEVEDORING' | 'OTHER'
  route_type: 'DOMESTIC' | 'FOREIGN'
  unit_of_measure: string
  legacy_t_scode?: string | null
  legacy_t_sname?: string | null
  cargo_class?: string | null
  is_active: boolean
  versions: TariffVersion[]
}

export interface FuelPriceObservation {
  id: number
  organization_id: number
  product_grade: string
  price: string
  currency: string
  unit_of_measure: string
  observed_at: string
  effective_at: string
  status: 'active' | 'retired'
  notes?: string | null
  source_reference?: string | null
  source_evidence_ref?: string | null
  entered_by?: { id: number; name: string } | null
}

export interface FuelSurchargeBand {
  id?: number
  policy_version_id?: number
  min_price: string
  max_price?: string | null
  surcharge_percent: string
  label: string
}

export interface FuelSurchargePolicy {
  id: number
  organization_id: number
  version_number: number
  basis: string
  effective_from: string
  effective_to?: string | null
  status: PricingStatus
  bands: FuelSurchargeBand[]
}

export interface TariffCreatePayload {
  tariff_code: string
  name: string
  service_type: Tariff['service_type']
  route_type: Tariff['route_type']
  unit_of_measure: string
}

export interface TariffVersionPayload {
  rate: string
  tax_treatment_key: TaxTreatment
  ppa_share_applicability: Applicability
  ppa_share_rate: string
  fuel_surcharge_applicability: Applicability
  effective_from: string
  effective_to?: string | null
  status: Exclude<PricingStatus, 'retired'>
}

export interface FuelObservationPayload {
  product_grade: string
  price: string
  currency: string
  unit_of_measure: string
  observed_at: string
  effective_at: string
  notes?: string
  source_reference?: string
  source_evidence_ref?: string
}

export interface FuelPolicyPayload {
  basis: string
  effective_from: string
  effective_to?: string | null
  status: Exclude<PricingStatus, 'retired'>
  bands: FuelSurchargeBand[]
}

export function fetchAdminTariffs() {
  return request.get<Tariff[]>({
    url: '/api/v1/admin/tariffs'
  })
}

export function createTariff(data: TariffCreatePayload) {
  return request.post<Tariff>({
    url: '/api/v1/admin/tariffs',
    data
  })
}

export function updateTariff(id: number, data: { is_active: boolean; reason: string }) {
  return request.put<Tariff>({
    url: `/api/v1/admin/tariffs/${id}`,
    data
  })
}

export function createTariffVersion(tariffId: number, data: TariffVersionPayload) {
  return request.post<TariffVersion>({
    url: `/api/v1/admin/tariffs/${tariffId}/versions`,
    data
  })
}

export function publishTariffVersion(tariffId: number, versionId: number) {
  return request.post<TariffVersion>({
    url: `/api/v1/admin/tariffs/${tariffId}/versions/${versionId}/publish`
  })
}

export function fetchFuelObservations(params?: { status?: string; product_grade?: string }) {
  return request.get<FuelPriceObservation[]>({
    url: '/api/v1/admin/fuel/observations',
    params
  })
}

export function createFuelObservation(data: FuelObservationPayload) {
  return request.post<FuelPriceObservation>({
    url: '/api/v1/admin/fuel/observations',
    data
  })
}

export function retireFuelObservation(id: number) {
  return request.post<FuelPriceObservation>({
    url: `/api/v1/admin/fuel/observations/${id}/retire`
  })
}

export function fetchFuelPolicies() {
  return request.get<FuelSurchargePolicy[]>({
    url: '/api/v1/admin/fuel/policies'
  })
}

export function createFuelPolicy(data: FuelPolicyPayload) {
  return request.post<FuelSurchargePolicy>({
    url: '/api/v1/admin/fuel/policies',
    data
  })
}

export function publishFuelPolicy(id: number) {
  return request.post<FuelSurchargePolicy>({
    url: `/api/v1/admin/fuel/policies/${id}/publish`
  })
}
