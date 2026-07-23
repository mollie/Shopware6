@core @shipping
Feature: Basic Shipment Feature
  In order to ship authorized order
  As an Admin

  Background:
    Given iam logged in as user "cypress@mollie.com"

  Scenario: shipping line item is working
    Given payment method "klarna" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "klarna"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    When i ship line item "MOL_REGULAR" with quantity "1"
    Then order payment status is "authorized"
    And delivery status is "shipped_partially"
    When i ship line item "MOL_REGULAR" with quantity "1"
    Then order payment status is "authorized"
    And delivery status is "shipped_partially"
    When i ship line item "MOL_REDUCED_TAX" with quantity "1"
    Then order payment status is "paid"
    And delivery status is "shipped"


  Scenario: automatic shipment is working
    Given payment method "klarna" exists and active
    And plugin configuration "automaticShipping" is set to "true"
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "klarna"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    When i select delivery status action "ship"
    Then order payment status is "paid"

  Scenario: automatic shipment captures the gross amount for a net customer
    Given iam logged in as user "cypress-net@mollie.com"
    And payment method "klarna" exists and active
    And plugin configuration "automaticShipping" is set to "true"
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "klarna"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    When i select delivery status action "ship"
    Then order payment status is "paid"
    And the mollie captured amount equals the order total

  Scenario: cancelling a line item still captures the gross amount of the shipped items for a net customer
    Given iam logged in as user "cypress-net@mollie.com"
    And payment method "klarna" exists and active
    And plugin configuration "automaticShipping" is set to "true"
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "klarna"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    When i cancel line item "MOL_REGULAR" with quantity "1"
    And i select delivery status action "ship"
    Then delivery status is "shipped"
    And the mollie captured amount matches the shipped gross amount

  Scenario: shipping a legacy order that was captured with the net amount only reconciles the missing tax
    Given iam logged in as user "cypress-net@mollie.com"
    And payment method "klarna" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "klarna"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    When the order is captured with the net amount only and marked as shipped
    And i ship the order via the operational api
    Then the mollie captured amount equals the order total

    Scenario: automatic shipment is disabled
      Given payment method "klarna" exists and active
      And plugin configuration "automaticShipping" is set to "false"
      And i select "DE" as billing country
      And i select "EUR" as currency
      And product "MOL_REGULAR" with quantity "2" is in cart
      And product "MOL_REDUCED_TAX" with quantity "1" is in cart
      When i start checkout with payment method "klarna"
      And select payment status "authorized"
      Then i see success page
      And order payment status is "authorized"
      When i select delivery status action "ship"
      Then order payment status is "authorized"

