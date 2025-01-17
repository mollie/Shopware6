<p align="center">
   <img src="/.github/assets/home-logo.png">
</p>

[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.2-8892BF.svg?style=flat)](https://php.net/)  ![Build Status](https://github.com/mollie/Shopware6/actions/workflows/ci_pipe.yml/badge.svg) ![GitHub release (latest by date)](https://img.shields.io/github/v/release/mollie/Shopware6) ![GitHub commits since latest release (by date)](https://img.shields.io/github/commits-since/mollie/Shopware6/latest)



## Introduction
Mollie offers various payment methods which can be easily integrated into your Shopware-powered webshop by using our official plugin. Mollie accepts all major payment methods such as Visa, Mastercard, American Express, PayPal, iDEAL, SOFORT Banking, SEPA Bank Transfer, SEPA Direct Debit, Apple Pay, KBC/CBC Payment Button, Bancontact, Belfius Pay Button, paysafecard, CartaSi, Cartes Bancaires, Gift cards, Billie, Monizze Vouchers and Sodexo Vouchers.

1.  Installation is easy.
2.  Go to  [Mollie](https://www.mollie.com/signup/)  to create your Mollie account
3.  Download our plugin in the Shopware store or in the Plugin Manager in your Shopware Backend
4.  Activate the plugin and enter your Mollie API key
5.  Assign the payment methods to your sales channels as necessary

Once the onboarding process in your Mollie account is completed, start accepting payments. You’ll usually be up and running within one working day.

## Shopware 6.5 notes
If you are using the new Shopware 6.5 please keep in mind that the ZIP file of the plugin supports Shopware 6.4 and 6.5 out of the box.
This means that Administration artifacts are built using Shopware 6.4 which also works in Shopware 6.5.
Our storefront artifacts are built using a custom webpack implementation that creates a separate **mollie-payments.js** file that is loaded in the storefront.
If you do not want to use this additional resource (e.g. for agencies), you can revert to the Shopware default behaviour by enabling this in the plugin configuration.
Afterwards please run `bin/build-storefront.sh` to rebuild the storefront artifacts.

## Manual installation
There are two ways of installing this plugin manually: You can either checkout this repository on your machine (in the plugins folder of your Shopware installation) or you can download the zip file above (most recent version can be found here: [master](https://github.com/mollie/Shopware/archive/master.zip)) and extract this on your machine (in the very same plugins folder).

## Finalizing steps
To finalize the installation you need to enter your API key in the corresponding box. You can find yours in the [Mollie Dashboard](https://www.mollie.com/dashboard/payments). Click save and then enable the plugin. Your payment methods are now being installed.

Also make sure to assign the payment methods to your sales channels, or they won't show up.

## Wiki Documentation
Read more about the plugin configuration on [our Wiki](https://github.com/mollie/Shopware6/wiki).
