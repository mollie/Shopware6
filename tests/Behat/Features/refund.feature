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

  Scenario: refund scenarios covering line item, partial amount, full refund and cancellation
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_CHEAP" with quantity "5" is in cart
    And i apply promotion code "mollie_5"
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund line item "MOL_REGULAR" with quantity "1" and description "Defective unit returned" and internal description "RMA-2026-001"
    Then the refund is created with status "pending"
    And the refund amount is "29.90"
    And the refund public description is "Defective unit returned"
    And the refund internal description is "RMA-2026-001"
    And there are 1 pending refunds
    When i refund line item "MOL_CHEAP" with partial amount "3.00"
    Then the refund is created with status "pending"
    And the refund amount is "3.00"
    And there are 2 pending refunds
    When i refund the amount "2.00"
    Then the refund is created with status "pending"
    And the refund amount is "2.00"
    And there are 3 pending refunds
    When i refund line item "mollie_fixture_shipment" with quantity "1" and description "Shipment refund" and internal description "Refund shipment"
    Then the refund is created with status "pending"
    And the refund amount is "4.99"
    And there are 4 pending refunds
    When i remember the refund id
    When i create a full refund with description "Full order refund" and internal description "Approved by support"
    Then the refund is created with status "pending"
    And the refund amount is "24.90"
    And the refund public description is "Full order refund"
    And the refund internal description is "Approved by support"
    And there are 5 pending refunds
    When i cancel the stored refund
    Then there are 4 pending refunds

  Scenario: refund a line item on an order placed via the Orders API
    Given payment method "paypal_ordersapi" exists and active
    And product "MOL_REGULAR" with quantity "2" is in cart
    And product "MOL_CHEAP" with quantity "5" is in cart
    And i apply promotion code "mollie_5"
    When i start checkout with payment method "paypal_ordersapi"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"
    When i refund line item "MOL_REGULAR" with quantity "1" and description "Defective unit returned" and internal description "RMA-2026-001"
    Then the refund is created with status "pending"
    And the refund amount is "29.90"
    And the refund public description is "Defective unit returned"
    And the refund internal description is "RMA-2026-001"
    And there are 1 pending refunds
    When i refund the amount "2.00"
    Then the refund is created with status "pending"
    And the refund amount is "2.00"
    And there are 2 pending refunds
    When i create a full refund with description "Full order refund" and internal description "Approved by support"
    Then the refund is created with status "pending"
    And the refund public description is "Full order refund"
    And the refund internal description is "Approved by support"

