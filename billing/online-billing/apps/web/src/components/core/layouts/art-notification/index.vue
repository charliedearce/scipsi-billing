<!-- In-app notification panel (W27) -->
<template>
  <div
    class="art-notification-panel art-card-sm !shadow-xl"
    :style="{
      transform: show ? 'scaleY(1)' : 'scaleY(0.9)',
      opacity: show ? 1 : 0
    }"
    v-show="visible"
    @click.stop
  >
    <div class="flex-cb px-3.5 mt-3.5">
      <span class="text-base font-medium text-g-800">Alerts</span>
      <span
        v-if="barActiveIndex === 0"
        class="text-xs text-g-800 px-1.5 py-1 c-p select-none rounded hover:bg-g-200"
        @click="handleMarkAll"
      >
        Mark work read
      </span>
    </div>

    <ul class="box-border flex items-end w-full h-12.5 px-3.5 border-b-d">
      <li
        v-for="(item, index) in barList"
        :key="index"
        class="h-12 leading-12 mr-5 overflow-hidden text-[13px] text-g-700 c-p select-none"
        :class="{ 'bar-active': barActiveIndex === index }"
        @click="barActiveIndex = index"
      >
        {{ item.name }} ({{ item.num }})
      </li>
    </ul>

    <div class="w-full h-[calc(100%-95px)]">
      <div class="h-[calc(100%-60px)] overflow-y-scroll scrollbar-thin">
        <ul v-show="barActiveIndex === 0">
          <li
            v-for="item in realtimeStore.recentNotifications"
            :key="item.id"
            class="box-border flex-c px-3.5 py-3.5 c-p last:border-b-0 hover:bg-g-200/60"
            @click="handleOpenNotification(item)"
          >
            <div
              class="size-9 leading-9 text-center rounded-lg flex-cc"
              :class="item.is_read ? 'bg-g-200 text-g-600' : 'bg-theme/12 text-theme'"
            >
              <ArtSvgIcon class="text-lg !bg-transparent" icon="ri:notification-3-line" />
            </div>
            <div class="w-[calc(100%-45px)] ml-3.5">
              <h4 class="text-sm font-normal leading-5.5 text-g-900">
                {{ item.title }}
                <span
                  v-if="!item.is_read"
                  class="inline-block ml-1 size-1.5 rounded-full bg-danger align-middle"
                ></span>
              </h4>
              <p class="mt-1 text-xs text-g-600 line-clamp-2">{{ item.body }}</p>
              <p class="mt-1.5 text-xs text-g-500">{{ formatTime(item.created_at) }}</p>
            </div>
          </li>
        </ul>

        <ul v-show="barActiveIndex === 1">
          <li
            v-for="item in chatSummaries"
            :key="item.id"
            class="box-border flex-c px-3.5 py-3.5 c-p last:border-b-0 hover:bg-g-200/60"
            @click="openChat(item.id)"
          >
            <div class="w-9 h-9 flex-cc rounded-lg bg-success/12 text-success">
              <ArtSvgIcon icon="ri:message-3-line" class="text-lg" />
            </div>
            <div class="w-[calc(100%-45px)] ml-3.5">
              <h4 class="text-sm font-normal leading-5.5 text-g-900">
                {{ item.customer?.name || item.subject || 'Conversation' }}
                <span
                  v-if="(item.unread_count || 0) > 0"
                  class="inline-block ml-1 size-1.5 rounded-full bg-danger align-middle"
                ></span>
              </h4>
              <p class="mt-1 text-xs text-g-600 line-clamp-2">{{ item.subject }}</p>
              <p class="mt-1.5 text-xs text-g-500">
                {{ item.unread_count || 0 }} unread message{{
                  (item.unread_count || 0) === 1 ? '' : 's'
                }}
              </p>
            </div>
          </li>
        </ul>

        <div
          v-show="currentTabIsEmpty"
          class="relative top-25 h-full text-g-500 text-center !bg-transparent"
        >
          <ArtSvgIcon icon="system-uicons:inbox" class="text-5xl" />
          <p class="mt-3.5 text-xs !bg-transparent">No {{ barList[barActiveIndex].name }} yet</p>
        </div>
      </div>

      <div class="relative box-border w-full px-3.5">
        <ElButton class="w-full mt-3" @click="handleViewAll" v-ripple>View all</ElButton>
      </div>
    </div>

    <div class="h-25"></div>
  </div>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import { useRouter } from 'vue-router'
  import { mittBus } from '@/utils/sys'
  import { useRealtimeStore } from '@/store/modules/realtime'
  import { useUserStore } from '@/store/modules/user'
  import { fetchConversations, type Conversation } from '@/api/chat'
  import type { InAppNotification } from '@/api/notifications'

  defineOptions({ name: 'ArtNotification' })

  const props = defineProps<{
    value: boolean
  }>()

  const emit = defineEmits<{
    'update:value': [value: boolean]
  }>()

  const router = useRouter()
  const realtimeStore = useRealtimeStore()
  const userStore = useUserStore()

  const show = ref(false)
  const visible = ref(false)
  const barActiveIndex = ref(0)
  const chatSummaries = ref<Conversation[]>([])

  const barList = computed(() => [
    { name: 'Work', num: realtimeStore.unreadNotificationCount },
    { name: 'Chat', num: realtimeStore.unreadChatCount }
  ])

  const currentTabIsEmpty = computed(() => {
    if (barActiveIndex.value === 0) return realtimeStore.recentNotifications.length === 0
    return chatSummaries.value.length === 0
  })

  function userRoles(): string[] {
    const roles = userStore.info?.roles
    return Array.isArray(roles) ? roles.map(String) : []
  }

  function canReviewBillClaims(): boolean {
    const roles = userRoles()
    if (roles.includes('Teller')) return true
    if (roles.includes('Administrator')) return false
    const permissions = (userStore.info as any)?.permissions
    return Array.isArray(permissions) && permissions.includes('bill_claims:review')
  }

  function billClaimDestination(): string {
    if (canReviewBillClaims()) return '/bill-claim-review'
    if (userRoles().includes('Administrator')) return '/notifications'
    return '/claim-bill'
  }

  function formatTime(iso?: string | null): string {
    if (!iso) return ''
    try {
      return new Date(iso).toLocaleString()
    } catch {
      return iso
    }
  }

  function showNotice(open: boolean) {
    if (open) {
      visible.value = true
      setTimeout(() => {
        show.value = true
      }, 5)
      realtimeStore.refreshUnreadCounts()
      realtimeStore.refreshRecentNotifications()
      loadChatSummaries()
    } else {
      show.value = false
      setTimeout(() => {
        visible.value = false
      }, 350)
    }
  }

  async function loadChatSummaries() {
    try {
      const res = await fetchConversations({ per_page: 20, status: 'open' })
      chatSummaries.value = (res.data || [])
        .filter((c) => (c.unread_count || 0) > 0)
        .sort((a, b) => (b.unread_count || 0) - (a.unread_count || 0))
    } catch {
      chatSummaries.value = []
    }
  }

  async function handleMarkAll() {
    if (barActiveIndex.value !== 0) return
    await realtimeStore.markAllRead()
  }

  async function handleOpenNotification(item: InAppNotification) {
    if (!item.is_read) {
      await realtimeStore.markOneRead(item.id)
    }
    emit('update:value', false)

    const data = item.data || {}
    if (item.type === 'TELLER_BILLING') {
      router.push('/billing-request-queue')
      return
    }
    if (item.type === 'TELLER_PAYMENT') {
      router.push('/payment-proof-review')
      return
    }
    if (data.conversation_id) {
      mittBus.emit('openChat', { conversationId: Number(data.conversation_id) })
      return
    }
    if (data.billing_request_id) {
      router.push('/my-billing-requests')
      return
    }
    if (data.bill_claim_request_id) {
      router.push(billClaimDestination())
      return
    }
    if (item.type === 'INVOICE' || data.invoice_id) {
      router.push('/my-bills')
      return
    }
    if (item.type === 'PAYMENT' || data.submission_id) {
      const roles = userRoles()
      router.push(
        roles.includes('Administrator') && !roles.includes('Teller')
          ? '/notifications'
          : '/payment-proof-review'
      )
      return
    }
    if (
      item.type === 'TAX' ||
      data.certificate_id ||
      data.exemption_id ||
      data.renew_path === '/my-tax-evidence'
    ) {
      router.push('/my-tax-evidence')
      return
    }
    router.push('/notifications')
  }

  function openChat(conversationId: number) {
    emit('update:value', false)
    mittBus.emit('openChat', { conversationId })
  }

  function handleViewAll() {
    emit('update:value', false)
    if (barActiveIndex.value === 1) {
      router.push('/chat')
    } else {
      router.push('/notifications')
    }
  }

  watch(
    () => props.value,
    (newValue) => {
      showNotice(newValue)
    }
  )

  watch(
    () => realtimeStore.panelOpenTick,
    () => {
      if (props.value) {
        loadChatSummaries()
      }
    }
  )
</script>

<style scoped>
  @reference '@styles/core/tailwind.css';

  .art-notification-panel {
    @apply absolute
    top-14.5
    right-5
    w-90
    h-125
    overflow-hidden
    transition-all
    duration-300
    origin-top
    will-change-[top,left]
    max-[640px]:top-[65px]
    max-[640px]:right-0
    max-[640px]:w-full
    max-[640px]:h-[80vh];
  }

  .bar-active {
    color: var(--theme-color) !important;
    border-bottom: 2px solid var(--theme-color);
  }

  .scrollbar-thin::-webkit-scrollbar {
    width: 5px !important;
  }

  .dark .scrollbar-thin::-webkit-scrollbar-track {
    background-color: var(--default-box-color);
  }

  .dark .scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: #222 !important;
  }

  .line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
  }
</style>
