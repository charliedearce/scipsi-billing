<template>
  <div v-if="hasBadges || loadFailed" class="flex flex-wrap items-center gap-1.5">
    <ElTooltip
      v-if="hasNonVat"
      content="Approved Non-VAT ruling valid on this bill date. Service coverage is checked for each bill line."
      placement="top"
    >
      <ElTag size="small" type="success" effect="plain">Non-VAT ruling</ElTag>
    </ElTooltip>
    <ElTooltip
      v-if="hasZeroRated"
      content="Approved zero-rated ruling valid on this bill date. Service coverage is checked for each bill line."
      placement="top"
    >
      <ElTag size="small" type="success" effect="plain">Zero-rated ruling</ElTag>
    </ElTooltip>
    <ElTooltip
      v-if="hasWithholding"
      content="Approved Form 2307 covers this bill date. Any credit is applied during payment and receipt allocation."
      placement="top"
    >
      <ElTag size="small" type="info" effect="plain">Withholding file</ElTag>
    </ElTooltip>
    <ElTooltip
      v-if="loadFailed"
      content="Tax evidence could not be checked. Refresh before posting."
      placement="top"
    >
      <ElTag size="small" type="warning" effect="plain">Tax status unavailable</ElTag>
    </ElTooltip>
  </div>
</template>

<script setup lang="ts">
  import { computed, ref, watch } from 'vue'
  import { fetchAdminExemptions, fetchAdminWithholding } from '@/api/taxEvidence'

  const props = defineProps<{
    customerId?: number | null
    businessDate?: string
  }>()

  const hasNonVat = ref(false)
  const hasZeroRated = ref(false)
  const hasWithholding = ref(false)
  const loadFailed = ref(false)
  const hasBadges = computed(() => hasNonVat.value || hasZeroRated.value || hasWithholding.value)
  let requestSequence = 0

  watch(
    () => [props.customerId, props.businessDate] as const,
    async ([customerId, businessDate]) => {
      const sequence = ++requestSequence
      hasNonVat.value = false
      hasZeroRated.value = false
      hasWithholding.value = false
      loadFailed.value = false
      if (!customerId || !businessDate) return

      const [nonVat, zeroRated, withholding] = await Promise.allSettled([
        fetchAdminExemptions({
          customer_id: customerId,
          status: 'APPROVED',
          business_date: businessDate,
          exemption_type: 'VAT_EXEMPT'
        }),
        fetchAdminExemptions({
          customer_id: customerId,
          status: 'APPROVED',
          business_date: businessDate,
          exemption_type: 'ZERO_RATED'
        }),
        fetchAdminWithholding({
          customer_id: customerId,
          status: 'APPROVED',
          business_date: businessDate
        })
      ])
      if (sequence !== requestSequence) return

      if (nonVat.status === 'fulfilled') {
        hasNonVat.value = nonVat.value.total > 0
      } else {
        loadFailed.value = true
      }
      if (zeroRated.status === 'fulfilled') {
        hasZeroRated.value = zeroRated.value.total > 0
      } else {
        loadFailed.value = true
      }
      if (withholding.status === 'fulfilled') {
        hasWithholding.value = withholding.value.total > 0
      } else {
        loadFailed.value = true
      }
    },
    { immediate: true }
  )
</script>
