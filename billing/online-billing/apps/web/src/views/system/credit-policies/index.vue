<template>
  <div class="page-content space-y-5">
    <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:safe-2-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <h1 class="text-xl font-medium text-g-900">VIP Credit &amp; Collections</h1>
          <p class="mt-1 max-w-2xl text-sm text-g-500">
            Publish versioned terms, limits, overdue restrictions and late-charge policies. Existing
            credit charges keep their captured terms; late charges never rewrite an invoice.
          </p>
        </div>
      </div>
      <ElButton :loading="loading" @click="load">
        <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
        Refresh
      </ElButton>
    </header>

    <section class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>New organization credit policy draft</h4>
          <p>
            Set the default rules for VIP accounts. Creating a draft saves these values for review;
            only publishing makes the version available for new credit charges.
          </p>
        </div>
      </div>
      <ElForm :model="form" label-position="top" class="grid grid-cols-1 md:grid-cols-2 gap-x-5">
        <ElFormItem label="Currency">
          <ElInput v-model="form.currency" maxlength="3" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Currency used for this policy and its credit limit.</p
          >
        </ElFormItem>
        <ElFormItem label="Credit limit mode">
          <ElSelect v-model="form.default_credit_limit_mode">
            <ElOption label="Capped limit" value="CAPPED" />
            <ElOption label="Explicitly unlimited" value="UNLIMITED" />
          </ElSelect>
          <p class="w-full text-xs text-g-500 mt-1">
            Capped limits new credit by amount. Unlimited removes the amount cap; other credit rules
            still apply.
          </p>
        </ElFormItem>
        <ElFormItem v-if="form.default_credit_limit_mode === 'CAPPED'" label="Default limit">
          <ElInput v-model="form.default_credit_limit_amount" inputmode="decimal" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Maximum outstanding credit for an account using the default limit.</p
          >
        </ElFormItem>
        <ElFormItem label="Payment terms (calendar days)">
          <ElInputNumber v-model="form.payment_terms_days" :min="1" :max="3650" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Days added to the selected basis date to set a new credit charge's due date.</p
          >
        </ElFormItem>
        <ElFormItem label="Due-date basis">
          <ElSelect v-model="form.due_date_basis">
            <ElOption label="Invoice date" value="INVOICE_DATE" />
            <ElOption label="Credit-charge date" value="CREDIT_CHARGE_DATE" />
          </ElSelect>
          <p class="w-full text-xs text-g-500 mt-1"
            >Start the payment term from the invoice date or the day the bill is charged to
            credit.</p
          >
        </ElFormItem>
        <ElFormItem label="Overdue restriction">
          <ElSelect v-model="form.overdue_restriction">
            <ElOption label="Allow" value="ALLOW" />
            <ElOption label="Warn" value="WARN" />
            <ElOption label="Block new credit" value="BLOCK" />
          </ElSelect>
          <p class="w-full text-xs text-g-500 mt-1"
            >Allow or warn lets new credit continue. Block stops new credit when an unpaid charge
            passes its due date plus grace days; existing debt can still be repaid.</p
          >
        </ElFormItem>
        <ElFormItem label="Overdue grace days">
          <ElInputNumber v-model="form.overdue_grace_days" :min="0" :max="365" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Extra days after the due date before the overdue restriction applies. This does not
            move the due date.</p
          >
        </ElFormItem>
        <ElFormItem label="Effective from">
          <ElDatePicker
            v-model="form.effective_from"
            type="datetime"
            value-format="YYYY-MM-DDTHH:mm:ssZ"
            class="!w-full"
          />
          <p class="w-full text-xs text-g-500 mt-1"
            >When the published version may start applying to new credit activity. Prefer a start at
            or after the current published policy. If the draft starts earlier, publish advances it
            and ends the open prior policy so windows do not overlap.</p
          >
        </ElFormItem>
        <ElFormItem label="Allow account-specific overrides" class="md:col-span-2">
          <ElSwitch v-model="form.allow_customer_overrides" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Permit approved VIP account profiles to use their own limit, terms, or overdue rules
            instead of these defaults.</p
          >
        </ElFormItem>
      </ElForm>
      <div class="flex justify-end">
        <ElButton type="primary" :loading="saving" @click="create">Create draft</ElButton>
      </div>
    </section>

    <section class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>Credit policy history</h4>
          <p>
            Drafts can be published or deleted. Published versions apply by effective date; existing
            charges keep their captured terms.
          </p>
        </div>
      </div>
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
            >{{ row.payment_terms_days }} days from
            {{
              row.due_date_basis === 'INVOICE_DATE' ? 'invoice date' : 'credit-charge date'
            }}</template
          >
        </ElTableColumn>
        <ElTableColumn label="Overdue rule" min-width="150">
          <template #default="{ row }">{{
            row.overdue_restriction === 'BLOCK' ? 'Block new credit' : row.overdue_restriction
          }}</template>
        </ElTableColumn>
        <ElTableColumn prop="status" label="Status" width="120" />
        <ElTableColumn label="Actions" width="180">
          <template #default="{ row }">
            <div v-if="row.status === 'DRAFT'" class="flex flex-wrap gap-2">
              <ElButton type="primary" size="small" @click="publish(row)">Publish</ElButton>
              <ElButton type="danger" size="small" plain @click="removeDraft(row)">Delete</ElButton>
            </div>
          </template>
        </ElTableColumn>
      </ElTable>
    </section>

    <section class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>VIP late-charge policy</h4>
          <p>
            Late charges are separate from invoices. Tax, document, and accounting treatment still
            needs accountant review. Run assessment checks eligible unpaid VIP credit for today.
          </p>
        </div>
        <ElButton :loading="lateRunning" @click="runLateCharges">
          <ArtSvgIcon icon="ri:play-circle-line" class="mr-1" />
          Run late-charge assessment
        </ElButton>
      </div>
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
          <p class="w-full text-xs text-g-500 mt-1"
            >Use a percentage of unpaid principal or a fixed amount per assessment.</p
          >
        </ElFormItem>
        <ElFormItem label="Cadence">
          <ElSelect v-model="lateForm.cadence">
            <ElOption label="Once" value="ONCE" />
            <ElOption label="Monthly" value="MONTHLY" />
          </ElSelect>
          <p class="w-full text-xs text-g-500 mt-1"
            >Assess once for a credit charge, or once per eligible monthly cycle.</p
          >
        </ElFormItem>
        <ElFormItem v-if="lateForm.basis === 'PERCENTAGE'" label="Percentage rate">
          <ElInput v-model="lateForm.percentage_rate" inputmode="decimal" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Rate applied to unpaid principal only; earlier late charges are excluded.</p
          >
        </ElFormItem>
        <ElFormItem v-else label="Fixed amount">
          <ElInput v-model="lateForm.fixed_amount" inputmode="decimal" />
          <p class="w-full text-xs text-g-500 mt-1">Amount charged for each eligible assessment.</p>
        </ElFormItem>
        <ElFormItem label="Late-charge grace days">
          <ElInputNumber v-model="lateForm.grace_days" :min="0" :max="3650" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Days past due with no late charge. Separate from the overdue credit restriction
            above.</p
          >
        </ElFormItem>
        <ElFormItem label="Cap amount">
          <ElInput v-model="lateForm.cap_amount" inputmode="decimal" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Maximum amount for one assessment. Leave blank for no per-assessment cap.</p
          >
        </ElFormItem>
        <ElFormItem label="Effective from">
          <ElDatePicker
            v-model="lateForm.effective_from"
            type="datetime"
            value-format="YYYY-MM-DDTHH:mm:ssZ"
            class="!w-full"
          />
          <p class="w-full text-xs text-g-500 mt-1"
            >When this published policy can be captured on future VIP credit charges.</p
          >
        </ElFormItem>
        <ElFormItem label="Contract reference">
          <ElInput v-model="lateForm.contract_reference" maxlength="128" />
          <p class="w-full text-xs text-g-500 mt-1"
            >Optional reference to the agreed customer terms supporting this charge.</p
          >
        </ElFormItem>
      </ElForm>
      <p class="text-xs text-g-500 mb-3">
        Fixed aging bands: 1–30 and 31+ days past due. The same rate or amount applies in both
        bands. The calculation excludes earlier late charges and truncates to two decimals.
      </p>
      <div class="flex justify-end mb-6">
        <ElButton type="primary" :loading="lateSaving" @click="createLatePolicy"
          >Create late-charge draft</ElButton
        >
      </div>
      <p class="text-xs text-g-500 mb-3"
        >Create a draft to review the terms. Publishing applies it only to future VIP credit
        charges; it does not backfill old debt.</p
      >
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
        <ElTableColumn label="Actions" width="180">
          <template #default="{ row }">
            <div v-if="row.status === 'DRAFT'" class="flex flex-wrap gap-2">
              <ElButton type="primary" size="small" @click="publishLate(row)">Publish</ElButton>
              <ElButton type="danger" size="small" plain @click="removeLateDraft(row)"
                >Delete</ElButton
              >
            </div>
          </template>
        </ElTableColumn>
      </ElTable>
    </section>

    <section class="art-card p-5">
      <div class="art-card-header">
        <div class="title">
          <h4>Recent late-charge assessments</h4>
          <p>
            Each row shows the checked date, days past due, assessed amount, and fiscal review
            status. Waive does not create a fiscal document; assessments stay pending accountant
            review until the fiscal gate is closed.
          </p>
        </div>
      </div>
      <ElAlert
        class="mb-4"
        type="info"
        :closable="false"
        show-icon
        title="Assessments remain PENDING_ACCOUNTANT_REVIEW until fiscal mapping is approved. Waive clears the assessment only."
      />
      <ElTable :data="lateAssessments">
        <ElTableColumn label="Customer" min-width="160">
          <template #default="{ row }">{{ row.customer?.name || '—' }}</template>
        </ElTableColumn>
        <ElTableColumn label="Invoice" width="140">
          <template #default="{ row }">{{ row.invoice?.invoice_number || '—' }}</template>
        </ElTableColumn>
        <ElTableColumn prop="as_of_date" label="Checked on" width="120" />
        <ElTableColumn prop="days_past_due" label="Days past due" width="120" />
        <ElTableColumn label="Amount" width="120">
          <template #default="{ row }">{{ money(row.assessed_amount) }}</template>
        </ElTableColumn>
        <ElTableColumn prop="status" label="Status" width="120" />
        <ElTableColumn label="Tax / accounting review" min-width="180">
          <template #default="{ row }">{{
            row.fiscal_mapping_status === 'PENDING_ACCOUNTANT_REVIEW'
              ? 'Pending accountant review'
              : row.fiscal_mapping_status
          }}</template>
        </ElTableColumn>
        <ElTableColumn label="Actions" width="110" fixed="right">
          <template #default="{ row }">
            <ElButton
              v-if="canWaiveAssessment(row)"
              type="warning"
              size="small"
              link
              @click="waiveAssessment(row)"
              >Waive</ElButton
            >
          </template>
        </ElTableColumn>
      </ElTable>
    </section>
  </div>
