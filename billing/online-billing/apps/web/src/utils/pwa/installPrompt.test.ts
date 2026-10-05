import assert from 'node:assert/strict'
import { test } from 'node:test'
import { installOfferKind } from './installPrompt'

test('installed and recently dismissed devices do not see an offer', () => {
  const now = Date.UTC(2026, 9, 5)
  assert.equal(
    installOfferKind({
      signedIn: true,
      standalone: true,
      hasPrompt: true,
      iosSafari: false,
      dismissedAt: 0,
      now
    }),
    null
  )
  assert.equal(
    installOfferKind({
      signedIn: true,
      standalone: false,
      hasPrompt: true,
      iosSafari: false,
      dismissedAt: now - 1000,
      now
    }),
    null
  )
})

test('eligible Chromium and iOS Safari browsers get the right install action', () => {
  const now = Date.UTC(2026, 9, 5)
  assert.equal(
    installOfferKind({
      signedIn: true,
      standalone: false,
      hasPrompt: true,
      iosSafari: false,
      dismissedAt: 0,
      now
    }),
    'native'
  )
  assert.equal(
    installOfferKind({
      signedIn: true,
      standalone: false,
      hasPrompt: false,
      iosSafari: true,
      dismissedAt: 0,
      now
    }),
    'ios'
  )
  assert.equal(
    installOfferKind({
      signedIn: false,
      standalone: false,
      hasPrompt: true,
      iosSafari: false,
      dismissedAt: 0,
      now
    }),
    null
  )
  assert.equal(
    installOfferKind({
      signedIn: true,
      standalone: false,
      hasPrompt: false,
      iosSafari: false,
      dismissedAt: 0,
      now
    }),
    null
  )
})
