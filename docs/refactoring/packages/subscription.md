# Package: Subscription

**Path:** `shopware/Component/Subscription/`  
**Namespace:** `Mollie\Shopware\Component\Subscription\*`  
**Coverage (as of 2026-04-22):** 108/1360 statements = **7.9 %**  
**Files in scope:** 65

## Description

Subscription feature: actions (Cancel/Pause/Resume/Skip), routes (Renew etc.), CopyOrderService, cart validators, DAL entities (incl. SubscriptionAddress/History aggregates), subscribers (Pending/Payment), SubscriptionActionHandler.

## Priority

Wave 1: cart/error classes, exception classes, event classes, DAL/Country/Currency/Customer/Order/Salutation.
Wave 2: Action/Skip/Pause/Cancel/Resume + SubscriptionActionHandler + SubscriptionDataService.
Wave 3: subscribers (Pending/Payment), Route/RenewRoute, controller, CopyOrderService.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Component/Subscription/Subscriber/PendingSubscriptionSubscriber.php` | 127 | 0 % | – | – |
| [ ] | `Component/Subscription/Subscriber/PaymentSubscriber.php` | 110 | 0 % | – | – |
| [ ] | `Component/Subscription/CopyOrderService.php` | 105 | 0 % | – | – |
| [/] | `Component/Subscription/DAL/Subscription/SubscriptionEntity.php` | 90 | 62 % | – | – |
| [ ] | `Component/Subscription/Route/RenewRoute.php` | 86 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/SkipAction.php` | 61 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/PauseAction.php` | 58 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/CancelAction.php` | 55 | 0 % | – | – |
| [ ] | `Component/Subscription/Route/RenewException.php` | 53 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/ResumeAction.php` | 50 | 0 % | – | – |
| [ ] | `Component/Subscription/SubscriptionDataService.php` | 49 | 0 % | – | – |
| [ ] | `Component/Subscription/SubscriptionActionHandler.php` | 47 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionAddress/SubscriptionAddressEntity.php` | 44 | 0 % | – | – |
| [ ] | `Component/Subscription/Controller/SubscriptionController.php` | 40 | 0 % | – | – |
| [x] | `Component/Subscription/SubscriptionMetadata.php` | 39 | 82 % | – | – |
| [ ] | `Component/Subscription/DAL/Subscription/SubscriptionDefinition.php` | 36 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionAddress/SubscriptionAddressDefinition.php` | 30 | 0 % | – | – |
| [ ] | `Component/Subscription/Cart/Validator/SubscriptionCartValidator.php` | 28 | 0 % | – | – |
| [ ] | `Component/Subscription/SubscriptionRemover.php` | 27 | 0 % | – | – |
| [ ] | `Component/Subscription/Route/WebhookRoute.php` | 25 | 0 % | – | – |
| [ ] | `Component/Subscription/Cart/Validator/AvailabilityInformationValidator.php` | 16 | 0 % | – | – |
| [ ] | `Component/Subscription/LineItemAnalyzer.php` | 16 | 0 % | – | – |
| [ ] | `Component/Subscription/Event/SubscriptionActionEvent.php` | 15 | 0 % | – | – |
| [ ] | `Component/Subscription/Controller/ApiController.php` | 14 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionHistory/SubscriptionHistoryDefinition.php` | 14 | 0 % | – | – |
| [/] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionHistory/SubscriptionHistoryEntity.php` | 12 | 17 % | – | – |
| [x] | `Component/Subscription/DAL/Subscription/SubscriptionStatus.php` | 11 | 100 % | – | – |
| [ ] | `Component/Subscription/Route/SubscriptionException.php` | 7 | 0 % | – | – |
| [ ] | `Component/Subscription/Route/WebhookException.php` | 7 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/AbstractAction.php` | 6 | 0 % | – | – |
| [ ] | `Component/Subscription/Cart/Error/PaymentMethodAvailabilityNotice.php` | 6 | 0 % | – | – |
| [x] | `Component/Subscription/DAL/Subscription/SubscriptionCollection.php` | 6 | 100 % | – | – |
| [ ] | `Component/Subscription/SubscriptionDataStruct.php` | 6 | 0 % | – | – |
| [ ] | `Component/Subscription/Cart/Error/InvalidGuestAccountError.php` | 5 | 0 % | – | – |
| [ ] | `Component/Subscription/Cart/Error/InvalidPaymentMethodError.php` | 5 | 0 % | – | – |
| [ ] | `Component/Subscription/Cart/Error/MixedCartBlockError.php` | 5 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Country/CountryExtension.php` | 3 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Country/CountryStateExtension.php` | 3 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Currency/CurrencyExtension.php` | 3 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Customer/CustomerExtension.php` | 3 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Order/OrderExtension.php` | 3 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Salutation/SalutationExtension.php` | 3 | 0 % | – | – |
| [ ] | `Component/Subscription/Event/ModifyCreateSubscriptionPayloadEvent.php` | 3 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/Exception/PauseAndResumeNotAllowedException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/Exception/SubscriptionActiveException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/Exception/SubscriptionNotActiveException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Exception/SubscriptionDisabledException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Exception/SubscriptionNotFoundException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Exception/SubscriptionWithoutAddressException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Exception/SubscriptionWithoutOrderException.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Route/AbstractRenewRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Route/AbstractWebhookRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Subscription/Action/Exception/NextPaymentAtNotFoundException.php` | 1 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionAddress/SubscriptionAddressCollection.php` | 1 | 0 % | – | – |
| [x] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionHistory/SubscriptionHistoryCollection.php` | 1 | 100 % | – | – |
| [ ] | `Component/Subscription/Event/SubscriptionCancelledEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/Subscription/Event/SubscriptionEndedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/Subscription/Event/SubscriptionPausedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/Subscription/Event/SubscriptionRenewedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/Subscription/Event/SubscriptionResumedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/Subscription/Event/SubscriptionSkippedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/Subscription/Event/SubscriptionStartedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/Subscription/DAL/Subscription/SubscriptionEvents.php` | 0 | 0 % | – | – |
| [ ] | `Component/Subscription/Exception/SubscriptionException.php` | 0 | 0 % | – | – |
| [ ] | `Component/Subscription/SubscriptionTag.php` | 0 | 0 % | – | – |

## Integration Tests

Candidates: services that persist subscriptions / schedules through the DAL
or call the Mollie customer / mandate / payment API.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- **Subscription services / managers** touching `subscription`,
  `subscription_address`, `subscription_history` repositories.
- **Renewal / scheduled handlers** that write state back to the DAL.
- **Routes** under `Component/Subscription/Route/*` exposed to store-api or
  admin-api.
- **Mollie-side calls**: customer creation, mandate listing, recurring
  payment creation — always via `MolliePage` / real sandbox.

Unit only: DTOs, events, exceptions, `SubscriptionTag`, enum-like structs,
pure config.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | _(to be filled per wave)_ | – | – | – |

## Notes

A release-level feature redesign is planned for this package — see
[`../features/subscriptions.md`](../features/subscriptions.md) for the
multi-subscription checkout plan. Several classes listed above are slated
to change shape or be removed by that feature (notably `CopyOrderService`,
`SubscriptionCartValidator`, `LineItemAnalyzer`). Coordinate test work with
the feature phases to avoid writing tests for classes that are about to go
away.

_(Space for package-specific decisions, fake requirements, special setups.)_
