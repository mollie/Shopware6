# Feature: Express Checkout — Address & Guest Account Sync

**Status:** Plan — decisions recorded, ready for implementation on
top of the partially-done Apple Pay path.
**Owner:** Vitalij Mik
**Created:** 2026-04-22
**Last updated:** 2026-04-22

---

## Motivation

The plugin offers two express-checkout payment methods — **Apple Pay
Direct** and **PayPal Express** — that let a customer pay without
registering. During checkout, a **guest account** and the matching
**addresses** are silently created from the Apple-Pay / PayPal payload.

Naive implementations spawn thousands of near-duplicate guest
accounts and duplicate address rows for the same customer. The
plugin already has logic to avoid this, but the work is
**incomplete** and **asymmetric**: the Apple Pay path is largely
done with good test coverage, the PayPal Express path searches for
existing data but does not act on the result.

A second scenario needs the same mechanism to work end-to-end: a
**registered, logged-in customer** with saved address A picks up a
new address B from Apple Pay / PayPal Express. B must become the
default billing and shipping address for the rest of the checkout —
otherwise the confirmation page shows the wrong address.

## Out of scope

- Onboarding new express-checkout payment methods.
- Visual redesign of the express buttons or their placement.
- GDPR / data-retention rules for guest accounts (separate topic).
- Merging guest accounts with registered accounts on sign-up
  (separate feature).

---

## Current state (2026-04-22)

Observed in the code base. Apple Pay lives in the legacy
`Kiener\MolliePayments` namespace, PayPal Express lives in the new
`Mollie\Shopware` namespace — the two paths therefore diverge
currently.

### Summary

| Aspect                                      | Apple Pay Direct                                       | PayPal Express                                                 |
|---------------------------------------------|--------------------------------------------------------|----------------------------------------------------------------|
| Guest reuse (find-by-email)                 | Done                                                   | Done                                                           |
| Guest creation                              | Done                                                   | Done                                                           |
| Address reuse — search for existing         | Done                                                   | Done                                                           |
| Address reuse — actually use the result     | Done                                                   | **Missing** — result ignored                                   |
| Set default billing/shipping on customer    | Done                                                   | **Missing**                                                    |
| Logged-in customer: update default address  | Done (for new/reused express address)                  | **Missing**                                                    |
| Unit / integration tests                    | Partial                                                | None                                                           |
| Namespace                                   | `Kiener\MolliePayments\*` (legacy)                     | `Mollie\Shopware\*` (new)                                      |

### What is already implemented

**Guest account reuse** — both paths:

- `src/Service/CustomerService.php::findCustomerByEmail()` filters by
  `guest = true`, `active = true`, sales-channel-bound, sorts by
  creation date descending, returns the most recent guest match.
- Apple Pay uses it via
  `src/Components/ApplePayDirect/ApplePayDirect.php::prepareCustomer()`.
- PayPal Express uses it via
  `shopware/Component/Payment/ExpressMethod/AccountService.php::loginOrCreateAccount()`
  (through `getCustomerByEmail`).
- Guest creation itself (when no existing guest matches) is
  implemented in both paths:
  `CustomerService::createGuestAccount()` (Apple Pay),
  `AccountService::createNewGuestAccount()` (PayPal).

**Address reuse (Apple Pay only):**

- `src/Service/CustomerService.php::reuseOrCreateAddresses()`
  (lines 536–629).
- Matches on the custom field
  `customFields.mollie_payments.express_address_id`, which is an MD5
  hash of address components.
- Current hash composition: `firstName`, `lastName`, `email`,
  `street`, `streetAdditional`, `zipCode`, `city`, `countryCode`.
  Does **not** include `phone` or `company` today (to be extended,
  see Decisions, point 2).
- If no match is found, new address rows are inserted and linked to
  the customer; the matched-on custom field is written so the next
  visit reuses them.
- Updates the customer's `defaultBillingAddressId` and
  `defaultShippingAddressId` so the following checkout steps pick up
  the express-provided address.
- Called from `ApplePayDirect::prepareCustomer()` with
  `$updateShippingAddress = true`, which covers the logged-in path.

**Address reuse (PayPal Express) — incomplete:**

- `shopware/Component/Payment/ExpressMethod/AccountService.php::createOrReuseAddresses()`
  (lines 285–299).
- Searches the customer's address book using the same MD5 matching
  strategy.
- **Returns the original `RequestDataBag` unchanged** — the search
  result is discarded. No address is linked to the customer, no
  defaults are updated.
- Caller (`loginOrCreateAccount()`, line 100) ignores the return
  value as well.

**Default address update for logged-in customers:**

- Apple Pay: handled by
  `CustomerService::reuseOrCreateAddresses()` — `defaultBillingAddressId`
  and `defaultShippingAddressId` on the `customer` row are rewritten
  to the express-address IDs (lines 618–621).
- PayPal Express: **not implemented**. If a logged-in customer with
  saved address A completes PayPal Express with address B, the
  customer row keeps A as default. The confirmation step therefore
  shows the wrong default address.

