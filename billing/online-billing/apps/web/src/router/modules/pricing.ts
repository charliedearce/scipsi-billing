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
        icon: 'ri:shield-check-line',
        keepAlive: true,
        roles: ['Administrator']
      }
    }
  ]
}
