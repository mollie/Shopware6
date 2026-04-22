# Package: FlowBuilder

**Path:** `shopware/Component/FlowBuilder/`  
**Namespace:** `Mollie\Shopware\Component\FlowBuilder\*`  
**Coverage (as of 2026-04-22):** 0/76 statements = **0.0 %**  
**Files in scope:** 16

## Description

Shopware Flow Builder events: payment events, webhook events, EventData storer, subscriber.

## Priority

Wave 1 in full: event classes are mostly plain getter/constructor classes — easy coverage win for many files.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Component/FlowBuilder/Event/Webhook/WebhookEvent.php` | 20 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Payment/AbstractPaymentEvent.php` | 18 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Subscriber/BusinessEventSubscriber.php` | 13 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/EventData/PaymentType.php` | 8 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Storer/PaymentDataStorer.php` | 7 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Payment/CancelledEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Payment/FailedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Payment/SuccessEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Webhook/WebhookStatusAuthorizedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Webhook/WebhookStatusCancelledEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Webhook/WebhookStatusExpiredEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Webhook/WebhookStatusFailedEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Webhook/WebhookStatusOpenEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Webhook/WebhookStatusPaidEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/Webhook/WebhookStatusPendingEvent.php` | 1 | 0 % | – | – |
| [ ] | `Component/FlowBuilder/Event/WebhookReceivedEvent.php` | 0 | 0 % | – | – |

## Integration Tests

Candidates: flow actions that actually trigger side effects when a Shopware
flow fires. Each action is resolved through the container and modifies DAL
state (order custom fields, mail, …).
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- All concrete `*Action` classes under `Component/FlowBuilder/Action/` —
  integration test that fires the flow and verifies the DAL side effect.

Unit only: `Event/*` classes (data carriers), abstract action base classes,
action definitions (config-only).

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | _(to be filled per wave)_ | – | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
