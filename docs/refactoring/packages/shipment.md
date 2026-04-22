# Package: Shipment

**Path:** `shopware/Component/Shipment/`  
**Namespace:** `Mollie\Shopware\Component\Shipment\*`  
**Coverage (as of 2026-04-22):** 0/291 statements = **0.0 %**  
**Files in scope:** 6

## Description

Shipment handling: ShipOrderRoute, OrderDeliverySubscriber, models, ShippingException.

## Priority

Wave 1: exception, models. Wave 2: ShipOrderRoute (139 stmts) with FakeGateway.
Wave 3: OrderDeliverySubscriber (90 stmts).

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Component/Shipment/Route/ShipOrderRoute.php` | 139 | 0 % | – | – |
| [ ] | `Component/Shipment/OrderDeliverySubscriber.php` | 90 | 0 % | – | – |
| [ ] | `Component/Shipment/Route/ShippingException.php` | 48 | 0 % | – | – |
| [ ] | `Component/Shipment/Route/ShipOrderResponse.php` | 9 | 0 % | – | – |
| [ ] | `Component/Shipment/OrderShippedEvent.php` | 3 | 0 % | – | – |
| [ ] | `Component/Shipment/Route/AbstractShipOrderRoute.php` | 2 | 0 % | – | – |

## Integration Tests

Candidates: the ship-order flow — DAL read of the order + Mollie shipment
call.
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

Primary targets:

- `Component/Shipment/Route/ShipOrderRoute.php` — integration test against
  the real Mollie sandbox via `MolliePage` with a completed fixture order.
- `Component/Shipment/OrderDeliverySubscriber.php` — Behat-level, since the
  subscriber reacts to a delivery state change event triggered from the UI
  flow.

Unit only: `ShipOrderResponse`, `OrderShippedEvent`, `ShippingException`,
`AbstractShipOrderRoute`.

| | Class | Reason | Test file | PR |
|---|---|---|---|---|
| [ ] | `Component/Shipment/Route/ShipOrderRoute.php` | Mollie shipment call + DAL order read | – | – |

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
