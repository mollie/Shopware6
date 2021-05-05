<p align="center">
  <img src="https://info.mollie.com/hubfs/github/shopware/logo.png" width="128" height="128"/>
</p>
<h1 align="center">Mollie for Shopware 6</h1>


![Build Status](https://github.com/mollie/Shopware6/actions/workflows/ci_pipe.yml/badge.svg) ![GitHub release (latest by date)](https://img.shields.io/github/v/release/mollie/Shopware6) ![GitHub commits since latest release (by date)](https://img.shields.io/github/commits-since/mollie/Shopware6/latest)

## Introduction
Mollie offers various payment methods which can be easily integrated into your Shopware-powered webshop by using our official plugin. Mollie accepts all major payment methods such as Visa, Mastercard, American Express, PayPal, iDEAL, SOFORT Banking, SEPA Bank Transfer, SEPA Direct Debit, Apple Pay, KBC/CBC Payment Button, Bancontact, Belfius Pay Button, paysafecard, CartaSi, Cartes Bancaires and Gift cards

1.  Installation is easy.
2.  Go to  [Mollie](https://www.mollie.com/signup/)  to create a Mollie account
3.  Download our plugin in the Shopware store or in the Plugin Manager in your Shopware Backend
4.  Activate the plugin and enter your Mollie API key

Once the onboarding process in your Mollie account is completed, start accepting payments. You’ll usually be up and running within one working day.

## Shopware 6.4 notes
If you are using the new currency rounding feature in shopware 6.4 (total rounding interval > 0.01) we will add the rounding difference amount as new lineItem to the mollie order. We are not calculating taxes for it, because Shopware isn't calculating taxes for the discount / surcharge of the rounding amount.

We advise, that you speak to your lawyer or tax consultant if you want to use the new rounding feature.

## Manual installation
There are two ways of installing this plugin manually: You can either checkout this repository on your machine (in the plugins folder of your Shopware installation) or you can download the zip file above (most recent version can be found here: [master](https://github.com/mollie/Shopware/archive/master.zip)) and extract this on your machine (in the very same plugins folder).

## Finalizing steps
To finalize the installation you need to enter your API key in the corresponding box. You can find yours in the [Mollie Dashboard](https://www.mollie.com/dashboard/payments). Click save and then enable the plugin. Your payment methods are now being installed.

## Wiki
Read more about the plugin configuration on [our Wiki](https://github.com/mollie/Shopware6/wiki).
