# P0-04: Printing inventory

Evidence date: 2026-09-17. Code definitions and installed printer metadata inspected; no print job submitted or physical output accepted. This inventory is retained for a future physical-print rollout and is not a current online-release gate.

| Output | Current caller/engine | Source contract and geometry | Remaining evidence |
| --- | --- | --- | --- |
| OR | `OrPrintTemplateService`, called from `frmORCash` and `frmOR`; compatibility `frmPrintOR` delegates | `Receipt`, `Allocations`, `ReceiptAllocations`; default Letter, 100 DPI, zero margins, detail height 600; Microsoft Sans Serif 7-10 pt | Paper stock, real offsets, longest allocation output |
| Normal service | `ServicePrintTemplateService.PrintBill`; compatibility `frmPrintService` delegates | `Bill`, `Items`, `BillItems`; Letter, 100 DPI, zero margins, detail height 520; Microsoft Sans Serif 8-10 pt | Saved customized template and physical sheet |
| NSCL service | Same pipeline, independent `ServiceBillingNSCL.repx` | Same fields and built-in geometry; any trimmed NSCL prefix selects whole-document NSCL layout | Distinct desired NSCL visual layout and accepted sample |
| PPA invoice | `frmBilling.btnPrint_Click` -> `DocPrint/frmPrintBill.vb::PrintDocument1_PrintPage` | Hard-coded DrawString using live form controls plus loaded items; item baseline y277; totals y410-500; fonts request `Microsoft San Serif` (spelling differs from template fonts) | Actual printer default page size and font fallback; no explicit PaperSize found in inspected designer |
| Statement | `Statement/frmPrintStatement.vb` -> `Statement` procedure, Crystal `PrintStatement` | Typed dataset plus text from current controls | Inspect RPT geometry in designer and accepted output |
| Yellow/white transmittal | `frmPrintYtrans` / `frmPrintWtrans`, Crystal, Ytans/Wtrans | Source header/items and procedure projections | RPT dimensions/fonts/margins and actual stock |
| Revenue/operator exports | BillRep/OrRep/SunBill/SunOr/Volume/RTC and direct queries | Crystal datasets and wrappers; output depends on report filters | PDF/Excel reconciliation and report-by-report acceptance |

Default Letter is 8.5 x 11 inches, but it is a code default, not confirmation of paper fed into the printer. Service and OR fixed multiline fields have `CanGrow=False`, so overflow is a real acceptance case. UI line limits also differ across handlers; the new designer must explicitly handle overflow and pagination.

Service fields include customer identity, bill/account/date, vessel/voyage/movement, particulars, quantities/units/services/cargo codes/rates/gross lines, PPA, discount, net share, VAT and total. OR fields include customer identity/date, amount-in-words, invoice/amount lines, VAT/zero-rated sales/totals, check number, withholding and net. Full contracts remain in the two `DocPrint/*PrintTemplateService.vb` CreateSchema methods; preserve their names if migrating existing layouts.

W28 adds the planned [Admin Document Studio](DOCUMENT_STUDIO.md). Its versioned sales-invoice binding must add captured tariff/tax/PPA/fuel-surcharge fields compatibly, and its collection-receipt binding must keep cash, withholding and allocations distinct. W30 late-charge output, if accountant-approved, uses a separate linked adjustment/charge contract rather than rewriting the original sales-invoice or receipt layout. The Studio maps legacy fields where useful but does not directly reuse `.repx` or Crystal layouts.

Print setting `tbl_settings.myprint=YES` chooses direct printing in template services; otherwise they use a print dialog. These paths mark requested printing, not physical delivery. The PPA load path marks printed even earlier. The web model will distinguish financial state, artifact availability, and print attempts.

## Installed printer observation

Windows reports the default as **EPSON L120 Series (redirected 1)** using **Remote Desktop Easy Print**. Other entries include PDF/XPS/Fax/AnyDesk/OneNote virtual printers. A redirected default is not evidence of the operating cashier printer, tray, paper size, or alignment. Confirm the actual deployment workstation and use fresh test sheets there.

A non-printing capability probe in this remote session produced no usable Epson paper-size or margin data. It neither validates nor rejects the printer; the physical device/driver profile remains an operator-supplied acceptance input.

## Required physical samples

For each required layout: identify model/driver, paper dimensions, preprinted vs blank, orientation, tray, scale=100%, printable margins, duplex/copies, actual operator flow and silent-print need. Test long customer/address text, zero/many items, multipage behavior, rounding/amount words, and normal/PPA/NSCL selection. Retain sanitized PDF/scan/sample reference and acceptance owner/date. No existing image is claimed as accepted.

The web Studio/renderer choice is resolved for the current digital scope by P2-03/P2-04 using server-rendered PDF artifacts. Phase 0 records legacy requirements, but physical renderer fidelity and printer/paper samples are deferred until P2-05 is re-opened. Future samples should include a normal/PPA/NSCL sales invoice with transparent tax/PPA/fuel components and a collection receipt with cash/withholding/allocations, plus any accountant-approved late-charge output.
