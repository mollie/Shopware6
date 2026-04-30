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
| [x] | `Component/Subscription/DAL/Subscription/SubscriptionEntity.php` | 90 | – | `tests/Unit/Subscription/DAL/SubscriptionEntityTest.php` | – |
| [ ] | `Component/Subscription/Route/RenewRoute.php` | 86 | 0 % | – | – |
| [x] | `Component/Subscription/Action/SkipAction.php` | 61 | – | `tests/Unit/Subscription/Action/SkipActionTest.php` | – |
| [x] | `Component/Subscription/Action/PauseAction.php` | 58 | – | `tests/Unit/Subscription/Action/PauseActionTest.php` | – |
| [x] | `Component/Subscription/Action/CancelAction.php` | 55 | – | `tests/Unit/Subscription/Action/CancelActionTest.php` | – |
| [x] | `Component/Subscription/Route/RenewException.php` | 53 | – | `tests/Unit/Subscription/Route/RouteExceptionsTest.php` | – |
| [x] | `Component/Subscription/Action/ResumeAction.php` | 50 | – | `tests/Unit/Subscription/Action/ResumeActionTest.php` | – |
| [x] | `Component/Subscription/SubscriptionDataService.php` | 49 | – | `tests/Unit/Subscription/SubscriptionDataServiceTest.php` | – |
| [x] | `Component/Subscription/SubscriptionActionHandler.php` | 47 | – | `tests/Unit/Subscription/SubscriptionActionHandlerTest.php` | – |
| [x] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionAddress/SubscriptionAddressEntity.php` | 44 | – | `tests/Unit/Subscription/DAL/SubscriptionAddressEntityTest.php` | – |
| [ ] | `Component/Subscription/Controller/SubscriptionController.php` | 40 | 0 % | – | – |
| [x] | `Component/Subscription/SubscriptionMetadata.php` | 39 | 82 % | – | – |
| [x] | `Component/Subscription/DAL/Subscription/SubscriptionDefinition.php` | 36 | – | `tests/Unit/Subscription/DAL/EntityDefinitionsTest.php` | – |
| [x] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionAddress/SubscriptionAddressDefinition.php` | 30 | – | `tests/Unit/Subscription/DAL/EntityDefinitionsTest.php` | – |
| [x] | `Component/Subscription/Cart/Validator/SubscriptionCartValidator.php` | 28 | – | `tests/Unit/Subscription/Cart/SubscriptionCartValidatorTest.php` | – |
| [x] | `Component/Subscription/SubscriptionRemover.php` | 27 | – | `tests/Unit/Subscription/SubscriptionRemoverTest.php` | – |
| [ ] | `Component/Subscription/SubscriptionLineItemsResolver.php` | – | 0 % | – | – |
| [x] | `Component/Subscription/SubscriptionGroupAmount.php` | – | – | `tests/Unit/Subscription/SubscriptionGroupAmountTest.php` | – |
| [ ] | `Component/Subscription/SubscriptionGroupCartBuilder.php` | – | 0 % | – | – |
| [x] | `Component/Subscription/SubscriptionAddressSyncer.php` | – | – | `tests/Unit/Subscription/SubscriptionAddressSyncerTest.php` | – |
| [/] | `Component/Subscription/SubscriptionAddressId.php` | – | – | `tests/Unit/Subscription/SubscriptionAddressSyncerTest.php` (indirect) | – |
| [ ] | `Component/Subscription/SubscriptionGroupCart.php` | – | 0 % | – | – |
| [ ] | `Component/Subscription/Route/WebhookRoute.php` | 25 | 0 % | – | – |
| [x] | `Component/Subscription/Cart/Validator/AvailabilityInformationValidator.php` | 16 | – | `tests/Unit/Subscription/Cart/AvailabilityInformationValidatorTest.php` | – |
| [x] | `Component/Subscription/LineItemAnalyzer.php` | 16 | – | `tests/Unit/Subscription/LineItemAnalyzerTest.php` | – |
| [x] | `Component/Subscription/Event/SubscriptionActionEvent.php` | 15 | – | `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` | – |
| [ ] | `Component/Subscription/Controller/ApiController.php` | 14 | 0 % | – | – |
| [x] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionHistory/SubscriptionHistoryDefinition.php` | 14 | – | `tests/Unit/Subscription/DAL/EntityDefinitionsTest.php` | – |
| [x] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionHistory/SubscriptionHistoryEntity.php` | 12 | – | `tests/Unit/Subscription/DAL/SubscriptionHistoryEntityTest.php` | – |
| [x] | `Component/Subscription/DAL/Subscription/SubscriptionStatus.php` | 11 | 100 % | – | – |
| [x] | `Component/Subscription/Route/SubscriptionException.php` | 7 | – | `tests/Unit/Subscription/Route/RouteExceptionsTest.php` | – |
| [x] | `Component/Subscription/Route/WebhookException.php` | 7 | – | `tests/Unit/Subscription/Route/RouteExceptionsTest.php` | – |
| [x] | `Component/Subscription/Action/AbstractAction.php` | 6 | – | `tests/Unit/Subscription/Action/AbstractActionTest.php` | – |
| [x] | `Component/Subscription/Cart/Error/PaymentMethodAvailabilityNotice.php` | 6 | – | `tests/Unit/Subscription/Cart/CartErrorsTest.php` | – |
| [x] | `Component/Subscription/DAL/Subscription/SubscriptionCollection.php` | 6 | – | `tests/Unit/Subscription/DAL/SubscriptionCollectionTest.php` | – |
| [x] | `Component/Subscription/SubscriptionDataStruct.php` | 6 | – | `tests/Unit/Subscription/SubscriptionDataStructTest.php` | – |
| [x] | `Component/Subscription/Cart/Error/InvalidGuestAccountError.php` | 5 | – | `tests/Unit/Subscription/Cart/CartErrorsTest.php` | – |
| [x] | `Component/Subscription/Cart/Error/InvalidPaymentMethodError.php` | 5 | – | `tests/Unit/Subscription/Cart/CartErrorsTest.php` | – |
| [x] | `Component/Subscription/DAL/Country/CountryExtension.php` | 3 | – | `tests/Unit/Subscription/DAL/EntityExtensionsTest.php` | – |
| [x] | `Component/Subscription/DAL/Country/CountryStateExtension.php` | 3 | – | `tests/Unit/Subscription/DAL/EntityExtensionsTest.php` | – |
| [x] | `Component/Subscription/DAL/Currency/CurrencyExtension.php` | 3 | – | `tests/Unit/Subscription/DAL/EntityExtensionsTest.php` | – |
| [x] | `Component/Subscription/DAL/Customer/CustomerExtension.php` | 3 | – | `tests/Unit/Subscription/DAL/EntityExtensionsTest.php` | – |
| [x] | `Component/Subscription/DAL/Order/OrderExtension.php` | 3 | – | `tests/Unit/Subscription/DAL/EntityExtensionsTest.php` | – |
| [x] | `Component/Subscription/DAL/Salutation/SalutationExtension.php` | 3 | – | `tests/Unit/Subscription/DAL/EntityExtensionsTest.php` | – |
| [x] | `Component/Subscription/Event/ModifyCreateSubscriptionPayloadEvent.php` | 3 | – | `tests/Unit/Subscription/Event/ModifyCreateSubscriptionPayloadEventTest.php` | – |
| [x] | `Component/Subscription/Action/Exception/PauseAndResumeNotAllowedException.php` | 2 | – | `tests/Unit/Subscription/Action/PauseActionTest.php` (and Resume/Skip) | – |
| [x] | `Component/Subscription/Action/Exception/SubscriptionActiveException.php` | 2 | – | `tests/Unit/Subscription/Action/ResumeActionTest.php` | – |
| [x] | `Component/Subscription/Action/Exception/SubscriptionNotActiveException.php` | 2 | – | `tests/Unit/Subscription/Action/CancelActionTest.php` (and Pause/Skip) | – |
| [x] | `Component/Subscription/Exception/SubscriptionDisabledException.php` | 2 | – | `tests/Unit/Subscription/SubscriptionActionHandlerTest.php` | – |
| [x] | `Component/Subscription/Exception/SubscriptionNotFoundException.php` | 2 | – | `tests/Unit/Subscription/SubscriptionDataServiceTest.php` | – |
| [x] | `Component/Subscription/Exception/SubscriptionWithoutAddressException.php` | 2 | – | `tests/Unit/Subscription/SubscriptionDataServiceTest.php` | – |
| [x] | `Component/Subscription/Exception/SubscriptionWithoutOrderException.php` | 2 | – | `tests/Unit/Subscription/SubscriptionDataServiceTest.php` | – |
| [x] | `Component/Subscription/Route/AbstractRenewRoute.php` | 2 | – | _abstract — covered transitively when `RenewRoute` is tested_ | – |
| [x] | `Component/Subscription/Route/AbstractWebhookRoute.php` | 2 | – | _abstract — covered transitively when `WebhookRoute` is tested_ | – |
| [x] | `Component/Subscription/Action/Exception/NextPaymentAtNotFoundException.php` | 1 | – | `tests/Unit/Subscription/Action/SkipActionTest.php` | – |
| [x] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionAddress/SubscriptionAddressCollection.php` | 1 | – | `tests/Unit/Subscription/DAL/SubscriptionAddressCollectionTest.php` | – |
| [x] | `Component/Subscription/DAL/Subscription/Aggregate/SubscriptionHistory/SubscriptionHistoryCollection.php` | 1 | 100 % | – | – |
| [x] | `Component/Subscription/Event/SubscriptionCancelledEvent.php` | 1 | – | `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` | – |
| [x] | `Component/Subscription/Event/SubscriptionEndedEvent.php` | 1 | – | `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` | – |
| [x] | `Component/Subscription/Event/SubscriptionPausedEvent.php` | 1 | – | `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` | – |
| [x] | `Component/Subscription/Event/SubscriptionRenewedEvent.php` | 1 | – | `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` | – |
| [x] | `Component/Subscription/Event/SubscriptionResumedEvent.php` | 1 | – | `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` | – |
| [x] | `Component/Subscription/Event/SubscriptionSkippedEvent.php` | 1 | – | `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` | – |
| [x] | `Component/Subscription/Event/SubscriptionStartedEvent.php` | 1 | – | `tests/Unit/Subscription/Event/SubscriptionEventsTest.php` | – |
| [x] | `Component/Subscription/DAL/Subscription/SubscriptionEvents.php` | 0 | – | _constants only — no test needed_ | – |
| [x] | `Component/Subscription/Exception/SubscriptionException.php` | 0 | – | _abstract base — covered transitively via subclass tests_ | – |
| [x] | `Component/Subscription/SubscriptionTag.php` | 0 | – | _constants only — no test needed_ | – |

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
| [ ] | `Component/Mollie/Gateway/SubscriptionGateway` | Verify `timesRemaining` survives a Mollie-side cancel — drives whether the plugin needs to persist `times` in metadata before pause, or can rely on Mollie returning it on the next read. | – | – |
| [ ] | `Component/Subscription/SubscriptionLineItemsResolver` | Resolves cart vs. order line items via `CartService` and `order.repository`. Pure Shopware integration surface — extracted from `SubscriptionRemover` so the remover stays unit-testable. | – | – |
| [ ] | `Component/Subscription/SubscriptionGroupCartBuilder` | Assembles a temporary cart from order line items via `OrderConverter`, `CartService`, `LineItemFactoryRegistry`. Pure Shopware integration surface — extracted from `SubscriptionAmountCalculator` so the calculator stays unit-testable. | – | – |

## Notes

A release-level feature redesign is planned for this package — see
[`../features/subscriptions.md`](../features/subscriptions.md) for the
multi-subscription checkout plan. Several classes listed above are slated
to change shape or be removed by that feature (notably `CopyOrderService`,
`SubscriptionCartValidator`, `LineItemAnalyzer`). Coordinate test work with
the feature phases to avoid writing tests for classes that are about to go
away.

_(Space for package-specific decisions, fake requirements, special setups.)_
