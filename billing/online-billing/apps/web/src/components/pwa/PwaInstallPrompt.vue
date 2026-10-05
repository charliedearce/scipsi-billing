<template>
  <ElDialog
    v-model="visible"
    title="Install SCIPSI Billing"
    width="min(420px, 94vw)"
    @close="dismiss"
  >
    <p class="text-sm text-g-700">Add SCIPSI Billing to your home screen for quick access.</p>
    <p v-if="offer === 'ios'" class="mt-3 text-sm text-g-700">
      In Safari, tap the Share button, then choose <strong>Add to Home Screen</strong>.
    </p>
    <template #footer>
      <ElButton @click="dismiss">Not now</ElButton>
      <ElButton v-if="offer === 'native'" type="primary" @click="install">Install app</ElButton>
      <ElButton v-else type="primary" @click="dismiss">Got it</ElButton>
    </template>
  </ElDialog>
</template>

<script setup lang="ts">
  import { computed, onMounted, onBeforeUnmount, ref } from 'vue'
  import { useUserStore } from '@/store/modules/user'
  import { installOfferKind } from '@/utils/pwa/installPrompt'

  type InstallEvent = Event & {
    prompt: () => Promise<void>
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>
  }
  const storageKey = 'scipsi-pwa-install-dismissed-at'
  const userStore = useUserStore()
  const promptEvent = ref<InstallEvent | null>(null)
  const dismissedAt = ref(0)
  const installed = ref(false)
  const offer = computed(() =>
    installOfferKind({
      signedIn: userStore.isLogin,
      standalone: installed.value,
      hasPrompt: !!promptEvent.value,
      iosSafari: isIosSafari(),
      dismissedAt: dismissedAt.value,
      now: Date.now()
    })
  )
  const visible = computed({
    get: () => offer.value !== null,
    set: (value: boolean) => {
      if (!value) dismiss()
    }
  })

  function isIosSafari() {
    const ua = navigator.userAgent
    const ios =
      /iPhone|iPad|iPod/.test(ua) ||
      (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)
    return ios && /Safari/.test(ua) && !/CriOS|FxiOS|EdgiOS/.test(ua)
  }
  function isStandalone() {
    return (
      window.matchMedia('(display-mode: standalone)').matches ||
      (navigator as Navigator & { standalone?: boolean }).standalone === true
    )
  }
  function onBeforeInstall(event: Event) {
    event.preventDefault()
    promptEvent.value = event as InstallEvent
  }
  function onInstalled() {
    installed.value = true
    promptEvent.value = null
  }
  function dismiss() {
    dismissedAt.value = Date.now()
    try {
      localStorage.setItem(storageKey, String(dismissedAt.value))
    } catch {
      /* storage unavailable */
    }
  }
  async function install() {
    const event = promptEvent.value
    if (!event) return
    promptEvent.value = null
    await event.prompt()
    const choice = await event.userChoice
    if (choice.outcome === 'dismissed') dismiss()
  }
  onMounted(() => {
    installed.value = isStandalone()
    try {
      dismissedAt.value = Number(localStorage.getItem(storageKey)) || 0
    } catch {
      /* storage unavailable */
    }
    window.addEventListener('beforeinstallprompt', onBeforeInstall)
    window.addEventListener('appinstalled', onInstalled)
  })
  onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstall)
    window.removeEventListener('appinstalled', onInstalled)
  })
</script>