### Hash composition change and its effect on existing addresses

Adding `phone` and `company` to the MD5 hash (Decisions, point 2)
changes the hash value for every address that previously had these
fields populated in Shopware. Existing
`customFields.mollie_payments.express_address_id` values were
computed from the old field set and will therefore **not match** a
freshly-computed hash on the customer's next express checkout.

Consequence: on the first express checkout after the update, a
returning customer may get a new address row inserted instead of
reusing the old one. The old row stays on the customer (not deleted)
and a new row with the new hash is added alongside. From the second
express checkout onwards the reuse works again.

This is an accepted one-time cost; no migration that recomputes
hashes is planned. The alternative — walking every customer's
addresses to recompute the hash — is expensive and adds a failure
mode for a transient issue.

### Shopware convention: `$billingAddress === null` means "same as shipping"

Shopware distinguishes between billing and shipping addresses, but
the storefront checkout exposes a "billing address is the same as
shipping" option. When that flag is active, the billing address
payload is **null** — the address itself is not duplicated, the
shipping address simply plays both roles.

The express flows pass this through: the `reuseOrCreateAddresses`
signature is
`(CustomerEntity $customer, AddressStruct $shippingAddress, ..., ?AddressStruct $billingAddress = null)`,
and `$billingAddress === null` is a valid input that the
synchronizer must keep supporting. Lines 614–617 in
`CustomerService.php` handle exactly this case: when no billing
address is provided and none was found, the shipping address is
reused as the billing default.

Consequence for the new `AddressSynchronizer`: the method keeps
`?Address $billing = null` as the nullable parameter and maps
`null` to "reuse the shipping-address id for both defaults". The
existing tests cover this case.

### Tests

- `tests/PHPUnit/Service/CustomerServiceAddressTest.php` (281 LOC) —
  Apple Pay / legacy path only:
  - `testReuseOrCreateAddressesSetsCorrectDefaultShippingAndBillingIds`
  - `testReuseOrCreateAddressesUsesSameIdForBothWhenNoBillingAddress`
  - `testCreateGuestAccountSetsBillingAddressDataFromBillingNotShipping`
- PayPal Express path has **no tests** for guest reuse or address
  reuse.
- No integration tests exercise the logged-in-customer-updates-default
  scenario for either path.

---

## Target state

Both paths reach behavioural parity and live in the new namespace:

1. **Guest reuse** — unchanged (already OK). Both paths reuse an
   existing guest by email within the sales channel.
2. **Address reuse — actually used:**
   - Every successful address match wires the matched address up to
     the customer and to the checkout request, including updating
     `defaultBillingAddressId` / `defaultShippingAddressId`.
   - Every non-match creates the address row with the
     `express_address_id` custom field set, so the next visit
     matches.
3. **Logged-in customer:** the express-provided address wins. The
   customer's default billing/shipping ids are updated to the
   express addresses for the duration of this checkout. (The
   customer keeps these defaults afterwards — same as today's Apple
   Pay behaviour; see Open questions, point 2.)
4. **One shared service:** the reuse logic moves to
   `shopware/Component/Payment/ExpressMethod/AddressSynchronizer.php`
   (new, final, strict types) and is consumed by both Apple Pay and
   PayPal Express. The Apple-Pay-specific copy in
   `CustomerService::reuseOrCreateAddresses()` is removed.
5. **Test coverage:** both paths covered by integration tests
   against `IntegrationTestBehaviour`, including the
   `billing === null` case.

---

## Proposed component changes

### New

- `shopware/Component/Payment/ExpressMethod/AddressSynchronizer.php`
  — canonical service. Final, `declare(strict_types=1)`. Public
  surface:
  - `syncAddresses(CustomerEntity $customer, Address $shipping, ?Address $billing, SalesChannelContext $context): AddressSyncResult`
  - `AddressSyncResult` carries the resolved shipping / billing
    address IDs so callers can feed them into the checkout request.
- Unit tests in `tests/Unit/Payment/ExpressMethod/AddressSynchronizerTest.php`.
- Integration tests in
  `tests/Integration/Payment/ExpressMethod/AddressSynchronizerTest.php`,
  including the logged-in-customer scenario.

### To adjust

- **Apple Pay Direct:**
  - `src/Components/ApplePayDirect/ApplePayDirect.php::prepareCustomer()`
    switches to the new synchronizer.
  - `src/Service/CustomerService.php::reuseOrCreateAddresses()` is
    deleted once all callers use the synchronizer.
- **PayPal Express:**
  - `shopware/Component/Payment/ExpressMethod/AccountService.php::loginOrCreateAccount()`
    calls `AddressSynchronizer::syncAddresses` and uses the
    returned IDs to build the checkout request.
  - `createOrReuseAddresses()` is removed.

### To drop

- `CustomerService::reuseOrCreateAddresses()` once no one calls it.
- `AccountService::createOrReuseAddresses()` once the synchronizer
  replaces it.

---

## Phases / work packages

