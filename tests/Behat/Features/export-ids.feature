@core @export-ids
Feature: Export IDs
  Mollie ids for an accounting export
  In order to reconcile the Shopware export with the Mollie report
  As an Admin

  Background:
    Given iam logged in as user "cypress@mollie.com"
    And i select "mollie_fixture_shipment" as shipping method
    And i select "DE" as billing country
    And i select "EUR" as currency

  Scenario: every capture and refund of a Payments API order is listed on the order
    Given payment method "klarna" exists and active
    And plugin configuration "directPaymentDisabledMethods" is set to "klarna"
    And product "MOL_REGULAR" with quantity "1" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "klarna"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    When i ship line item "MOL_REGULAR" with quantity "1"
    Then delivery status is "shipped_partially"
    And the order has 1 capture ids
    When i ship line item "MOL_REDUCED_TAX" with quantity "1"
    Then order payment status is "paid"
    And delivery status is "shipped"
    And the order has 2 capture ids
    When i refund line item "MOL_REGULAR" with quantity "1"
    Then the refund is created with status "pending"
    And the order has 1 refund ids
    And the order has 2 capture ids

  Scenario: every shipment and refund of an Orders API order is listed on the order
    Given payment method "klarna_ordersapi" exists and active
    And product "MOL_REGULAR" with quantity "1" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "klarna_ordersapi"
    And select payment status "authorized"
    Then i see success page
    And order payment status is "authorized"
    When i ship line item "MOL_REGULAR" with quantity "1"
    Then delivery status is "shipped_partially"
    And the order has 1 shipment ids
    When i ship line item "MOL_REDUCED_TAX" with quantity "1"
    Then order payment status is "paid"
    And delivery status is "shipped"
    And the order has 2 shipment ids
    # The Orders API captures on shipment, so there is no capture of its own
    And the order has 0 capture ids
    When i refund line item "MOL_REGULAR" with quantity "1"
    Then the refund is created with status "pending"
    And the order has 1 refund ids
