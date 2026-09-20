# P0-03: Calculation rules and synthetic fixtures

Evidence: SOURCE_REVIEWED and REFERENCE_ARITHMETIC_CHECKED. Fixtures are deliberately synthetic, with synthetic percentages, in `fixtures/legacy-calculations.json` relative to the project root. They contain no customer transactions. Run `./scripts/Test-DiscoveryFixtures.ps1` from the project root. The script checks hand-specified expectations using a decimal reference evaluator; it does not execute the legacy UI or establish accounting acceptance.

## Extracted rules

Define T2(x) = truncate(x * 100) / 100, truncating toward zero.

- Tariff selection (`BillingAddMod.vCargo`) depends on DOMESTIC versus other route and ARRASTRE/STEVEDORING/OTHER service type. Capture all six rate selections in the future calculator; examples here start from the chosen rate/gross.
- Quantity base gross = quantity * rate. With no factor/zero factor, legacy resets to base gross/rate. Danger multiplies without explicit truncation in `qtyvalidate`. Fuel surcharge truncates both multiplied gross and rate to whole units. The resulting gross can differ from quantity * truncated displayed rate.
- PPA = T2(gross * route PPA rate) when checked, otherwise 0.
- `XXXX` discount = T2(gross * (1 - route PPA rate) * marker percentage / 100). Marker is read from unit grid column 2; percentage is from rate column 5. Multiple matches overwrite with the last match. PPA rate still affects discount even when PPA is unchecked.
- Base net = gross - PPA. Tax = T2((base net - discount) * VAT rate) when checked. Stored UI net = base net - discount. SCIPSI = T2(T2(base net + tax) - discount) when marker exists; total charge = T2(gross + tax).
- `viewItem` sums stored gross/PPA/discount/net/tax/SCIPSI columns, not a recalculation from current settings.
- Full receipt withholding = (SCIPSI - VAT) * configured CITW when enabled; net = SCIPSI - withholding. No explicit T2 at this point; SQL decimal storage and formatting must be tested separately.
- Partial handler uses Double for VAT = (net cash + withholding) / 1.12 * .12; status F if amount <= cash + withholding, otherwise P. Balance = amount - cash, excluding withholding. The reference evaluator captures this formula in decimal for exact examples; it does not reproduce floating-point/event-order artifacts.
- Statement overdue = prior balance + finance charge - payment; amount due = overdue + current charges. Legacy uses Double.
- Period: days 1-25 use current month; after 25 use next month except December stays 12. Period is year + three-digit month. Bill reference is RB + two-digit period month + 0 + unpadded bill number. Header branch/book identifiers are separate constants.

## Fixture coverage and limitations

The 18 cases cover domestic/foreign rates, PPA/VAT switches, XXXX with/without PPA, fractional gross/truncation, danger/fuel factor differences, full receipt CITW on/off, partial cash and the F-positive-balance anomaly, statement amounts, and period 25/26/December/year change. Amounts are JSON decimal strings; dates/numbers remain strings.

Still required before P0-03 acceptance:

1. Execute matching sanitized examples in a disposable legacy copy, recording actual UI values, persisted decimals, and output. Include text formatting/event ordering and currency precision boundaries.
2. Add missing cases: no-factor branch, multiple XXXX rows, service-rate selection, negative/zero/large inputs, edit recalculation, cash change/check handling, canceled receipt history, amount-to-words, and header/item sums.
3. Verify input validation and null handling; do not infer that a mathematical result is permitted input.
4. Resolve partial balance/withholding, fixed 12% assumptions, period anomalies, and item event order with the business owner. Preserve anomaly fixtures as regression documentation; do not use them as approved web expectations until decided.

No production tax rates or regulatory policy are inferred from the synthetic inputs. Live `tbl_settings` values were not exported as policy.

## W29/W30 target-fixture expansion

The approved online design does not carry forward the legacy manual fuel multiplier or manually entered Statement finance charge as a target calculation. Before P2-10/P3-11 acceptance, add separate sanitized decimal fixtures with a reviewed source/policy version for:

- tariff tax treatment, PPA applicability and fuel applicability on mixed invoice lines;
- fuel-price lower-inclusive/upper-exclusive boundaries, explicit zero band, no-match error, future activation in Asia/Manila, currency/unit mismatch, approved basis/rounding and changed rule before post;
- stored tariff/PPA/fuel/tax snapshots surviving later policy edits and Document Studio rendering the stored values only;
- VIP due date/current/day-1/grace/band boundaries, cap/minimum/cadence, non-compounding principal-only base, policy capture for new credit and no retroactive backfill;
- idempotent late-charge cycle retry, timely pending-proof hold, payment effective before/after assessment, receipt reversal, corrected invoice, waiver/reversal and separate principal/charge allocation.

These fixture inputs require the P0-06 fiscal/accountant matrix and commercial policy values. Do not invent a fuel price, late-charge percentage, tax treatment, compounding rule or document type merely to make an automated test pass.
