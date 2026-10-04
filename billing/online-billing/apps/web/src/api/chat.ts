import request from '@/utils/http'

export interface ConversationParticipant {
  id: number
  conversation_id: number
  user_id: number
  role: string
  last_read_message_id?: number | null
  last_read_at?: string | null
  user?: {
    id: number
    name: string
    email: string
  }
}

export interface ChatMessage {
  id: number
  conversation_id: number
  sender_id?: number | null
  message_type: 'customer' | 'staff_note' | 'system'
  body: string
  attachments?: Array<{
    id: number
    name: string
    size_bytes: number
    mime_type: string
  }> | null
  created_at: string
  sender?: {
    id: number
    name: string
    email: string
  }
}

export interface Conversation {
  id: number
  organization_id: number
  customer_id: number
  subject: string
  context_type?: string | null
  context_id?: number | null
  status: 'open' | 'closed' | 'archived'
  last_message_at?: string | null
  created_at: string
  unread_count?: number
  messages_count?: number
  customer?: {
    id: number
    name: string
    email: string
  }
  participants?: ConversationParticipant[]
}

export interface FetchConversationsParams {
  page?: number
  per_page?: number
  status?: string
  context_type?: string
}

export interface PaginatedConversationsResponse {
  data: Conversation[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
  }
}

export interface PaginatedMessagesResponse {
  data: ChatMessage[]
  meta: {
    current_page: number
    per_page: number
    total: number
    last_page: number
    peer_last_read_message_id?: number | null
  }
}

export interface CreateConversationPayload {
  subject: string
  customer_id?: number
  context_type?: string
  context_id?: number
  initial_message?: string
  participant_ids?: number[]
}

export interface SendMessagePayload {
  body: string
  message_type?: 'customer' | 'staff_note' | 'system'
  attachments?: Array<{
    id: number
    name: string
    size_bytes: number
    mime_type: string
  }>
}

export function fetchConversations(params?: FetchConversationsParams) {
  return request.get<PaginatedConversationsResponse>({
    url: '/api/v1/conversations',
    params
  })
}

export function fetchConversation(id: number) {
  return request.get<{ data: Conversation }>({
    url: `/api/v1/conversations/${id}`
  })
}

export function createConversation(data: CreateConversationPayload) {
  return request.post<Conversation>({
    url: '/api/v1/conversations',
    data
  })
}

export function fetchMessages(
  conversationId: number,
  params?: { page?: number; per_page?: number }
) {
  return request.get<PaginatedMessagesResponse>({
    url: `/api/v1/conversations/${conversationId}/messages`,
    params
  })
}

export function sendMessage(conversationId: number, data: SendMessagePayload) {
  return request.post<ChatMessage>({
    url: `/api/v1/conversations/${conversationId}/messages`,
    data
  })
}

export function markConversationAsRead(conversationId: number) {
  return request.post<{
    conversation_id: number
    user_id: number
    last_read_message_id?: number | null
    last_read_at?: string | null
    notifications_marked?: number
  }>({
    url: `/api/v1/conversations/${conversationId}/read`
  })
}

export function addParticipant(conversationId: number, data: { user_id: number; role?: string }) {
  return request.post<ConversationParticipant>({
    url: `/api/v1/conversations/${conversationId}/participants`,
    data
  })
}

export function ensureConversationForBillingRequest(billingRequestId: number) {
  return request.post<Conversation>({
    url: `/api/v1/conversations/for-billing-request/${billingRequestId}`
  })
}

export function ensureConversationForBillClaim(billClaimId: number) {
  return request.post<Conversation>({
    url: `/api/v1/conversations/for-bill-claim/${billClaimId}`
  })
}

export function ensureConversationForInvoice(invoiceId: number) {
  return request.post<Conversation>({
    url: `/api/v1/conversations/for-invoice/${invoiceId}`
  })
}

export function ensureConversationForReceipt(receiptId: number) {
  return request.post<Conversation>({
    url: `/api/v1/conversations/for-receipt/${receiptId}`
  })
}
