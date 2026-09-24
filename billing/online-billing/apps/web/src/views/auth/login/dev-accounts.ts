export interface DevLoginAccount {
  role: 'Administrator' | 'Teller' | 'PPA user' | 'Customer'
  name: string
  email: string
  password: string
}

/**
 * Seeded local accounts from DatabaseSeeder. The array is empty outside Vite
 * development so a production bundle does not keep these passwords.
 */
export const devLoginAccounts: DevLoginAccount[] = import.meta.env.DEV
  ? [
      {
        role: 'Administrator',
        name: 'System Administrator',
        email: 'admin@scipsi.test',
        password: 'AdminPassword123!'
      },
      {
        role: 'Teller',
        name: 'Maria Santos',
        email: 'teller1@scipsi.test',
        password: 'TellerPassword123!'
      },
      {
        role: 'Teller',
        name: 'Jose Reyes',
        email: 'teller2@scipsi.test',
        password: 'TellerPassword123!'
      },
      {
        role: 'PPA user',
        name: 'Ricardo Dela Cruz',
        email: 'ppa1@ppa.gov.ph',
        password: 'PpaPassword123!'
      },
      {
        role: 'PPA user',
        name: 'Luzviminda Flores',
        email: 'ppa2@ppa.gov.ph',
        password: 'PpaPassword123!'
      },
      {
        role: 'Customer',
        name: 'Andres Shipping Corp.',
        email: 'customer1@example.com',
        password: 'CustomerPassword123!'
      }
    ]
  : []
