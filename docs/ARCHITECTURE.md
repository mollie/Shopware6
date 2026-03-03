# Architecture Overview

This document gives a **developer-friendly overview** of the Mollie Payment Plugin architecture.  
Goal: Quickly understand **where things happen** and **where to look in code**.

---

## Folder Structure Overview

| Folder | Purpose |
|--------|---------|
| `/shopware` | All new PHP code, domain & infrastructure |
| `/src` | Legacy code (being migrated) |
| `/polyfill` | Compatibility layer for different Shopware versions |
| `/tests` | Automated tests |
| `/config` | Tooling configs (PHPUnit, PHPCS, etc.) |
| `/docs` | Documentation |

---

##  Key Entry Points

| Feature | Entry Point | Description |
|---------|------------|-------------|
| Payment creation | PaymentHandler | `/shopware/Handler/PaymentHandler.php`<br>Triggered during checkout |
| Refund creation | RefundService::createRefund | Called when refund is triggered in Shopware admin |
| Webhook handling | WebhookController | `/shopware/Controller/WebhookController.php`<br>Receives Mollie webhook events |
| State mapping | StateMachineMapper | `/shopware/Service/StateMachineMapper.php`<br>Maps Mollie status → Shopware order state |



---

## Notes

- Avoid `/src` for new code.
- `/polyfill` contains backwards compatibility for Shopware < 6.4.
- Namespace for new code: `Mollie\Shopware`.
- Always write tests for new features in `/tests`.
