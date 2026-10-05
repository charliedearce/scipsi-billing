# P0-05: Tariff classifications and scheduled fuel surcharge

User requirement W29, recorded 2026-09-19. Administrators must be able to classify tariffs as VATable, PPA-share applicable and fuel-surcharge applicable, then maintain fuel-price ranges with designated surcharge percentages and scheduled effectivity. This approved scope replaces the legacy form-wide checkbox/fuel-factor behavior with explicit, versioned pricing rules. It is not a tax-rate decision, an implemented migration, or authority to reprice issued invoices.

## Legacy boundary and target model

The current source tariff master records tariff identity, classification, route/service rate components and service mapping, but does not evidence effective-dated VAT/PPA/fuel rules. Legacy VAT/PPA are bill-level controls using global percentages, and its fuel input is a teller-entered multiplier that mutates the base rate/gross and can disable PPA for the entire bill. The online model retains versioned fuel bands and transparent components. On 2026-09-25 the user directed Fuel mode to follow the VB numeric and PPA behavior: retain centavos in the adjusted rate, round final fuel gross to a whole peso, and suppress PPA across the bill. The online policy still supplies the percentage; the original tariff PPA classification is snapshotted with the suppression reason.

The user's later 2026-09-25 Dangerous cargo direction preserves its distinct VB behavior: multiply the tariff rate by the entered factor, display/save the adjusted rate to centavos, and calculate gross from the unrounded adjusted rate × quantity before rounding gross to centavos. Dangerous cargo continues to use the tariff's PPA rule. For example, rate 107.20 × factor 0.92 × quantity 7 displays rate 98.62 and gross 690.37.

The online model makes each tariff version declare the following controlled classifications:

| Tariff field | Target meaning | Boundary |
| --- | --- | --- |
| `tax_treatment_key` | Tax classification such as `VATABLE`, `EXEMPT` or `ZERO_RATED`, linked to an approved fiscal rule | The Admin UI may display a **VATable** tag, but a boolean alone cannot grant an exemption or choose a tax rate |
| `ppa_share_applicability` | `NOT_APPLICABLE` or `APPLICABLE`, with the applicable versioned PPA policy/rule | The result is calculated per eligible line; it is not a global checkbox side effect |
| `fuel_surcharge_applicability` | `NOT_APPLICABLE` or `APPLICABLE`, with the applicable versioned fuel policy | A surcharge is calculated as its own transparent component, not by silently changing the tariff rate |

Tariff code, description, unit, class, service/route rates and the three classifications belong in a draft/published `tariff_version` with explicit effective dates. A change creates a future version; it cannot alter an issued line or a historical report. Draft calculations show the selected versions and return a conflict/reconfirmation when an effective rule changes before posting.

2026-10-05 archive rule: an Administrator may archive or restore a tariff master with a recorded reason. Archived tariffs remain in Admin history and retain their versions and invoice references. New draft tariff selection rejects them, and posting an existing draft with an archived tariff requires its affected line to be replaced first. Archiving does not cancel a draft, reverse an issued bill, or correct a wrong published rate; a rate correction requires a separately reviewed replacement version and non-overlapping effective window.

## Fuel price and surcharge schedule

Admin Settings > **Tariffs & Surcharges** contains two separate records:

1. **Fuel price observations**: source/reference, location/scope, product/grade, price, currency, unit of measure, observed time, effective time, entered/reviewed actor and optional private source evidence.
2. **Fuel surcharge schedule versions**: draft/published/effective status, scope, calculation basis, approved fuel-price source/selection rule, and non-overlapping price bands. Each band has a minimum price inclusive, maximum price exclusive (or an explicit open upper bound), designated percentage and display label. A zero-percent band is explicit; it is not a missing configuration.

Schedule publication rejects mixed currency/unit definitions, overlapping effective windows, overlapping price bands, invalid bounds, unsupported percentages, ambiguous scope and a rule with no defined response for a price it claims to cover. The exact set of permitted pricing bases is code-defined, for example `BASE_TARIFF_AMOUNT` or another accountant-reviewed basis; administrators choose from the allowed list rather than enter arbitrary formulas. The tax/PPA/fuel ordering matrix is versioned financial policy and must be approved with P0-03/P0-06 fixtures before production use.

An authorized Administrator can schedule a published tariff/fuel rule for a future Asia/Manila effective time. The scheduler activates it for new calculations only and emits an after-commit admin refresh. It never updates drafts without a new server calculation and never reprices issued invoices.

## Calculation and snapshot protocol

