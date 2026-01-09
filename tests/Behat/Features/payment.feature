Feature: Basic payment checkout
  In order to use payment methods
  As a customer

  Background:
    Given iam logged in as user "cypress@mollie.com"

  Scenario Outline: payment success
    Given payment method "<paymentMethod>" exists and active
    And i select "<billingCountry>" as billing country
    And i select "<currency>" as currency
    And product "<productNumber>" with quantity "<quantity>" is in cart
    When i start checkout with payment method "<paymentMethod>"
    And select payment status "<paymentStatus>"
    Then i see success page
    And order payment status is "<paymentStatus>"

    Examples:
      | paymentMethod | productNumber | quantity | paymentStatus | billingCountry | currency |
      | alma          | MOL_REGULAR   | 2        | paid          | FR             | EUR      |
      | bancomatpay   | MOL_REGULAR   | 1        | paid          | IT             | EUR      |
      | bancontact    | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | banktransfer  | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | belfius       | MOL_REGULAR   | 1        | paid          | BE             | EUR      |
      | billie        | MOL_REGULAR   | 1        | authorized    | DE             | EUR      |
      | bizum         | MOL_REGULAR   | 1        | paid          | ES             | EUR      |
      | blik          | MOL_REGULAR   | 1        | paid          | DE             | PLN      |
      | eps           | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | in3           | MOL_REGULAR   | 2        | paid          | NL             | EUR      |
      | klarna        | MOL_REGULAR   | 1        | authorized    | DE             | EUR      |
      | mbway         | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | multibanco    | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | mybank        | MOL_REGULAR   | 1        | paid          | IT             | EUR      |
      | paybybank     | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | paypal        | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | przelewy24    | MOL_REGULAR   | 1        | paid          | PL             | PLN      |
      | riverty       | MOL_REGULAR   | 1        | authorized    | NL             | EUR      |
      | satispay      | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | swish         | MOL_REGULAR   | 1        | paid          | SE             | SEK      |
      | trustly       | MOL_REGULAR   | 1        | paid          | DE             | EUR      |
      | twint         | MOL_REGULAR   | 1        | paid          | DE             | CHF      |

  Scenario Outline: payment success with issuer
    Given payment method "<paymentMethod>" exists and active
    And i select "<billingCountry>" as billing country
    And i select "<currency>" as currency
    And product "<productNumber>" with quantity "<quantity>" is in cart
    When i start checkout with payment method "<paymentMethod>"
    And i select issuer "<issuer>"
    And select payment status "<paymentStatus>"
    Then i see success page
    And order payment status is "<paymentStatus>"

    Examples:
      | paymentMethod | productNumber | quantity | paymentStatus | billingCountry | currency | issuer             |
      | ideal         | MOL_REGULAR   | 5        | paid          | NL             | EUR      | ideal_INGBNL2A     |
      | kbc           | MOL_REGULAR   | 1        | paid          | BE             | EUR      | kbc                |
      | kbc           | MOL_REGULAR   | 1        | paid          | BE             | EUR      | cbc                |
      | giftcard      | MOL_CHEAP     | 1        | paid          | NL             | EUR      | beautycadeaukaart  |
      | giftcard      | MOL_CHEAP     | 1        | paid          | NL             | EUR      | biercheque         |
      | giftcard      | MOL_CHEAP     | 1        | paid          | NL             | EUR      | bloemencadeaukaart |

  Scenario Outline: payment success with giftcard and rest amount
    Given payment method "<paymentMethod>" exists and active
    And i select "<billingCountry>" as billing country
    And i select "<currency>" as currency
    And product "<productNumber>" with quantity "<quantity>" is in cart
    When i start checkout with payment method "<paymentMethod>"
    And i select issuer "<issuer>"
    And select payment status "<paymentStatus>"
    And select mollie payment method "<molliePaymentMethod>"
    And select payment status "<paymentStatus>"
    Then i see success page
    And order payment status is "<paymentStatus>"

    Examples:
      | paymentMethod | molliePaymentMethod | productNumber | quantity | paymentStatus | billingCountry | currency | issuer             |
      | giftcard      | paypal              | MOL_REGULAR   | 1        | paid          | NL             | EUR      | beautycadeaukaart  |
      | giftcard      | paypal              | MOL_REGULAR   | 1        | paid          | NL             | EUR      | biercheque         |
      | giftcard      | paypal              | MOL_REGULAR   | 1        | paid          | NL             | EUR      | bloemencadeaukaart |

  Scenario: payment success with mixed tax
    Given payment method "paypal" exists and active
    And i select "DE" as billing country
    And i select "EUR" as currency
    And product "MOL_REGULAR" with quantity "1" is in cart
    And product "MOL_REDUCED_TAX" with quantity "1" is in cart
    And product "MOL_TAX_FREE" with quantity "1" is in cart
    When i start checkout with payment method "paypal"
    And select payment status "paid"
    Then i see success page
    And order payment status is "paid"