<template>
  <span
    :class="[
      'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium border transition-colors',
      tagStyles.bg,
      tagStyles.text,
      tagStyles.border,
      sizeClasses
    ]"
  >
    <span v-if="dot" :class="['h-1.5 w-1.5 rounded-full', tagStyles.dot]" />
    <slot>{{ displayLabel }}</slot>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  status?: string
  label?: string
  size?: 'small' | 'default' | 'large'
  dot?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  status: '',
  size: 'default',
  dot: true
})

const normalizedStatus = computed(() => {
  return (props.status || '').toUpperCase().trim()
})

const displayLabel = computed(() => {
  if (props.label) return props.label
  const s = normalizedStatus.value
  switch (s) {
    case 'SUBMITTED':
      return 'Submitted'
    case 'IN_REVIEW':
      return 'In Review'
    case 'CLAIMED':
      return 'Claimed by Teller'
    case 'APPROVED':
      return 'Approved'
    case 'POSTED':
      return 'Posted'
    case 'PAID':
      return 'Paid'
    case 'REJECTED':
      return 'Rejected'
    case 'REVERSED':
      return 'Reversed'
    case 'CANCELLED':
      return 'Cancelled'
    case 'DRAFT':
      return 'Draft'
    case 'ACTIVE':
      return 'Active'
    case 'SUSPENDED':
      return 'Suspended'
    case 'PENDING':
      return 'Pending'
    case 'SCHEDULED':
      return 'Scheduled'
    case 'PUBLISHED':
      return 'Published'
    case 'RETIRED':
      return 'Retired'
    default:
      return props.status || 'Unknown'
  }
})

const tagStyles = computed(() => {
  const s = normalizedStatus.value
  switch (s) {
    case 'APPROVED':
    case 'POSTED':
    case 'PAID':
    case 'ACTIVE':
    case 'PUBLISHED':
      return {
        bg: 'bg-emerald-50 dark:bg-emerald-950/40',
        text: 'text-emerald-700 dark:text-emerald-300',
        border: 'border-emerald-200 dark:border-emerald-800',
        dot: 'bg-emerald-500'
      }
    case 'SUBMITTED':
    case 'IN_REVIEW':
    case 'PENDING':
    case 'SCHEDULED':
      return {
        bg: 'bg-amber-50 dark:bg-amber-950/40',
        text: 'text-amber-700 dark:text-amber-300',
        border: 'border-amber-200 dark:border-amber-800',
        dot: 'bg-amber-500'
      }
    case 'CLAIMED':
      return {
        bg: 'bg-sky-50 dark:bg-sky-950/40',
        text: 'text-sky-700 dark:text-sky-300',
        border: 'border-sky-200 dark:border-sky-800',
        dot: 'bg-sky-500'
      }
    case 'REJECTED':
    case 'CANCELLED':
    case 'REVERSED':
    case 'SUSPENDED':
      return {
        bg: 'bg-rose-50 dark:bg-rose-950/40',
        text: 'text-rose-700 dark:text-rose-300',
        border: 'border-rose-200 dark:border-rose-800',
        dot: 'bg-rose-500'
      }
    case 'DRAFT':
    case 'RETIRED':
    default:
      return {
        bg: 'bg-slate-50 dark:bg-slate-900/50',
        text: 'text-slate-700 dark:text-slate-300',
        border: 'border-slate-200 dark:border-slate-800',
        dot: 'bg-slate-400'
      }
  }
})

const sizeClasses = computed(() => {
  switch (props.size) {
    case 'small':
      return 'text-[11px] px-2 py-0.5'
    case 'large':
      return 'text-sm px-3 py-1'
    default:
      return 'text-xs px-2.5 py-0.5'
  }
})
</script>
