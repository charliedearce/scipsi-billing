<template>
  <div class="p-4 sm:p-6 max-w-6xl mx-auto space-y-6">
    <section
      class="rounded-2xl bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 p-6 flex items-center justify-between"
    >
      <div>
        <h1 class="text-xl font-bold">VIP Credit &amp; Collections</h1>
        <p class="text-sm text-slate-500 mt-1">
          Publish versioned terms, limits, overdue restrictions and late-charge policies. Existing
          credit charges keep their captured terms; late charges never rewrite an invoice.
        </p>
      </div>
      <ElButton :loading="loading" @click="load">Refresh</ElButton>
    </section>

    <ElCard shadow="never">
      <template #header
        ><h2 class="font-semibold">New organization credit policy draft</h2></template
      >
      <ElForm :model="form" label-position="top" class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
        <ElFormItem label="Currency"><ElInput v-model="form.currency" maxlength="3" /></ElFormItem>
        <ElFormItem label="Credit limit mode">
          <ElSelect v-model="form.default_credit_limit_mode">
            <ElOption label="Capped limit" value="CAPPED" />
            <ElOption label="Explicitly unlimited" value="UNLIMITED" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem v-if="form.default_credit_limit_mode === 'CAPPED'" label="Default limit">
          <ElInput v-model="form.default_credit_limit_amount" inputmode="decimal" />
        </ElFormItem>
        <ElFormItem label="Payment terms (calendar days)">
          <ElInputNumber v-model="form.payment_terms_days" :min="1" :max="3650" />
        </ElFormItem>
        <ElFormItem label="Due-date basis">
          <ElSelect v-model="form.due_date_basis">
            <ElOption label="Invoice date" value="INVOICE_DATE" />
            <ElOption label="Credit-charge date" value="CREDIT_CHARGE_DATE" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="Overdue restriction">
          <ElSelect v-model="form.overdue_restriction">
            <ElOption label="Allow" value="ALLOW" />
            <ElOption label="Warn" value="WARN" />
            <ElOption label="Block new credit" value="BLOCK" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="Grace days">
          <ElInputNumber v-model="form.overdue_grace_days" :min="0" :max="365" />
        </ElFormItem>
        <ElFormItem label="Effective from">
          <ElDatePicker
            v-model="form.effective_from"
            type="datetime"
            value-format="YYYY-MM-DDTHH:mm:ssZ"
            class="!w-full"
          />
        </ElFormItem>
        <ElFormItem label="Allow account-specific overrides" class="md:col-span-2">
          <ElSwitch v-model="form.allow_customer_overrides" />
        </ElFormItem>
      </ElForm>
      <div class="flex justify-end">
        <ElButton type="primary" :loading="saving" @click="create">Create draft</ElButton>
      </div>
    </ElCard>

    <ElCard shadow="never">
      <template #header><h2 class="font-semibold">Credit policy history</h2></template>
      <ElTable :data="policies">
        <ElTableColumn prop="version_number" label="Version" width="100" />
        <ElTableColumn prop="currency" label="Currency" width="100" />
        <ElTableColumn label="Limit" min-width="160">
          <template #default="{ row }">{{
            row.default_credit_limit_mode === 'UNLIMITED'
              ? 'Unlimited'
              : money(row.default_credit_limit_amount, row.currency)
          }}</template>
        </ElTableColumn>
        <ElTableColumn label="Terms" min-width="160">
          <template #default="{ row }"
            >{{ row.payment_terms_days }} days · {{ row.due_date_basis }}</template
          >
        </ElTableColumn>
        <ElTableColumn prop="overdue_restriction" label="Overdue" width="130" />
        <ElTableColumn prop="status" label="Status" width="120" />
        <ElTableColumn label="Actions" width="130">
          <template #default="{ row }">
            <ElButton
              v-if="row.status === 'DRAFT'"
              type="primary"
              size="small"
              @click="publish(row)"
              >Publish</ElButton
            >
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElCard shadow="never">
      <template #header>
        <div class="flex items-center justify-between gap-3">
          <div>
            <h2 class="font-semibold">VIP late-charge policy (W30)</h2>
            <p class="text-xs text-slate-500 mt-1">
              Assessments are separate from invoices and fiscal documents. Accountant tax/GL mapping
              remains pending before operational activation.
            </p>
          </div>
          <ElButton :loading="lateRunning" @click="runLateCharges">Run assessment</ElButton>
        </div>
      </template>
      <ElForm
        :model="lateForm"
        label-position="top"
        class="grid grid-cols-1 md:grid-cols-2 gap-x-5 mb-4"
      >
        <ElFormItem label="Basis">
          <ElSelect v-model="lateForm.basis">
            <ElOption label="Percentage of unpaid principal" value="PERCENTAGE" />
            <ElOption label="Fixed amount" value="FIXED" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="Cadence">
          <ElSelect v-model="lateForm.cadence">
            <ElOption label="Once" value="ONCE" />
            <ElOption label="Monthly" value="MONTHLY" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem v-if="lateForm.basis === 'PERCENTAGE'" label="Percentage rate">
          <ElInput v-model="lateForm.percentage_rate" inputmode="decimal" />
        </ElFormItem>
        <ElFormItem v-else label="Fixed amount">
          <ElInput v-model="lateForm.fixed_amount" inputmode="decimal" />
        </ElFormItem>
        <ElFormItem label="Grace days">
          <ElInputNumber v-model="lateForm.grace_days" :min="0" :max="3650" />
        </ElFormItem>
        <ElFormItem label="Cap amount">
          <ElInput v-model="lateForm.cap_amount" inputmode="decimal" />
        </ElFormItem>
        <ElFormItem label="Effective from">
          <ElDatePicker
            v-model="lateForm.effective_from"
            type="datetime"
            value-format="YYYY-MM-DDTHH:mm:ssZ"
            class="!w-full"
          />
        </ElFormItem>
        <ElFormItem label="Contract reference">
          <ElInput v-model="lateForm.contract_reference" maxlength="128" />
        </ElFormItem>
      </ElForm>
      <p class="text-xs text-slate-500 mb-3">
        Default bands: 1-30 and 31+ (contiguous, non-overlapping). Formula applies to unpaid
        principal only; compounding is disabled.
      </p>
      <div class="flex justify-end mb-6">
        <ElButton type="primary" :loading="lateSaving" @click="createLatePolicy"
          >Create late-charge draft</ElButton
        >
      </div>
      <ElTable :data="latePolicies">
        <ElTableColumn prop="version_number" label="Version" width="100" />
        <ElTableColumn prop="basis" label="Basis" width="120" />
        <ElTableColumn prop="cadence" label="Cadence" width="110" />
        <ElTableColumn label="Rate / amount" min-width="140">
          <template #default="{ row }">{{
            row.basis === 'PERCENTAGE'
              ? `${row.percentage_rate}%`
              : money(row.fixed_amount, row.currency)
          }}</template>
        </ElTableColumn>
        <ElTableColumn prop="status" label="Status" width="120" />
        <ElTableColumn label="Actions" width="130">
          <template #default="{ row }">
            <ElButton
              v-if="row.status === 'DRAFT'"
              type="primary"
              size="small"
              @click="publishLate(row)"
              >Publish</ElButton
            >
          </template>
        </ElTableColumn>
      </ElTable>
    </ElCard>

    <ElCard shadow="never">
      <template #header><h2 class="font-semibold">Recent late-charge assessments</h2></template>
      <ElTable :data="lateAssessments">
        <ElTableColumn label="Customer" min-width="160">
          <template #default="{ row }">{{ row.customer?.name || '—' }}</template>
        </ElTableColumn>
        <ElTableColumn label="Invoice" width="140">
          <template #default="{ row }">{{ row.invoice?.invoice_number || '—' }}</template>
        </ElTableColumn>
        <ElTableColumn prop="as_of_date" label="As of" width="120" />
        <ElTableColumn prop="days_past_due" label="Days" width="80" />
        <ElTableColumn label="Amount" width="120">
          <template #default="{ row }">{{ money(row.assessed_amount) }}</template>
        </ElTableColumn>
        <ElTableColumn prop="status" label="Status" width="120" />
        <ElTableColumn prop="fiscal_mapping_status" label="Fiscal" min-width="180" />
      </ElTable>
    </ElCard>
  </div>
