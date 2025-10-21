Feature: Basic payment checkout
  In order to use payment methods
  As a customer

  Background:
    Given iam logged in as user "test@mollie.com"

  Scenario Outline: payment success
    Given payment method "<paymentMethod>" exists and active
    And i select "<billingCountry>" as billing country
    And i select "<currency>" as currency
    And product "<productNumber>" with quantity "<quantity>" is in cart
    When i start checkout with payment method "<paymentMethod>"
    And select payment status "<paymentStatus>"
    Then i see success page

    Examples:
      | paymentMethod               | productNumber | quantity | paymentStatus | billingCountry | currency |
      | payment_mollie_paypal       | SWDEMO10007.1 | 1        | paid          | NL             | EUR      |
      | payment_mollie_klarna       | SWDEMO10007.1 | 1        | authorize     | DE             | EUR      |
      | payment_mollie_billie       | SWDEMO10007.1 | 1        | authorize     | DE             | EUR      |
      | payment_mollie_riverty      | SWDEMO10007.1 | 1        | authorize     | NL             | EUR      |
      | payment_mollie_eps          | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | payment_mollie_przelewy24   | SWDEMO10007.1 | 1        | paid          | PL             | PLN      |
      | payment_mollie_twint        | SWDEMO10007.1 | 1        | paid          | DE             | CHF      |
      | payment_mollie_blik         | SWDEMO10007.1 | 1        | paid          | DE             | PLN      |
      | payment_mollie_payconiq     | SWDEMO10007.1 | 1        | paid          | BE             | EUR      |
      | payment_mollie_mbway        | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | payment_mollie_swish        | SWDEMO10007.1 | 1        | paid          | SE             | SEK      |
      | payment_mollie_multibanco   | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | payment_mollie_trustly      | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | payment_mollie_alma         | SWDEMO10007.1 | 5        | authorize     | FR             | EUR      |
      | payment_mollie_eps          | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | payment_mollie_banktransfer | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | payment_mollie_bancontact   | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | payment_mollie_paybybank    | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | payment_mollie_satispay     | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |