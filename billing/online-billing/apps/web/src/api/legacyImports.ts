import request from '@/utils/http'

export interface LegacyPage<T> {
  data: T[]
  total: number
  current_page: number
}
export interface LegacyBatch {
  id: number
  source_key: string
  status: 'STAGING' | 'READY' | 'IMPORTED' | 'FAILED'
  package_hash: string
  processed_rows: number
  expected_rows: number
  manifest: {
    exported_at: string
    consistency: string
    scope: string
    counts: Record<string, number>
  }
  summary: {
    tables: {
      source_table: string
      rows: number
      review_rows: number
      stored_amount_sum: string | null
    }[]
    dispositions: Record<string, number>
  } | null
  error_message: string | null
  created_at: string
  reason: string | null
}
export interface LegacyRecord {
  id: number
  batch_id: number
  source_key: string
  source_table: string
  reference: string | null
  related_reference: string | null
  account_number: string | null
  display_name: string | null
  source_date: string | null
  source_status: string | null
  disposition: string
  due_amount: string | null
  issues: string[]
  payload?: Record<string, string | null>
  reconciliation?: {
    line_count: number
    amounts: Record<
      string,
      { header: string | null; lines: string | null; difference: string | null }
    >
  } | null
}
const url = '/api/v1/admin/legacy-imports'
export interface LegacySqlConnection {
  host: string
  port: number
  database: string
  username: string
  password: string
  restored_database: boolean
}
export interface LegacySourceRead {
  id: number
  source_key: string
  status: 'QUEUED' | 'READING' | 'EXTRACTED' | 'FAILED' | 'CANCELLED'
  read_rows: number
  expected_rows: number | null
  batch_id: number | null
  created_at: string
  error_message: string | null
}
export const fetchLegacySourceReads = () =>
  request.get<{ driver_available: boolean; reads: LegacySourceRead[] }>({
    url: `${url}/sql-server`,
    showErrorMessage: false
  })
export const testLegacySqlConnection = (data: LegacySqlConnection) =>
  request.post<{ consistency: string; tables: number; message: string }>({
    url: `${url}/sql-server/test`,
    data,
    timeout: 60000
  })
export const startLegacySqlRead = (data: LegacySqlConnection, requestKey: string) =>
  request.post<LegacySourceRead>({
    url: `${url}/sql-server/start`,
    data: { ...data, request_key: requestKey }
  })
export const cancelLegacySqlRead = (id: number) =>
  request.post<LegacySourceRead>({ url: `${url}/sql-server/${id}/cancel` })
export const fetchLegacySchema = () =>
  request.get<{ value: string; label: string }[]>({ url: `${url}/schema` })
export const fetchLegacyBatches = (page = 1) =>
  request.get<LegacyPage<LegacyBatch>>({ url, params: { page } })
export const fetchLegacyBatch = (id: number) => request.get<LegacyBatch>({ url: `${url}/${id}` })
export function uploadLegacyPackage(file: File) {
  const data = new FormData()
  data.append('package', file)
  return request.post<LegacyBatch>({ url, data, timeout: 180000 })
}
export const advanceLegacyBatch = (id: number) =>
  request.post<LegacyBatch>({ url: `${url}/${id}/advance`, timeout: 180000 })
export const finalizeLegacyBatch = (batch: LegacyBatch, reason: string) =>
  request.post<LegacyBatch>({
    url: `${url}/${batch.id}/finalize`,
    data: { package_hash: batch.package_hash, reason },
    timeout: 180000
  })
export const fetchLegacyRecords = (params: Record<string, unknown>) =>
  request.get<LegacyPage<LegacyRecord>>({ url: `${url}/records`, params })
export const fetchLegacyRecord = (id: number) =>
  request.get<LegacyRecord>({ url: `${url}/records/${id}` })
export async function downloadLegacyToolkit() {
  const blob = await request.get<Blob>({ url: `${url}/toolkit`, responseType: 'blob' })
  const objectUrl = URL.createObjectURL(blob)
  const link = document.createElement('a')
  link.href = objectUrl
  link.download = 'legacy-export-toolkit.zip'
  link.click()
  setTimeout(() => URL.revokeObjectURL(objectUrl), 1000)
}
