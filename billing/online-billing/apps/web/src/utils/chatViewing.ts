/**
 * Whether the user is actually looking at the chat UI (tab visible).
 * Used so "Seen" is not sent while the drawer is closed or the tab is hidden.
 */
export function isChatTabVisible(): boolean {
  if (typeof document === 'undefined') return false
  return document.visibilityState === 'visible'
}
