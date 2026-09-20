<template>
  <span :class="['inline-flex items-baseline font-tabular-nums', sizeClass, colorClass, weightClass]">
    <span class="mr-0.5 text-[0.8em] font-medium opacity-80">{{ symbol }}</span>
    <span>{{ formattedValue }}</span>
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue'

interface Props {
  value: number | string | null | undefined
  currency?: string
  highlight?: 'due' | 'paid' | 'credit' | 'neutral' | 'muted'
  size?: 'xs' | 'sm' | 'base' | 'lg' | 'xl' | '2xl'
  weight?: 'normal' | 'medium' | 'semibold' | 'bold'
}

const props = withDefaults(defineProps<Props>(), {
  currency: 'PHP',
  highlight: 'neutral',
  size: 'base',
  weight: 'semibold'
})

const symbol = computed(() => {
  if (props.currency === 'PHP') return '₱'
  if (props.currency === 'USD') return '$'
  return `${props.currency} `
})

const formattedValue = computed(() => {
  if (props.value === null || props.value === undefined || props.value === '') {
    return '0.00'
  }
  const num = typeof props.value === 'string' ? parseFloat(props.value) : props.value
  if (isNaN(num)) {
    return '0.00'
  }
  return num.toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
})

const sizeClass = computed(() => {
  switch (props.size) {
    case 'xs':
      return 'text-xs'
    case 'sm':
      return 'text-sm'
    case 'lg':
      return 'text-lg'
    case 'xl':
      return 'text-xl'
    case '2xl':
      return 'text-2xl tracking-tight'
    default:
      return 'text-base'
  }
})

const weightClass = computed(() => {
  switch (props.weight) {
    case 'normal':
      return 'font-normal'
    case 'medium':
      return 'font-medium'
    case 'bold':
      return 'font-bold'
    default:
      return 'font-semibold'
  }
})

const colorClass = computed(() => {
  switch (props.highlight) {
    case 'due':
      return 'text-amber-700 dark:text-amber-400'
    case 'paid':
      return 'text-emerald-700 dark:text-emerald-400'
    case 'credit':
      return 'text-blue-700 dark:text-blue-400'
    case 'muted':
      return 'text-slate-500 dark:text-slate-400'
    default:
      return 'text-slate-800 dark:text-slate-200'
  }
})
</script>

<style scoped>
.font-tabular-nums {
  font-variant-numeric: tabular-nums;
}
</style>
