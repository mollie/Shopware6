Feature: Basic payment checkout
  In order to use payment methods
  As a customer

  Background:
    Given user "Mollie" exists
    And iam loggedin as user "Mollie"

  Scenario Outline: payment success
    Given payment method "<paymentMethod>" exists and active
    And product "<productNumber>" with quantity "<quantity>" is in cart
    When i start checkout with payment method "<paymentMethod>"
    And select payment status "<paymentStatus>"
    Then i see success page

    Examples:
      | paymentMethod | productNumber | quantity | paymentStatus |
      | paypal        | test          | 1        | paid          |