const DISMISS_FOR_MS = 30 * 24 * 60 * 60 * 1000

export function installOfferKind(state: {
  signedIn: boolean
  standalone: boolean
  hasPrompt: boolean
  iosSafari: boolean
  dismissedAt: number
  now: number
}): 'native' | 'ios' | null {
  if (!state.signedIn || state.standalone) return null
  if (state.dismissedAt > 0 && state.now - state.dismissedAt < DISMISS_FOR_MS) return null
  if (state.hasPrompt) return 'native'
  return state.iosSafari ? 'ios' : null
}
