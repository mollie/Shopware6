---
name: payment-methods
description: Add or remove a Mollie payment method in this Shopware 6 plugin. Adding = new enum case plus a handler with marker interfaces (manual capture, bank transfer, B2B-only, subscriptions, recurring mandate, Orders API); registration is automatic, no migration or icon needed. Removing = add DeprecatedMethodAwareInterface so the method is deactivated but stays in the shop, keeping old orders intact — never delete a handler. Use when asked to add, implement, register, remove, deprecate or disable a payment method such as Blik, Twint, Pay by Bank, Payconiq, Trustly.
---

Read `.agents/skills/payment-methods/SKILL.md` in the plugin root and follow the matching
half — *Adding* or *Removing*. That file is the single source of truth for this skill.
