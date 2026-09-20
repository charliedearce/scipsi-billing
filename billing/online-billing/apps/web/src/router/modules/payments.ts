import { AppRouteRecord } from '@/types/router'

export const paymentRoutes: AppRouteRecord[] = [
  {
    path: '/my-bills',
    name: 'MyBills',
    component: '/payments/customer/index',
    meta: {
      title: 'My Bills & Payments',
      icon: 'ri:bank-card-line',
      keepAlive: true,
      roles: ['Customer']
    }
  },
  {
    path: '/payment-proof-review',
    name: 'PaymentProofReview',
    component: '/payments/teller/index',
    meta: {
      title: 'Payment Proof Review',
      icon: 'ri:bank-line',
      keepAlive: true,
      roles: ['Teller', 'Administrator']
    }
  },
  {
    path: '/document-corrections',
    name: 'DocumentCorrections',
    component: '/payments/corrections/index',
    meta: {
      title: 'Document Corrections',
      icon: 'ri:file-edit-line',
      keepAlive: true,
      roles: ['Teller', 'Administrator']
    }
  },
  {
    path: '/backdate-authorizations',
    name: 'BackdateAuthorizations',
    component: '/payments/backdates/index',
    meta: {
      title: 'Backdate Authorization',
      icon: 'ri:calendar-check-line',
      keepAlive: true,
      roles: ['Teller', 'Administrator']
    }
  },
  {
    path: '/account-statements',
    name: 'AccountStatements',
    component: '/reports/account-statements/index',
    meta: {
      title: 'Account Statements',
      icon: 'ri:file-list-2-line',
      keepAlive: true,
      roles: ['Teller', 'Administrator']
    }
  },
  {
    path: '/transmittals',
    name: 'Transmittals',
    component: '/reports/transmittals/index',
    meta: {
      title: 'Yellow & White Transmittals',
      icon: 'ri:folder-transfer-line',
      keepAlive: true,
      roles: ['Teller', 'Administrator']
    }
  },
  {
    path: '/billing-collections-report',
    name: 'BillingCollectionsReport',
    component: '/reports/billing-collections/index',
    meta: {
      title: 'Billing & Collections Report',
      icon: 'ri:bar-chart-2-line',
      keepAlive: true,
      roles: ['Teller', 'Administrator']
    }
  },
  {
    path: '/my-credit',
    name: 'MyVipCredit',
    component: '/payments/vip-credit/index',
    meta: {
      title: 'My VIP Credit',
      icon: 'ri:funds-line',
      keepAlive: true,
      roles: ['Customer']
    }
  },
  {
    path: '/vip-credit-review',
    name: 'VipCreditRepaymentReview',
    component: '/payments/vip-credit/teller',
    meta: {
      title: 'VIP Repayment Review',
      icon: 'ri:hand-coin-line',
      keepAlive: true,
      roles: ['Teller', 'Administrator']
    }
  },
  {
    path: '/vip-credit-aging',
    name: 'VipCreditAging',
    component: '/payments/vip-credit/aging',
    meta: {
      title: 'VIP Credit Aging',
      icon: 'ri:bar-chart-box-line',
      keepAlive: true,
      roles: ['Teller', 'Administrator']
    }
  },
  {
    path: '/ppa-verification',
    name: 'PpaBillVerification',
    component: '/payments/ppa-verification/index',
    meta: {
      title: 'PPA Bill Verification',
      icon: 'ri:shield-check-line',
      keepAlive: true,
      roles: ['PPA user', 'Administrator']
    }
  }
]
