<template>
  <div v-if="compact" class="min-w-0 text-sm leading-snug">
    <div class="flex items-center gap-1.5 font-medium" :class="ownerTextClass">
      <ArtSvgIcon :icon="ownerIcon" class="shrink-0 text-sm" />
      <span class="truncate">{{ compactLabel }}</span>
    </div>
    <div class="text-xs text-g-500 line-clamp-2">{{ progress.required_action }}</div>
  </div>

  <div v-else class="rounded-lg border border-g-300 p-3 space-y-3">
    <div class="flex items-center gap-2 text-xs uppercase tracking-wider text-g-500">
      <ArtSvgIcon icon="ri:route-line" class="text-sm" />
      Progress
    </div>

    <div class="rounded-md p-2.5 space-y-1" :class="summaryClass">
      <div class="flex items-center gap-1.5 text-sm font-medium" :class="ownerTextClass">
        <ArtSvgIcon :icon="ownerIcon" class="shrink-0 text-sm" />
        {{ ownerHeading }}
      </div>
      <p class="text-sm text-g-800">{{ progress.required_action }}</p>
      <p v-if="progress.next_step" class="text-xs text-g-600">Next: {{ progress.next_step }}</p>
    </div>

    <ol class="grid grid-cols-2 sm:grid-cols-3 gap-2">
      <li
        v-for="step in progress.steps"
        :key="step.key"
        class="flex items-start gap-2 rounded-md border px-2 py-1.5"
        :class="stepBoxClass(step.status)"
      >
        <ArtSvgIcon
          :icon="stepIcon(step.status)"
          class="mt-0.5 shrink-0 text-base"
          :class="stepIconClass(step.status)"
        />
        <div class="min-w-0">
          <div
            class="text-xs font-medium"
            :class="step.status === 'upcoming' ? 'text-g-500' : 'text-g-900'"
          >
            {{ step.label }}
          </div>
          <div v-if="step.at" class="text-xs text-g-500">{{ formatDateTimeManila(step.at) }}</div>
        </div>
      </li>
    </ol>
  </div>
</template>

<script setup lang="ts">
  import { computed } from 'vue'
  import type { BillingRequestProgress } from '@/api/billingRequests'
  import { formatDateTimeManila } from '@/utils/date/formatDateTime'

  type StepStatus = BillingRequestProgress['steps'][number]['status']

  const props = defineProps<{
    progress: BillingRequestProgress
    compact?: boolean
  }>()

  const ownerIcon = computed(() => {
    if (props.progress.state === 'cancelled') return 'ri:close-circle-line'
    if (props.progress.state === 'complete') return 'ri:checkbox-circle-line'
    if (props.progress.owner.type === 'customer') return 'ri:user-line'
    if (props.progress.owner.type === 'teller') return 'ri:customer-service-2-line'
    return 'ri:time-line'
  })

  const ownerTextClass = computed(() => {
    switch (props.progress.state) {
      case 'attention':
        return 'text-warning'
      case 'complete':
        return 'text-success'
      case 'cancelled':
        return 'text-g-600'
      default:
        return 'text-theme'
    }
  })

  const summaryClass = computed(() => {
    switch (props.progress.state) {
      case 'attention':
        return 'bg-warning/10'
      case 'complete':
        return 'bg-success/10'
      case 'cancelled':
        return 'bg-g-200'
      default:
        return 'bg-theme/10'
    }
  })

  const ownerHeading = computed(() => {
    const { state, owner } = props.progress
    if (state === 'cancelled') return 'Cancelled'
    if (state === 'complete') return 'Complete'
    if (owner.type === 'none') return owner.label
    return `With ${owner.label}`
  })

  const compactLabel = computed(() => {
    if (props.progress.state === 'cancelled') return 'Cancelled'
    if (props.progress.state === 'complete') return 'Complete'
    return props.progress.owner.label
  })

  function stepIcon(status: StepStatus) {
    if (status === 'complete') return 'ri:checkbox-circle-fill'
    if (status === 'current') {
      return props.progress.state === 'attention'
        ? 'ri:error-warning-fill'
        : 'ri:record-circle-line'
    }
    return 'ri:checkbox-blank-circle-line'
  }

  function stepIconClass(status: StepStatus) {
    if (status === 'complete') return 'text-success'
    if (status === 'current') {
      return props.progress.state === 'attention' ? 'text-warning' : 'text-theme'
    }
    return 'text-g-400'
  }

  function stepBoxClass(status: StepStatus) {
    if (status !== 'current') return 'border-g-200'
    return props.progress.state === 'attention'
      ? 'border-warning/40 bg-warning/10'
      : 'border-theme/20 bg-theme/10'
  }
</script>
