/** Asia/Manila calendar date as YYYY-MM-DD for billing business dates. */
export function manilaBusinessDate(date: Date = new Date()): string {
  return new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Manila',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit'
  }).format(date)
}
