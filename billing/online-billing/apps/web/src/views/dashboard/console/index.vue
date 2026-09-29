<template>
  <TellerWorkboard v-if="isTeller && !isAdmin" />
  <div v-else class="dashboard-console-container max-w-7xl mx-auto p-4 sm:p-6 space-y-6">
    <!-- Welcome Header Banner -->
    <div
      class="bg-gradient-to-r from-slate-900 via-slate-800 to-slate-900 text-white p-6 sm:p-8 rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6"
    >
      <div class="space-y-2">
        <div class="flex items-center gap-3">
          <span
            class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wider bg-blue-500/20 text-blue-300 border border-blue-400/30"
          >
            {{ primaryRole }}
          </span>
          <span class="text-xs text-slate-400 font-mono">{{ currentDate }}</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-bold tracking-tight">
          Welcome back, {{ userDisplayName }}
        </h1>
        <p class="text-slate-300 text-sm max-w-xl">
          SCIPSI Online Billing &amp; Port Service Portal. Access your billing records, manage
          payment claims, and monitor financial transactions.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-3">
        <ElButton
          v-if="isCustomer"
          type="primary"
          size="large"
          class="!font-semibold shadow-lg shadow-blue-500/25"
          @click="$router.push('/my-bills')"
        >
          <ElIcon class="mr-1"><Wallet /></ElIcon> My Bills &amp; Payments
        </ElButton>
        <ElButton
          v-else-if="isTeller"
          type="primary"
          size="large"
          class="!font-semibold !bg-amber-500 hover:!bg-amber-400 !border-none !text-slate-950 shadow-lg shadow-amber-500/25"
          @click="$router.push('/payment-proof-review')"
        >
          <ElIcon class="mr-1"><Select /></ElIcon> Open Proof Review Queue
        </ElButton>
        <ElButton
          v-else
          type="primary"
          size="large"
          class="!font-semibold shadow-lg shadow-blue-500/25"
          @click="$router.push('/pricing/tariffs')"
        >
          <ElIcon class="mr-1"><Setting /></ElIcon> Tariffs &amp; Pricing
        </ElButton>
      </div>
    </div>

    <!-- Quick Shortcuts Grid -->
    <div>
      <h2 class="text-base font-semibold text-slate-800 dark:text-slate-100 mb-3"
        >Quick Navigation</h2
      >
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <!-- Customer Portal Card -->
        <div
          v-if="isCustomer || isAdmin"
          class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-blue-400 dark:hover:border-blue-600 transition-all cursor-pointer shadow-sm group"
          @click="$router.push('/my-bills')"
        >
          <div class="flex items-center justify-between mb-3">
            <div
              class="h-10 w-10 rounded-xl bg-blue-50 dark:bg-blue-950/50 flex items-center justify-center text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform"
            >
              <ElIcon :size="20"><Wallet /></ElIcon>
            </div>
            <ElIcon class="text-slate-400 group-hover:text-blue-500 transition-colors"
              ><ArrowRight
            /></ElIcon>
          </div>
          <h3
            class="font-semibold text-slate-800 dark:text-slate-100 text-sm group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors"
          >
            My Bills &amp; Payments
          </h3>
          <p class="text-xs text-slate-500 mt-1">
            View payable bills, select invoices, and submit deposit slips.
          </p>
        </div>

        <!-- Teller Review Card -->
        <div
          v-if="isTeller || isAdmin"
          class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-amber-400 dark:hover:border-amber-600 transition-all cursor-pointer shadow-sm group"
          @click="$router.push('/payment-proof-review')"
        >
          <div class="flex items-center justify-between mb-3">
            <div
              class="h-10 w-10 rounded-xl bg-amber-50 dark:bg-amber-950/50 flex items-center justify-center text-amber-600 dark:text-amber-400 group-hover:scale-110 transition-transform"
            >
              <ElIcon :size="20"><DocumentChecked /></ElIcon>
            </div>
            <ElIcon class="text-slate-400 group-hover:text-amber-500 transition-colors"
              ><ArrowRight
            /></ElIcon>
          </div>
          <h3
            class="font-semibold text-slate-800 dark:text-slate-100 text-sm group-hover:text-amber-600 dark:group-hover:text-amber-400 transition-colors"
          >
            Proof Review Queue
          </h3>
          <p class="text-xs text-slate-500 mt-1">
            Inspect customer bank receipts and issue official receipts.
          </p>
        </div>

        <!-- Pricing & Tariffs -->
        <div
          v-if="isAdmin"
          class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-emerald-400 dark:hover:border-emerald-600 transition-all cursor-pointer shadow-sm group"
          @click="$router.push('/pricing/tariffs')"
        >
          <div class="flex items-center justify-between mb-3">
            <div
              class="h-10 w-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition-transform"
            >
              <ElIcon :size="20"><PriceTag /></ElIcon>
            </div>
            <ElIcon class="text-slate-400 group-hover:text-emerald-500 transition-colors"
              ><ArrowRight
            /></ElIcon>
          </div>
          <h3
            class="font-semibold text-slate-800 dark:text-slate-100 text-sm group-hover:text-emerald-600 dark:group-hover:text-emerald-400 transition-colors"
          >
            Tariffs &amp; Fuel Surcharges
          </h3>
          <p class="text-xs text-slate-500 mt-1">
            Configure versioned tariff rates, schedules, and fuel bands.
          </p>
        </div>

        <!-- Document Studio -->
        <div
          v-if="isAdmin"
          class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-purple-400 dark:hover:border-purple-600 transition-all cursor-pointer shadow-sm group"
          @click="$router.push('/system/document-studio')"
        >
          <div class="flex items-center justify-between mb-3">
            <div
              class="h-10 w-10 rounded-xl bg-purple-50 dark:bg-purple-950/50 flex items-center justify-center text-purple-600 dark:text-purple-400 group-hover:scale-110 transition-transform"
            >
              <ElIcon :size="20"><Tickets /></ElIcon>
            </div>
            <ElIcon class="text-slate-400 group-hover:text-purple-500 transition-colors"
              ><ArrowRight
            /></ElIcon>
          </div>
          <h3
            class="font-semibold text-slate-800 dark:text-slate-100 text-sm group-hover:text-purple-600 dark:group-hover:text-purple-400 transition-colors"
          >
            Document Studio
          </h3>
          <p class="text-xs text-slate-500 mt-1">
            Design and preview official sales invoices and collection receipts.
          </p>
        </div>

        <!-- System Administration -->
        <div
          v-if="isAdmin"
          class="bg-white dark:bg-slate-900 p-5 rounded-xl border border-slate-200/80 dark:border-slate-800 hover:border-slate-400 dark:hover:border-slate-600 transition-all cursor-pointer shadow-sm group"
          @click="$router.push('/system/settings')"
        >
          <div class="flex items-center justify-between mb-3">
            <div
              class="h-10 w-10 rounded-xl bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-600 dark:text-slate-300 group-hover:scale-110 transition-transform"
            >
              <ElIcon :size="20"><Setting /></ElIcon>
            </div>
            <ElIcon class="text-slate-400 group-hover:text-slate-600 transition-colors"
              ><ArrowRight
            /></ElIcon>
          </div>
          <h3
            class="font-semibold text-slate-800 dark:text-slate-100 text-sm group-hover:text-slate-900 dark:group-hover:text-white transition-colors"
          >
            System Settings
          </h3>
          <p class="text-xs text-slate-500 mt-1">
            Organization parameters, currency rules, and audit logs.
          </p>
        </div>
      </div>
    </div>

    <!-- Security & Compliance Status Card -->
    <div
      class="bg-white dark:bg-slate-900 p-6 rounded-2xl border border-slate-200/80 dark:border-slate-800 shadow-sm flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4"
    >
      <div class="flex items-start gap-3.5">
        <div
          class="h-10 w-10 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 flex items-center justify-center text-emerald-600 dark:text-emerald-400 shrink-0"
        >
          <ElIcon :size="22"><Lock /></ElIcon>
        </div>
        <div>
          <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100"
            >Immutable Financial Audit &amp; Fiscal Controls Active</h3
          >
          <p class="text-xs text-slate-500 mt-0.5 max-w-2xl">
            All posted invoices, collection receipts, and payment submissions are protected by
            append-only audit histories, optimistic concurrency locks, and SHA-256 tamper-evident
            digests.
          </p>
        </div>
      </div>
      <div class="shrink-0 flex items-center gap-2">
        <span
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-300 dark:border-emerald-800"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> System Operational
        </span>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import {
    Wallet,
    Select,
    Setting,
    ArrowRight,
    DocumentChecked,
    PriceTag,
    Tickets,
    Lock
  } from '@element-plus/icons-vue'
  import { useUserStore } from '@/store/modules/user'
  import TellerWorkboard from './TellerWorkboard.vue'

  defineOptions({ name: 'Console' })

  const userStore = useUserStore()

  const userDisplayName = computed(() => {
    return userStore.info?.userName || 'User'
  })

  const roles = computed<string[]>(() => {
    return userStore.info?.roles || []
  })

  const isAdmin = computed(
    () => roles.value.includes('Administrator') || roles.value.includes('R_SUPER')
  )
  const isTeller = computed(() => roles.value.includes('Teller'))
  const isCustomer = computed(
    () => roles.value.includes('Customer') || (!isAdmin.value && !isTeller.value)
  )

  const primaryRole = computed(() => {
    if (isAdmin.value) return 'Administrator'
    if (isTeller.value) return 'Teller'
    if (isCustomer.value) return 'Customer Portal'
    return roles.value[0] || 'Member'
  })

  const currentDate = computed(() => {
    return new Intl.DateTimeFormat('en-PH', {
      weekday: 'short',
      year: 'numeric',
      month: 'short',
      day: 'numeric',
      timeZone: 'Asia/Manila'
    }).format(new Date())
  })
</script>

<style scoped>
  .dashboard-console-container {
    animation: fadeIn 0.25s ease-out;
  }

  @keyframes fadeIn {
    from {
      opacity: 0;
      transform: translateY(4px);
    }
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }
</style>
