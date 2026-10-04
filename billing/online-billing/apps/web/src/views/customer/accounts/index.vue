<template>
  <div class="customer-accounts-page page-content !p-0 overflow-hidden">
    <header
      class="flex flex-col gap-4 border-b border-g-200 px-5 py-5 sm:flex-row sm:items-start sm:justify-between sm:px-6"
    >
      <div class="flex items-start gap-3.5">
        <div class="size-11 flex-cc shrink-0 rounded-lg bg-theme/10 text-theme">
          <ArtSvgIcon icon="ri:building-2-line" class="text-2xl" />
        </div>
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h1 class="text-xl font-medium text-g-900">Customer Accounts</h1>
            <ElTag type="success" size="small" effect="plain">W32</ElTag>
          </div>
          <p class="mt-1 max-w-2xl text-sm text-g-500">
            Business accounts, portal links, verified contacts, buyer profiles, and tax verification
            request status.
          </p>
        </div>
      </div>
      <div class="flex flex-wrap items-center gap-2">
        <ElButton type="primary" @click="openCreateDialog">
          <ArtSvgIcon icon="ri:add-line" class="mr-1" />
          Add account
        </ElButton>
        <ElButton :loading="loading" @click="fetchCustomers">
          <ArtSvgIcon icon="ri:refresh-line" class="mr-1" />
          Refresh
        </ElButton>
      </div>
    </header>

    <section class="border-b border-g-200 bg-g-100/40 px-5 py-3.5 sm:px-6">
      <ElAlert
        type="info"
        :closable="false"
        show-icon
        title="Matching company name or phone never auto-links an account. Mobile OTP proves possession only. Buyer profile edits affect future drafts only and never rewrite issued fiscal artifacts."
      />
    </section>

    <section class="space-y-4 p-5 sm:p-6">
      <div class="flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div class="flex flex-wrap items-center gap-3">
          <ElInput
            v-model="filters.search"
            placeholder="Search account # or company name"
            clearable
            class="!w-72"
            @keyup.enter="fetchCustomers"
            @clear="fetchCustomers"
          >
            <template #prefix>
              <ArtSvgIcon icon="ri:search-line" class="text-g-500" />
            </template>
          </ElInput>

          <ElSelect
            v-model="filters.status"
            placeholder="Status"
            clearable
            class="!w-36"
            @change="fetchCustomers"
          >
            <ElOption label="All statuses" value="" />
            <ElOption label="Active" value="active" />
            <ElOption label="Pending" value="pending" />
            <ElOption label="Suspended" value="suspended" />
            <ElOption label="Archived" value="archived" />
          </ElSelect>

          <ElSelect
            v-model="filters.customer_type"
            placeholder="Type"
            clearable
            class="!w-36"
            @change="fetchCustomers"
          >
            <ElOption label="All types" value="" />
            <ElOption label="Business" value="business" />
            <ElOption label="Walk-in" value="walk_in" />
            <ElOption label="VIP" value="vip" />
          </ElSelect>

          <ElSelect
            v-model="filters.tax_status"
            placeholder="Tax verification"
            clearable
            class="!w-48"
            @change="applyClientFilters"
          >
            <ElOption label="Any tax status" value="" />
            <ElOption label="Needs review" value="PENDING_REVIEW" />
            <ElOption label="Needs correction" value="NEEDS_CORRECTION" />
            <ElOption label="Approved" value="APPROVED" />
            <ElOption label="Rejected" value="REJECTED" />
            <ElOption label="No requests" value="NONE" />
          </ElSelect>

          <ElButton type="primary" plain @click="fetchCustomers">Filter</ElButton>
        </div>

        <div class="text-xs text-g-500">
          {{ filteredCustomers.length }} shown · {{ pagination.total }} total
        </div>
      </div>

      <ElTable
        :data="filteredCustomers"
        v-loading="loading"
        stripe
        border
        empty-text="No customer accounts match these filters."
        class="w-full"
      >
        <ElTableColumn label="Account" prop="account_number" width="160">
          <template #default="{ row }">
            <span class="font-mono text-sm font-medium text-theme">
              {{ row.account_number || '—' }}
            </span>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Company / buyer" prop="name" min-width="200">
          <template #default="{ row }">
            <div class="font-medium text-g-900">{{ row.name }}</div>
            <div class="mt-0.5 text-xs text-g-500">
              Profile v{{ row.buyer_profile?.current_version || 1 }}
            </div>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Type" prop="customer_type" width="110">
          <template #default="{ row }">
            <ElTag size="small" :type="typeTag(row.customer_type)">
              {{ typeLabel(row.customer_type) }}
            </ElTag>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Status" prop="status" width="110">
          <template #default="{ row }">
            <ElTag size="small" :type="accountStatusTag(row.status)">
              {{ statusLabel(row.status) }}
            </ElTag>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Tax verification" min-width="220">
          <template #default="{ row }">
            <div class="flex flex-col gap-1.5 py-0.5">
              <div class="flex items-center gap-1.5">
                <span class="w-14 shrink-0 text-[11px] uppercase tracking-wide text-g-500"
                  >2307</span
                >
                <ElTag
                  size="small"
                  effect="plain"
                  :type="taxStatusTag(taxTrack(row, 'withholding').display_status)"
                >
                  {{ taxStatusLabel(taxTrack(row, 'withholding')) }}
                </ElTag>
                <span
                  v-if="taxTrack(row, 'withholding').pending_count"
                  class="text-[11px] text-warning"
                >
                  {{ taxTrack(row, 'withholding').pending_count }} open
                </span>
              </div>
              <div class="flex items-center gap-1.5">
                <span class="w-14 shrink-0 text-[11px] uppercase tracking-wide text-g-500"
                  >Exempt</span
                >
                <ElTag
                  size="small"
                  effect="plain"
                  :type="taxStatusTag(taxTrack(row, 'exemption').display_status)"
                >
                  {{ taxStatusLabel(taxTrack(row, 'exemption')) }}
                </ElTag>
                <span
                  v-if="taxTrack(row, 'exemption').pending_count"
                  class="text-[11px] text-warning"
                >
                  {{ taxTrack(row, 'exemption').pending_count }} open
                </span>
              </div>
            </div>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Links / contacts" width="140" align="center">
          <template #default="{ row }">
            <div class="text-sm text-g-800">{{ linkedUsers(row).length }} linked</div>
            <div class="text-xs text-g-500">
              {{ getVerifiedCount(row) }}/{{ row.contact_points?.length || 0 }} verified
            </div>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Created" width="140">
          <template #default="{ row }">
            <span class="text-xs text-g-500">{{ formatDateTimeManila(row.created_at) }}</span>
          </template>
        </ElTableColumn>

        <ElTableColumn label="Actions" width="200" fixed="right">
          <template #default="{ row }">
            <div class="flex items-center justify-end gap-0.5">
              <ElTooltip content="View detail" placement="top">
                <ElButton
                  link
                  type="primary"
                  :icon="View"
                  aria-label="View detail"
                  @click="openDetail(row)"
                />
              </ElTooltip>
              <ElTooltip content="Edit account" placement="top">
                <ElButton
                  link
                  type="primary"
                  :icon="Edit"
                  aria-label="Edit account"
                  @click="openEditDialog(row)"
                />
              </ElTooltip>
              <ElTooltip content="Change status" placement="top">
                <ElButton
                  link
                  type="warning"
                  :icon="Switch"
                  aria-label="Change status"
                  @click="openStatusDialog(row)"
                />
              </ElTooltip>
              <ElTooltip content="Tax evidence review" placement="top">
                <ElButton
                  link
                  type="primary"
                  :icon="DocumentChecked"
                  aria-label="Tax evidence review"
                  @click="goTaxEvidence(row)"
                />
              </ElTooltip>
            </div>
          </template>
        </ElTableColumn>
      </ElTable>

      <div class="flex justify-end">
        <ElPagination
          v-model:current-page="pagination.page"
          v-model:page-size="pagination.per_page"
          :total="pagination.total"
          :page-sizes="[10, 20, 50]"
          layout="total, sizes, prev, pager, next"
          @size-change="fetchCustomers"
          @current-change="fetchCustomers"
        />
      </div>
    </section>

    <ElDrawer
      v-model="drawerVisible"
      title="Customer account"
      size="680px"
      direction="rtl"
      destroy-on-close
    >
      <div v-if="selectedCustomer" class="space-y-6">
        <div class="rounded-lg border border-g-200 bg-g-100/50 p-4">
          <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
              <div class="font-mono text-sm text-theme">
                {{ selectedCustomer.account_number || '—' }}
              </div>
              <div class="mt-1 text-base font-medium text-g-900">{{ selectedCustomer.name }}</div>
            </div>
            <div class="flex flex-wrap items-center gap-1.5">
              <ElTag size="small" :type="typeTag(selectedCustomer.customer_type)">
                {{ typeLabel(selectedCustomer.customer_type) }}
              </ElTag>
              <ElTag size="small" :type="accountStatusTag(selectedCustomer.status)">
                {{ statusLabel(selectedCustomer.status) }}
              </ElTag>
            </div>
          </div>

          <ElForm label-position="top" class="min-w-0" @submit.prevent="saveIdentity">
            <ElFormItem label="VIP client">
              <ElSwitch v-model="identityForm.is_vip" @change="onVipToggle" />
              <p class="mt-1 text-xs text-g-500">
                Regular customers default to 11001-0000. VIP clients need their own account number.
                Tagging VIP does not open credit borrowing — publish a VIP Credit profile below after
                the organization credit policy is published.
              </p>
            </ElFormItem>
            <ElFormItem label="Account number" required>
              <div class="flex gap-2">
                <ElInput
                  v-model="identityForm.account_number"
                  :placeholder="identityForm.is_vip ? 'VIP account number' : '11001-0000'"
                  maxlength="64"
                  show-word-limit
                  class="min-w-0 flex-1"
                />
                <ElButton
                  v-if="identityForm.is_vip"
                  :loading="generatingAccountNumber"
                  @click="generateVipAccountNumber"
                >
                  Generate
                </ElButton>
              </div>
              <p v-if="identityForm.is_vip" class="mt-1 text-xs text-g-500">
                Generate a unique VIP-###### number, or type your own.
              </p>
            </ElFormItem>
            <ElFormItem label="Company / registered buyer name" required>
              <ElInput v-model="identityForm.name" maxlength="255" show-word-limit />
            </ElFormItem>
            <ElButton type="primary" :loading="savingIdentity" @click="saveIdentity">
              Save account
            </ElButton>
          </ElForm>
        </div>

        <div
          v-if="String(selectedCustomer.customer_type || '').toLowerCase() === 'vip'"
          class="rounded-lg border border-g-200 bg-g-100/50 p-4"
        >
          <div class="mb-3 flex flex-wrap items-start justify-between gap-2">
            <div>
              <h4 class="text-sm font-medium text-g-800">VIP Credit profile</h4>
              <p class="mt-1 text-xs text-g-500">
                Hold or disable blocks new charges only. Repayment of existing credit debt stays
                available. A published organization credit policy must be effective before you can
                activate a profile.
              </p>
            </div>
            <ElTag
              v-if="creditProfileLatest"
              size="small"
              :type="creditProfileStatusTag(creditProfileLatest.status)"
            >
              {{ creditProfileLatest.status }} · v{{ creditProfileLatest.version_number }}
            </ElTag>
            <ElTag v-else size="small" type="info">No profile yet</ElTag>
          </div>

          <div v-loading="creditProfileLoading" class="space-y-3">
            <div
              v-if="creditProfileAccount"
              class="rounded-md border border-g-200 bg-box p-3 text-xs text-g-600"
            >
              <p>
                Account #{{ creditProfileAccount.id }} · lock v{{
                  creditProfileAccount.lock_version
                }}
              </p>
              <p v-if="creditProfileLatest" class="mt-1">
                Effective
                {{ formatDateTimeManila(creditProfileLatest.effective_from) }}
                <template v-if="creditProfileLatest.effective_to">
                  → {{ formatDateTimeManila(creditProfileLatest.effective_to) }}
                </template>
                <template v-else> → open</template>
              </p>
              <p v-if="creditProfileLatest?.reason" class="mt-1 text-g-500">
                {{ creditProfileLatest.reason }}
              </p>
            </div>

            <ElForm label-position="top" class="min-w-0" @submit.prevent="publishCreditProfile">
              <div class="grid gap-3 sm:grid-cols-2">
                <ElFormItem label="Status" required>
                  <ElSelect v-model="creditProfileForm.status" class="w-full">
                    <ElOption label="Active — allow new charges" value="ACTIVE" />
                    <ElOption label="Held — block new charges" value="HELD" />
                    <ElOption label="Disabled — block new charges" value="DISABLED" />
                  </ElSelect>
                </ElFormItem>
                <ElFormItem label="Effective from" required>
                  <ElDatePicker
                    v-model="creditProfileForm.effective_from"
                    type="datetime"
                    value-format="YYYY-MM-DDTHH:mm:ssZ"
                    class="!w-full"
                  />
                </ElFormItem>
                <ElFormItem label="Effective to (optional)">
                  <ElDatePicker
                    v-model="creditProfileForm.effective_to"
                    type="datetime"
                    value-format="YYYY-MM-DDTHH:mm:ssZ"
                    clearable
                    class="!w-full"
                  />
                </ElFormItem>
                <ElFormItem label="Reason" required class="sm:col-span-2">
                  <ElInput
                    v-model="creditProfileForm.reason"
                    type="textarea"
                    :rows="2"
                    maxlength="1000"
                    show-word-limit
                    placeholder="Why this profile version is being published"
                  />
                </ElFormItem>
              </div>

              <template v-if="creditOverridesAllowed">
                <ElCheckbox v-model="creditProfileForm.use_overrides" class="mb-2">
                  Use account-specific overrides
                </ElCheckbox>
                <div
                  v-if="creditProfileForm.use_overrides"
                  class="mb-3 grid gap-3 rounded-md border border-g-200 p-3 sm:grid-cols-2"
                >
                  <ElFormItem label="Limit mode override">
                    <ElSelect
                      v-model="creditProfileForm.credit_limit_mode_override"
                      clearable
                      placeholder="Inherit org policy"
                      class="w-full"
                    >
                      <ElOption label="Capped" value="CAPPED" />
                      <ElOption label="Unlimited" value="UNLIMITED" />
                    </ElSelect>
                  </ElFormItem>
                  <ElFormItem
                    v-if="creditProfileForm.credit_limit_mode_override === 'CAPPED'"
                    label="Limit amount override"
                  >
                    <ElInput
                      v-model="creditProfileForm.credit_limit_amount_override"
                      inputmode="decimal"
                    />
                  </ElFormItem>
                  <ElFormItem label="Payment terms days override">
                    <ElInputNumber
                      v-model="creditProfileForm.payment_terms_days_override"
                      :min="1"
                      :max="3650"
                      controls-position="right"
                      class="!w-full"
                    />
                  </ElFormItem>
                  <ElFormItem label="Due-date basis override">
                    <ElSelect
                      v-model="creditProfileForm.due_date_basis_override"
                      clearable
                      placeholder="Inherit org policy"
                      class="w-full"
                    >
                      <ElOption label="Invoice date" value="INVOICE_DATE" />
                      <ElOption label="Credit-charge date" value="CREDIT_CHARGE_DATE" />
                    </ElSelect>
                  </ElFormItem>
                  <ElFormItem label="Overdue restriction override">
                    <ElSelect
                      v-model="creditProfileForm.overdue_restriction_override"
                      clearable
                      placeholder="Inherit org policy"
                      class="w-full"
                    >
                      <ElOption label="Allow" value="ALLOW" />
                      <ElOption label="Warn" value="WARN" />
                      <ElOption label="Block new credit" value="BLOCK" />
                    </ElSelect>
                  </ElFormItem>
                  <ElFormItem label="Overdue grace days override">
                    <ElInputNumber
                      v-model="creditProfileForm.overdue_grace_days_override"
                      :min="0"
                      :max="365"
                      controls-position="right"
                      class="!w-full"
                    />
                  </ElFormItem>
                </div>
              </template>
              <p v-else class="mb-3 text-xs text-g-500">
                Organization policy does not allow account-specific overrides, or no published
                policy is loaded yet.
              </p>

              <ElButton type="primary" :loading="savingCreditProfile" @click="publishCreditProfile">
                Publish credit profile
              </ElButton>
            </ElForm>
          </div>
        </div>

        <div>
          <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
            <h4 class="text-sm font-medium text-g-800">Tax verification requests</h4>
            <ElButton link type="primary" @click="goTaxEvidence(selectedCustomer)">
              Open Tax Evidence Review
            </ElButton>
          </div>
          <div class="grid gap-3 sm:grid-cols-2">
            <div class="rounded-lg border border-g-200 p-3">
              <div class="mb-2 flex items-center justify-between gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-g-500"
                  >Withholding 2307</span
                >
                <ElTag
                  size="small"
                  :type="taxStatusTag(taxTrack(selectedCustomer, 'withholding').display_status)"
                >
                  {{ taxStatusLabel(taxTrack(selectedCustomer, 'withholding')) }}
                </ElTag>
              </div>
              <p class="text-xs text-g-500">
                {{ taxTrack(selectedCustomer, 'withholding').total || 0 }} request(s) ·
                {{ taxTrack(selectedCustomer, 'withholding').pending_count || 0 }} open ·
                {{ taxTrack(selectedCustomer, 'withholding').approved_count || 0 }} approved
              </p>
              <ElTable
                v-if="taxRequests(selectedCustomer, 'withholding').length"
                :data="taxRequests(selectedCustomer, 'withholding')"
                size="small"
                class="mt-2"
                empty-text="No 2307 requests"
              >
                <ElTableColumn label="Certificate" min-width="120">
                  <template #default="{ row }">
                    <span class="font-mono text-xs">{{ row.certificate_no || `#${row.id}` }}</span>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Status" width="120">
                  <template #default="{ row }">
                    <ElTag size="small" effect="plain" :type="taxStatusTag(row.status)">
                      {{ row.status }}
                    </ElTag>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Period" min-width="150">
                  <template #default="{ row }">
                    <span class="text-xs text-g-500">{{
                      formatDateRangeManila(row.period_from, row.period_to)
                    }}</span>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="" width="48" fixed="right">
                  <template #default="{ row }">
                    <ElTooltip v-if="row.private_file_id" content="View file" placement="top">
                      <ElButton
                        link
                        type="primary"
                        :icon="View"
                        aria-label="View file"
                        @click="openTaxFilePreview(row)"
                      />
                    </ElTooltip>
                  </template>
                </ElTableColumn>
              </ElTable>
              <p v-else class="mt-2 text-xs text-g-500">No withholding requests yet.</p>
            </div>

            <div class="rounded-lg border border-g-200 p-3">
              <div class="mb-2 flex items-center justify-between gap-2">
                <span class="text-xs font-medium uppercase tracking-wide text-g-500"
                  >Non-VAT / zero-rated</span
                >
                <ElTag
                  size="small"
                  :type="taxStatusTag(taxTrack(selectedCustomer, 'exemption').display_status)"
                >
                  {{ taxStatusLabel(taxTrack(selectedCustomer, 'exemption')) }}
                </ElTag>
              </div>
              <p class="text-xs text-g-500">
                {{ taxTrack(selectedCustomer, 'exemption').total || 0 }} request(s) ·
                {{ taxTrack(selectedCustomer, 'exemption').pending_count || 0 }} open ·
                {{ taxTrack(selectedCustomer, 'exemption').approved_count || 0 }} approved
              </p>
              <ElTable
                v-if="taxRequests(selectedCustomer, 'exemption').length"
                :data="taxRequests(selectedCustomer, 'exemption')"
                size="small"
                class="mt-2"
                empty-text="No exemption requests"
              >
                <ElTableColumn label="Ruling" min-width="120">
                  <template #default="{ row }">
                    <span class="font-mono text-xs">{{
                      row.ruling_or_cert_no || row.exemption_type || `#${row.id}`
                    }}</span>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Status" width="120">
                  <template #default="{ row }">
                    <ElTag size="small" effect="plain" :type="taxStatusTag(row.status)">
                      {{ row.status }}
                    </ElTag>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="Validity" min-width="150">
                  <template #default="{ row }">
                    <span class="text-xs text-g-500">{{
                      formatDateRangeManila(row.valid_from, row.valid_to)
                    }}</span>
                  </template>
                </ElTableColumn>
                <ElTableColumn label="" width="48" fixed="right">
                  <template #default="{ row }">
                    <ElTooltip v-if="row.private_file_id" content="View file" placement="top">
                      <ElButton
                        link
                        type="primary"
                        :icon="View"
                        aria-label="View file"
                        @click="openTaxFilePreview(row)"
                      />
                    </ElTooltip>
                  </template>
                </ElTableColumn>
              </ElTable>
              <p v-else class="mt-2 text-xs text-g-500">No exemption requests yet.</p>
            </div>
          </div>
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between gap-2">
            <h4 class="text-sm font-medium text-g-800">Portal user links</h4>
            <ElTag size="small" effect="plain"
              >{{ linkedUsers(selectedCustomer).length }} linked</ElTag
            >
          </div>
          <ElTable
            :data="linkedUsers(selectedCustomer)"
            size="small"
            border
            empty-text="No portal users linked to this account."
          >
            <ElTableColumn label="User" min-width="140">
              <template #default="{ row }">{{ row.user?.name || '—' }}</template>
            </ElTableColumn>
            <ElTableColumn label="Email" min-width="160">
              <template #default="{ row }">{{ row.user?.email || '—' }}</template>
            </ElTableColumn>
            <ElTableColumn label="Authority" prop="authority_role" width="120">
              <template #default="{ row }">
                <ElTag size="small" type="info">{{ row.authority_role || '—' }}</ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Link" width="100" align="center">
              <template #default="{ row }">
                <ElTag size="small" :type="row.is_active ? 'success' : 'danger'">
                  {{ row.is_active ? 'Active' : 'Inactive' }}
                </ElTag>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>

        <div>
          <h4 class="mb-2 text-sm font-medium text-g-800">Verified contacts</h4>
          <ElTable
            :data="selectedCustomer.contact_points || []"
            size="small"
            border
            empty-text="No contact points on this account."
          >
            <ElTableColumn label="Channel" prop="type" width="100">
              <template #default="{ row }">
                <ElTag size="small" :type="row.type === 'mobile' ? 'success' : 'primary'">
                  {{ row.type }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Value" prop="value" min-width="150" />
            <ElTableColumn label="Verification" width="120" align="center">
              <template #default="{ row }">
                <ElTag size="small" :type="row.is_verified ? 'success' : 'warning'">
                  {{ row.is_verified ? 'Verified' : 'Unverified' }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Verified at" width="150">
              <template #default="{ row }">
                <span class="text-xs text-g-500">{{ formatDateTimeManila(row.verified_at) }}</span>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>

        <div>
          <div class="mb-2 flex items-center justify-between gap-2">
            <h4 class="text-sm font-medium text-g-800">Buyer profile versions</h4>
            <span class="text-xs text-g-500"
              >Current v{{ selectedCustomer.buyer_profile?.current_version || 1 }}</span
            >
          </div>
          <ElTable
            :data="selectedCustomer.buyer_profile?.versions || []"
            size="small"
            border
            empty-text="No buyer profile versions."
          >
            <ElTableColumn label="Ver" prop="version" width="60" align="center">
              <template #default="{ row }">
                <span class="font-medium">v{{ row.version }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Registered name" prop="registered_name" min-width="150" />
            <ElTableColumn label="TIN" prop="tin" width="130">
              <template #default="{ row }">
                <span class="font-mono text-xs">{{ row.tin || 'Unset' }}</span>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Status" prop="status" width="120" align="center">
              <template #default="{ row }">
                <ElTag
                  size="small"
                  :type="
                    row.status === 'active'
                      ? 'success'
                      : row.status === 'pending_review'
                        ? 'warning'
                        : 'info'
                  "
                >
                  {{ row.status }}
                </ElTag>
              </template>
            </ElTableColumn>
            <ElTableColumn label="Action" width="130" fixed="right">
              <template #default="{ row }">
                <div v-if="row.status === 'pending_review'" class="flex gap-1">
                  <ElButton
                    size="small"
                    type="success"
                    link
                    @click="handleReviewVersion(row, 'approve')"
                  >
                    Approve
                  </ElButton>
                  <ElButton
                    size="small"
                    type="danger"
                    link
                    @click="handleReviewVersion(row, 'reject')"
                  >
                    Reject
                  </ElButton>
                </div>
                <span v-else class="text-xs text-g-400">Immutable</span>
              </template>
            </ElTableColumn>
          </ElTable>
        </div>
      </div>
    </ElDrawer>

    <ElDialog
      v-model="taxPreviewOpen"
      :title="taxPreviewTitle"
      width="min(920px, 96vw)"
      destroy-on-close
      append-to-body
      @closed="taxPreviewFileId = null"
    >
      <DocumentPreviewPane
        v-if="taxPreviewFileId"
        :active="taxPreviewOpen"
        :reload-key="taxPreviewFileId"
        :title="taxPreviewTitle"
        :fetcher="taxPreviewFetcher"
        min-height="70vh"
      />
    </ElDialog>

    <ElDialog
      v-model="dialogVisible"
      :title="isEditing ? 'Edit customer account' : 'Add customer account'"
      width="480px"
      destroy-on-close
    >
      <ElForm ref="formRef" :model="identityForm" :rules="identityRules" label-position="top">
        <ElFormItem label="VIP client">
          <ElSwitch v-model="identityForm.is_vip" @change="onVipToggle" />
        </ElFormItem>
        <ElFormItem label="Account number" prop="account_number">
          <div class="flex gap-2">
            <ElInput
              v-model="identityForm.account_number"
              :placeholder="identityForm.is_vip ? 'VIP account number' : '11001-0000'"
              maxlength="64"
              show-word-limit
              class="min-w-0 flex-1"
            />
            <ElButton
              v-if="identityForm.is_vip"
              :loading="generatingAccountNumber"
              @click="generateVipAccountNumber"
            >
              Generate
            </ElButton>
          </div>
          <p v-if="identityForm.is_vip" class="mt-1 text-xs text-g-500">
            Generate a unique VIP-###### number, or type your own.
          </p>
        </ElFormItem>
        <ElFormItem label="Company / registered buyer name" prop="name">
          <ElInput v-model="identityForm.name" maxlength="255" show-word-limit />
        </ElFormItem>
        <p class="text-xs text-g-500">
          Non-VIP customers use 11001-0000 (legacy WALK-IN). A VIP client needs a unique account
          number (generated or custom). This does not create a portal login or change issued
          invoices.
        </p>
      </ElForm>
      <template #footer>
        <ElButton @click="dialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="savingIdentity" @click="saveIdentity">
          {{ isEditing ? 'Save account' : 'Create account' }}
        </ElButton>
      </template>
    </ElDialog>

    <ElDialog v-model="statusDialogVisible" title="Update customer status" width="420px">
      <ElForm label-position="top">
        <ElFormItem label="Status">
          <ElSelect v-model="statusForm.status" class="w-full">
            <ElOption label="Active" value="active" />
            <ElOption label="Pending" value="pending" />
            <ElOption label="Suspended" value="suspended" />
            <ElOption label="Archived" value="archived" />
          </ElSelect>
        </ElFormItem>
        <ElFormItem label="Notes (optional)">
          <ElInput
            v-model="statusForm.notes"
            type="textarea"
            :rows="2"
            maxlength="500"
            show-word-limit
          />
        </ElFormItem>
      </ElForm>
      <template #footer>
        <ElButton @click="statusDialogVisible = false">Cancel</ElButton>
        <ElButton type="primary" :loading="savingStatus" @click="saveStatus"
          >Update status</ElButton
        >
      </template>
    </ElDialog>
  </div>
</template>

<script setup lang="ts">
  import { computed, onMounted, reactive, ref } from 'vue'
  import { useRouter } from 'vue-router'
  import { ElMessage, ElMessageBox } from 'element-plus'
  import type { FormInstance, FormRules } from 'element-plus'
  import { DocumentChecked, Edit, Switch, View } from '@element-plus/icons-vue'
  import DocumentPreviewPane from '@/components/business/DocumentPreviewPane.vue'
  import { downloadPrivateFile } from '@/api/documentRequirements'
  import {
    createAdminCustomer,
    fetchAdminCustomerDetail,
    fetchAdminCustomers,
    fetchNextVipAccountNumber,
    reviewAdminBuyerProfile,
    updateAdminCustomer,
    updateAdminCustomerStatus
  } from '@/api/registration'
  import {
    configureVipCreditAccount,
    fetchAdminCreditAccounts,
    fetchCreditPolicies,
    type AdminCreditAccount,
    type AdminCreditAccountVersion,
    type VipCreditProfileStatus
  } from '@/api/vipCredit'
  import { formatDateRangeManila, formatDateTimeManila } from '@/utils/date/formatDateTime'

  defineOptions({ name: 'CustomerAccounts' })

  type TaxTrack = {
    kind?: string
    latest_status?: string | null
    latest_id?: number | null
    total?: number
    pending_count?: number
    approved_count?: number
    display_status?: string | null
    requests?: any[]
  }

  const router = useRouter()
  const loading = ref(false)
  const savingIdentity = ref(false)
  const generatingAccountNumber = ref(false)
  const savingStatus = ref(false)
  const customers = ref<any[]>([])
  const selectedCustomer = ref<any>(null)
  const drawerVisible = ref(false)
  const dialogVisible = ref(false)
  const statusDialogVisible = ref(false)
  const isEditing = ref(false)
  const formRef = ref<FormInstance>()

  const taxPreviewOpen = ref(false)
  const taxPreviewFileId = ref<number | null>(null)
  const taxPreviewTitle = ref('Tax evidence file')
  const taxPreviewFetcher = computed(() => {
    const id = taxPreviewFileId.value
    if (!id) return null
    return async () => {
      const blob = await downloadPrivateFile(id)
      return { blob }
    }
  })

  const creditProfileLoading = ref(false)
  const savingCreditProfile = ref(false)
  const creditProfileAccount = ref<AdminCreditAccount | null>(null)
  const creditOverridesAllowed = ref(false)
  const creditProfileForm = reactive({
    status: 'ACTIVE' as VipCreditProfileStatus,
    effective_from: new Date().toISOString(),
    effective_to: '' as string | null,
    reason: '',
    use_overrides: false,
    credit_limit_mode_override: null as 'CAPPED' | 'UNLIMITED' | null,
    credit_limit_amount_override: '',
    payment_terms_days_override: null as number | null,
    due_date_basis_override: null as 'INVOICE_DATE' | 'CREDIT_CHARGE_DATE' | null,
    overdue_restriction_override: null as 'ALLOW' | 'WARN' | 'BLOCK' | null,
    overdue_grace_days_override: null as number | null
  })

  const creditProfileLatest = computed<AdminCreditAccountVersion | null>(() => {
    const versions = creditProfileAccount.value?.versions
    if (!versions?.length) return null
    return versions[0] || null
  })

  const NON_VIP_ACCOUNT_NUMBER = '11001-0000'

  const identityForm = reactive({
    id: 0,
    account_number: NON_VIP_ACCOUNT_NUMBER,
    name: '',
    is_vip: false
  })

  const statusForm = reactive({
    id: 0,
    status: 'active',
    notes: ''
  })

  const identityRules: FormRules = {
    account_number: [
      { required: true, message: 'Account number is required', trigger: 'blur' },
      {
        pattern: /^[A-Za-z0-9][A-Za-z0-9._/-]{0,63}$/,
        message: 'Use letters, numbers, period, slash, underscore or hyphen',
        trigger: 'blur'
      }
    ],
    name: [
      { required: true, message: 'Company / registered buyer name is required', trigger: 'blur' }
    ]
  }

  const filters = reactive({
    search: '',
    status: '',
    customer_type: '',
    tax_status: ''
  })

  const pagination = reactive({
    page: 1,
    per_page: 20,
    total: 0
  })

  const emptyTaxTrack = (): TaxTrack => ({
    total: 0,
    pending_count: 0,
    approved_count: 0,
    display_status: null,
    latest_status: null,
    requests: []
  })

  const taxTrack = (customer: any, kind: 'withholding' | 'exemption'): TaxTrack => {
    return customer?.tax_verification?.[kind] || emptyTaxTrack()
  }

  const taxRequests = (customer: any, kind: 'withholding' | 'exemption') => {
    return taxTrack(customer, kind).requests || []
  }

  const filteredCustomers = computed(() => {
    const status = filters.tax_status
    if (!status) return customers.value

    return customers.value.filter((row) => {
      const withholding = taxTrack(row, 'withholding')
      const exemption = taxTrack(row, 'exemption')
      if (status === 'NONE') {
        return (withholding.total || 0) === 0 && (exemption.total || 0) === 0
      }
      return withholding.display_status === status || exemption.display_status === status
    })
  })

  const linkedUsers = (customer: any) => {
    return customer?.user_links || customer?.userLinks || customer?.links || []
  }

  const getVerifiedCount = (customer: any) => {
    return (customer.contact_points || []).filter((c: any) => c.is_verified).length
  }

  const typeLabel = (value?: string) => {
    if (value === 'vip') return 'VIP'
    if (value === 'walk_in') return 'Walk-in'
    if (value === 'business') return 'Business'
    return value || '—'
  }

  const typeTag = (value?: string) => {
    if (value === 'vip') return 'warning'
    if (value === 'business') return 'primary'
    return 'info'
  }

  const statusLabel = (value?: string) => {
    if (!value) return '—'
    return value.charAt(0).toUpperCase() + value.slice(1)
  }

  const accountStatusTag = (value?: string) => {
    if (value === 'active') return 'success'
    if (value === 'pending') return 'warning'
    if (value === 'archived') return 'info'
    return 'danger'
  }

  const creditProfileStatusTag = (status?: string) => {
    if (status === 'ACTIVE') return 'success'
    if (status === 'HELD') return 'warning'
    if (status === 'DISABLED') return 'danger'
    return 'info'
  }

  const resetCreditProfileForm = () => {
    creditProfileForm.status = 'ACTIVE'
    creditProfileForm.effective_from = new Date().toISOString()
    creditProfileForm.effective_to = ''
    creditProfileForm.reason = ''
    creditProfileForm.use_overrides = false
    creditProfileForm.credit_limit_mode_override = null
    creditProfileForm.credit_limit_amount_override = ''
    creditProfileForm.payment_terms_days_override = null
    creditProfileForm.due_date_basis_override = null
    creditProfileForm.overdue_restriction_override = null
    creditProfileForm.overdue_grace_days_override = null
  }

  const findCreditAccountForCustomer = async (
    customerId: number
  ): Promise<AdminCreditAccount | null> => {
    let page = 1
    let lastPage = 1
    while (page <= lastPage && page <= 20) {
      const res: any = await fetchAdminCreditAccounts(page)
      const rows: AdminCreditAccount[] = Array.isArray(res?.data)
        ? res.data
        : Array.isArray(res)
          ? res
          : []
      lastPage = Number(res?.last_page || 1)
      const match = rows.find((row) => Number(row.customer_id) === customerId)
      if (match) return match
      page += 1
    }
    return null
  }

  const loadCreditProfile = async (customer: any) => {
    creditProfileAccount.value = null
    creditOverridesAllowed.value = false
    resetCreditProfileForm()
    if (String(customer?.customer_type || '').toLowerCase() !== 'vip' || !customer?.id) {
      return
    }
    creditProfileLoading.value = true
    try {
      const [account, policiesRes] = await Promise.all([
        findCreditAccountForCustomer(customer.id),
        fetchCreditPolicies()
      ])
      creditProfileAccount.value = account
      const policies = Array.isArray(policiesRes) ? policiesRes : ((policiesRes as any)?.data ?? [])
      const published = policies
        .filter((p: any) => p.status === 'PUBLISHED')
        .sort((a: any, b: any) => Number(b.version_number) - Number(a.version_number))
      creditOverridesAllowed.value = Boolean(published[0]?.allow_customer_overrides)
      if (account?.versions?.[0]) {
        const latest = account.versions[0]
        creditProfileForm.status = latest.status
        if (
          latest.credit_limit_mode_override ||
          latest.payment_terms_days_override != null ||
          latest.due_date_basis_override ||
          latest.overdue_restriction_override
        ) {
          creditProfileForm.use_overrides = true
          creditProfileForm.credit_limit_mode_override = latest.credit_limit_mode_override || null
          creditProfileForm.credit_limit_amount_override = latest.credit_limit_amount_override || ''
          creditProfileForm.payment_terms_days_override = latest.payment_terms_days_override ?? null
          creditProfileForm.due_date_basis_override = latest.due_date_basis_override || null
          creditProfileForm.overdue_restriction_override =
            latest.overdue_restriction_override || null
          creditProfileForm.overdue_grace_days_override = latest.overdue_grace_days_override ?? null
        }
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to load VIP credit profile.')
    } finally {
      creditProfileLoading.value = false
    }
  }

  const publishCreditProfile = async () => {
    const customer = selectedCustomer.value
    if (!customer?.id) return
    if (!creditProfileForm.reason.trim() || creditProfileForm.reason.trim().length < 3) {
      ElMessage.warning('Enter a reason of at least 3 characters.')
      return
    }
    if (!creditProfileForm.effective_from) {
      ElMessage.warning('Effective from is required.')
      return
    }
    savingCreditProfile.value = true
    try {
      const body: Record<string, unknown> = {
        status: creditProfileForm.status,
        effective_from: creditProfileForm.effective_from,
        reason: creditProfileForm.reason.trim()
      }
      if (creditProfileAccount.value?.lock_version) {
        body.expected_account_lock_version = creditProfileAccount.value.lock_version
      }
      if (creditProfileForm.effective_to) {
        body.effective_to = creditProfileForm.effective_to
      }
      if (creditProfileForm.use_overrides && creditOverridesAllowed.value) {
        if (creditProfileForm.credit_limit_mode_override) {
          body.credit_limit_mode_override = creditProfileForm.credit_limit_mode_override
          if (creditProfileForm.credit_limit_mode_override === 'CAPPED') {
            body.credit_limit_amount_override =
              creditProfileForm.credit_limit_amount_override || null
          }
        }
        if (creditProfileForm.payment_terms_days_override != null) {
          body.payment_terms_days_override = creditProfileForm.payment_terms_days_override
        }
        if (creditProfileForm.due_date_basis_override) {
          body.due_date_basis_override = creditProfileForm.due_date_basis_override
        }
        if (creditProfileForm.overdue_restriction_override) {
          body.overdue_restriction_override = creditProfileForm.overdue_restriction_override
        }
        if (creditProfileForm.overdue_grace_days_override != null) {
          body.overdue_grace_days_override = creditProfileForm.overdue_grace_days_override
        }
      }
      await configureVipCreditAccount(customer.id, body as any)
      ElMessage.success('VIP credit profile version published.')
      await loadCreditProfile(customer)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to publish VIP credit profile.')
    } finally {
      savingCreditProfile.value = false
    }
  }

  const taxStatusTag = (status?: string | null) => {
    if (status === 'APPROVED') return 'success'
    if (status === 'PENDING_REVIEW' || status === 'NEEDS_CORRECTION') return 'warning'
    if (status === 'REJECTED' || status === 'REVOKED') return 'danger'
    if (status === 'EXPIRED') return 'info'
    return 'info'
  }

  const taxStatusLabel = (track: TaxTrack) => {
    if (!track.total) return 'None'
    return track.display_status || track.latest_status || 'None'
  }

  const applyClientFilters = () => {
    // Client-side filter only; pagination total remains server-side.
  }

  const resetIdentityForm = () => {
    identityForm.id = 0
    identityForm.account_number = NON_VIP_ACCOUNT_NUMBER
    identityForm.name = ''
    identityForm.is_vip = false
  }

  const assignIdentityForm = (customer: any) => {
    identityForm.id = customer?.id || 0
    identityForm.account_number = customer?.account_number || NON_VIP_ACCOUNT_NUMBER
    identityForm.name = customer?.name || ''
    identityForm.is_vip = String(customer?.customer_type || '').toLowerCase() === 'vip'
  }

  const onVipToggle = async (value: string | number | boolean) => {
    const isVip = Boolean(value)
    if (!isVip) {
      if (
        !identityForm.account_number.trim() ||
        /^VIP-\d+$/i.test(identityForm.account_number.trim())
      ) {
        identityForm.account_number = NON_VIP_ACCOUNT_NUMBER
      }
      return
    }

    const current = identityForm.account_number.trim()
    if (!current || current === NON_VIP_ACCOUNT_NUMBER) {
      await generateVipAccountNumber()
    }
  }

  const generateVipAccountNumber = async () => {
    generatingAccountNumber.value = true
    try {
      const res = await fetchNextVipAccountNumber()
      identityForm.account_number = res.account_number
    } catch (error: any) {
      ElMessage.error(error?.message || 'Unable to generate a VIP account number.')
    } finally {
      generatingAccountNumber.value = false
    }
  }

  const fetchCustomers = async () => {
    try {
      loading.value = true
      const res: any = await fetchAdminCustomers({
        page: pagination.page,
        per_page: pagination.per_page,
        search: filters.search,
        status: filters.status,
        customer_type: filters.customer_type
      })

      const raw = res?.data || res
      customers.value = Array.isArray(raw) ? raw : raw?.data || []
      pagination.total = res?.meta?.total || raw?.total || customers.value.length
      loading.value = false
    } catch (error: any) {
      loading.value = false
      ElMessage.error(error?.message || 'Failed to fetch customer accounts.')
    }
  }

  const openCreateDialog = () => {
    isEditing.value = false
    resetIdentityForm()
    dialogVisible.value = true
  }

  const openEditDialog = (row: any) => {
    isEditing.value = true
    assignIdentityForm(row)
    dialogVisible.value = true
  }

  const openStatusDialog = (customer: any) => {
    statusForm.id = customer.id
    statusForm.status = customer.status || 'active'
    statusForm.notes = ''
    statusDialogVisible.value = true
  }

  const saveStatus = async () => {
    if (!statusForm.id) return
    savingStatus.value = true
    try {
      await updateAdminCustomerStatus(
        statusForm.id,
        statusForm.status,
        statusForm.notes || 'Status updated via admin console'
      )
      ElMessage.success(`Status updated to ${statusForm.status}`)
      statusDialogVisible.value = false
      await fetchCustomers()
      if (selectedCustomer.value?.id === statusForm.id) {
        selectedCustomer.value.status = statusForm.status
      }
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to update status.')
    } finally {
      savingStatus.value = false
    }
  }

  const goTaxEvidence = (customer: any) => {
    router.push({
      path: '/system/tax-evidence',
      query: customer?.name ? { q: customer.name } : undefined
    })
  }

  const openTaxFilePreview = (row: {
    private_file_id?: number | null
    private_file?: { latest_version?: { original_name?: string | null } | null } | null
  }) => {
    if (!row.private_file_id) {
      ElMessage.warning('No attached file for this request.')
      return
    }
    taxPreviewFileId.value = row.private_file_id
    taxPreviewTitle.value =
      row.private_file?.latest_version?.original_name || `File #${row.private_file_id}`
    taxPreviewOpen.value = true
  }

  const saveIdentity = async () => {
    const persist = async () => {
      savingIdentity.value = true
      try {
        const payload = {
          account_number: identityForm.account_number.trim(),
          name: identityForm.name.trim(),
          is_vip: identityForm.is_vip,
          customer_type: identityForm.is_vip ? 'vip' : 'business'
        }
        if (isEditing.value && identityForm.id) {
          const res: any = await updateAdminCustomer(identityForm.id, payload)
          const updated = res?.customer || res?.data?.customer
          ElMessage.success('Account number saved.')
          if (selectedCustomer.value?.id === identityForm.id && updated) {
            selectedCustomer.value = {
              ...selectedCustomer.value,
              ...updated,
              tax_verification: selectedCustomer.value.tax_verification
            }
            assignIdentityForm(selectedCustomer.value)
          }
        } else {
          await createAdminCustomer(payload)
          ElMessage.success('Customer account created.')
          dialogVisible.value = false
        }
        await fetchCustomers()
        if (!isEditing.value) {
          resetIdentityForm()
        }
      } catch (error: any) {
        ElMessage.error(error?.message || 'Failed to save account number.')
      } finally {
        savingIdentity.value = false
      }
    }

    if (dialogVisible.value && formRef.value) {
      await formRef.value.validate(async (valid) => {
        if (!valid) return
        await persist()
      })
      return
    }

    if (!identityForm.account_number.trim() || !identityForm.name.trim()) {
      ElMessage.error('Account number and company name are required.')
      return
    }
    await persist()
  }

  const openDetail = async (row: any) => {
    try {
      const res: any = await fetchAdminCustomerDetail(row.id)
      selectedCustomer.value = res?.customer || res?.data?.customer || res || row
      isEditing.value = true
      assignIdentityForm(selectedCustomer.value)
      drawerVisible.value = true
      await loadCreditProfile(selectedCustomer.value)
    } catch (error: any) {
      ElMessage.error(error?.message || 'Failed to load customer details.')
    }
  }

  const handleReviewVersion = (version: any, action: 'approve' | 'reject') => {
    ElMessageBox.prompt(
      `Provide notes for ${action} action:`,
      `${action === 'approve' ? 'Approve' : 'Reject'} Version ${version.version}`,
      {
        confirmButtonText: action === 'approve' ? 'Approve Version' : 'Reject Version',
        cancelButtonText: 'Cancel',
        inputPlaceholder: 'e.g. Verified with BIR Certificate of Registration'
      }
    )
      .then(async ({ value }) => {
        try {
          await reviewAdminBuyerProfile(selectedCustomer.value.id, version.id, action, value)
          ElMessage.success(`Buyer profile version ${version.version} ${action}d.`)
          openDetail(selectedCustomer.value)
          fetchCustomers()
        } catch (error: any) {
          ElMessage.error(error?.message || `Failed to ${action} version.`)
        }
      })
      .catch(() => {})
  }

  onMounted(() => {
    fetchCustomers()
  })
</script>
