import { AppRouteRecord } from '@/types/router'

/**
 * Customer portal menus, grouped by what the customer is trying to do.
 *
 * Children keep their original absolute paths. Stored notification payloads and
 * customer bookmarks already point at these URLs, so grouping must not move them.
 */
export const portalRoutes: AppRouteRecord[] = [
  {
    path: '/portal-billing',
    name: 'PortalBilling',
    component: '/index/index',
    meta: {
      title: 'Billing',
      icon: 'ri:bill-line',
      roles: ['Customer'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/my-bills',
        name: 'MyBills',
        component: '/payments/customer/index',
        meta: {
          title: 'My Bills & Payments',
          description:
            'See your posted bills and balances, pay selected bills by uploading a bank or payment proof, and track each proof until a receipt (OR) is issued.',
          icon: 'ri:bank-card-line',
          keepAlive: true,
          roles: ['Customer']
        }
      },
      {
        path: '/my-billing-requests',
        name: 'MyBillingRequests',
        component: '/billing/requests/index',
        meta: {
          title: 'Request Billing',
          description:
            'Ask SCIPSI to prepare a bill for a port service. Upload the required documents; a teller reviews them and posts the bill for you to pay.',
          icon: 'ri:file-list-3-line',
          keepAlive: true,
          roles: ['Customer']
        }
      },
      {
        path: '/claim-bill',
        name: 'ClaimBill',
        component: '/billing/claim-bill/index',
        meta: {
          title: 'Claim Bill',
          description:
            'Link a bill that was issued at the counter (walk-in) to your portal account so you can view and pay it online. A teller verifies each claim.',
          icon: 'ri:file-search-line',
          keepAlive: true,
          roles: ['Customer']
        }
      },
      {
        path: '/my-credit',
        name: 'MyVipCredit',
        component: '/payments/vip-credit/index',
        meta: {
          title: 'My VIP Credit',
          description:
            'For VIP accounts: charge eligible bills to your credit line, repay credit bills with a bank proof, and track due dates, overdue amounts and repayment status.',
          icon: 'ri:funds-line',
          keepAlive: true,
          roles: ['Customer']
        }
      }
    ]
  },
  {
    path: '/portal-account',
    name: 'PortalAccount',
    component: '/index/index',
    meta: {
      title: 'My Account',
      icon: 'ri:account-circle-line',
      roles: ['Customer'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/my-profile',
        name: 'CustomerProfileSettings',
        component: '/customer/profile/index',
        meta: {
          title: 'Profile Settings',
          description:
            'Manage your login, contact details, company buyer information and password. Changes apply to future documents only; issued bills and receipts keep their original details.',
          icon: 'ri:user-settings-line',
          keepAlive: true,
          roles: ['Customer']
        }
      },
      {
        path: '/my-tax-evidence',
        name: 'MyTaxEvidence',
        component: '/billing/tax-evidence/index',
        meta: {
          title: 'Tax Evidence',
          description:
            'Upload BIR Form 2307 withholding certificates so approved withholding can be applied when you pay. An Administrator reviews each certificate.',
          icon: 'ri:file-shield-2-line',
          keepAlive: true,
          roles: ['Customer']
        }
      }
    ]
  }
]
