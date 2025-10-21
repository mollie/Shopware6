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
      | paymentMethod | productNumber | quantity | paymentStatus | billingCountry | currency |
      | paypal       | SWDEMO10007.1 | 1        | paid          | NL             | EUR      |
      | klarna       | SWDEMO10007.1 | 1        | authorize     | DE             | EUR      |
      | billie       | SWDEMO10007.1 | 1        | authorize     | DE             | EUR      |
      | riverty      | SWDEMO10007.1 | 1        | authorize     | NL             | EUR      |
      | eps          | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | przelewy24   | SWDEMO10007.1 | 1        | paid          | PL             | PLN      |
      | twint        | SWDEMO10007.1 | 1        | paid          | DE             | CHF      |
      | blik         | SWDEMO10007.1 | 1        | paid          | DE             | PLN      |
      | payconiq     | SWDEMO10007.1 | 1        | paid          | BE             | EUR      |
      | mbway        | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | swish        | SWDEMO10007.1 | 1        | paid          | SE             | SEK      |
      | multibanco   | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | trustly      | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | alma         | SWDEMO10007.1 | 5        | authorize     | FR             | EUR      |
      | eps          | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | banktransfer | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | bancontact   | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | paybybank    | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |
      | satispay     | SWDEMO10007.1 | 1        | paid          | DE             | EUR      |