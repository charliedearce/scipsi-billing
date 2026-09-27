export interface WithholdingCertificateOption {
  id: number
  certificate_no: string
  period_from: string
  period_to: string
  remaining_amount?: string | null
  status?: string
}

export interface WithholdingBillInput {
  invoiceId: number
  businessDate: string
  outstanding: string
}

export interface WithholdingLinePlan {
  invoiceId: number
  certificateId: number | null
  withholdingAmount: string
  cashAmount: string
}

export function toCents(value: string | number | null | undefined): number {
  if (value === null || value === undefined || value === '') return 0
  const raw = String(value).trim()
  const negative = raw.startsWith('-')
  const [whole, fraction = ''] = raw.replace('-', '').split('.')
  const cents = Number(whole || '0') * 100 + Number((fraction + '00').slice(0, 2))
  if (!Number.isFinite(cents)) return 0
  return negative ? -cents : cents
}

export function fromCents(cents: number): string {
  const negative = cents < 0
  const abs = Math.abs(Math.trunc(cents))
  const whole = Math.floor(abs / 100)
  const fraction = String(abs % 100).padStart(2, '0')
  return `${negative ? '-' : ''}${whole}.${fraction}`
}

export function dateOnly(value: string | null | undefined): string {
  return String(value || '').slice(0, 10)
}

export function certificateCoversDate(
  certificate: WithholdingCertificateOption,
  businessDate: string
): boolean {
  const day = dateOnly(businessDate)
  if (!day) return false
  return dateOnly(certificate.period_from) <= day && day <= dateOnly(certificate.period_to)
}

export function usableWithholdingCertificates<T extends WithholdingCertificateOption>(
  certificates: T[]
): T[] {
  return certificates
    .filter(
      (certificate) =>
        certificate.status === 'APPROVED' && toCents(certificate.remaining_amount) > 0
    )
    .sort((left, right) => {
      const period = dateOnly(left.period_from).localeCompare(dateOnly(right.period_from))
      return period || left.id - right.id
    })
}

/**
 * Cash is applied first. Withholding fills only the unpaid remainder, up to the
 * unused approved certificate, and only when the bill date falls in its period.
 * One certificate's remaining amount is shared across the bills in invoice order.
 */
export function planWithholding(
  bills: WithholdingBillInput[],
  certificates: WithholdingCertificateOption[],
  options?: {
    certificateId?: number | null
    cashByInvoice?: Record<number, string>
  }
): WithholdingLinePlan[] {
  const usable = usableWithholdingCertificates(certificates)
  const chosen =
    options?.certificateId === undefined
      ? null
      : usable.find((certificate) => certificate.id === options.certificateId) || null
  const pools = new Map(
    usable.map((certificate) => [certificate.id, toCents(certificate.remaining_amount)])
  )

  return [...bills]
    .sort((left, right) => left.invoiceId - right.invoiceId)
    .map((bill) => {
      const outstanding = Math.max(toCents(bill.outstanding), 0)
      const certificate =
        options?.certificateId === undefined
          ? usable.find(
              (candidate) =>
                certificateCoversDate(candidate, bill.businessDate) &&
                (pools.get(candidate.id) || 0) > 0
            ) || null
          : chosen && certificateCoversDate(chosen, bill.businessDate)
            ? chosen
            : null
      const available = certificate ? pools.get(certificate.id) || 0 : 0
      const typedCash = options?.cashByInvoice?.[bill.invoiceId]
      let cash = typedCash === undefined ? null : Math.max(toCents(typedCash), 0)
      let withholding: number
      if (cash === null) {
        withholding = Math.min(available, outstanding)
        cash = outstanding - withholding
      } else if (cash >= outstanding) {
        withholding = 0
      } else {
        withholding = Math.min(available, outstanding - cash)
      }
      if (certificate) {
        pools.set(certificate.id, available - withholding)
      }
      return {
        invoiceId: bill.invoiceId,
        certificateId: withholding > 0 && certificate ? certificate.id : null,
        withholdingAmount: fromCents(withholding),
        cashAmount: fromCents(cash)
      }
    })
}
