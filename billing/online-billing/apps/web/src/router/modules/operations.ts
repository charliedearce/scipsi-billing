import { AppRouteRecord } from '@/types/router'

/**
 * Teller workspace, grouped along the billing lifecycle: issue a bill, collect
 * against it, correct or authorize exceptions, then report. Corrections and
 * reports stay available to an Administrator; the counter groups do not.
 *
 * Children keep their original absolute paths so existing deep links, worktabs
 * and dashboard shortcuts resolve unchanged.
 */
export const operationRoutes: AppRouteRecord[] = [
  {
    path: '/billing-desk',
    name: 'BillingDesk',
    component: '/index/index',
    meta: {
      title: 'Billing Desk',
      icon: 'ri:briefcase-4-line',
      roles: ['Teller'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/billing-request-queue',
        name: 'BillingRequestQueue',
        component: '/billing/teller-queue/index',
        meta: {
          title: 'Billing Request Queue',
          description:
            'Claim the oldest customer billing request, check the uploaded files, encode the bill and post it so the customer can pay. Also where tellers start a correction on an unpaid bill.',
          icon: 'ri:user-voice-line',
          keepAlive: true,
          roles: ['Teller']
        }
      },
      {
        path: '/walk-in-billing',
        name: 'WalkInBilling',
        component: '/billing/walk-in/index',
        meta: {
          title: 'Walk-in Billing',
          description:
            'Issue a bill for a counter customer who has no portal request or uploads. The customer can later claim the bill to pay it online.',
          icon: 'ri:store-2-line',
          keepAlive: true,
          roles: ['Teller']
        }
      },
      {
        path: '/bill-claim-review',
        name: 'BillClaimReview',
        component: '/billing/claim-review/index',
        meta: {
          title: 'Bill Claim Review',
          description:
            'Verify customer requests to link a walk-in bill to their portal account. Approving lets the customer view and pay that bill online.',
          icon: 'ri:shield-user-line',
          keepAlive: true,
          roles: ['Teller']
        }
      }
    ]
  },
  {
    path: '/collections',
    name: 'Collections',
    component: '/index/index',
    meta: {
      title: 'Collections',
      icon: 'ri:wallet-3-line',
      roles: ['Teller'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/payment-proof-review',
        name: 'PaymentProofReview',
        component: '/payments/teller/index',
        meta: {
          title: 'Payment Proof Review',
          description:
            'Claim the oldest customer payment proof, confirm the funds and reference, then approve to post the receipt (OR or acknowledgement) or reject with a reason.',
          icon: 'ri:bank-line',
          keepAlive: true,
          roles: ['Teller']
        }
      },
      {
        path: '/vip-credit-review',
        name: 'VipCreditRepaymentReview',
        component: '/payments/vip-credit/teller',
        meta: {
          title: 'VIP Repayment Review',
          description:
            'Verify bank proofs that VIP customers submit to repay credit bills. Approving posts one collection receipt with the confirmed allocations; rejecting asks the customer to resubmit.',
          icon: 'ri:hand-coin-line',
          keepAlive: true,
          roles: ['Teller']
        }
      }
    ]
  },
  {
    path: '/corrections',
    name: 'CorrectionsAndApprovals',
    component: '/index/index',
    meta: {
      title: 'Corrections & Approvals',
      icon: 'ri:draft-line',
      roles: ['Teller', 'Administrator'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/document-corrections',
        name: 'DocumentCorrections',
        component: '/payments/corrections/index',
        meta: {
          title: 'Document Corrections',
          description:
            'Request, review and carry out corrections to posted documents: a linked replacement for an unpaid invoice, or a settlement-only reversal of a receipt. Originals are kept for audit.',
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
          description:
            'Request or approve permission to issue an invoice or receipt with a past business date. Each approval can be used once.',
          icon: 'ri:calendar-check-line',
          keepAlive: true,
          roles: ['Teller', 'Administrator']
        }
      }
    ]
  },
  {
    path: '/reports',
    name: 'Reports',
    component: '/index/index',
    meta: {
      title: 'Reports',
      icon: 'ri:file-chart-line',
      roles: ['Teller', 'Administrator'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/billing-collections-report',
        name: 'BillingCollectionsReport',
        component: '/reports/billing-collections/index',
        meta: {
          title: 'Billing & Collections Report',
          description:
            'Register of issued invoices and posted collection receipts for a date range, with totals and export. Read-only.',
          icon: 'ri:bar-chart-2-line',
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
          description:
            "Generate a customer's statement of account as of a date: posted invoices, payments applied and balance due. Statements are saved snapshots and do not post anything.",
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
          description:
            'Prepare yellow (invoice) and white (receipt) transmittal lists of selected posted documents for hand-over. They are saved snapshots and do not change the documents.',
          icon: 'ri:folder-transfer-line',
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
          description:
            'See unpaid VIP credit principal by age bucket (current, 1–30, 31–60, 61–90, 91+ days) at a chosen cutoff date, per customer.',
          icon: 'ri:bar-chart-box-line',
          keepAlive: true,
          roles: ['Teller', 'Administrator']
        }
      },
      {
        path: '/vip-credit-accounts',
        name: 'VipCreditAccounts',
        component: '/payments/vip-credit/accounts',
        meta: {
          title: 'VIP Credit Accounts',
          description:
            "List of customers with a VIP credit account, their profile status (active, on hold, disabled) and current exposure. Change a customer's profile from Customer Accounts.",
          icon: 'ri:bank-card-line',
          keepAlive: true,
          roles: ['Teller', 'Administrator']
        }
      }
    ]
  }
]
