<template>
  <div class="payment-policy-page p-4 sm:p-6 space-y-5">
    <ElCard shadow="never">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-lg font-semibold text-slate-800 dark:text-slate-100"
              >Payment Routes &amp; Deadlines</h1
            >
            <ElTag type="info" effect="plain">Versioned financial policy</ElTag>
          </div>
          <p class="mt-1 max-w-3xl text-sm text-slate-500"
            >Publish a new version for new customer instructions. Existing instructions retain their
            selected bills, bank instructions and deadline snapshot.</p
          >
        </div>
        <div class="flex gap-2">
          <ElButton :loading="loading" @click="loadPolicies">Refresh</ElButton>
          <ElButton type="primary" @click="openCreate">New policy draft</ElButton>
        </div>
      </div>
      <ElAlert
        class="mt-4"
        type="warning"
        :closable="false"
        show-icon
        title="Gateway activation is not available here. P3-06 must configure and verify a provider before an eligible online payment can start; do not publish a gateway-enabled policy for current operations."
      />
    </ElCard>

    <ElCard shadow="never" v-loading="loading">
      <template #header
        ><div class="flex items-center justify-between"
          ><span class="font-semibold">Policy versions</span
          ><ElTag>{{ policies.length }} recorded</ElTag></div
        ></template
      >
      <ElEmpty
        v-if="policies.length === 0"
        description="No payment policy is published. Customers cannot receive a payment instruction until one is published."
      />
      <ElTable v-else :data="policies" stripe>
        <ElTableColumn label="Version" width="100"
          ><template #default="{ row }">v{{ row.version_number }}</template></ElTableColumn
        >
        <ElTableColumn label="Status" width="125"
          ><template #default="{ row }"
            ><ElTag :type="row.status === 'PUBLISHED' ? 'success' : 'info'">{{
              row.status
            }}</ElTag></template
          ></ElTableColumn
        >
        <ElTableColumn prop="currency" label="Currency" width="100" />
        <ElTableColumn label="Gateway threshold" min-width="155"
          ><template #default="{ row }">{{
            row.gateway_enabled
              ? formatAmount(row.gateway_threshold_amount || '0', row.currency)
              : 'Manual only'
          }}</template></ElTableColumn
        >
        <ElTableColumn label="Manual deadline" min-width="135"
          ><template #default="{ row }"
            >{{ row.manual_deadline_hours }} hours</template
          ></ElTableColumn
        >
        <ElTableColumn prop="effective_from" label="Effective from" min-width="170" />
        <ElTableColumn label="Publication reason" min-width="220"
          ><template #default="{ row }">{{
            row.publication_reason || 'Draft not yet published'
          }}</template></ElTableColumn
        >
        <ElTableColumn label="Actions" width="110" fixed="right"
          ><template #default="{ row }"
            ><ElButton
              v-if="row.status === 'DRAFT'"
              size="small"
              type="success"
              plain
              @click="publish(row)"
              >Publish</ElButton
            ></template
          ></ElTableColumn
        >
      </ElTable>
    </ElCard>

    <ElDrawer
      v-model="drawerOpen"
      title="New payment-route policy draft"
      size="560px"
      destroy-on-close
    >
      <ElForm label-position="top" @submit.prevent="saveDraft">
        <ElAlert
          type="info"
          :closable="false"
          show-icon
          title="Manual bank instructions are customer-visible and will be frozen on each payment instruction. Do not include credentials or internal-only reconciliation notes."
        />
        <div class="mt-5 grid grid-cols-1 gap-4 sm:grid-cols-2">
          <ElFormItem label="Currency"
            ><ElInput v-model="form.currency" maxlength="3"
          /></ElFormItem>
          <ElFormItem label="Manual payment deadline (hours)"
            ><ElInputNumber
              v-model="form.manual_deadline_hours"
              :min="1"
              :max="720"
              class="!w-full"
          /></ElFormItem>
          <ElFormItem label="Review target (hours)"
            ><ElInputNumber v-model="form.review_target_hours" :min="0" :max="720" class="!w-full"
          /></ElFormItem>
          <ElFormItem label="Clearance target (hours)"
            ><ElInputNumber
              v-model="form.clearance_target_hours"
              :min="0"
              :max="720"
              class="!w-full"
          /></ElFormItem>
          <ElFormItem label="Correction window (hours)"
            ><ElInputNumber
              v-model="form.correction_window_hours"
              :min="0"
              :max="720"
              class="!w-full"
          /></ElFormItem>
          <ElFormItem label="Effective from"
            ><ElDatePicker
              v-model="form.effective_from"
              type="datetime"
              value-format="YYYY-MM-DDTHH:mm:ssZ"
              class="!w-full"
          /></ElFormItem>
        </div>
        <ElFormItem label="Customer-facing manual bank instructions" required
          ><ElInput
            v-model="form.manual_instructions"
            type="textarea"
            :rows="6"
            maxlength="5000"
            show-word-limit
            placeholder="State receiving bank/account instructions, required reference and where the customer can ask for support."
        /></ElFormItem>
        <ElDivider content-position="left">Future gateway route</ElDivider>
        <ElFormItem label="Enable gateway routing"
          ><ElSwitch v-model="form.gateway_enabled"
        /></ElFormItem>
        <ElFormItem v-if="form.gateway_enabled" label="Strict gateway threshold amount"
          ><ElInput v-model="form.gateway_threshold_amount" inputmode="decimal" placeholder="0.00"
        /></ElFormItem>
        <ElAlert
          v-if="form.gateway_enabled"
          type="error"
          :closable="false"
          title="No provider is configured in P3-10. Publishing this policy is allowed for preconfiguration, but gateway-eligible selections will be blocked until P3-06 completes provider verification."
        />
      </ElForm>
      <template #footer
        ><div class="flex justify-end gap-2"
          ><ElButton @click="drawerOpen = false">Cancel</ElButton
          ><ElButton type="primary" :loading="saving" @click="saveDraft">Save draft</ElButton></div
        ></template
      >
    </ElDrawer>
  </div>
