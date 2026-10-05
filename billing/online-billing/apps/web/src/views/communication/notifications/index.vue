<template>
  <div class="notifications-page page-content mx-auto max-w-6xl !p-0 overflow-hidden">
    <header
      class="flex flex-col gap-5 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6"
    >
      <div class="flex items-center gap-3.5">
        <div class="size-11 flex-cc rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:notification-3-line" class="text-2xl" />
        </div>
        <div>
          <h1 class="text-xl font-medium text-g-900">Notifications</h1>
          <p class="mt-1 text-sm text-g-500">
            Work alerts (billing, payments, claims) stay separate from chat
          </p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2.5">
        <BrowserChatAlertsButton />
        <div class="flex items-center gap-2 rounded-lg bg-g-200/70 px-3 py-2">
          <span class="text-xs text-g-500">Unread</span>
          <ElBadge :value="unreadCount" :max="99" :hidden="unreadCount === 0" />
          <span v-if="unreadCount === 0" class="text-sm font-medium text-g-800">0</span>
        </div>
        <div class="flex items-center gap-2 rounded-lg bg-g-200/70 px-3 py-2 text-xs text-g-600">
          <span
            class="size-2 rounded-full"
            :class="realtimeAvailable ? 'bg-success' : 'bg-warning'"
          ></span>
          {{ realtimeAvailable ? 'Realtime' : 'Polling sync' }}
        </div>
      </div>
    </header>

    <section class="border-y border-g-200 bg-g-100/40 px-5 py-3.5 sm:px-6">
      <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-wrap items-center gap-3">
          <ElRadioGroup v-model="filterChannel" @change="handleFilterChange">
            <ElRadioButton value="work">Work</ElRadioButton>
            <ElRadioButton value="chat">Chat</ElRadioButton>
            <ElRadioButton value="all">All</ElRadioButton>
          </ElRadioGroup>
          <ElRadioGroup v-model="filterUnread" @change="handleFilterChange">
            <ElRadioButton :value="false">All</ElRadioButton>
            <ElRadioButton :value="true">
              Unread
              <span
                v-if="unreadCount"
                class="ml-1 rounded-full bg-theme/10 px-1.5 py-0.5 text-[10px] font-medium text-theme"
              >
                {{ unreadCount > 99 ? '99+' : unreadCount }}
              </span>
            </ElRadioButton>
          </ElRadioGroup>
          <ElSelect
            v-if="filterChannel !== 'chat'"
            v-model="filterType"
            placeholder="All categories"
            clearable
            class="!w-48"
            @change="handleFilterChange"
          >
            <ElOption label="Queue updates" value="QUEUE" />
            <ElOption label="Invoices" value="INVOICE" />
            <ElOption label="Payments" value="PAYMENT" />
            <ElOption label="Bill claims" value="BILL_CLAIM" />
            <ElOption label="Tax reviews" value="TAX" />
            <ElOption label="Credit" value="CREDIT" />
            <ElOption v-if="filterChannel === 'all'" label="Messages" value="chat_message" />
            <ElOption label="System notices" value="SYSTEM" />
          </ElSelect>
        </div>
        <div class="flex flex-wrap items-center gap-2">
          <span class="mr-1 text-xs text-g-500"
            >{{ total }} {{ total === 1 ? 'notification' : 'notifications' }}</span
          >
          <ElButton :loading="loading" @click="loadNotifications">
            <ElIcon class="mr-1"><Refresh /></ElIcon>Refresh
          </ElButton>
          <ElButton
            type="primary"
            plain
            :loading="markingAll"
            :disabled="unreadCount === 0 || loading"
            @click="handleMarkAllRead"
          >
            <ElIcon class="mr-1"><CircleCheck /></ElIcon>Mark all read
          </ElButton>
        </div>
      </div>
    </section>

    <section class="p-5 sm:p-6" aria-live="polite" :aria-busy="loading">
      <div v-if="loading && notificationList.length === 0" class="space-y-3">
        <div
          v-for="index in 4"
          :key="index"
          class="flex animate-pulse gap-4 rounded-lg border border-g-200 p-4"
        >
          <div class="h-10 w-10 shrink-0 rounded-lg bg-g-300/70"></div>
          <div class="flex-1 space-y-3">
            <div class="h-4 w-1/3 rounded bg-g-300/70"></div>
            <div class="h-3 w-4/5 rounded bg-g-200/80"></div>
            <div class="h-3 w-1/4 rounded bg-g-200/80"></div>
          </div>
        </div>
      </div>

      <div
        v-else-if="notificationList.length === 0"
        class="rounded-lg border border-dashed border-g-300 px-6 py-16 text-center"
      >
        <div class="mx-auto size-13 flex-cc rounded-lg bg-g-200/70 text-g-500">
          <ArtSvgIcon
            :icon="filterUnread ? 'ri:checkbox-circle-line' : 'ri:notification-off-line'"
            class="text-2xl"
          />
        </div>
        <h2 class="mt-4 text-base font-medium text-g-800">{{
          filterUnread ? 'You’re all caught up' : 'No notifications yet'
        }}</h2>
        <p class="mx-auto mt-1 max-w-md text-sm text-g-500">
          {{
            filterUnread
              ? 'There are no unread updates matching the selected category.'
              : 'New billing, payment, and review updates will appear here. Chat lives under the Chat tab.'
          }}
        </p>
        <ElButton
          v-if="filterUnread || filterType || filterChannel !== 'work'"
          class="mt-5"
          type="primary"
          plain
          @click="clearFilters"
          >Show work notifications</ElButton
        >
      </div>

      <div v-else class="space-y-6">
        <div v-for="group in groupedNotifications" :key="group.label" class="space-y-3">
          <div class="flex items-center gap-3 px-1">
            <h2 class="text-xs font-medium uppercase tracking-wider text-g-500">{{
              group.label
            }}</h2>
            <div class="h-px flex-1 bg-g-200"></div>
          </div>
          <article
            v-for="item in group.items"
            :key="item.id"
            class="group relative overflow-hidden rounded-lg border p-4 transition-colors duration-200 hover:bg-g-100/50 sm:p-4.5"
            :class="item.is_read ? 'border-g-200' : 'border-theme/20 bg-active-color/60'"
          >
            <span
              v-if="!item.is_read"
              class="absolute inset-y-0 left-0 w-0.75 bg-theme"
              aria-label="Unread"
            ></span>
            <div class="flex items-start gap-3 sm:gap-4">
              <div
                class="size-10 flex-cc shrink-0 rounded-lg"
                :class="typePresentation(item.type).iconClass"
              >
                <ArtSvgIcon :icon="typePresentation(item.type).icon" class="text-xl" />
              </div>
              <div class="min-w-0 flex-1">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                  <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                      <h3
                        class="text-sm text-g-900 sm:text-base"
                        :class="item.is_read ? 'font-medium' : 'font-semibold'"
                        >{{ item.title }}</h3
                      >
                      <ElTag size="small" effect="plain" :type="typePresentation(item.type).tag">{{
                        formatType(item.type)
                      }}</ElTag>
                      <span
                        v-if="!item.is_read"
                        class="rounded-full bg-theme/10 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-theme"
                        >New</span
                      >
                    </div>
                    <p class="mt-1.5 whitespace-pre-line text-sm leading-6 text-g-600">{{
                      item.body
                    }}</p>
                  </div>
                  <time
                    class="shrink-0 text-xs text-g-500"
                    :datetime="item.created_at"
                    :title="formatFullTimestamp(item.created_at)"
                  >
                    {{ formatRelativeTime(item.created_at) }}
                  </time>
                </div>
                <div v-if="notificationFacts(item).length" class="mt-3 flex flex-wrap gap-2">
                  <span
                    v-for="fact in notificationFacts(item)"
                    :key="`${fact.label}-${fact.value}`"
                    class="inline-flex max-w-full items-center gap-1 rounded border border-g-300 bg-g-200/50 px-2.5 py-1 text-xs text-g-600"
                  >
                    <span class="text-g-500">{{ fact.label }}:</span>
                    <span class="truncate font-medium text-g-800">{{ fact.value }}</span>
                  </span>
                </div>
                <div
                  class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-g-200 pt-3"
                >
                  <span class="text-xs text-g-500">{{
                    item.is_read
                      ? `Read ${formatRelativeTime(item.read_at)}`
                      : 'Unread notification'
                  }}</span>
                  <div class="flex items-center gap-1">
                    <ElButton
                      v-if="!item.is_read"
                      size="small"
                      text
                      :loading="markingId === item.id"
                      @click="handleMarkRead(item)"
                      >Mark read</ElButton
                    >
                    <ElButton
                      v-if="notificationDestination(item)"
                      size="small"
                      type="primary"
                      plain
                      @click="openNotification(item)"
                    >
                      Open<ElIcon class="ml-1"><ArrowRight /></ElIcon>
                    </ElButton>
                  </div>
                </div>
              </div>
            </div>
          </article>
        </div>
      </div>
    </section>

    <div
      v-if="total > pageSize"
      class="flex flex-col items-center justify-between gap-3 border-t border-g-200 px-5 py-4 sm:flex-row sm:px-6"
    >
      <p class="text-xs text-g-500">Showing {{ pageStart }}–{{ pageEnd }} of {{ total }}</p>
      <ElPagination
        v-model:current-page="currentPage"
        v-model:page-size="pageSize"
        :total="total"
        :page-sizes="[10, 20, 50]"
        layout="sizes, prev, pager, next"
        background
        @current-change="loadNotifications"
        @size-change="handlePageSizeChange"
      />
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed, onActivated, onBeforeUnmount, onDeactivated, onMounted, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import { ElMessage } from 'element-plus'
  import { ArrowRight, CircleCheck, Refresh } from '@element-plus/icons-vue'
  import {
    fetchNotifications,
    markAllNotificationsAsRead,
    markNotificationAsRead,
    type InAppNotification,
    type NotificationChannel
  } from '@/api/notifications'
  import { initEcho, onUserNotification } from '@/utils/echo'
  import { useUserStore } from '@/store/modules/user'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'
  import BrowserChatAlertsButton from '@/components/business/BrowserChatAlertsButton.vue'

  defineOptions({ name: 'PortalNotifications' })
  const router = useRouter()
  const userStore = useUserStore()
  const notificationList = ref<InAppNotification[]>([])
  const unreadCount = ref(0)
  const loading = ref(false)
  const markingAll = ref(false)
  const markingId = ref<number | null>(null)
  const filterUnread = ref(false)
  const filterType = ref('')
  const filterChannel = ref<NotificationChannel>('work')
  const currentPage = ref(1)
  const pageSize = ref(20)
  const total = ref(0)
  const realtimeAvailable = ref(false)
  const currentTime = ref(Date.now())
  let unsubscribeNotif: (() => void) | null = null
  let pollTimer: ReturnType<typeof setInterval> | null = null
  let clockTimer: ReturnType<typeof setInterval> | null = null
  let feedStarted = false

  const groupedNotifications = computed(() => {
    const groups = new Map<string, InAppNotification[]>()
    for (const item of notificationList.value) {
      const label = dateGroupLabel(item.created_at)
      groups.set(label, [...(groups.get(label) || []), item])
    }
    return Array.from(groups, ([label, items]) => ({ label, items }))
  })
  const pageStart = computed(() => (total.value ? (currentPage.value - 1) * pageSize.value + 1 : 0))
  const pageEnd = computed(() => Math.min(currentPage.value * pageSize.value, total.value))

  async function loadNotifications() {
    loading.value = true
    try {
      const res = await fetchNotifications({
        page: currentPage.value,
        per_page: pageSize.value,
        unread_only: filterUnread.value,
        type: filterChannel.value === 'chat' ? undefined : filterType.value || undefined,
        channel: filterChannel.value
      })
      notificationList.value = res.data || []
      unreadCount.value = res.unread_count ?? 0
      total.value = res.meta?.total ?? 0
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load notifications.')
    } finally {
      loading.value = false
    }
  }

  function handleFilterChange() {
    if (filterChannel.value === 'chat') {
      filterType.value = ''
    }
    if (filterChannel.value === 'work' && filterType.value === 'chat_message') {
      filterType.value = ''
    }
    currentPage.value = 1
    loadNotifications()
  }
  function handlePageSizeChange() {
    currentPage.value = 1
    loadNotifications()
  }
  function clearFilters() {
    filterUnread.value = false
    filterType.value = ''
    filterChannel.value = 'work'
    currentPage.value = 1
    loadNotifications()
  }

  async function handleMarkRead(item: InAppNotification, quiet = false) {
    if (item.is_read) return
    markingId.value = item.id
    try {
      const res = await markNotificationAsRead(item.id)
      item.is_read = true
      item.read_at = res.data?.read_at || new Date().toISOString()
      unreadCount.value = Math.max(0, unreadCount.value - 1)
      if (filterUnread.value) {
        notificationList.value = notificationList.value.filter((row) => row.id !== item.id)
        total.value = Math.max(0, total.value - 1)
      }
      if (!quiet) ElMessage.success('Notification marked as read.')
    } catch (error: any) {
      if (!quiet) ElMessage.error(error?.message || 'Failed to mark notification as read.')
    } finally {
      markingId.value = null
    }
  }

  async function handleMarkAllRead() {
    markingAll.value = true
    try {
      const res = await markAllNotificationsAsRead(filterChannel.value)
      ElMessage.success(res.message || 'Notifications marked as read.')
      await loadNotifications()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to mark all notifications as read.')
    } finally {
      markingAll.value = false
    }
  }

  function normalizedType(type: string) {
    return type.trim().toUpperCase()
  }
  function typePresentation(type: string): {
    icon: string
    iconClass: string
    tag: 'primary' | 'success' | 'warning' | 'danger' | 'info'
  } {
    switch (normalizedType(type)) {
      case 'QUEUE':
      case 'QUEUE_UPDATE':
        return { icon: 'ri:time-line', iconClass: 'bg-warning/10 text-warning', tag: 'warning' }
      case 'TELLER_BILLING':
        return { icon: 'ri:user-voice-line', iconClass: 'bg-warning/10 text-warning', tag: 'warning' }
      case 'INVOICE':
      case 'BILL_READY':
        return {
          icon: 'ri:file-list-3-line',
          iconClass: 'bg-success/10 text-success',
          tag: 'success'
        }
      case 'PAYMENT':
      case 'PAYMENT_REMINDER':
      case 'PROOF_REVIEW':
      case 'TELLER_PAYMENT':
        return {
          icon: 'ri:bank-card-line',
          iconClass: 'bg-theme/10 text-theme',
          tag: 'primary'
        }
      case 'BILL_CLAIM':
        return {
          icon: 'ri:file-search-line',
          iconClass: 'bg-theme/10 text-theme',
          tag: 'primary'
        }
      case 'TAX':
        return {
          icon: 'ri:file-shield-2-line',
          iconClass: 'bg-g-200 text-g-700',
          tag: 'info'
        }
      case 'CREDIT':
        return { icon: 'ri:funds-line', iconClass: 'bg-theme/10 text-theme', tag: 'primary' }
      case 'CHAT_MESSAGE':
        return {
          icon: 'ri:message-3-line',
          iconClass: 'bg-theme/10 text-theme',
          tag: 'primary'
        }
      case 'SYSTEM':
        return { icon: 'ri:settings-3-line', iconClass: 'bg-g-200 text-g-700', tag: 'info' }
      default:
        return { icon: 'ri:notification-3-line', iconClass: 'bg-g-200 text-g-700', tag: 'info' }
    }
  }

  function formatType(type: string) {
    const aliases: Record<string, string> = {
      QUEUE: 'Queue',
      INVOICE: 'Invoice',
      PAYMENT: 'Payment',
      BILL_CLAIM: 'Bill claim',
      TAX: 'Tax review',
      CREDIT: 'Credit',
      CHAT_MESSAGE: 'Message',
      SYSTEM: 'System'
    }
    const normalized = normalizedType(type)
    return aliases[normalized] || normalized.replaceAll('_', ' ').toLowerCase()
  }

  function notificationFacts(item: InAppNotification) {
    const data = item.data || {}
    const candidates: Array<[string, unknown]> = [
      ['Request', data.transaction_no],
      ['Ticket', data.ticket_number],
      ['Invoice', data.invoice_number],
      ['Receipt', data.receipt_number],
      ['Certificate', data.certificate_no],
      ['Status', data.status]
    ]
    return candidates
      .filter((entry): entry is [string, string | number] =>
        ['string', 'number'].includes(typeof entry[1])
      )
      .slice(0, 3)
      .map(([label, value]) => ({ label, value: String(value) }))
  }

  function billClaimDestination(): string {
    const roles = Array.isArray(userStore.info?.roles) ? userStore.info.roles.map(String) : []
    if (roles.includes('Teller') || roles.includes('Administrator')) {
      return '/bill-claim-review'
    }
    const permissions = (userStore.info as any)?.permissions
    if (Array.isArray(permissions) && permissions.includes('bill_claims:review')) {
      return '/bill-claim-review'
    }
    return '/claim-bill'
  }

  function notificationDestination(item: InAppNotification): string | null {
    const data = item.data || {}
    switch (normalizedType(item.type)) {
      case 'TELLER_BILLING':
        return '/billing-request-queue'
      case 'TELLER_PAYMENT':
        return '/payment-proof-review'
      case 'QUEUE':
      case 'QUEUE_UPDATE':
        return '/my-billing-requests'
      case 'INVOICE':
      case 'BILL_READY':
      case 'PAYMENT':
      case 'PAYMENT_REMINDER':
      case 'PROOF_REVIEW':
        return '/my-bills'
      case 'BILL_CLAIM':
        return billClaimDestination()
      case 'TAX':
        return '/my-tax-evidence'
      case 'CREDIT':
        return '/my-credit'
      case 'CHAT_MESSAGE':
        return data.conversation_id ? `/chat?id=${data.conversation_id}` : '/chat'
      default:
        return null
    }
  }

  async function openNotification(item: InAppNotification) {
    const destination = notificationDestination(item)
    if (!destination) return
    if (!item.is_read) await handleMarkRead(item, true)
    await router.push(destination)
  }
  function formatFullTimestamp(value?: string | null) {
    return value ? formatDateTimeManila(value) : 'Not available'
  }
  function formatRelativeTime(value?: string | null) {
    if (!value) return 'just now'
    const timestamp = new Date(value).getTime()
    if (Number.isNaN(timestamp)) return value
    const seconds = Math.round((timestamp - currentTime.value) / 1000)
    const formatter = new Intl.RelativeTimeFormat('en', { numeric: 'auto' })
    if (Math.abs(seconds) < 60) return formatter.format(seconds, 'second')
    const minutes = Math.round(seconds / 60)
    if (Math.abs(minutes) < 60) return formatter.format(minutes, 'minute')
    const hours = Math.round(minutes / 60)
    if (Math.abs(hours) < 24) return formatter.format(hours, 'hour')
    const days = Math.round(hours / 24)
    if (Math.abs(days) < 7) return formatter.format(days, 'day')
    return formatDateTimeManila(value)
  }
  function dateGroupLabel(value: string) {
    const date = new Date(value)
    const today = new Date(currentTime.value)
    const startToday = new Date(today.getFullYear(), today.getMonth(), today.getDate()).getTime()
    const startDate = new Date(date.getFullYear(), date.getMonth(), date.getDate()).getTime()
    const dayDifference = Math.round((startToday - startDate) / 86_400_000)
    if (dayDifference === 0) return 'Today'
    if (dayDifference === 1) return 'Yesterday'
    if (dayDifference < 7) return 'Earlier this week'
    return 'Earlier'
  }

  function startFeed() {
    if (feedStarted) return
    feedStarted = true
    loadNotifications()
    clockTimer = setInterval(() => (currentTime.value = Date.now()), 60_000)
    const echo = initEcho()
    if (echo) {
      realtimeAvailable.value = true
      const userId = (userStore.info as any)?.id
      if (userId) unsubscribeNotif = onUserNotification(userId, () => loadNotifications())
    } else {
      pollTimer = setInterval(loadNotifications, 15_000)
    }
  }

  function stopFeed() {
    if (!feedStarted) return
    feedStarted = false
    unsubscribeNotif?.()
    unsubscribeNotif = null
    if (pollTimer) clearInterval(pollTimer)
    pollTimer = null
    if (clockTimer) clearInterval(clockTimer)
    clockTimer = null
  }

  onMounted(startFeed)
  onActivated(startFeed)
  onDeactivated(stopFeed)
  onBeforeUnmount(stopFeed)
</script>
