@core @shipping
Feature: Shipment
  In order to ship authorized order
  As an Admin

  Background:
    Given iam logged in as user "cypress@mollie.com"
    # A paid shipping method is part of every scenario: shipping costs are a separate Mollie line
    # (and net for net-tax orders), so without them a capture that drops them stays unnoticed.
    And i select "mollie_fixture_shipment" as shipping method

  Scenario: shipping line item is working
    Given payment method "klarna" exists and active
    And plugin configuration "directPaymentKlarna" is set to "false"
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
    # The first shipment carries the whole shipping costs, so the capture must contain them
    And the mollie captured amount matches the shipped gross amount
    When i ship line item "MOL_REGULAR" with quantity "1"
    Then order payment status is "authorized"
    And delivery status is "shipped_partially"
    When i ship line item "MOL_REDUCED_TAX" with quantity "1"
    Then order payment status is "paid"
    And delivery status is "shipped"


  Scenario: automatic shipment is working
    Given payment method "klarna" exists and active
    And plugin configuration "directPaymentKlarna" is set to "false"
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

  # Needs SwagCustomizedProducts, the scenario stays pending without it. Rebuilds a merchant order:
  # the container repeats the product price and owns the delivery position, so capturing it exceeds
  # the authorization and filtering it must not lose the shipping costs.
  @customized-products
  Scenario: automatic shipment of a custom products order captures the product and the shipping costs once
    Given payment method "klarna" exists and active
    And plugin configuration "directPaymentKlarna" is set to "false"
    And plugin configuration "automaticShipping" is set to "true"
    And i select "mollie_fixture_parcel_shipment" as shipping method
    And i select "DE" as billing country
    And i select "EUR" as currency
    And customized product "MOL_CUSTOMIZED_PRODUCT" with engraving "Max Mustermann" is in cart
    When i start checkout with payment method "klarna"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    And order total is "47.94"
    When i select delivery status action "ship"
    Then order payment status is "paid"
    And delivery status is "shipped"
    And the mollie captured amount equals the order total
    # Only true when the shipping costs on the container's delivery are marked as shipped
    And the mollie captured amount matches the shipped gross amount

  Scenario: automatic shipment captures the gross amount for a net customer
    Given iam logged in as user "cypress-net@mollie.com"
    And payment method "klarna" exists and active
    And plugin configuration "directPaymentKlarna" is set to "false"
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

  Scenario: shipping a line item captures the gross shipping costs for a net customer
    Given iam logged in as user "cypress-net@mollie.com"
    And payment method "klarna" exists and active
    And plugin configuration "directPaymentKlarna" is set to "false"
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "klarna"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    # A partial shipment is captured from the collected shipping items (not from the authorized
    # remainder), so this is the path that would silently drop the net shipping costs
    When i ship line item "MOL_REGULAR" with quantity "1"
    Then delivery status is "shipped_partially"
    And the mollie captured amount matches the shipped gross amount

  Scenario: cancelling a line item still captures the gross amount of the shipped items for a net customer
    Given iam logged in as user "cypress-net@mollie.com"
    And payment method "klarna" exists and active
    And plugin configuration "directPaymentKlarna" is set to "false"
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
    And plugin configuration "directPaymentKlarna" is set to "false"
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
      And plugin configuration "directPaymentKlarna" is set to "false"
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

