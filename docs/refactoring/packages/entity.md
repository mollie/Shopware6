# Package: Entity

**Path:** `shopware/Entity/`  
**Namespace:** `Mollie\Shopware\Entity\*`  
**Coverage (as of 2026-04-22):** 0/79 statements = **0.0 %**  
**Files in scope:** 5

## Description

Shopware DAL entities under `shopware/Entity/`: Cart, Customer, Order, PaymentMethod, Product.

## Priority

Wave 1: consolidate entity getter/setter tests with `#[DataProvider]`. One test per entity covering every getter via data provider.

## Files

Legend: `[x]` = test exists & covers ≥ 80 %, `[/]` = test exists but < 80 %, `[ ]` = no test, `[~]` = to-be-deleted.

| | File | Stmts | Cov % | Test file | PR |
|---|---|---:|---:|---|---|
| [ ] | `Entity/Product/Product.php` | 31 | 0 % | – | – |
| [ ] | `Entity/Cart/MollieShopwareCart.php` | 30 | 0 % | – | – |
| [ ] | `Entity/Order/MollieShopwareOrder.php` | 8 | 0 % | – | – |
| [ ] | `Entity/Customer/Customer.php` | 7 | 0 % | – | – |
| [ ] | `Entity/PaymentMethod/PaymentMethod.php` | 3 | 0 % | – | – |

## Integration Tests

No integration tests in this package. Entities are plain data classes. DAL
round-trips are covered through the services that use them (Payment,
Subscription, Transaction integration tests).
See [`../rules/integration-testing.md`](../rules/integration-testing.md).

## Notes

_(Space for package-specific decisions, fake requirements, special setups.)_
