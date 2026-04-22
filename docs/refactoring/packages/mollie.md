# Package: Mollie

**Path:** `shopware/Component/Mollie/`  
**Namespace:** `Mollie\Shopware\Component\Mollie\*`  
**Coverage (as of 2026-04-22):** 448/936 statements = **47.9 %**  
**Files in scope:** 40

## Description

Mollie SDK-facing struct and gateway layer: Address, CreatePayment, Customer, LineItem, Money, Payment, PaymentMethod, Profile, Terminal, Gateway (Client/Payment/Subscription/Session/Mandate/Profile/Customer).

## Priority

Most advanced package (47.9%). Wave 1: finish remaining struct getters (Mandate, Voucher, PaymentStatus) to reach 100%.
Wave 2: Gateway/SubscriptionGateway, SessionGateway, exception classes.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [/] | `Component/Mollie/Gateway/MollieGateway.php` | 163 | 60 % | – | – |
| [/] | `Component/Mollie/Address.php` | 88 | 56 % | – | – |
| [/] | `Component/Mollie/Payment.php` | 80 | 65 % | – | – |
| [x] | `Component/Mollie/LineItem.php` | 74 | 84 % | – | – |
| [ ] | `Component/Mollie/Gateway/SubscriptionGateway.php` | 73 | 0 % | – | – |
| [ ] | `Component/Mollie/Subscription.php` | 63 | 0 % | – | – |
| [ ] | `Component/Mollie/Gateway/SessionGateway.php` | 53 | 0 % | – | – |
| [x] | `Component/Mollie/CreatePayment.php` | 52 | 90 % | – | – |
| [x] | `Component/Mollie/PaymentStatus.php` | 40 | 80 % | – | – |
| [ ] | `Component/Mollie/Session.php` | 35 | 0 % | – | – |
| [ ] | `Component/Mollie/Gateway/CachedMollieGateway.php` | 24 | 0 % | – | – |
| [x] | `Component/Mollie/Gateway/ClientFactory.php` | 22 | 91 % | – | – |
| [ ] | `Component/Mollie/CreateSubscription.php` | 19 | 0 % | – | – |
| [x] | `Component/Mollie/Terminal.php` | 19 | 100 % | – | – |
| [x] | `Component/Mollie/Customer.php` | 16 | 100 % | – | – |
| [ ] | `Component/Mollie/Gateway/ApplePayGateway.php` | 12 | 0 % | – | – |
| [ ] | `Component/Mollie/CreateCapture.php` | 10 | 0 % | – | – |
| [x] | `Component/Mollie/Gateway/ExceptionTrait.php` | 10 | 100 % | – | – |
| [x] | `Component/Mollie/Money.php` | 10 | 90 % | – | – |
| [ ] | `Component/Mollie/Capture.php` | 9 | 0 % | – | – |
| [ ] | `Component/Mollie/SubscriptionStatus.php` | 8 | 0 % | – | – |
| [/] | `Component/Mollie/Interval.php` | 7 | 29 % | – | – |
| [x] | `Component/Mollie/LineItemType.php` | 7 | 100 % | – | – |
| [x] | `Component/Mollie/Locale.php` | 7 | 100 % | – | – |
| [x] | `Component/Mollie/VoucherCategory.php` | 6 | 100 % | – | – |
| [x] | `Component/Mollie/Mandate.php` | 5 | 100 % | – | – |
| [x] | `Component/Mollie/Profile.php` | 5 | 100 % | – | – |
| [x] | `Component/Mollie/MandateCollection.php` | 3 | 100 % | – | – |
| [ ] | `Component/Mollie/Exception/ApiException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Mollie/Exception/MissingCalculatedTaxException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Mollie/Exception/MissingCountryException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Mollie/Exception/MissingLineItemPriceException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Mollie/Exception/MissingOrderAddressException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Mollie/Exception/MissingSalutationException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Mollie/Exception/MissingShippingMethodException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Mollie/Exception/TransactionWithoutMollieDataException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Mollie/Exception/ApiKeyException.php` | 0 | 0 % | – | – |
| [ ] | `Component/Mollie/LineItemCollection.php` | 0 | 0 % | – | – |
| [ ] | `Component/Mollie/TerminalCollection.php` | 0 | 0 % | – | – |
| [ ] | `Component/Mollie/VoucherCategoryCollection.php` | 0 | 0 % | – | – |

## Integration Tests

Candidates: every Mollie API wrapper / gateway / client. This package is the
main consumer of the Mollie sandbox.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- **Gateways** (`MollieGateway*`) — always tested against the sandbox via
  `MolliePage`.
- **API wrappers / services** that call `MollieApiClient` (payments,
  customers, mandates, methods, shipments, refunds, captures).
- **Factory classes** that build authenticated clients from settings (DAL
  read of API keys + real HTTP connection).

Unit only: collections (`VoucherCategoryCollection`, …), enum/tag classes,
pure value objects.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | _(to be filled per wave)_ | – | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
