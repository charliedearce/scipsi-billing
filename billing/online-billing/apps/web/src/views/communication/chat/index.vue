<!-- Production chat page (Art Design Pro layout, W27) -->
<template>
  <div class="chat-page page-content flex !p-0 !overflow-hidden" :style="shellStyle">
    <aside
      v-if="!focusedFromDocument"
      class="chat-sidebar box-border flex h-full min-h-0 w-[22.5rem] shrink-0 flex-col border-r border-g-300 p-5 max-md:w-full max-md:max-h-48 max-md:border-r-0 max-md:border-b"
    >
      <div class="pb-4 max-md:!hidden">
        <div class="flex-c gap-3">
          <ElAvatar :size="50">{{ displayInitial }}</ElAvatar>
          <div class="min-w-0">
            <div class="truncate text-base font-medium">{{ displayName }}</div>
            <div class="mt-1 truncate text-xs text-g-500">{{ displayEmail }}</div>
          </div>
        </div>
        <div class="mt-3">
          <ElInput v-model="searchQuery" placeholder="Search conversations" clearable />
        </div>
        <div class="mt-3 flex-c gap-2 text-xs text-g-500">
          <span
            class="inline-block h-2 w-2 rounded-full"
            :class="realtimeStore.wsConnected ? 'bg-success' : 'bg-g-400'"
          ></span>
          {{ realtimeStore.wsConnected ? 'Realtime connected' : 'Polling sync' }}
        </div>
      </div>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <div v-if="loadingList" class="py-8 text-center text-sm text-g-500">Loading…</div>
        <template v-else>
          <div v-for="group in groupedConversations" :key="group.label" class="mb-4">
            <div class="px-3 pb-1 text-[10px] font-medium uppercase tracking-wide text-g-500">
              {{ group.label }}
            </div>
            <div
              v-for="item in group.items"
              :key="item.id"
              class="mb-1 flex-c c-p rounded-lg p-3 tad-200 hover:bg-active-color/30"
              :class="{ 'bg-active-color': selectedId === item.id }"
              @click="selectConversation(item.id)"
            >
              <div class="relative mr-3">
                <ElAvatar :size="40">
                  {{ (item.customer?.name || topicOf(item).reference || '?').charAt(0) }}
                </ElAvatar>
              </div>
              <div class="min-w-0 flex-1">
                <div class="mb-1 flex-cb">
                  <span class="truncate text-sm font-medium">{{ topicOf(item).reference }}</span>
                  <ElBadge
                    v-if="(item.unread_count || 0) > 0"
                    :value="item.unread_count"
                    :max="99"
                  />
                </div>
                <div class="flex-cb">
                  <span class="overflow-hidden text-ellipsis whitespace-nowrap text-xs text-g-600">
                    {{ item.customer?.name || item.customer?.email || 'Conversation' }}
                  </span>
                  <span class="ml-2 shrink-0 text-[10px] text-g-500">
                    {{ formatShort(item.last_message_at) }}
                  </span>
                </div>
              </div>
            </div>
          </div>
          <div
            v-if="filteredConversations.length === 0"
            class="py-10 text-center text-sm text-g-500"
          >
            No conversations yet.
          </div>
        </template>
      </div>
    </aside>

    <section class="box-border flex h-full min-h-0 min-w-0 flex-1 flex-col">
      <div class="flex-cb shrink-0 border-b border-g-200 px-4 pb-3 pt-4">
        <div class="min-w-0">
          <button
            v-if="focusedFromDocument"
            type="button"
            class="mb-1 text-xs text-theme"
            @click="showAllMessages"
          >
            All messages
          </button>
          <span class="block truncate text-base font-medium">{{ activeSubject }}</span>
          <div class="mt-1 truncate text-xs text-g-500">{{ activeCustomer }}</div>
        </div>
        <div class="flex shrink-0 items-center gap-2">
          <BrowserChatAlertsButton />
          <ElButton :loading="loadingList" @click="loadConversations">Refresh</ElButton>
        </div>
      </div>

      <div
        ref="messageContainer"
        class="min-h-0 flex-1 overflow-y-auto px-4 py-5 [&::-webkit-scrollbar]:!w-1"
      >
        <div v-if="!selectedId" class="flex h-full flex-cc text-g-500">Select a conversation</div>
        <div v-else-if="loadingMessages" class="py-10 text-center text-sm text-g-500">
          Loading messages…
        </div>
        <div v-else-if="messages.length === 0" class="flex h-full flex-cc text-g-500">
          No messages yet. Say hello.
        </div>
        <template v-else>
          <div
            v-for="message in messages"
            :key="message.id"
            :class="[
              'mb-6 flex w-full items-start gap-2',
              isMine(message) ? 'flex-row-reverse' : 'flex-row'
            ]"
          >
            <ElAvatar :size="32" class="shrink-0">
              {{ (message.sender?.name || '?').charAt(0) }}
            </ElAvatar>
            <div
              :class="['flex max-w-[70%] flex-col', isMine(message) ? 'items-end' : 'items-start']"
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
                  'whitespace-pre-wrap rounded-md px-3.5 py-2.5 text-sm leading-[1.4] text-g-900',
                  isMine(message) ? 'bg-theme/15' : 'bg-g-300/50',
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

      <div v-if="selectedId" class="shrink-0 border-t border-g-200 px-4 py-4">
        <div v-if="canStaffNote" class="mb-2">
          <ElCheckbox v-model="asStaffNote" label="Internal staff note (hidden from customer)" />
        </div>
        <ElInput
          v-model="messageText"
          type="textarea"
          :rows="3"
          placeholder="Type a message…"
          resize="none"
          @focus="unlockChatSounds"
          @keydown.enter.exact.prevent="send"
        />
        <div class="mt-3 flex justify-end">
          <ElButton type="primary" :loading="sending" v-ripple @click="send">Send</ElButton>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup lang="ts">
  import { useRoute, useRouter } from 'vue-router'
  import { ElMessage } from 'element-plus'
  import { useAutoLayoutHeight } from '@/hooks/core/useLayoutHeight'
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

  defineOptions({ name: 'ChatPage' })

  const { containerMinHeight } = useAutoLayoutHeight()
  const route = useRoute()
  const router = useRouter()
  const userStore = useUserStore()
  const realtimeStore = useRealtimeStore()

  const searchQuery = ref('')
  const focusedFromDocument = ref(false)
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
  let stopListen: (() => void) | null = null
  let stopReadListen: (() => void) | null = null
  let bootstrapped = false

  const shellStyle = computed(() => ({
    height: containerMinHeight.value,
    maxHeight: containerMinHeight.value,
    minHeight: '420px'
  }))

  const canStaffNote = computed(() => {
    const info = userStore.info as any
    const list: string[] = info?.buttons || info?.permissions || []
    return list.includes('conversations:staff_notes')
  })
  const myUserId = computed(() =>
    Number((userStore.info as any)?.userId || (userStore.info as any)?.id || 0)
  )
  const displayName = computed(
    () => (userStore.info as any)?.userName || (userStore.info as any)?.name || 'You'
  )
  const displayEmail = computed(() => (userStore.info as any)?.email || '')
  const displayInitial = computed(() => (displayName.value || '?').charAt(0))

  const filteredConversations = computed(() => {
    const q = searchQuery.value.trim().toLowerCase()
    if (!q) return conversations.value
    return conversations.value.filter((c) => {
      const topic = conversationTopic(c)
      return (
        topic.label.toLowerCase().includes(q) ||
        topic.reference.toLowerCase().includes(q) ||
        c.subject.toLowerCase().includes(q) ||
        (c.customer?.name || '').toLowerCase().includes(q) ||
        (c.customer?.email || '').toLowerCase().includes(q)
      )
    })
  })

  const groupedConversations = computed(() =>
    groupConversationsByTopic(filteredConversations.value)
  )

  const activeConversation = computed(() =>
    conversations.value.find((c) => c.id === selectedId.value)
  )
  const activeSubject = computed(() =>
    activeConversation.value ? conversationTopic(activeConversation.value).title : 'Messages'
  )

  function topicOf(conversation: Conversation) {
    return conversationTopic(conversation)
  }

  function showAllMessages(): void {
    focusedFromDocument.value = false
    router.replace({ path: '/chat' })
  }
  const activeCustomer = computed(
    () =>
      activeConversation.value?.customer?.name ||
      activeConversation.value?.customer?.email ||
      'Select a thread to begin'
  )

  /** Messenger-style: only the latest of my messages that the peer has read. */
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
    return Boolean(selectedId.value) && isChatTabVisible()
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
      return new Date(iso).toLocaleString()
    } catch {
      return iso
    }
  }

  function formatShort(iso?: string | null): string {
    if (!iso) return ''
    try {
      return new Date(iso).toLocaleDateString()
    } catch {
      return ''
    }
  }

  function scrollToBottom(): void {
    nextTick(() => {
      setTimeout(() => {
        if (messageContainer.value) {
          messageContainer.value.scrollTop = messageContainer.value.scrollHeight
        }
      }, 80)
    })
  }

  function clearThreadListeners(): void {
    if (stopListen) {
      stopListen()
      stopListen = null
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
      if (conv) conv.unread_count = 0
      realtimeStore.markChatNotificationsReadLocally(id)
      await realtimeStore.refreshUnreadCounts()
    } catch {
      // Non-fatal
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
      await acknowledgeRead(id)

      stopListen = onConversationMessage(id, (payload) => {
        const incoming = normalizeLiveMessage(payload)
        if (!incoming?.id || messages.value.some((m) => m.id === incoming.id)) return
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

  async function send(): Promise<void> {
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

  async function openTopicFromRoute(
    loader: () => Promise<Conversation | undefined>,
    failure: string
  ): Promise<void> {
    focusedFromDocument.value = true
    try {
      const conversation = await loader()
      await loadConversations()
      if (conversation?.id) await selectConversation(conversation.id)
    } catch (err: any) {
      ElMessage.error(err.message || failure)
    }
  }

  async function syncFromRoute(): Promise<void> {
    await loadConversations()

    const billingRequestId = Number(route.query.billingRequestId || 0)
    if (billingRequestId > 0) {
      await openTopicFromRoute(
        () => ensureConversationForBillingRequest(billingRequestId),
        'Could not open billing conversation.'
      )
      return
    }

    const billClaimId = Number(route.query.billClaimId || 0)
    if (billClaimId > 0) {
      await openTopicFromRoute(
        () => ensureConversationForBillClaim(billClaimId),
        'Could not open claim conversation.'
      )
      return
    }

    const invoiceId = Number(route.query.invoiceId || 0)
    if (invoiceId > 0) {
      await openTopicFromRoute(
        () => ensureConversationForInvoice(invoiceId),
        'Could not open bill conversation.'
      )
      return
    }

    const receiptId = Number(route.query.receiptId || 0)
    if (receiptId > 0) {
      await openTopicFromRoute(
        () => ensureConversationForReceipt(receiptId),
        'Could not open receipt conversation.'
      )
      return
    }

    focusedFromDocument.value = false
    const id = Number(route.query.id || 0)
    if (id > 0) {
      await selectConversation(id)
    }
  }

  function onVisibilityChange(): void {
    if (isActivelyViewingThread() && selectedId.value) {
      acknowledgeRead(selectedId.value)
    }
  }

  onMounted(async () => {
    bootstrapped = true
    unlockChatSounds()
    document.addEventListener('visibilitychange', onVisibilityChange)
    await syncFromRoute()
  })

  onActivated(async () => {
    if (!bootstrapped) return
    await syncFromRoute()
  })

  watch(
    () => [
      route.query.id,
      route.query.billingRequestId,
      route.query.billClaimId,
      route.query.invoiceId,
      route.query.receiptId
    ],
    async () => {
      if (!bootstrapped) return
      await syncFromRoute()
    }
  )

  onUnmounted(() => {
    document.removeEventListener('visibilitychange', onVisibilityChange)
    clearThreadListeners()
  })
</script>

<style scoped>
  .chat-page {
    background: var(--default-box-color);
  }
</style>
