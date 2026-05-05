@core @subscription
Feature: Subscription checkout
  In order to use subscription payment methods
  As a customer

  Background:
    Given iam logged in as user "cypress@mollie.com"

  Scenario: subscription can be bought, paused, resumed and cancelled
    Given payment method "eps" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_SUB_1" with quantity "1" is in cart
    When i start checkout with payment method "eps"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And the subscription status is "active"
    Then i "pause" the subscription
    And the subscription status is "paused"
    Then i "resume" the subscription
    And the subscription status is "resumed"
    Then i "cancel" the subscription
    And the subscription status is "canceled"

  Scenario: pause, resume and cancel keep timesRemaining so the count stays correct across state changes
    Given payment method "eps" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_SUB_5" with quantity "1" is in cart
    When i start checkout with payment method "eps"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And the subscription status is "active"
    And the mollie subscription reports "9" times remaining
    Then i "pause" the subscription
    And the subscription status is "paused"
    And the mollie subscription reports "9" times remaining
    Then i "resume" the subscription
    And the subscription status is "resumed"
    And the mollie subscription reports "9" times remaining
    Then i "cancel" the subscription
    And the subscription status is "canceled"
    And the mollie subscription reports "9" times remaining

  Scenario: mixed cart with one-off product, daily and weekly subscription and a 5 euro voucher creates a Mollie subscription per subscription product
    Given payment method "eps" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And i select "mollie_fixture_shipment" as shipping method
    And product "MOL_REGULAR" with quantity "1" is in cart
    And product "MOL_SUB_1" with quantity "1" is in cart
    And product "MOL_SUB_2" with quantity "1" is in cart
    And i apply promotion code "mollie_5"
    When i start checkout with payment method "eps"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And all subscriptions of the order have a mollie id

  Scenario: subscription with one product can be renewed via the webhook
    Given payment method "eps" exists and active
    And payment method "belfius" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And i select "mollie_fixture_shipment" as shipping method
    And product "MOL_SUB_1" with quantity "1" is in cart
    When i start checkout with payment method "eps"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And i remember the subscription for renewal
    Given product "MOL_REGULAR" with quantity "1" is in cart
    When i start checkout with payment method "belfius"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And i remember the mollie payment id
    When i trigger the subscription renewal webhook
    Then the subscription has been renewed
    And order payment status is "paid"

  Scenario: mixed cart renewal creates a renewal order containing only the products of the renewed subscription group
    Given payment method "eps" exists and active
    And payment method "belfius" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And i select "mollie_fixture_shipment" as shipping method
    And product "MOL_REGULAR" with quantity "1" is in cart
    And product "MOL_SUB_1" with quantity "1" is in cart
    And product "MOL_SUB_2" with quantity "1" is in cart
    And i apply promotion code "mollie_5"
    When i start checkout with payment method "eps"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And i remember the subscription with interval "1 days" for renewal
    Given product "MOL_REGULAR" with quantity "1" is in cart
    When i start checkout with payment method "belfius"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And i remember the mollie payment id
    When i trigger the subscription renewal webhook
    Then the subscription has been renewed
    And order payment status is "paid"
    And order total is "23.99"

  Scenario: subscription renewal uses the address that was changed on the subscription, not the original order address
    Given payment method "eps" exists and active
    And payment method "belfius" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And i select "mollie_fixture_shipment" as shipping method
    And product "MOL_SUB_1" with quantity "1" is in cart
    When i start checkout with payment method "eps"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And i remember the subscription for renewal
    And the order shipping country is "DE"
    When i change the subscription shipping address to country "NL"
    Given product "MOL_REGULAR" with quantity "1" is in cart
    When i start checkout with payment method "belfius"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And i remember the mollie payment id
    When i trigger the subscription renewal webhook
    Then the subscription has been renewed
    And order payment status is "paid"
    And the order shipping country is "NL"

