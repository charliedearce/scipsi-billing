import { AppRouteRecord } from '@/types/router'
import { dashboardRoutes } from './dashboard'
import { systemRoutes } from './system'
import { pricingRoutes } from './pricing'
import { paymentRoutes } from './payments'

/**
 * Export active domain routes for SCIPSI Online Billing
 */
export const routeModules: AppRouteRecord[] = [
  dashboardRoutes,
  ...paymentRoutes,
  pricingRoutes,
  systemRoutes
]