</template>
<script setup lang="ts">
  import { onMounted, reactive, ref } from 'vue'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import {
    createCreditPolicy,
    deleteCreditPolicyDraft,
    fetchCreditPolicies,
    publishCreditPolicy,
    type CreditPolicyVersion
  } from '@/api/vipCredit'
  import {
    createLateChargePolicy,
    deleteLateChargePolicyDraft,
    fetchLateChargeAssessments,
    fetchLateChargePolicies,
    publishLateChargePolicy,
    runLateChargeAssessments,
    waiveLateChargeAssessment,
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

  async function removeDraft(policy: CreditPolicyVersion) {
    try {
      await ElMessageBox.confirm(
        `Delete credit policy draft v${policy.version_number}? This cannot be undone.`,
        'Delete draft',
        { type: 'warning', confirmButtonText: 'Delete draft', cancelButtonText: 'Cancel' }
      )
      await deleteCreditPolicyDraft(policy.id, policy.lock_version)
      ElMessage.success('Credit policy draft deleted.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to delete credit policy draft.')
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

  async function removeLateDraft(policy: LateChargePolicyVersion) {
    try {
      await ElMessageBox.confirm(
        `Delete late-charge policy draft v${policy.version_number}? This cannot be undone.`,
        'Delete draft',
        { type: 'warning', confirmButtonText: 'Delete draft', cancelButtonText: 'Cancel' }
      )
      await deleteLateChargePolicyDraft(policy.id, policy.lock_version)
      ElMessage.success('Late-charge policy draft deleted.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to delete late-charge policy draft.')
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

  function canWaiveAssessment(row: LateChargeAssessment) {
    return row.status === 'POSTED' || row.status === 'ON_HOLD'
  }

  async function waiveAssessment(row: LateChargeAssessment) {
    try {
      const result = await ElMessageBox.prompt(
        'Waive clears this assessment only. It does not create a fiscal document or reverse posted collections.',
        'Waive late-charge assessment',
        {
          inputPattern: /.{3,}/,
          inputErrorMessage: 'Give a reason of at least three characters.',
          confirmButtonText: 'Waive',
          type: 'warning'
        }
      )
      await waiveLateChargeAssessment(row.id, row.lock_version, result.value)
      ElMessage.success('Late-charge assessment waived.')
      await load()
    } catch (error: any) {
      if (error !== 'cancel' && error !== 'close')
        ElMessage.error(error?.message || 'Unable to waive assessment.')
    }
  }

  onMounted(load)
</script>
