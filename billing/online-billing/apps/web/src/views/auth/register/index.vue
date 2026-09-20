<!-- Customer Portal Registration with optional OTP (Decision W32 / P1-10) -->
<template>
  <div class="customer-registration flex w-full h-screen">
    <LoginLeftView />

    <div class="relative flex-1 overflow-y-auto">
      <AuthTopBar />

      <div class="auth-right-wrap py-8">
        <div class="form max-w-[480px] w-full mx-auto">
          <!-- Header -->
          <div class="text-left mb-6">
            <h3 class="title text-2xl font-bold text-gray-900 dark:text-gray-100">
              {{ currentStep === 'form' ? 'Customer Registration' : 'Verify Mobile Number' }}
            </h3>
            <p class="sub-title text-sm text-gray-500 mt-1">
              {{
                currentStep === 'form'
                  ? 'Register your customer business account and personal portal identity'
                  : 'Enter the 6-digit security code sent to your registered mobile number'
              }}
            </p>
          </div>

          <!-- Step 1: 4 Mandatory Registration Fields -->
          <ElForm
            v-if="currentStep === 'form'"
            ref="formRef"
            :model="formData"
            :rules="rules"
            label-position="top"
            @keyup.enter="handleRegister"
          >
            <!-- 1. Full Name (Portal User) -->
            <ElFormItem label="Full Name (Sign-in Identity)" prop="full_name">
              <ElInput
                class="custom-height"
                v-model.trim="formData.full_name"
                placeholder="e.g. Juan Dela Cruz"
                prefix-icon="User"
              />
            </ElFormItem>

            <!-- 2. Company / Registered Buyer Name (Customer Business Account) -->
            <ElFormItem label="Company / Registered Buyer Name" prop="company_name">
              <ElInput
                class="custom-height"
                v-model.trim="formData.company_name"
                placeholder="e.g. General Santos Freight Inc."
                prefix-icon="OfficeBuilding"
              />
            </ElFormItem>

            <!-- 3. Email Address (Login & Contact Point) -->
            <ElFormItem label="Email Address" prop="email">
              <ElInput
                class="custom-height"
                v-model.trim="formData.email"
                placeholder="e.g. juan@gensanfreight.ph"
                prefix-icon="Message"
                type="email"
              />
            </ElFormItem>

            <!-- 4. Mobile Number (E.164 / OTP Destination) -->
            <ElFormItem label="Mobile Number (Philippine +639XXXXXXXXX or 09XXXXXXXXX)" prop="mobile">
              <ElInput
                class="custom-height"
                v-model.trim="formData.mobile"
                placeholder="e.g. 09171234567"
                prefix-icon="Iphone"
              />
            </ElFormItem>

            <!-- Password -->
            <ElFormItem label="Password" prop="password">
              <ElInput
                class="custom-height"
                v-model.trim="formData.password"
                placeholder="Minimum 8 characters"
                type="password"
                autocomplete="off"
                show-password
                prefix-icon="Lock"
              />
            </ElFormItem>

            <!-- Confirm Password -->
            <ElFormItem label="Confirm Password" prop="password_confirmation">
              <ElInput
                class="custom-height"
                v-model.trim="formData.password_confirmation"
                placeholder="Re-enter your password"
                type="password"
                autocomplete="off"
                show-password
                prefix-icon="Lock"
              />
            </ElFormItem>

            <!-- Terms Agreement -->
            <ElFormItem prop="agreement">
              <ElCheckbox v-model="formData.agreement">
                I agree to the SCIPSI Port Terms of Service and
                <span class="text-theme cursor-pointer underline">Privacy Policy</span>
              </ElCheckbox>
            </ElFormItem>

            <div style="margin-top: 20px">
              <ElButton
                class="w-full custom-height font-medium text-base"
                type="primary"
                @click="handleRegister"
                :loading="submitting"
                v-ripple
              >
                Register
              </ElButton>
            </div>

            <div class="mt-6 text-sm text-center text-gray-500">
              <span>Already have an account? </span>
              <RouterLink class="text-theme font-medium hover:underline" :to="{ name: 'Login' }">
                Sign in here
              </RouterLink>
            </div>
          </ElForm>

          <!-- Step 2: Interactive Mobile OTP Verification -->
          <div v-else class="otp-verification-panel">
            <div class="bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-900 rounded-xl p-4 mb-6">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center text-blue-600 dark:text-blue-400 font-bold text-lg">
                  🛡️
                </div>
                <div>
                  <p class="text-sm font-semibold text-blue-900 dark:text-blue-200">
                    Security Verification Sent
                  </p>
                  <p class="text-xs text-blue-700 dark:text-blue-300">
                    A 6-digit one-time PIN was sent to
                    <span class="font-mono font-bold">{{ mobileMasked }}</span>. Valid for 5 minutes.
                  </p>
                </div>
              </div>
            </div>

            <ElForm @submit.prevent="handleVerifyOtp">
              <ElFormItem label="6-Digit Verification Code">
                <ElInput
                  v-model.trim="otpCode"
                  maxlength="6"
                  class="otp-input custom-height text-center tracking-widest text-2xl font-mono font-bold"
                  placeholder="000000"
                  autofocus
                  @keyup.enter="handleVerifyOtp"
                />
              </ElFormItem>

              <div v-if="verificationError" class="mb-4 text-sm text-red-600 bg-red-50 dark:bg-red-950/40 border border-red-200 dark:border-red-900 rounded-lg p-2.5">
                {{ verificationError }}
              </div>

              <div class="flex items-center justify-between mt-6 gap-3">
                <ElButton
                  class="flex-1 custom-height"
                  @click="backToForm"
                  :disabled="verifying"
                >
                  Edit Details
                </ElButton>

                <ElButton
                  class="flex-1 custom-height"
                  @click="handleResendOtp"
                  :disabled="resendCountdown > 0 || resending"
                  :loading="resending"
                >
                  {{ resendCountdown > 0 ? `Resend (${resendCountdown}s)` : 'Resend Code' }}
                </ElButton>
              </div>

              <div class="mt-4">
                <ElButton
                  class="w-full custom-height font-medium text-base"
                  type="primary"
                  @click="handleVerifyOtp"
                  :loading="verifying"
                  :disabled="otpCode.length !== 6"
                  v-ripple
                >
                  Verify Code & Enter Portal
                </ElButton>
              </div>
            </ElForm>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
  import { ref, reactive, onUnmounted } from 'vue'
  import { useRouter, RouterLink } from 'vue-router'
  import { ElMessage, type FormInstance, type FormRules } from 'element-plus'
  import { registerCustomer, verifyMobileOtp, resendRegistrationOtp } from '@/api/registration'

  defineOptions({ name: 'Register' })

  const router = useRouter()
  const formRef = ref<FormInstance>()

  const currentStep = ref<'form' | 'otp'>('form')
  const submitting = ref(false)
  const verifying = ref(false)
  const resending = ref(false)

  const challengeId = ref<number | null>(null)
  const mobileMasked = ref('')
  const otpCode = ref('')
  const verificationError = ref('')

  const resendCountdown = ref(0)
  let timer: number | null = null

  const formData = reactive({
    full_name: '',
    company_name: '',
    email: '',
    mobile: '',
    password: '',
    password_confirmation: '',
    agreement: false
  })

  const validateConfirmPassword = (_rule: any, value: string, callback: (error?: Error) => void) => {
    if (!value) {
      callback(new Error('Please confirm your password'))
      return
    }
    if (value !== formData.password) {
      callback(new Error('Passwords do not match'))
      return
    }
    callback()
  }

  const validateAgreement = (_rule: any, value: boolean, callback: (error?: Error) => void) => {
    if (!value) {
      callback(new Error('You must agree to the Terms of Service and Privacy Policy'))
      return
    }
    callback()
  }

  const validateMobile = (_rule: any, value: string, callback: (error?: Error) => void) => {
    if (!value) {
      callback(new Error('Mobile number is required'))
      return
    }
    const clean = value.replace(/[^\d+]/g, '')
    if (!clean.match(/^(\+639|09)\d{9}$/)) {
      callback(new Error('Enter a valid Philippine mobile number (09XXXXXXXXX or +639XXXXXXXXX)'))
      return
    }
    callback()
  }

  const rules: FormRules = {
    full_name: [
      { required: true, message: 'Full name is required', trigger: 'blur' },
      { min: 3, max: 255, message: 'Name must be between 3 and 255 characters', trigger: 'blur' }
    ],
    company_name: [
      { required: true, message: 'Company or registered buyer name is required', trigger: 'blur' },
      { min: 2, max: 255, message: 'Company name must be between 2 and 255 characters', trigger: 'blur' }
    ],
    email: [
      { required: true, message: 'Email address is required', trigger: 'blur' },
      { type: 'email', message: 'Please enter a valid email address', trigger: 'blur' }
    ],
    mobile: [
      { required: true, validator: validateMobile, trigger: 'blur' }
    ],
    password: [
      { required: true, message: 'Password is required', trigger: 'blur' },
      { min: 8, message: 'Password must be at least 8 characters', trigger: 'blur' }
    ],
    password_confirmation: [
      { required: true, validator: validateConfirmPassword, trigger: 'blur' }
    ],
    agreement: [
      { validator: validateAgreement, trigger: 'change' }
    ]
  }

  const startResendTimer = (seconds = 60) => {
    resendCountdown.value = seconds
    if (timer) clearInterval(timer)
    timer = window.setInterval(() => {
      if (resendCountdown.value > 0) {
        resendCountdown.value--
      } else {
        if (timer) clearInterval(timer)
      }
    }, 1000)
  }

  onUnmounted(() => {
    if (timer) clearInterval(timer)
  })

  const handleRegister = async () => {
    if (!formRef.value) return

    try {
      await formRef.value.validate()
      submitting.value = true
      verificationError.value = ''

      const res = await registerCustomer({
        full_name: formData.full_name,
        company_name: formData.company_name,
        email: formData.email,
        mobile: formData.mobile,
        password: formData.password,
        password_confirmation: formData.password_confirmation
      })

      submitting.value = false

      if (res.data?.challenge_id) {
        challengeId.value = res.data.challenge_id
        mobileMasked.value = res.data.mobile_masked || formData.mobile
        currentStep.value = 'otp'
        startResendTimer(60)
        ElMessage.success('Security verification code sent to your mobile number.')
      } else if (res.data?.registration_status === 'active') {
        ElMessage.success('Registration completed. Redirecting to login...')
        setTimeout(() => {
          router.push({ name: 'Login' })
        }, 800)
      } else {
        // Non-enumerating response
        ElMessage.info('Registration initiated. If eligible, a verification code was sent.')
      }
    } catch (error: any) {
      submitting.value = false
      const errorMsg = error?.response?.data?.error?.message || error?.message || 'Registration failed. Please check your details.'
      ElMessage.error(errorMsg)
    }
  }

  const handleVerifyOtp = async () => {
    if (!challengeId.value || otpCode.value.length !== 6) {
      verificationError.value = 'Please enter the complete 6-digit code.'
      return
    }

    try {
      verifying.value = true
      verificationError.value = ''

      const res = await verifyMobileOtp({
        challenge_id: challengeId.value,
        code: otpCode.value
      })

      verifying.value = false

      if (res.data?.success) {
        ElMessage.success('Account successfully verified! Redirecting to login...')
        setTimeout(() => {
          router.push({ name: 'Login' })
        }, 1200)
      }
    } catch (error: any) {
      verifying.value = false
      const msg = error?.response?.data?.error?.message || error?.message || 'Invalid or expired code. Please try again.'
      verificationError.value = msg
    }
  }

  const handleResendOtp = async () => {
    if (!challengeId.value || resendCountdown.value > 0) return

    try {
      resending.value = true
      verificationError.value = ''

      const res = await resendRegistrationOtp(challengeId.value)
      resending.value = false

      if (res.data?.challenge_id) {
        challengeId.value = res.data.challenge_id
        otpCode.value = ''
        startResendTimer(60)
        ElMessage.success('A fresh 6-digit code has been sent.')
      }
    } catch (error: any) {
      resending.value = false
      ElMessage.error(error?.response?.data?.error?.message || 'Failed to resend code. Please try again.')
    }
  }

  const backToForm = () => {
    currentStep.value = 'form'
    otpCode.value = ''
    verificationError.value = ''
  }
</script>

<style scoped>
  @import '../login/style.css';

  /* The login shell is intentionally compact, but registration has a longer
   * form. Keep the shared visual treatment while allowing the form to scroll
   * instead of clipping the submit button below the fixed login height. */
  .auth-right-wrap {
    @apply relative inset-auto w-full h-auto min-h-screen m-0 overflow-y-auto overflow-x-hidden;
  }

  .auth-right-wrap .form {
    @apply h-auto min-h-screen py-20;
  }

</style>

<style>
  .customer-registration .otp-input .el-input__inner {
    text-align: center;
    letter-spacing: 0.4em;
    font-size: 1.5rem;
    font-weight: 700;
  }
</style>
