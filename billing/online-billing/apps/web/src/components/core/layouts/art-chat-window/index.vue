<!-- Teller ↔ Customer chat drawer (W27 / Art Design Pro shell) -->
<template>
  <div>
    <ElDrawer
      v-model="isDrawerVisible"
      :size="isMobile ? '100%' : '520px'"
      :with-header="false"
      destroy-on-close
    >
      <div class="mb-4 flex-cb">
        <div>
          <span class="text-base font-medium">Messages</span>
          <div class="mt-1.5 flex-c gap-1">
            <div
              class="h-2 w-2 rounded-full"
              :class="realtimeStore.wsConnected ? 'bg-success/100' : 'bg-g-400'"
            ></div>
            <span class="text-xs text-g-600">
              {{ realtimeStore.wsConnected ? 'Realtime connected' : 'Polling sync' }}
            </span>
          </div>
          <div class="mt-2"><BrowserChatAlertsButton /></div>
        </div>
        <div class="flex-c gap-2">
          <ElButton text type="primary" @click="goFullPage">Open full chat</ElButton>
          <ElIcon class="c-p" :size="20" @click="closeChat">
            <Close />
          </ElIcon>
        </div>
      </div>

      <div class="flex h-[calc(100%-70px)] min-h-0 gap-3">
        <!-- Conversation list -->
        <div v-if="!focusedTopic" class="w-[38%] overflow-y-auto border-r border-g-300 pr-2">
          <div v-if="loadingList" class="py-8 text-center text-xs text-g-500">Loading…</div>
          <div
            v-else-if="conversations.length === 0"
            class="px-2 py-8 text-center text-xs text-g-500"
          >
            No conversations yet. Open Chat from a bill, request, claim, or receipt.
          </div>
          <div v-for="group in groupedConversations" :key="group.label" class="mb-3">
            <div class="px-2 pb-1 text-[10px] font-medium uppercase tracking-wide text-g-500">
              {{ group.label }}
            </div>
            <div
              v-for="conv in group.items"
              :key="conv.id"
              class="mb-1 c-p rounded-lg p-2 hover:bg-active-color/20"
              :class="{ 'bg-active-color/30': selectedId === conv.id }"
              @click="selectConversation(conv.id)"
            >
              <div class="flex-cb gap-1">
                <span class="truncate text-xs font-medium">{{ topicOf(conv).reference }}</span>
                <ElBadge v-if="(conv.unread_count || 0) > 0" :value="conv.unread_count" :max="99" />
              </div>
              <div class="mt-0.5 truncate text-[11px] text-g-500">
                {{ conv.customer?.name || 'Customer' }}
              </div>
            </div>
          </div>
        </div>

        <!-- Thread -->
        <div class="flex min-h-0 min-w-0 flex-1 flex-col">
          <div v-if="selectedId && activeConversation" class="mb-2 shrink-0">
            <button
              v-if="focusedTopic"
              type="button"
              class="mb-1 text-xs text-theme"
              @click="showInbox"
            >
              All messages
            </button>
            <div class="truncate text-sm font-medium">{{ activeTopicTitle }}</div>
            <div class="truncate text-xs text-g-500">{{ activeCustomerName }}</div>
          </div>
          <div v-if="!selectedId" class="flex-1 flex-cc text-sm text-g-500">
            Select a conversation
          </div>
          <template v-else>
            <div
              ref="messageContainer"
              class="min-h-0 flex-1 overflow-y-auto border-t-d px-2 py-4 [&::-webkit-scrollbar]:!w-1"
            >
              <div v-if="loadingMessages" class="py-6 text-center text-xs text-g-500">
                Loading messages…
              </div>
              <div v-else-if="messages.length === 0" class="py-6 text-center text-xs text-g-500">
                No messages yet.
              </div>
              <template v-for="message in messages" :key="message.id">
                <div
                  :class="[
                    'mb-5 flex w-full items-start gap-2',
                    isMine(message) ? 'flex-row-reverse' : 'flex-row'
                  ]"
                >
                  <ElAvatar :size="28" class="shrink-0">
                    {{ (message.sender?.name || '?').charAt(0) }}
                  </ElAvatar>
                  <div
                    :class="[
                      'flex max-w-[78%] flex-col',
                      isMine(message) ? 'items-end' : 'items-start'
                    ]"
                  >
                    <div
                      :class="[
                        'mb-1 flex gap-2 text-xs',
                        isMine(message) ? 'flex-row-reverse' : 'flex-row'
                      ]"
                    >
                      <span class="font-medium">
                        {{ message.sender?.name || 'System' }}
                        <ElTag
                          v-if="message.message_type === 'staff_note'"
                          size="small"
                          type="warning"
                          class="ml-1"
                          >Internal</ElTag
                        >
                      </span>
                      <span class="text-g-600">{{ formatTime(message.created_at) }}</span>
                    </div>
                    <div
                      :class="[
                        'whitespace-pre-wrap rounded-md px-3 py-2 text-sm leading-[1.4] text-g-900',
                        isMine(message) ? 'message-right bg-theme/15' : 'message-left bg-g-300/50',
                        message.message_type === 'staff_note' ? 'border border-warning/40' : ''
                      ]"
                      >{{ message.body }}</div
                    >
                    <div
                      v-if="isMine(message) && seenMessageId === message.id"
                      class="mt-1 text-[11px] text-g-500"
                    >
                      Seen
                    </div>
                  </div>
                </div>
              </template>
            </div>

            <div class="shrink-0 border-t border-g-200 px-1 pt-3">
              <div v-if="canStaffNote" class="mb-2">
                <ElCheckbox
                  v-model="asStaffNote"
                  label="Internal staff note (hidden from customer)"
                />
              </div>
              <ElInput
                v-model="messageText"
                type="textarea"
                :rows="3"
                placeholder="Type a message…"
                resize="none"
                @focus="unlockChatSounds"
                @keydown.enter.exact.prevent="sendMessage"
              />
              <div class="mt-2 flex justify-end">
                <ElButton type="primary" :loading="sending" v-ripple @click="sendMessage">
                  Send
                </ElButton>
              </div>
            </div>
          </template>
        </div>
      </div>
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { Close } from '@element-plus/icons-vue'
  import { useWindowSize } from '@vueuse/core'
  import { useRouter } from 'vue-router'
  import { ElMessage } from 'element-plus'
  import { mittBus } from '@/utils/sys'
  import { useUserStore } from '@/store/modules/user'
  import { useRealtimeStore } from '@/store/modules/realtime'
  import { onConversationMessage, onConversationRead } from '@/utils/echo'
  import { playChatSound, unlockChatSounds } from '@/utils/chatSound'
  import { isChatTabVisible } from '@/utils/chatViewing'
  import BrowserChatAlertsButton from '@/components/business/BrowserChatAlertsButton.vue'
  import {
    fetchConversations,
    fetchMessages,
    sendMessage as apiSendMessage,
    markConversationAsRead,
    ensureConversationForBillingRequest,
    ensureConversationForBillClaim,
    ensureConversationForInvoice,
    ensureConversationForReceipt,
    type ChatMessage,
    type Conversation
  } from '@/api/chat'
  import { conversationTopic, groupConversationsByTopic } from '@/utils/chat/conversationTopic'

  defineOptions({ name: 'ArtChatWindow' })

  const MOBILE_BREAKPOINT = 640
  const SCROLL_DELAY = 80

  const router = useRouter()
  const userStore = useUserStore()
  const realtimeStore = useRealtimeStore()
  const { width } = useWindowSize()
  const isMobile = computed(() => width.value < MOBILE_BREAKPOINT)

  const isDrawerVisible = ref(false)
  const focusedTopic = ref(false)
  const conversations = ref<Conversation[]>([])
  const selectedId = ref<number | null>(null)
  const messages = ref<ChatMessage[]>([])
  const messageText = ref('')
  const asStaffNote = ref(false)
  const loadingList = ref(false)
  const loadingMessages = ref(false)
  const sending = ref(false)
  const peerLastReadMessageId = ref<number | null>(null)
  const messageContainer = ref<HTMLElement | null>(null)

  let stopMessageListen: (() => void) | null = null
  let stopReadListen: (() => void) | null = null

  const canStaffNote = computed(() => {
    const info = userStore.info as any
    const list: string[] = info?.buttons || info?.permissions || []
    return list.includes('conversations:staff_notes')
  })

  const myUserId = computed(() =>
    Number((userStore.info as any)?.userId || (userStore.info as any)?.id || 0)
  )

  const groupedConversations = computed(() => groupConversationsByTopic(conversations.value))
  const activeConversation = computed(() =>
    conversations.value.find((item) => item.id === selectedId.value)
  )
  const activeTopicTitle = computed(() =>
    activeConversation.value ? conversationTopic(activeConversation.value).title : 'Messages'
  )
  const activeCustomerName = computed(
    () =>
      activeConversation.value?.customer?.name || activeConversation.value?.customer?.email || ''
  )

  function topicOf(conversation: Conversation) {
    return conversationTopic(conversation)
  }

  function showInbox(): void {
    focusedTopic.value = false
  }

  const seenMessageId = computed(() => {
    const peerRead = peerLastReadMessageId.value
    if (!peerRead) return null
    let latest: number | null = null
    for (const m of messages.value) {
      if (isMine(m) && Number(m.id) <= peerRead) {
        latest = Number(m.id)
      }
    }
    return latest
  })

  function isActivelyViewingThread(): boolean {
    return isDrawerVisible.value && isChatTabVisible()
  }

  function unwrapList<T>(res: any): T[] {
    if (Array.isArray(res)) return res
    if (Array.isArray(res?.data)) return res.data
    return []
  }

  function isMine(message: ChatMessage): boolean {
    return Number(message.sender_id) === Number(myUserId.value)
  }

  function normalizeLiveMessage(payload: any): ChatMessage | null {
    const raw = payload?.message || payload
    if (!raw?.id) return null
    return {
      id: raw.id,
      conversation_id: raw.conversation_id,
      sender_id: raw.sender_id,
      message_type: raw.message_type || 'customer',
      body: raw.body,
      attachments: raw.attachments ?? null,
      created_at: raw.created_at,
      sender: raw.sender || {
        id: raw.sender_id,
        name: raw.sender_name || 'User',
        email: ''
      }
    }
  }

  function formatTime(iso: string): string {
    try {
      return new Date(iso).toLocaleString([], {
        month: 'short',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      })
    } catch {
      return iso
    }
  }

  function scrollToBottom(): void {
    nextTick(() => {
      setTimeout(() => {
        if (messageContainer.value) {
          messageContainer.value.scrollTop = messageContainer.value.scrollHeight
        }
      }, SCROLL_DELAY)
    })
  }

  function clearThreadListeners(): void {
    if (stopMessageListen) {
      stopMessageListen()
      stopMessageListen = null
    }
    if (stopReadListen) {
      stopReadListen()
      stopReadListen = null
    }
  }

  async function acknowledgeRead(id: number): Promise<void> {
    if (!isActivelyViewingThread() || selectedId.value !== id) return
    try {
      await markConversationAsRead(id)
      const conv = conversations.value.find((c) => c.id === id)
      if (conv && (conv.unread_count || 0) > 0) {
        realtimeStore.bumpChatUnread(-(conv.unread_count || 0))
        conv.unread_count = 0
      }
      realtimeStore.markChatNotificationsReadLocally(id)
      await realtimeStore.refreshUnreadCounts()
    } catch {
      // Non-fatal: unread badges refresh on next poll/open
    }
  }

  async function loadConversations(): Promise<void> {
    loadingList.value = true
    try {
      const res = await fetchConversations({ per_page: 50, status: 'open' })
      conversations.value = unwrapList<Conversation>(res)
      await realtimeStore.refreshUnreadCounts()
    } catch (err: any) {
      ElMessage.error(err.message || 'Failed to load conversations.')
    } finally {
      loadingList.value = false
    }
  }

  async function selectConversation(id: number): Promise<void> {
    selectedId.value = id
    clearThreadListeners()
    loadingMessages.value = true
    try {
      const res = await fetchMessages(id, { per_page: 100 })
      messages.value = unwrapList<ChatMessage>(res)
      peerLastReadMessageId.value = res?.meta?.peer_last_read_message_id ?? null
      // Only mark Seen when the drawer is open and this tab is visible.
      await acknowledgeRead(id)

      stopMessageListen = onConversationMessage(id, (payload) => {
        const incoming = normalizeLiveMessage(payload)
        if (!incoming?.id) return
        if (messages.value.some((m) => m.id === incoming.id)) return
        messages.value.push(incoming)
        scrollToBottom()
        if (!isMine(incoming)) {
          if (isActivelyViewingThread()) {
            playChatSound('receive')
            acknowledgeRead(id)
          }
        }
      })
      stopReadListen = onConversationRead(id, (payload) => {
        if (!payload || Number(payload.user_id) === Number(myUserId.value)) return
        const readId = Number(payload.last_read_message_id || 0)
        if (readId > 0) {
          peerLastReadMessageId.value = Math.max(peerLastReadMessageId.value || 0, readId)
        }
      })
      scrollToBottom()
    } catch (err: any) {
      ElMessage.error(err.message || 'Failed to load messages.')
      messages.value = []
    } finally {
      loadingMessages.value = false
    }
  }

  async function sendMessage(): Promise<void> {
    const text = messageText.value.trim()
    if (!text || !selectedId.value || sending.value) return
    unlockChatSounds()
    sending.value = true
    try {
      const created = await apiSendMessage(selectedId.value, {
        body: text,
        message_type: asStaffNote.value && canStaffNote.value ? 'staff_note' : 'customer'
      })
      if (created && !messages.value.some((m) => m.id === created.id)) {
        messages.value.push(created)
      }
      messageText.value = ''
      playChatSound('send')
      scrollToBottom()
      await loadConversations()
    } catch (err: any) {
      ElMessage.error(err.message || 'Failed to send message.')
    } finally {
      sending.value = false
    }
  }

  async function openTopic(
    loader: () => Promise<Conversation | undefined>,
    failure: string
  ): Promise<void> {
    focusedTopic.value = true
    try {
      const conversation = await loader()
      await loadConversations()
      if (conversation?.id) await selectConversation(conversation.id)
    } catch (err: any) {
      ElMessage.error(err.message || failure)
    }
  }

  async function openChat(payload?: {
    conversationId?: number
    billingRequestId?: number
    billClaimId?: number
    invoiceId?: number
    receiptId?: number
  }): Promise<void> {
    unlockChatSounds()
    isDrawerVisible.value = true
    await loadConversations()

    if (payload?.billingRequestId) {
      await openTopic(
        () => ensureConversationForBillingRequest(payload.billingRequestId as number),
        'Could not open billing conversation.'
      )
      return
    }

    if (payload?.billClaimId) {
      await openTopic(
        () => ensureConversationForBillClaim(payload.billClaimId as number),
        'Could not open claim conversation.'
      )
      return
    }

    if (payload?.invoiceId) {
      await openTopic(
        () => ensureConversationForInvoice(payload.invoiceId as number),
        'Could not open bill conversation.'
      )
      return
    }

    if (payload?.receiptId) {
      await openTopic(
        () => ensureConversationForReceipt(payload.receiptId as number),
        'Could not open receipt conversation.'
      )
      return
    }

    if (payload?.conversationId) {
      focusedTopic.value = true
      await selectConversation(payload.conversationId)
      return
    }

    focusedTopic.value = false
    selectedId.value = null
    messages.value = []
    clearThreadListeners()
  }

  function closeChat(): void {
    isDrawerVisible.value = false
    // Stop listening so background Echo delivery cannot mark Seen.
    clearThreadListeners()
  }

  function goFullPage(): void {
    const query: Record<string, string> = {}
    if (selectedId.value) query.id = String(selectedId.value)
    closeChat()
    router.push({ path: '/chat', query })
  }

  function onVisibilityChange(): void {
    if (isActivelyViewingThread() && selectedId.value) {
      acknowledgeRead(selectedId.value)
    }
  }

  watch(isDrawerVisible, (open) => {
    if (!open) {
      clearThreadListeners()
    }
  })

  onMounted(() => {
    mittBus.on('openChat', openChat as any)
    document.addEventListener('visibilitychange', onVisibilityChange)
  })

  onUnmounted(() => {
    mittBus.off('openChat', openChat as any)
    document.removeEventListener('visibilitychange', onVisibilityChange)
    clearThreadListeners()
  })
</script>
