import { AppRouteRecord } from '@/types/router'

/**
 * Pricing administration routes. Roles match the names returned by the
 * Laravel API; the old Art Design Pro demo identifiers are not used here.
 */
export const pricingRoutes: AppRouteRecord = {
  path: '/pricing',
  name: 'Pricing',
  component: '/index/index',
  meta: {
    title: 'Pricing & Tariffs',
    icon: 'ri:price-tag-3-line',
    roles: ['Administrator']
  },
  children: [
    {
      path: 'tariffs',
      name: 'TariffsPricing',
      component: '/system/tariffs',
      meta: {
        title: 'Tariffs & Fuel Surcharges',
        description:
          'Maintain versioned tariff rates, fuel-price bands and pricing rules used when bills are calculated. Published changes affect new bills only; issued invoices keep their amounts.',
        icon: 'ri:price-tag-3-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'payment-policies',
      name: 'PaymentPolicies',
      component: '/system/payment-policies',
      meta: {
        title: 'Payment Routes & Deadlines',
        description:
          'Publish versioned payment policies: the bank instructions, payment routes and deadlines customers receive when they pay a bill. Instructions already issued keep their original terms.',
        icon: 'ri:bank-card-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'credit-policies',
      name: 'VipCreditPolicies',
      component: '/system/credit-policies',
      meta: {
        title: 'VIP Credit & Collections',
        description:
          'Publish versioned VIP credit terms (limit, payment days, overdue rules) and late-charge policies, run late-charge assessment and waive charges. Drafts can be deleted; published versions cannot.',
        icon: 'ri:funds-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    },
    {
      path: 'ppa-clearance',
      name: 'PpaClearancePolicies',
      component: '/system/ppa-clearance',
      meta: {
        title: 'PPA Clearance Policy',
        description:
          'Versioned policy that controls whether a qualifying VIP credit status can be accepted by PPA in place of full payment.',
        icon: 'ri:shield-check-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    }
  ]
}
