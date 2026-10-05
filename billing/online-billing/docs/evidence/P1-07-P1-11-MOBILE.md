# P1-07/P1-11: Customer camera PDF and PWA install offer

## Scope and status

- Task / phase: P1-07 private customer uploads; P1-11 PWA install UI extension.
- Status: Implemented locally; mobile device and staging acceptance pending.
- Date and contributor: 2026-10-05, Codex.
- Tested revision: Uncommitted `apps/web/package.json`, `pnpm-lock.yaml`, `src/App.vue`, five customer upload views, new `PhotoToPdfPicker.vue`, `PwaInstallPrompt.vue`, `photoPdf.ts`, `installPrompt.ts`, and their two test files. Documentation updated in `README.md`, `docs/PROGRESS.md`, and this file.
- Dependencies checked: Existing `PrivateFileController`/document-type rules, Vite PWA shell, customer upload call sites. Added `pdf-lib@1.17.1` for local PDF encoding.
- Decision: User approved all customer document uploads that accept PDF and the install modal design on 2026-10-05. W33 applies to online-only private data.

## Implementation and evidence

- The shared picker takes repeated camera photos or multiple gallery images, permits page reordering/removal, resizes to at most 2000 pixels, encodes JPEG, and places each image on a PDF page. The generated `File` enters the same submission state and server upload path as a chosen PDF. Image-only avatar upload has no PDF picker.
- The install offer appears after sign-in only when an install route is available and the app is not standalone. Browser installation runs only on a button click. iOS Safari shows Add to Home Screen instructions. A dismissal timestamp is stored locally for 30 days; photo data is never stored there.
- No financial, number-pool, API, database, permission, or print contract changed. The server still validates file MIME and size.

| Check | Exact command | Environment | Result |
| --- | --- | --- | --- |
| Focused tests | `pnpm.cmd exec tsx --test src/utils/uploads/photoPdf.test.ts src/utils/pwa/installPrompt.test.ts` | Windows, Node 24.19.0, no database | 5 passed; initial red run failed for missing implementation/dependency as expected. |
| Typecheck | `pnpm.cmd exec vue-tsc --noEmit` | Windows, Node 24.19.0 | Passed. |
| Production build | `pnpm.cmd build` | Windows, Node 24.19.0 | Passed; Vite built the service worker. |

## Acceptance and handoff

- Database workflow: Not run; only web code and package metadata changed.
- Browser/mobile: Android Chrome camera capture, iOS Safari capture and install instructions, authenticated upload, and final PDF preview remain to be checked on devices.
- Print/Crystal Reports: Not run; no print path changed.
- Staging: Not deployed in this task.
- Pre-existing changes preserved: Two unrelated legacy Crystal Report `.rpt` modifications.
- Next action: Review on actual mobile devices and staging, then deploy the reviewed build.
