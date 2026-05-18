# Package: Payment

**Path:** `shopware/Component/Payment/`  
**Namespace:** `Mollie\Shopware\Component\Payment\*`  
**Coverage (as of 2026-04-22):** 324/2210 statements = **14.7 %** _(Wave 1 additions not yet re-measured)_  
**Files in scope:** 134

## Description

Payment processing: payment handlers, actions (Pay/Finalize), CreatePaymentBuilder, PaymentMethodInstaller, ApplePayDirect, PayPalExpress, mandate handling, webhook routes, express methods, method removers.

## Priority

Wave 1 (low-hanging): exception classes, struct (response/payload DTOs), empty per-method subclasses (one per payment method).
Wave 2: route tests with FakeGateway, ApplePayDirect routes/controller, PaymentMethodInstaller.
Wave 3: ExpressMethod/AccountService, ApplePayController (requires HTTP context).

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Component/Payment/ExpressMethod/AccountService.php` | 131 | 0 % | – | – |
| [x] | `Component/Payment/CreatePaymentBuilder.php` | 118 | 100 % | – | – |
| [x] | `Component/Payment/Route/WebhookRoute.php` | 112 | ≥80 % | `tests/Unit/Payment/Route/WebhookRouteTest.php` | – |
| [ ] | `Component/Payment/PaymentMethodInstaller.php` | 105 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/GetShippingMethodsRoute.php` | 101 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/ApplePayController.php` | 88 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/PayRoute.php` | 72 | 0 % | – | – |
| [x] | `Component/Payment/ApplePayDirect/ApplePayDirectException.php` | 70 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectExceptionTest.php` | – |
| [x] | `Component/Payment/Action/Pay.php` | 64 | 83 % | – | – |
| [x] | `Component/Payment/Route/WebhookException.php` | 64 | ~100 % | `tests/Unit/Payment/Route/WebhookExceptionTest.php` | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/GetCartRoute.php` | 59 | 0 % | – | – |
| [ ] | `Component/Payment/Controller/PaymentController.php` | 55 | 0 % | – | – |
| [x] | `Component/Payment/PayPalExpress/PaypalExpressException.php` | 51 | ~100 % | `tests/Unit/Payment/PayPalExpress/PaypalExpressExceptionTest.php` | – |
| [ ] | `Component/Payment/PayPalExpress/PaypalExpressMethodRemover.php` | 47 | 0 % | – | – |
| [/] | `Component/Payment/PayAction.php` | 43 | 12 % | – | – |
| [ ] | `Component/Payment/MethodRemover/VoucherPaymentMethodRemover.php` | 42 | 0 % | – | – |
| [x] | `Component/Payment/ApplePayDirect/Route/CreateSessionRoute.php` | 37 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectRoutesTest.php` | – |
| [x] | `Component/Payment/Action/Finalize.php` | 36 | 100 % | – | – |
| [x] | `Component/Payment/Mandate/Route/ListMandatesRoute.php` | 36 | ~100 % | `tests/Unit/Payment/Mandate/MandateRoutesTest.php` | – |
| [ ] | `Component/Payment/ExpressMethod/CartBackupService.php` | 34 | 0 % | – | – |
| [x] | `Component/Payment/Mandate/Route/RevokeMandateRoute.php` | 34 | ~100 % | `tests/Unit/Payment/Mandate/MandateRoutesTest.php` | – |
| [x] | `Component/Payment/PaymentMethodUpdater.php` | 32 | 94 % | – | – |
| [ ] | `Component/Payment/MethodRemover/RemovePaymentMethodRoute.php` | 30 | 0 % | – | – |
| [ ] | `Component/Payment/PayPalExpress/PayPalExpressController.php` | 28 | 0 % | – | – |
| [ ] | `Component/Payment/Handler/AbstractMolliePaymentHandler.php` | 27 | 0 % | – | – |
| [ ] | `Component/Payment/PointOfSale/PointOfSaleController.php` | 27 | 0 % | – | – |
| [/] | `Component/Payment/FinalizeAction.php` | 26 | 4 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/ApplePayStoreFrontSubscriber.php` | 25 | 0 % | – | – |
| [x] | `Component/Payment/PayPalExpress/Route/FinishCheckoutRoute.php` | 25 | ~100 % | `tests/Unit/Payment/PayPalExpress/PayPalExpressRoutesTest.php` | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/SetShippingMethodRoute.php` | 24 | 0 % | – | – |
| [x] | `Component/Payment/Mandate/MandateException.php` | 23 | ~100 % | `tests/Unit/Payment/Mandate/MandateExceptionTest.php` | – |
| [ ] | `Component/Payment/PayPalExpress/PayPalExpressStoreFrontSubscriber.php` | 20 | 0 % | – | – |
| [x] | `Component/Payment/PayPalExpress/Route/CancelCheckoutRoute.php` | 18 | ~100 % | `tests/Unit/Payment/PayPalExpress/PayPalExpressRoutesTest.php` | – |
| [ ] | `Component/Payment/Controller/PaymentMethodController.php` | 16 | 0 % | – | – |
| [ ] | `Component/Payment/Mandate/MandateController.php` | 16 | 0 % | – | – |
| [x] | `Component/Payment/PayPalExpress/Route/StartCheckoutRoute.php` | 16 | ~100 % | `tests/Unit/Payment/PayPalExpress/PayPalExpressRoutesTest.php` | – |
| [x] | `Component/Payment/PayPalExpress/Route/FinishCheckoutResponse.php` | 14 | ~100 % | `tests/Unit/Payment/PayPalExpress/PayPalExpressResponsesTest.php` | – |
| [ ] | `Component/Payment/PaymentMethodRepository.php` | 14 | 0 % | – | – |
| [x] | `Component/Payment/Route/ReturnRouteResponse.php` | 14 | ~100 % | `tests/Unit/Payment/Route/PaymentRouteResponsesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/PayResponse.php` | 13 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectResponsesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Struct/ApplePayCart.php` | 12 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectStructsTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Struct/FakeApplePayAddress.php` | 12 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectStructsTest.php` | – |
| [ ] | `Component/Payment/PaymentHandlerLocator.php` | 12 | 0 % | – | – |
| [x] | `Component/Payment/PointOfSale/Route/ListTerminalsRoute.php` | 12 | ~100 % | `tests/Unit/Payment/PointOfSale/PointOfSaleRoutesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/RestoreCartRoute.php` | 11 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectRoutesTest.php` | – |
| [ ] | `Component/Payment/ExpressMethod/ExpressCartItemAddRoute.php` | 11 | 0 % | – | – |
| [x] | `Component/Payment/PayPalExpress/Route/StartCheckoutResponse.php` | 11 | ~100 % | `tests/Unit/Payment/PayPalExpress/PayPalExpressResponsesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/ApplePayDirectEnabledResponse.php` | 10 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectResponsesTest.php` | – |
| [/] | `Component/Payment/PaymentHandlerTrait.php` | 10 | 20 % | – | – |
| [x] | `Component/Payment/ApplePayDirect/Route/ApplePayDirectEnabledRoute.php` | 9 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectRoutesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/GetApplePayIdResponse.php` | 9 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectResponsesTest.php` | – |
| [x] | `Component/Payment/Method/CardPayment.php` | 9 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/CreditCard/StoreCreditCardTokenResponse.php` | 8 | ~100 % | `tests/Unit/Payment/CreditCard/CreditCardRoutesTest.php` | – |
| [x] | `Component/Payment/Mandate/Route/ListMandatesResponse.php` | 8 | ~100 % | `tests/Unit/Payment/Mandate/MandateResponsesTest.php` | – |
| [x] | `Component/Payment/Mandate/Route/StoreMandateIdResponse.php` | 8 | ~100 % | `tests/Unit/Payment/Mandate/MandateResponsesTest.php` | – |
| [x] | `Component/Payment/PointOfSale/Route/ListTerminalsResponse.php` | 8 | ~100 % | `tests/Unit/Payment/PointOfSale/PointOfSaleResponsesTest.php` | – |
| [x] | `Component/Payment/PointOfSale/Route/StoreTerminalResponse.php` | 8 | ~100 % | `tests/Unit/Payment/PointOfSale/PointOfSaleResponsesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/SetShippingMethodResponse.php` | 7 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectResponsesTest.php` | – |
| [ ] | `Component/Payment/CreditCard/CreditCardController.php` | 7 | 0 % | – | – |
| [x] | `Component/Payment/Method/PayPalExpressPayment.php` | 7 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/PayPalExpress/Route/CancelCheckoutResponse.php` | 7 | ~100 % | `tests/Unit/Payment/PayPalExpress/PayPalExpressResponsesTest.php` | – |
| [ ] | `Component/Payment/Route/ReturnRoute.php` | 7 | 0 % | – | – |
| [x] | `Component/Payment/Route/WebhookResponse.php` | 7 | ~100 % | `tests/Unit/Payment/Route/PaymentRouteResponsesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/CreateSessionResponse.php` | 6 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectResponsesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/GetApplePayIdRoute.php` | 6 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectRoutesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/GetCartResponse.php` | 6 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectResponsesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Route/GetShippingMethodsResponse.php` | 6 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectResponsesTest.php` | – |
| [x] | `Component/Payment/Event/PaymentCreatedEvent.php` | 6 | ~100 % | `tests/Unit/Payment/Event/PaymentEventsTest.php` | – |
| [x] | `Component/Payment/Mandate/Route/RevokeMandateResponse.php` | 6 | ~100 % | `tests/Unit/Payment/Mandate/MandateResponsesTest.php` | – |
| [x] | `Component/Payment/Method/ApplePayPayment.php` | 6 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/BancomatPayPayment.php` | 6 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/BizumPayment.php` | 6 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/PosPayment.php` | 6 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [~] | `Component/Payment/ApplePayDirect/Route/AddProductResponse.php` | 5 | – | to-be-deleted (file no longer exists) | – |
| [x] | `Component/Payment/ApplePayDirect/Route/RestoreCartResponse.php` | 5 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectResponsesTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Struct/ApplePayShippingMethod.php` | 5 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectStructsTest.php` | – |
| [x] | `Component/Payment/ApplePayDirect/Struct/ApplePayLineItem.php` | 4 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectStructsTest.php` | – |
| [x] | `Component/Payment/Method/PaySafeCardPayment.php` | 4 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [~] | `Component/Payment/ApplePayDirect/Route/AddProductRoute.php` | 3 | – | to-be-deleted (file no longer exists) | – |
| [x] | `Component/Payment/ApplePayDirect/Struct/ApplePayAmount.php` | 3 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectStructsTest.php` | – |
| [x] | `Component/Payment/CreditCard/StoreCreditCardTokenRoute.php` | 3 | ~100 % | `tests/Unit/Payment/CreditCard/CreditCardRoutesTest.php` | – |
| [x] | `Component/Payment/Event/ModifyCreatePaymentPayloadEvent.php` | 3 | ~100 % | `tests/Unit/Payment/Event/PaymentEventsTest.php` | – |
| [x] | `Component/Payment/Event/PaymentFinalizeEvent.php` | 3 | ~100 % | `tests/Unit/Payment/Event/PaymentEventsTest.php` | – |
| [x] | `Component/Payment/Mandate/Route/StoreMandateIdRoute.php` | 3 | ~100 % | `tests/Unit/Payment/Mandate/MandateRoutesTest.php` | – |
| [x] | `Component/Payment/PointOfSale/Route/StoreTerminalRoute.php` | 3 | ~100 % | `tests/Unit/Payment/PointOfSale/PointOfSaleRoutesTest.php` | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/AbstractApplePayDirectEnabledRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/AbstractCreateSessionRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/AbstractGetApplePayIdRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/AbstractGetCartRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/AbstractGetShippingMethodsRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/AbstractPayRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/AbstractRestoreCartRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/ApplePayDirect/Route/AbstractSetShippingMethodRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/ExpressMethod/AbstractAccountService.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/Mandate/Route/AbstractListMandatesRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/Mandate/Route/AbstractRevokeMandateRoute.php` | 2 | 0 % | – | – |
| [x] | `Component/Payment/Method/AlmaPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/BanContactPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/BankTransferPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/BelfiusPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/BilliePayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/BlikPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/DirectDebitPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/EpsPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/GiftCardPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/IdealPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/In3Payment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/KbcPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/KlarnaPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/MbWayPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/MobilePayPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/MultiBancoPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/MyBankPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/PayByBankPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/PayPalPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/PayconiqPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/Przelewy24Payment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/RivertyPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/SatisPayPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/SwishPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/TrustlyPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/TwintPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/VippsPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [x] | `Component/Payment/Method/VoucherPayment.php` | 2 | ~100 % | `tests/Unit/Payment/Method/PaymentMethodsTest.php` | – |
| [ ] | `Component/Payment/PayPalExpress/Route/AbstractCancelCheckoutRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/PayPalExpress/Route/AbstractFinishCheckoutRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/PayPalExpress/Route/AbstractStartCheckoutRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/PointOfSale/Route/AbstractListTerminalsRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/Route/AbstractReturnRoute.php` | 2 | 0 % | – | – |
| [ ] | `Component/Payment/MethodRemover/AbstractPaymentRemover.php` | 1 | 0 % | – | – |
| [x] | `Component/Payment/ApplePayDirect/Struct/ApplePayShippingLineItem.php` | 0 | ~100 % | `tests/Unit/Payment/ApplePayDirect/ApplePayDirectStructsTest.php` | – |
| [ ] | `Component/Payment/ExpressMethod/AbstractCartBackupService.php` | 0 | 0 % | – | – |
| [ ] | `Component/Payment/ExpressMethod/VisibilityRestrictionCollection.php` | 0 | 0 % | – | – |
| [ ] | `Component/Payment/Route/AbstractWebhookRoute.php` | 0 | 0 % | – | – |

