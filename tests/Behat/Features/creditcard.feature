@core @creditcard
Feature: Credit card
  In order to pay with a credit card
  As a customer

  Background:
    Given iam logged in as user "cypress@mollie.com"
    And payment method "creditcard" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency

  Scenario: a mastercard payment is paid
    Given plugin configuration "enableCreditCardComponents" is set to "true"
    And product "MOL_REGULAR" with quantity "1" is in cart
    And i use the test credit card "Mastercard"
    When i start checkout with payment method "creditcard"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"

  Scenario: without the credit card components the checkout stops at mollie's own card form
    Given plugin configuration "enableCreditCardComponents" is set to "false"
    And product "MOL_REGULAR" with quantity "1" is in cart
    And i use the test credit card "Mastercard"
    When i start checkout with payment method "creditcard"
    Then the mollie page offers no payment status selection

  Scenario: saving a credit card leaves a mandate behind
    Given plugin configuration "enableCreditCardComponents" is set to "true"
    And plugin configuration "oneClickPaymentsEnabled" is set to "true"
    And i remember the number of mandates for payment method "creditcard"
    And product "MOL_REGULAR" with quantity "1" is in cart
    And i use the test credit card "Mastercard"
    And i want to save the credit card
    When i start checkout with payment method "creditcard"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And the number of mandates for payment method "creditcard" increased by "1"

  # Mollie's test mode asks for the card data again although the payment carries a valid mandate, so
  # it never reaches the status selection - and entering the card there creates a second mandate.
  @mollie-broken
  Scenario: a stored mandate pays the next order without entering the card again
    Given plugin configuration "enableCreditCardComponents" is set to "true"
    And plugin configuration "oneClickPaymentsEnabled" is set to "true"
    And i remember the number of mandates for payment method "creditcard"
    And product "MOL_REGULAR" with quantity "1" is in cart
    And i use the test credit card "Mastercard"
    And i want to save the credit card
    When i start checkout with payment method "creditcard"
    And select payment status "paid"
    Then i see success page
    And the number of mandates for payment method "creditcard" increased by "1"
    When product "MOL_REGULAR" with quantity "1" is in cart
    And i pay with the stored mandate for payment method "creditcard"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"

  # Mollie creates a customer-present mandate for every card payment that carries a customer id, so
  # what the setting can promise is that the shop never offers a stored card - not that Mollie has
  # none stored.
  Scenario: without one click payments a saved card is not offered
    Given plugin configuration "enableCreditCardComponents" is set to "true"
    And plugin configuration "oneClickPaymentsEnabled" is set to "false"
    And product "MOL_REGULAR" with quantity "1" is in cart
    And i use the test credit card "Mastercard"
    And i want to save the credit card
    When i start checkout with payment method "creditcard"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    And the shop offers no stored cards for payment method "creditcard"
