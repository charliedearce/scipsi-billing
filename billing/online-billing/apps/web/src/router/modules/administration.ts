import { AppRouteRecord } from '@/types/router'

/**
 * Administrator groups split out of the former single System menu, so that
 * customer data, document design and outbound communications are each reachable
 * without scanning an unrelated settings list.
 *
 * Children keep their original `/system/...` paths; only the menu grouping moved.
 */
export const administrationRoutes: AppRouteRecord[] = [
  {
    path: '/admin-customers',
    name: 'CustomersAndCompliance',
    component: '/index/index',
    meta: {
      title: 'Customers & Compliance',
      icon: 'ri:group-line',
      roles: ['Administrator'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/system/customers',
        name: 'CustomerAccounts',
        component: '/customer/accounts/index',
        meta: {
          title: 'Customer Accounts',
          description:
            'Manage customer business accounts, linked portal users, verified contacts, buyer profiles and tax status. Also where a VIP credit profile is published for a customer.',
          icon: 'ri:building-line',
          keepAlive: true,
          roles: ['Administrator']
        }
      },
      {
        path: '/system/tax-evidence',
        name: 'TaxEvidenceReview',
        component: '/system/tax-evidence/index',
        meta: {
          title: 'Tax Evidence Review',
          description:
            'Approve, return for correction, reject or revoke the BIR Form 2307 withholding certificates customers upload. Only approved certificates can be applied to payments.',
          icon: 'ri:file-shield-2-line',
          keepAlive: true,
          roles: ['Administrator']
        }
      },
      {
        path: '/system/billing-requirements',
        name: 'BillingRequirements',
        component: '/system/billing-requirements/index',
        meta: {
          title: 'Billing Requirements',
          description:
            'Define the document types customers may upload (allowed file types and size limits) and which documents each service requires before a billing request can be submitted.',
          icon: 'ri:file-upload-line',
          keepAlive: true,
          roles: ['Administrator']
        }
      }
    ]
  },
  {
    path: '/admin-documents',
    name: 'Documents',
    component: '/index/index',
    meta: {
      title: 'Documents',
      icon: 'ri:file-paper-2-line',
      roles: ['Administrator'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/system/document-studio',
        name: 'DocumentStudio',
        component: '/admin/document-studio/index',
        meta: {
          title: 'Document Studio',
          description:
            'Design, preview, validate and activate the printed layouts for invoices, receipts and operational documents. Layouts only arrange data; they never calculate amounts.',
          icon: 'ri:layout-masonry-line',
          keepAlive: true,
          roles: ['Administrator']
        }
      },
      {
        path: '/system/document-series',
        name: 'DocumentNumbering',
        component: '/system/document-series/index',
        meta: {
          title: 'Document Numbering',
          description:
            'Configure the number series and prefix used for future invoice and receipt numbers. Numbers already issued never change.',
          icon: 'ri:hashtag',
          keepAlive: true,
          roles: ['Administrator']
        }
      }
    ]
  },
  {
    path: '/admin-communications',
    name: 'Communications',
    component: '/index/index',
    meta: {
      title: 'Communications',
      icon: 'ri:chat-smile-2-line',
      roles: ['Administrator'],
      absoluteChildPaths: true
    },
    children: [
      {
        path: '/system/announcements',
        name: 'InAppAnnouncements',
        component: '/communication/announcements/index',
        meta: {
          title: 'Announcements',
          description:
            'Publish in-app notices such as maintenance windows or service updates to selected audiences. Announcements are shown inside the app only; they do not send SMS or email.',
          icon: 'ri:broadcast-line',
          keepAlive: true,
          roles: ['Administrator']
        }
      },
      {
        path: '/system/sms',
        name: 'TransactionalSMS',
        component: '/communication/sms/index',
        meta: {
          title: 'Transactional SMS',
          description:
            'Configure the SMS provider and message templates for transactional customer alerts (for example, bill ready or receipt issued). Not for bulk or marketing messages.',
          icon: 'ri:message-2-line',
          keepAlive: true,
          roles: ['Administrator']
        }
      },
      {
        path: '/system/communications-operations',
        name: 'CommunicationOperations',
        component: '/communication/operations/index',
        meta: {
          title: 'Communications Operations',
          description:
            'Monitor SMS and notification delivery volumes and failures. Delivery status is not proof of payment, receipt delivery or that a customer read the message.',
          icon: 'ri:bar-chart-grouped-line',
          keepAlive: true,
          roles: ['Administrator']
        }
      }
    ]
  }
]
