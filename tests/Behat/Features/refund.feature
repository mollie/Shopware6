@core @refund
Feature: Refund Management
  In order to refund paid orders
  As an Admin

  Background:
    Given iam logged in as user "cypress@mollie.com"
    And payment method "paypal" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And i select "mollie_fixture_shipment" as shipping method

  Scenario: full refund
    And product "MOL_REGULAR" with quantity "1" is in cart
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i create a full refund
    Then the refund is created with status "pending"
    And the refund amount is "34.89"

  Scenario: refund a single line item
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund line item "MOL_REGULAR" with quantity "1"
    Then the refund is created with status "pending"
    And the refund amount is "29.90"

  Scenario: refund a specific amount
    And product "MOL_REGULAR" with quantity "2" is in cart
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund the amount "5.00"
    Then the refund is created with status "pending"
    And the refund amount is "5.00"

  Scenario: refund a line item with a custom partial amount via quantity
    And product "MOL_REGULAR" with quantity "1" is in cart
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund line item "MOL_REGULAR" with quantity "1" and amount "20.00"
    Then the refund is created with status "pending"
    And the refund amount is "20.00"

  Scenario: refund a partial amount of a line item without quantity
    And product "MOL_REGULAR" with quantity "1" is in cart
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund line item "MOL_REGULAR" with partial amount "20.00"
    Then the refund is created with status "pending"
    And the refund amount is "20.00"

  Scenario: full refund after partial refund includes shipping costs and voucher discount in remaining amount
    And product "MOL_REGULAR" with quantity "1" is in cart
    And product "MOL_CHEAP" with quantity "1" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    And i apply promotion code "mollie_5"
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund line item "MOL_REGULAR" with quantity "1"
    Then the refund is created with status "pending"
    And the refund amount is "29.90"
    And there are 1 pending refunds
    When i create a full refund
    Then the refund is created with status "pending"
    And the refund amount is "20.89"
    And there are 2 pending refunds

  Scenario: full refund with voucher discount
    And product "MOL_REGULAR" with quantity "1" is in cart
    And i apply promotion code "mollie_5"
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i create a full refund
    Then the refund is created with status "pending"
    And the refund amount is "29.89"

  Scenario: cancel a pending refund
    And product "MOL_REGULAR" with quantity "1" is in cart
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund the amount "5.00"
    Then the refund is created with status "pending"
    And there are 1 pending refunds
    When i cancel the last refund
    Then there are 0 pending refunds

  Scenario: full refund after partial refund refunds only the remaining amount
    And product "MOL_REGULAR" with quantity "3" is in cart
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund the amount "29.90"
    Then the refund is created with status "pending"
    And the refund amount is "29.90"
    And there are 1 pending refunds
    When i create a full refund
    Then the refund is created with status "pending"
    And the refund amount is "64.79"
    And there are 2 pending refunds

