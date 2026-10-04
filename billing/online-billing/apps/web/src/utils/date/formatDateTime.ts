/** Format an ISO datetime for staff/customer display in Asia/Manila. */
export function formatDateTimeManila(value?: string | Date | null): string {
  if (!value) return '—'
  const date = value instanceof Date ? value : new Date(value)
  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
    hour12: true
  }).format(date)
}

/**
 * Format a calendar date (YYYY-MM-DD or ISO) for staff/customer display in Asia/Manila.
 * Date-only values stay on the same calendar day (no local midnight shift).
 */
export function formatDateManila(value?: string | Date | null): string {
  if (!value) return '—'

  let date: Date
  if (value instanceof Date) {
    date = value
  } else if (/^\d{4}-\d{2}-\d{2}/.test(value)) {
    const [year, month, day] = value.slice(0, 10).split('-').map(Number)
    date = new Date(Date.UTC(year, month - 1, day, 12, 0, 0))
  } else {
    date = new Date(value)
  }

  if (Number.isNaN(date.getTime())) return String(value)

  return new Intl.DateTimeFormat('en-PH', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: 'short',
    day: '2-digit'
  }).format(date)
}

/** Inclusive date range for Period / Validity columns. */
export function formatDateRangeManila(
  from?: string | Date | null,
  to?: string | Date | null,
  openLabel = 'Open ended'
): string {
  const start = formatDateManila(from)
  if (start === '—') return '—'
  return `${start} → ${to ? formatDateManila(to) : openLabel}`
}