Each phase is an independent PR.

### Phase 1 — Extract the synchronizer

- Introduce `AddressSynchronizer` in the new namespace.
- Port the Apple Pay logic verbatim, preserving the
  `$billing === null` "same as shipping" semantics.
- Add unit tests for the happy paths, the two-distinct-addresses
  case and the `$billing === null` case.
- Apple Pay delegates to the synchronizer. Behaviour unchanged.

### Phase 2 — PayPal Express parity

- `AccountService::loginOrCreateAccount()` calls the synchronizer
  and uses its result.
- Delete the stub `createOrReuseAddresses()`.
- Add integration tests covering:
  - New guest → new addresses created with `express_address_id`.
  - Returning guest → existing addresses reused.
  - Logged-in customer → defaults updated to express addresses.

### Phase 3 — Clean-up

- Remove `CustomerService::reuseOrCreateAddresses()`.
- Delete the Apple-Pay-only `CustomerServiceAddressTest.php` cases
  that duplicate the synchronizer tests; keep only what exercises
  `createGuestAccount` (still in CustomerService).

---

## Tests

- **Unit** (`tests/Unit/Payment/ExpressMethod/AddressSynchronizerTest.php`):
  - Two distinct addresses (explicit billing) → both rows inserted,
    defaults point to the correct rows.
  - `$billing === null` → single shipping row inserted, both defaults
    point to that row (Shopware's "billing same as shipping").
  - Existing matching address → reused, no insert.
  - Partial match (shipping matches, billing does not) → only
    billing is inserted, shipping is reused.
- **Integration**
  (`tests/Integration/Payment/ExpressMethod/AddressSynchronizerTest.php`,
  uses `IntegrationTestBehaviour`):
  - New guest with Apple Pay-style payload → guest + addresses
    created, defaults set.
  - Same email again → existing guest reused, existing addresses
    reused.
  - Logged-in registered customer with saved address A → Apple Pay
    returns address B → customer `defaultBillingAddressId` and
    `defaultShippingAddressId` now point to B.
  - Same scenarios for PayPal Express to prove parity.
- **Behat:** an end-to-end express scenario per path would be
  valuable but is not blocking; tracked separately.

---

## Decisions

All open questions have been resolved. The answers drive the body of
this document.

1. **Permanent default-address update** → keep as-is. The
   synchronizer writes the express addresses as the customer's
   permanent `defaultBillingAddressId` / `defaultShippingAddressId`.
   A registered customer can change the defaults afterwards in the
   account UI; a guest has no account UI and is unaffected.
2. **Address matching fields** → include `phone` and
   `organizationName` / `company` in the MD5 hash **when they are
   provided**. Missing values are treated as empty strings. Match
   set is therefore: `firstName`, `lastName`, `email`, `street`,
   `streetAdditional`, `zipCode`, `city`, `countryCode`, `phone`,
   `company`. See "Hash composition" note in Current state.
3. ~~**Shipping-address-only payloads**~~ → resolved earlier.
   Shopware's checkout treats `billing === null` as "billing is the
   same as shipping" (the storefront checkbox sets the billing
   payload to null). The synchronizer keeps `?Address $billing = null`
   as its nullable contract and maps `null` to "use the
   shipping-address id for the billing default". See the "Shopware
   convention" note in Current state.
4. **Apple Pay namespace migration** → in scope for the broader
   refactor. Apple Pay's orchestration class
   (`src/Components/ApplePayDirect/`) is being moved to
   `shopware/Component/Payment/ApplePayDirect/` step by step; some
   parts have already moved. This feature does **not** do the full
   orchestration move — it only extracts the synchronizer and wires
   both express paths through it. The rest of the Apple Pay
   migration is tracked with the general `src/` → `shopware/`
   refactor in `../packages/payment.md`.
5. **Guest cleanup** → out of scope. The plugin never touches guest
   accounts after creation. Cleanup / retention is the merchant's
   responsibility.

---

## References

- `src/Service/CustomerService.php` — `findCustomerByEmail`,
  `reuseOrCreateAddresses`, `createGuestAccount` (legacy).
- `src/Components/ApplePayDirect/ApplePayDirect.php` — Apple Pay
  orchestration, calls `prepareCustomer`.
- `src/Struct/Address/AddressStruct.php` — legacy express address
  struct with MD5 `mollieAddressId`.
- `shopware/Component/Payment/ExpressMethod/AccountService.php` —
  PayPal Express guest + (stub) address handling.
- `shopware/Component/Mollie/Address.php` — new express address
  struct with MD5 id.
- `shopware/Component/Payment/PayPalExpress/Route/FinishCheckoutRoute.php`
  — PayPal finish flow.
- `tests/PHPUnit/Service/CustomerServiceAddressTest.php` — existing
  Apple Pay tests (to be ported / replaced by synchronizer tests).
- `../packages/payment.md` — refactor progress tracking for the
  Payment package (new `AddressSynchronizer` lands under
  `shopware/Component/Payment/ExpressMethod/`).