</template>

<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    createPaymentPolicy,
    fetchPaymentPolicies,
    publishPaymentPolicy,
    type PaymentPolicyPayload,
    type PaymentPolicyVersion
  } from '@/api/payments'

  defineOptions({ name: 'PaymentPolicies' })

  const policies = ref<PaymentPolicyVersion[]>([])
  const loading = ref(false)
  const saving = ref(false)
  const drawerOpen = ref(false)
  const form = reactive<PaymentPolicyPayload>({
    currency: 'PHP',
    gateway_enabled: false,
    manual_instructions: '',
    manual_deadline_hours: 24,
    review_target_hours: 24,
    clearance_target_hours: 48,
    correction_window_hours: 24,
    effective_from: new Date().toISOString()
  })

  function formatAmount(amount: string, currency: string) {
    return new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(
      Number(amount || 0)
    )
  }

  function resetForm() {
    Object.assign(form, {
      currency: 'PHP',
      gateway_enabled: false,
      gateway_threshold_amount: undefined,
      manual_instructions: '',
      manual_deadline_hours: 24,
      review_target_hours: 24,
      clearance_target_hours: 48,
      correction_window_hours: 24,
      effective_from: new Date().toISOString(),
      effective_to: undefined
    })
  }

  function openCreate() {
    resetForm()
    drawerOpen.value = true
  }

  async function loadPolicies() {
    loading.value = true
    try {
      policies.value = await fetchPaymentPolicies()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load payment policies.')
    } finally {
      loading.value = false
    }
  }

  async function saveDraft() {
    saving.value = true
    try {
      const created = await createPaymentPolicy({ ...form, currency: form.currency?.toUpperCase() })
      policies.value = [created, ...policies.value]
      drawerOpen.value = false
      ElMessage.success(
        'Payment policy draft saved. Publish it when the dates and instructions are approved.'
      )
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to save payment policy draft.')
    } finally {
      saving.value = false
    }
  }

  async function publish(policy: PaymentPolicyVersion) {
    try {
      const { value } = await ElMessageBox.prompt(
        'Record why this policy can be used for new payment instructions.',
        `Publish payment policy v${policy.version_number}`,
        {
          inputPattern: /.{3,}/,
          inputErrorMessage: 'Enter at least 3 characters.'
        }
      )
      const published = await publishPaymentPolicy(policy.id, policy.lock_version, value)
      policies.value = policies.value.map((item) => (item.id === published.id ? published : item))
      ElMessage.success('Payment policy published. Existing instructions were not changed.')
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to publish payment policy.')
    }
  }

  onMounted(loadPolicies)
</script>

<style scoped>
  .payment-policy-page {
    min-height: 100%;
  }
</style>
