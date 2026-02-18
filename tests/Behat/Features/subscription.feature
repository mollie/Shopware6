@core @subscription
Feature: Subscription checkout
  In order to use subscription payment methods
  As a customer

  Background:
    Given iam logged in as user "cypress@mollie.com"

  Scenario: subscription can be bought, paused, resumed and cancelled
    Given payment method "trustly" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_SUB_1" with quantity "1" is in cart
    When i start checkout with payment method "trustly"
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

