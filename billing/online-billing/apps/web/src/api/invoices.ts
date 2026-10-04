import request from '@/utils/http'
import type { Tariff } from '@/api/pricing'

export interface InvoiceDraftItemInput {
  tariff_version_id?: number | null
  tariff_code?: string | null
  tariff_id?: number | null
  service_type?: 'ARRASTRE' | 'STEVEDORING' | 'OTHER' | null
  description?: string | null
  quantity: string | number
  unit_rate?: string | number | null
  discount_amount?: string | number | null
}

export type BillingServiceType = 'ARRASTRE' | 'STEVEDORING' | 'OTHER'

export type BillingDraftLine = {
  /** Stable UI identity for ElTable row-key / ElSelect keys (not sent to API). */
  client_key: string
  tariff_version_id: number | null
  tariff_code: string
  service_type: BillingServiceType | ''
  quantity: string
  unit_rate: string
  discount_amount: string
  description: string
}

let billingLineKeySeq = 0

export function newBillingLineKey(): string {
  billingLineKeySeq += 1
  return `bl-${Date.now().toString(36)}-${billingLineKeySeq}`
}

export function emptyBillingLine(): BillingDraftLine {
  return {
    client_key: newBillingLineKey(),
    tariff_version_id: null,
    tariff_code: '',
    service_type: '',
    quantity: '1',
    unit_rate: '',
    discount_amount: '0.00',
    description: ''
  }
}

export function billingLineFromDraftItem(item: InvoiceDraftItem): BillingDraftLine {
  const tariff = item.tariff_version?.tariff
  const service = tariff?.service_type
  return {
    client_key: newBillingLineKey(),
    tariff_version_id: item.tariff_version_id || null,
    tariff_code: tariff?.tariff_code || '',
    service_type:
      service === 'ARRASTRE' || service === 'STEVEDORING' || service === 'OTHER' ? service : '',
    quantity: item.quantity,
    unit_rate: item.unit_rate || '',
    discount_amount: item.discount_amount || '0.00',
    description: ''
  }
}

export interface InvoiceDraftItem {
  id?: number
  line_number?: number
  tariff_version_id?: number | null
  description?: string | null
  quantity: string
  unit_rate: string
  discount_amount?: string
  base_gross_amount?: string
  fuel_surcharge_amount?: string
  gross_amount?: string
  ppa_amount?: string
  net_amount?: string
  tax_amount?: string
  total_charge_amount?: string
  snapshot?: { tax_treatment_key?: string }
  pricing_snapshot?: { tax_treatment_key?: string }
  tariff_version?: {
    id: number
    tariff_id: number
    rate: string
    tariff?: {
      id: number
      tariff_code: string
      name: string
      service_type?: BillingServiceType
      route_type?: RouteType
      unit_of_measure?: string
      legacy_t_scode?: string | null
    }
  } | null
}

export type MovementType = 'IN' | 'OUT'
export type RouteType = 'DOMESTIC' | 'FOREIGN'

export interface InvoiceShipmentInput {
  vessel_id: number
  voyage: string
  notes: string
  movement_type: MovementType
  route_type: RouteType
}

export const VOYAGE_PATTERN = /^[0-9]{1,10}$/
export const NOTES_PATTERN = /^[A-Za-z0-9][A-Za-z0-9 .,'/-]{0,149}$/

export function validateInvoiceShipment(input: {
  vessel_id: number | null
  voyage: string
  notes: string
  movement_type: MovementType | ''
  route_type: RouteType | ''
}): string | null {
  if (!input.vessel_id) return 'Select a vessel from the vessel list.'
  if (!VOYAGE_PATTERN.test(input.voyage.trim())) return 'Voyage must be a number up to 10 digits.'
  if (!NOTES_PATTERN.test(input.notes.trim())) {
    return "Notes must be alphanumeric (letters, numbers, spaces and . , ' / -)."
  }
  if (input.movement_type !== 'IN' && input.movement_type !== 'OUT') return 'Select IN or OUT.'
  if (input.route_type !== 'DOMESTIC' && input.route_type !== 'FOREIGN') {
    return 'Select domestic or foreign route.'
  }
  return null
}

export function invoiceShipmentPayload(input: {
  vessel_id: number | null
  voyage: string
  notes: string
  movement_type: MovementType | ''
  route_type: RouteType | ''
}): InvoiceShipmentInput | null {
  if (validateInvoiceShipment(input)) return null
  return {
    vessel_id: input.vessel_id as number,
    voyage: input.voyage.trim(),
    notes: input.notes.trim(),
    movement_type: input.movement_type as MovementType,
    route_type: input.route_type as RouteType
  }
}

export type SurchargeMode = 'NONE' | 'FUEL' | 'DANGEROUS_CARGO'

export interface InvoiceDraft {
  id: number
  organization_id: number
  location_id?: number | null
  customer_id: number
  status: 'DRAFT' | 'POSTED' | 'CANCELLED' | string
  invoice_number?: string | null
  business_date: string
  currency: string
  base_gross_amount: string
  fuel_surcharge_amount: string
  gross_amount: string
  ppa_amount: string
  discount_amount: string
  net_amount: string
  tax_amount: string
  total_charge_amount: string
  surcharge_mode?: SurchargeMode | string | null
  dangerous_cargo_percent?: string | null
  vessel_id?: number | null
  vessel_name?: string | null
  voyage?: string | null
  movement_type?: MovementType | null
  route_type?: RouteType | null
  notes?: string | null
  lock_version: number
  items?: InvoiceDraftItem[]
  customer?: { id: number; name: string; account_number?: string } | null
}

export function fetchEffectiveTariffs(routeType?: RouteType | '') {
  return request.get<Tariff[]>({
    url: '/api/v1/tariffs',
    params: routeType ? { route_type: routeType } : undefined
  })
}

export interface InvoiceCalculationPreview {
  customer_id: number
  business_date: string
  is_fiscal_ready?: boolean
  surcharge_mode?: SurchargeMode | string
  dangerous_cargo_percent?: string | null
  items: InvoiceDraftItem[]
  totals: {
    base_gross_amount: string
    fuel_surcharge_amount: string
    gross_amount: string
    ppa_amount: string
    discount_amount: string
    net_amount: string
    tax_amount: string
    total_charge_amount: string
  }
}

export function calculateInvoiceDraft(data: {
  customer_id: number
  business_date?: string
  route_type?: RouteType
  surcharge_mode?: SurchargeMode
  dangerous_cargo_percent?: string | number | null
  items: InvoiceDraftItemInput[]
}) {
  return request.post<InvoiceCalculationPreview>({
    url: '/api/v1/invoices/calculate',
    data,
    showErrorMessage: false
  })
}

export function fetchInvoiceDraft(id: number) {
  return request.get<InvoiceDraft>({
    url: `/api/v1/invoices/drafts/${id}`
  })
}

export function updateInvoiceDraft(
  id: number,
  data: InvoiceShipmentInput & {
    expected_version: number
    business_date?: string
    reason?: string
    surcharge_mode?: SurchargeMode
    dangerous_cargo_percent?: string | number | null
    items: InvoiceDraftItemInput[]
  }
) {
  return request.put<InvoiceDraft>({
    url: `/api/v1/invoices/drafts/${id}`,
    data
  })
}

export function postInvoiceDraft(
  id: number,
  data: {
    expected_version: number
    series_id?: number
    backdate_authorization_id?: number
  }
) {
  return request.post<InvoiceDraft>({
    url: `/api/v1/invoices/drafts/${id}/post`,
    data
  })
}
