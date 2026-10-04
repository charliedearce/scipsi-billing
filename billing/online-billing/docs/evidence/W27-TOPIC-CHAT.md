# W27: Topic-scoped chat

## Scope and status

- Task / phase: W27 customer and teller chat, topic per bill, request, claim, or receipt
- Status: AUTOMATED_VERIFIED with authenticated customer browser acceptance for bill and receipt entry
- Date and contributor: 2026-09-29, implementation session
- Tested revision, or exact uncommitted files: uncommitted working tree on `cursor/p3-11-vip-late-charges`
- Dependencies checked: existing billing-request and bill-claim threads stay; chat does not post money
- Decision IDs and approvals, where applicable: W27 messages remain non-financial

## Implementation and evidence

- Legacy source paths/symbols and confidence: no legacy VB chat path; online conversation model only
- Changed paths and resulting behavior:
  - `apps/api/app/Services/Communications/ConversationService.php` adds invoice and receipt find-or-create. A bill that already has a billing request reuses that thread.
  - `apps/api/app/Http/Controllers/Api/V1/ConversationController.php` and `apps/api/routes/api.php` add `POST /api/v1/conversations/for-invoice/{id}` and `POST /api/v1/conversations/for-receipt/{id}`.
  - `apps/api/tests/Feature/Realtime/ConversationBillingChatTest.php` covers idempotent invoice and receipt threads and an unlinked customer rejection.
  - `apps/web/src/components/core/layouts/art-chat-window/index.vue` and `apps/web/src/views/communication/chat/index.vue` show the topic title and a grouped inbox. Header Messages opens the inbox without selecting the first thread.
  - My Bills opens the billing-request thread when the bill has one, otherwise the invoice thread. Customer receipt rows and the teller receipt card open the receipt thread.
- Financial, numbering, permission, and transaction effects: none. Participants are the linked portal user and the creating or posting teller. A user outside that customer is rejected. PPA remains blocked.
- API/schema/template compatibility and migration effects: no migration. New context types are `invoice` and `receipt`. Existing subjects are unchanged.

| Check | Exact command or manual procedure | Environment / DB / device | Actual result and evidence location |
| --- | --- | --- | --- |
| Invoice and receipt find-or-create | `php vendor/bin/phpunit --filter "test_invoice_without_billing_request_reuses_one_thread_and_rejects_an_outsider|test_invoice_with_a_billing_request_keeps_that_thread|test_posted_receipt_reuses_one_thread_and_rejects_an_outsider"` | PostgreSQL `online_billing_test`, RefreshDatabase | Passed 3 tests / 23 assertions |
| PHP format | `vendor/bin/pint --dirty` | API app | Passed |
| Vue typecheck | `pnpm.cmd exec vue-tsc --noEmit` | Node, `apps/web` | Passed |
| Production build | `pnpm.cmd build` | Node, `apps/web` | Passed |
| Customer bill chat | Signed in as the seeded customer, opened bill SI-0000000020, Chat with teller | Desktop browser at `http://127.0.0.1:3006/#/my-bills` | Title `Request REQ-202609-7CQGB1` and customer name. Other threads hidden until All messages. |
| Header inbox | Header Messages after a topic was open | Same session | Grouped request list and “Select a conversation”. No thread auto-selected. |
| Customer receipt chat | Paid tab, Chat beside OR CR-0000000004 | Same session, then 390px width | Title `Receipt CR-0000000004`. Request list not shown. Drawer fit the narrow width. No message sent. |

## Acceptance and handoff

- Automated verification: feature tests, Pint, Vue typecheck, production build
- Database workflow and database used: isolated `online_billing_test` for PHPUnit. Browser used the existing development database and created the receipt conversation for CR-0000000004. No payment, balance, or invoice change.
- Browser / PDF / physical-print verification: customer desktop and 390px drawer as above. No PDF or print path.
- Business or operational acceptance (who/date/evidence), if required: not required for this chat organization
- Not tested / blockers: teller receipt Chat button was not opened in the browser. Full `/chat` page was not opened. No second user was used to confirm live delivery.
- Pre-existing changes preserved: unrelated dirty work left unstaged
- Next exact task/action: open a posted receipt as the teller who posted it and confirm the same receipt title
- PROGRESS.md updated: yes
