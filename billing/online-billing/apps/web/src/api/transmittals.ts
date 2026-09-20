import request from '@/utils/http'

export type TransmittalKind = 'YELLOW_INVOICE' | 'WHITE_RECEIPT'

export interface TransmittalSource {
  id: number
  source_number: string
  business_date: string
  currency: string
  party_name: string | null
  amount: string
}

export interface YellowTransmittalItem {
  id: number
  invoice_id: number
  invoice_number: string
  business_date: string
  currency: string
  total_charge_amount: string
  buyer_snapshot: { name?: string }
}

export interface WhiteTransmittalItem {
  id: number
  receipt_id: number
  receipt_number: string
  business_date: string
  currency: string
  cash_received_amount: string
  withholding_received_amount: string
  applied_amount: string
  unapplied_amount: string
  payer_snapshot: { registered_name?: string; name?: string }
}

export interface Transmittal {
  id: number
  transmittal_number: string
  kind: TransmittalKind
  as_of_date: string
  currency: string
  source_item_count: number
  summary: Record<string, string>
  status: 'GENERATED' | 'VOID'
  generated_at: string
  yellow_items?: YellowTransmittalItem[]
  white_items?: WhiteTransmittalItem[]
}

export interface TransmittalPage {
  data: Transmittal[]
  total: number
}

export interface SourcePage {
  data: TransmittalSource[]
  total: number
}

export function fetchTransmittals(params?: { kind?: TransmittalKind; as_of_date?: string }) {
  return request.get<TransmittalPage>({ url: '/api/v1/transmittals', params })
}

export function fetchTransmittal(id: number) {
  return request.get<Transmittal>({ url: `/api/v1/transmittals/${id}` })
}

export function fetchEligibleTransmittalSources(data: {
  kind: TransmittalKind
  as_of_date: string
}) {
  return request.get<SourcePage>({
    url: '/api/v1/transmittals/eligible-sources',
    params: { ...data, per_page: 100 }
  })
}

export function generateTransmittal(data: {
  kind: TransmittalKind
  as_of_date: string
  invoice_ids?: number[]
  receipt_ids?: number[]
}) {
  return request.post<Transmittal>({ url: '/api/v1/transmittals', data })
}

/** Authenticated download of the canonical PDF rendered from the frozen transmittal snapshot. */
export function downloadTransmittalArtifact(id: number) {
  return request.get<Blob>({
    url: `/api/v1/transmittals/${id}/artifact/download`,
    responseType: 'blob',
    showErrorMessage: false
  })
}