At server calculation/posting, use the invoice's locked business date/time, never a browser clock:

1. Resolve the active tariff version for the item, location/service/route and business date.
2. Resolve its approved tax treatment and, when applicable, PPA policy/rule version.
3. If the tariff is fuel-surcharge applicable, resolve the active fuel schedule and its authorized fuel-price observation, then exactly one matching price band.
4. Calculate the surcharge with decimal arithmetic. For an active positive Fuel band, use `factor = 1 + band_fraction`, show/store the adjusted rate rounded to centavos, and round full-precision `quantity × tariff_rate × factor` to the nearest whole peso (half away from zero). Store the difference between rounded gross and the two-decimal base gross as the distinct fuel component so line and header amounts reconcile. A zero band leaves base gross unchanged. Snapshot the rounding method, inputs, rate and resulting amounts.
5. Fuel mode suppresses PPA for every line on the bill, including lines without a fuel component. Other modes use each tariff's PPA policy. Recalculate tax from the resulting net under the captured treatment; snapshot every resolved policy/version, source price, band, basis, original PPA classification, applied suppression, inputs and final amounts with the invoice item.

If an applicable tariff has no valid published fuel observation, schedule or matching band, the server returns a configuration error before posting. It must not silently charge zero, use the latest unapproved price, or reuse a stale schedule. A time-critical exceptional rate requires a separately authorized, reasoned published emergency version; direct post-time overrides are not the initial design.

The Document Studio receives only the resulting `FuelSurchargeRate`, `FuelSurchargeAmount`, `PpaShareAmount` and tax snapshots. It cannot decide them. Sales invoices should disclose a fuel surcharge transparently according to the approved fiscal/output matrix.

## Access, records and operational views

Candidate permissions are `tariffs.view/manage/publish`, `pricing_rules.view/manage/publish`, `fuel_prices.view/manage/publish`, `fuel_surcharge_schedules.view/manage/publish`, and a separate exceptional-activation permission if operations needs it. Publishing/activation uses expected versions, actor, reason, audit and scoped approval. Teller invoice entry can select only currently eligible tariffs and view the resolved explanation; it cannot edit a policy or an observation.

Logical records are `tariffs`, `tariff_versions`, `ppa_share_rule_versions`, `fuel_price_observations`, `fuel_surcharge_policy_versions`, `fuel_surcharge_bands`, `invoice_item_pricing_snapshots` and explicit `invoice_charge_components`. Use scoped foreign keys, effective-time/range indexes, explicit version references and protected deletion. The invoice remains the monetary authority; a reporting projection may be rebuilt but cannot recompute history from current tariff rules.

Admin views show current/upcoming/retired tariff and fuel schedules, band boundaries, source freshness, affected tariff counts, preview calculations, conflict diagnostics and audit history. Customer and teller bill detail show the captured surcharge/PPA/tax result, not live policy values. Reverb silently refreshes authorized admin lists after publication/activation; it never broadcasts pricing data to unrelated customers. A changed effective Fuel surcharge percentage additionally creates an IMPORTANT, Customer-role-only in-app announcement in the same transaction as the source change. The notice contains only old/new percentages and the future-bills boundary; the broadcast carries an announcement hint, and each customer refetches through the authorized announcement feed.

## Fixtures, phases and acceptance

P0-03 expands its verified fixture set to include tariff classification, PPA/fuel ordering, decimal/rounding and boundary examples. P2-10 implements the versioned tariff/fuel policy workflow after P1-03/P1-05, and P2-01/P2-06 consume its snapshots during calculation and fiscal issuance. P4 reporting reconciles tariff base, PPA share, fuel surcharge and tax by captured rule version. Historical legacy records retain their original calculation provenance and are not retroactively tagged from current tariffs.

Required future acceptance includes:

- Mixed VATable/exempt/zero-rated, PPA-applicable and fuel-applicable lines on the same invoice; no bill-wide checkbox side effect.
- Fuel prices exactly at lower/upper band boundaries, no matching band, overlapping/gapped bands, changed unit/currency, scheduled future activation and Asia/Manila date/time boundary.
- A draft recalculated after a policy change, a post-time stale-version conflict, a published invoice after later tariff/schedule changes and a failed/retried posting.
- Correct transparent fuel/PPA/tax display in service, NSCL and PPA document routes; price source/audit access restrictions and unauthorized policy publication rejected.

Actual fuel sources, units, schedule cadence, tax/PPA/fuel ordering, rate percentages and rounding/truncation rules remain setup/accountant/fixture inputs. They are not inferred from the legacy manual multiplier.
