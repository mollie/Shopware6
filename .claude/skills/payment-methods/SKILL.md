---
name: payment-methods
description: Add or remove a Mollie payment method in this Shopware 6 plugin. Adding = new enum case plus a handler with marker interfaces (manual capture, bank transfer, B2B-only, subscriptions, recurring mandate, Orders API); registration is automatic, no migration or icon needed. Removing = add DeprecatedMethodAwareInterface so the method is deactivated but stays in the shop, keeping old orders intact — never delete a handler. Use when asked to add, implement, register, remove, deprecate or disable a payment method such as Blik, Twint, Pay by Bank, Payconiq, Trustly.
---

Read `.agents/skills/payment-methods.md` in the plugin root and follow the matching half —
*Adding* or *Removing*.

When adding, ask everything in step 1 **in a single message**; the answers decide which
marker interfaces the handler implements, and asking one at a time wastes turns. If it is
not a standard Payments-API method, stop and report what the special integration would need
instead of guessing at it.

When removing, never delete the handler class or the enum case — existing orders resolve
their payment method through both.