## Integration Tests

Candidates: classes that talk to the Shopware DAL (order, order_transaction,
customer repositories) **or** the Mollie client / gateway / webhook.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- **Actions:** `Pay`, `Finalize`, `PayAction`, `FinalizeAction` — Mollie
  sandbox + state machine + DAL writes.
- **Routes with real Mollie traffic:** `WebhookRoute`, `ReturnRoute`,
  `ApplePayDirect/Route/*`, `PayPalExpress/Route/*`, `Mandate/Route/*`,
  `PointOfSale/Route/*`, `CreditCard/StoreCreditCardTokenRoute`.
- **Installers / updaters:** `PaymentMethodInstaller`, `PaymentMethodUpdater`
  — persist into `payment_method` table.
- **Services with DAL:** `ExpressMethod/AccountService`,
  `ExpressMethod/CartBackupService`, `PaymentHandlerLocator`,
  `PaymentMethodRepository`, `MethodRemover/*Route`.
- **Payment handlers:** `Handler/AbstractMolliePaymentHandler` and per-method
  handlers — end-to-end happy path via `MolliePage`.

Unit only (no integration test):

- All `Component/Payment/Method/*Payment.php` classes (configuration only).
- All `*Response`, `Struct/*`, `Event/*`, `*Exception` classes.
- `Abstract*` routes / services (no concrete behaviour).
- `PaymentHandlerTrait` (trait, covered through handlers).

Legend: `[x]` = integration test covers the flow, `[/]` = partial, `[ ]` = no
test, `[~]` = to-be-deleted.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | _(to be filled per wave)_ | – | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