</template>
<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    createCreditPolicy,
    fetchCreditPolicies,
    publishCreditPolicy,
    type CreditPolicyVersion
  } from '@/api/vipCredit'
  import {
    createLateChargePolicy,
    fetchLateChargeAssessments,
    fetchLateChargePolicies,
    publishLateChargePolicy,
    runLateChargeAssessments,
    type LateChargeAssessment,
    type LateChargePolicyVersion
  } from '@/api/lateCharges'

  defineOptions({ name: 'VipCreditPolicies' })
  const loading = ref(false)
  const saving = ref(false)
  const lateSaving = ref(false)
  const lateRunning = ref(false)
  const policies = ref<CreditPolicyVersion[]>([])
  const latePolicies = ref<LateChargePolicyVersion[]>([])
  const lateAssessments = ref<LateChargeAssessment[]>([])
  const form = reactive({
    currency: 'PHP',
    default_credit_limit_mode: 'CAPPED' as 'CAPPED' | 'UNLIMITED',
    default_credit_limit_amount: '',
    payment_terms_days: 30,
    due_date_basis: 'CREDIT_CHARGE_DATE' as 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE',
    overdue_restriction: 'BLOCK' as 'ALLOW' | 'WARN' | 'BLOCK',
    overdue_grace_days: 0,
    allow_customer_overrides: true,
    effective_from: new Date().toISOString()
  })
  const lateForm = reactive({
    currency: 'PHP',
    basis: 'PERCENTAGE' as 'FIXED' | 'PERCENTAGE',
    percentage_rate: '1.5',
    fixed_amount: '',
    cadence: 'ONCE' as 'ONCE' | 'MONTHLY',
    grace_days: 0,
    cap_amount: '',
    contract_reference: '',
    effective_from: new Date().toISOString()
  })
  const money = (amount?: string | null, currency = 'PHP') =>
    amount === null || amount === undefined
      ? '—'
      : new Intl.NumberFormat('en-PH', { style: 'currency', currency }).format(Number(amount))

  async function load() {
    loading.value = true
    try {
      const [credit, late, assessments] = await Promise.all([
        fetchCreditPolicies(),
        fetchLateChargePolicies(),
        fetchLateChargeAssessments()
      ])
      policies.value = Array.isArray(credit) ? credit : ((credit as any)?.data ?? [])
      latePolicies.value = Array.isArray(late) ? late : ((late as any)?.data ?? [])
      const rows = (assessments as any)?.data ?? assessments
      lateAssessments.value = Array.isArray(rows) ? rows : []
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load credit policies.')
    } finally {
      loading.value = false
    }
  }

  async function create() {
    saving.value = true
    try {
      await createCreditPolicy({
        ...form,
        default_credit_limit_amount:
          form.default_credit_limit_mode === 'CAPPED' ? form.default_credit_limit_amount : null
      })
      ElMessage.success('Credit policy draft created.')
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to create policy draft.')
    } finally {
      saving.value = false
    }
  }

  async function publish(policy: CreditPolicyVersion) {
    try {
      const result = await ElMessageBox.prompt(
        'Explain why this policy is being published. Existing credit charges will not be changed.',
        'Publish credit policy',
        { inputPattern: /.{3,}/, inputErrorMessage: 'Give a reason of at least three characters.' }
      )
      await publishCreditPolicy(policy.id, policy.lock_version, result.value)
      ElMessage.success('Credit policy published.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to publish policy.')
    }
  }

  async function createLatePolicy() {
    lateSaving.value = true
    try {
      await createLateChargePolicy({
        currency: lateForm.currency,
        enabled: true,
        grace_days: lateForm.grace_days,
        basis: lateForm.basis,
        percentage_rate: lateForm.basis === 'PERCENTAGE' ? lateForm.percentage_rate : null,
        fixed_amount: lateForm.basis === 'FIXED' ? lateForm.fixed_amount : null,
        cadence: lateForm.cadence,
        cap_amount: lateForm.cap_amount || null,
        rounding_mode: 'TRUNCATE_2',
        contract_reference: lateForm.contract_reference || null,
        effective_from: lateForm.effective_from,
        bands: [
          { days_from: 1, days_to: 30, label: '1-30' },
          { days_from: 31, days_to: null, label: '31+' }
        ]
      })
      ElMessage.success('Late-charge policy draft created.')
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to create late-charge draft.')
    } finally {
      lateSaving.value = false
    }
  }

  async function publishLate(policy: LateChargePolicyVersion) {
    try {
      const result = await ElMessageBox.prompt(
        'Publishing captures this policy on future VIP credit charges only. It does not backfill old debt or create fiscal documents.',
        'Publish late-charge policy',
        { inputPattern: /.{3,}/, inputErrorMessage: 'Give a reason of at least three characters.' }
      )
      await publishLateChargePolicy(policy.id, policy.lock_version, result.value)
      ElMessage.success('Late-charge policy published.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to publish late-charge policy.')
    }
  }

  async function runLateCharges() {
    lateRunning.value = true
    try {
      const result = await runLateChargeAssessments()
      const payload = (result as any)?.data ?? result
      ElMessage.success(
        `Assessment run complete: created ${payload.created}, reused ${payload.reused}, held ${payload.held}, skipped ${payload.skipped}.`
      )
      await load()
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to run late-charge assessment.')
    } finally {
      lateRunning.value = false
    }
  }

  onMounted(load)
</script>
