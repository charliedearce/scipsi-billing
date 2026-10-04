export interface ConversationTopicSource {
  subject?: string | null
  context_type?: string | null
  customer?: { name?: string | null; email?: string | null } | null
}

export interface ConversationTopic {
  label: string
  reference: string
  title: string
}

const TOPIC_ORDER = ['Request', 'Bill', 'Claim', 'Receipt', 'Message']

export function conversationTopic(conversation: ConversationTopicSource): ConversationTopic {
  const subject = (conversation.subject || '').trim()
  const strip = (prefix: string) => subject.replace(new RegExp(`^${prefix}\\s+`, 'i'), '').trim()

  let label = 'Message'
  let reference = subject || 'Conversation'

  switch (conversation.context_type) {
    case 'billing_request':
      label = 'Request'
      reference = strip('Billing') || subject
      break
    case 'bill_claim':
      label = 'Claim'
      reference = strip('Claim') || subject
      break
    case 'invoice':
      label = 'Bill'
      reference = strip('Bill') || subject
      break
    case 'receipt':
      label = 'Receipt'
      reference = strip('Receipt') || subject
      break
    default:
      break
  }

  return {
    label,
    reference,
    title: `${label} ${reference}`.trim()
  }
}

export function groupConversationsByTopic<T extends ConversationTopicSource>(
  items: T[]
): Array<{
  label: string
  items: T[]
}> {
  const buckets = new Map<string, T[]>()
  for (const item of items) {
    const label = conversationTopic(item).label
    const current = buckets.get(label) || []
    current.push(item)
    buckets.set(label, current)
  }

  return TOPIC_ORDER.filter((label) => buckets.has(label)).map((label) => ({
    label,
    items: buckets.get(label) || []
  }))
}
