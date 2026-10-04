# P1-08: Floating chat preview

## Scope and status

- Task / phase: P1-08, shared chat notifications.
- Status: Automated verified; authenticated two-user browser acceptance pending.
- Date: 2026-09-29.
- Tested files: uncommitted `apps/web/src/api/notifications.ts`, `apps/web/src/store/modules/realtime.ts`, and `apps/web/src/App.vue`.
- Decision: W27 keeps chat unread state separate from work notifications.

## Implementation and evidence

The existing `notification.created` user-private Reverb listener receives durable `chat_message` notifications. The web client now displays the latest message preview at the lower left for five seconds while the tab is visible. A click opens its conversation through the existing chat drawer event. The preview is capped to two lines, fits narrow viewports, and is closed on logout/teardown. It does not mark a message read or alter the work notification stream. Duplicate notification IDs in the current session do not open a second preview.

Follow-up: The first implementation relied only on Reverb. Read-only inspection of the local development database found recent chat messages had created recipient `chat_message` notifications, but those notifications were already marked read because the recipient had the thread open. The recovery check therefore uses all new chat notifications, not only unread ones. Every five seconds it compares IDs from the user-scoped API against the initial baseline and shows newer previews even if a Seen update happened first. Reverb arrivals update the same cursor so polling does not repeat them. Polling failures stay quiet.

User clarification on 2026-09-29: the intended alert is a native browser/OS popup while the site is open. The new Chat and drawer control requests browser notification permission on a click. When granted, a test alert is requested immediately and new chat notifications use generic text without customer or message content. Clicking a message alert focuses the site and opens the conversation. If browser permission is missing or the native API fails, the in-page preview remains. This adds no background Web Push subscription or service-worker push handling.

Further role clarification on 2026-09-29: all four roles, including PPA user, should be able to opt into browser popups for their existing **work** notifications. The Notifications page now exposes the control to each role. New work notifications use the authorized work feed, a generic popup, deduplication across Reverb and five-second API recovery polling, and click-through to Notifications. Chat authorization remains Customer, Teller and Administrator. Initial work feed rows set the cursor without replaying old notifications.

No API, schema, financial, numbering, permission, transaction, print, or report path changed. The existing server excludes staff notes and the sender from customer-visible chat notifications; the client uses only the already authorized user-private payload.

| Check | Command | Environment | Result |
| --- | --- | --- | --- |
| Formatting | `pnpm.cmd exec prettier --write src/store/modules/realtime.ts src/App.vue` | Web workspace, local Windows | Passed |
| Typecheck | `pnpm.cmd exec vue-tsc --noEmit` | Web workspace, Node 24.19.0 | Passed |
| Production build | `pnpm.cmd build` | Web workspace, Node 24.19.0 | Passed, 3,398 modules; PWA precache generated |
| Follow-up typecheck and build | `pnpm.cmd exec vue-tsc --noEmit`; `pnpm.cmd build` | Web workspace, Node 24.19.0 | Passed after adding API recovery; 416 PWA precache entries |
| Browser alert frontend checks | `pnpm.cmd exec eslint` on five changed frontend files; `pnpm.cmd exec vue-tsc --noEmit`; `pnpm.cmd build` | Web workspace, Node 24.19.0 | Passed after adding the Notifications API permission control and browser alert path; 416 PWA precache entries |
| All-role work alert frontend checks | `pnpm.cmd exec prettier --write` and `eslint` on the three changed frontend files; `pnpm.cmd build`; final `eslint` and `vue-tsc --noEmit` after click-through refinement | Web workspace, bundled Node 24 | Passed; production build generated 417 PWA precache entries. Work alerts not tested in a native OS popup. |
| Browser inspection | Open `http://127.0.0.1:3006/#/chat`; inspect chat | Authenticated local in-app browser | Chat page loaded and displayed “Realtime connected.” The read-only inspection sandbox did not expose the socket object. No second user message was sent. |
| Local data inspection | Read recent `chat_messages` and `in_app_notifications` IDs, actors, types, read flags, and times | Local development PostgreSQL; no message bodies queried | Two recent customer messages in conversation 7 had created recipient `chat_message` rows, both already read. No data modified. |
| Browser preview | Insert temporary `chat_message` notifications addressed to the signed-in local Admin, already marked read; observe `.chat-preview-notification` | Local development PostgreSQL and authenticated in-app browser at `127.0.0.1:3006/#/chat` | Preview appeared at lower left with the test title/body, then disappeared. Four synthetic notification rows were deleted by exact ID and test marker. |
| Browser popup control | Open Chat and inspect “Enable browser alerts”; click the control | Authenticated local in-app browser | Control rendered. The in-app browser did not expose a native permission prompt, so OS popup was not verified there. |
| Diff whitespace | `git diff --check -- online-billing/apps/web/src/App.vue online-billing/apps/web/src/store/modules/realtime.ts` | Repository root | Passed; realtime store is an existing untracked worktree file |

The first typecheck attempt used system Node 14.21.3 and stopped in a dependency at the `??=` syntax. Retrying with the bundled Node 24 passed.

## Acceptance and handoff

- Database workflow and database: Read-only diagnosis and four temporary notification inserts/deletes on local development PostgreSQL; no financial rows changed.
- Browser: Authenticated chat page and in-page floating preview visually verified on a synthetic already-read notification. Native browser permission grant, OS popup, all-role work delivery, click-through, and narrow-width visual check are not yet verified.
- Printing and Crystal Reports: Not affected or exercised.
- Business acceptance and deployment: Not performed.
- Pre-existing changes: Left untouched; this checkout already contains extensive tracked and untracked web and legacy edits.
- Next: In the user's normal browser, click Enable browser alerts on Notifications and allow the site. Confirm the test OS popup; then generate a work notification for a PPA user and a chat message for a chat-authorized role while recipients keep the site open. Verify both native popups and click-through at desktop and narrow widths.
