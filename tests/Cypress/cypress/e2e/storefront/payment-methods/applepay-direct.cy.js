import {ApplePaySessionMockFactory} from "Services/applepay/ApplePay.Mock";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import Devices from "Services/utils/Devices";
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import PDPRepository from "Repositories/storefront/products/PDPRepository";
import StorefrontClient from "Services/shopware/StorefrontClient";

const storefrontClient = new StorefrontClient();

const devices = new Devices();
const configAction = new ShopConfigurationAction();
const applePayFactory = new ApplePaySessionMockFactory();
const topMenu = new TopMenuAction();
const listing = new ListingAction();

const repoPDP = new PDPRepository();


describe('Apple Pay Direct - Storefront Routes', () => {

    describe('Functional', () => {

        it('C4084: Domain Verification file has been downloaded @core', () => {
            cy.request('/.well-known/apple-developer-merchantid-domain-association');
        })
    })

    describe('Routes', () => {

        it('/mollie/apple-pay/available @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.get('/mollie/apple-pay/available').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('available').should('exist', true)
            });
        })

        it('/mollie/apple-pay/applepay-id @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.get('/mollie/apple-pay/applepay-id').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('id').should('not.eq', '')
            });
        })

        it('/mollie/apple-pay/add-product without product ID @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/add-product', {}).then(response => {
                    resolve({'data': response.data.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('success').should('eq', false)
                cy.wrap(data).its('error').should('contain', 'Please provide a product ID');
            });
        })

        it('/mollie/apple-pay/validate without data @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/validate').then(response => {
                    resolve({'data': response.data.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('success').should('eq', false)
            });
        })

        it('/mollie/apple-pay/shipping-methods @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/shipping-methods', {'countryCode': 'DE'}).then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('success').should('eq', true)
                cy.wrap(data).its('shippingmethods').its('length').should('be.gt', 0)
            });
        })

        it('/mollie/apple-pay/shipping-methods without country code @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/shipping-methods', {}).then(response => {
                    resolve({'data': response.data.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('success').should('eq', false)
                cy.wrap(data).its('error').should('contain', 'No Country Code provided');
            });
        })

        it('/mollie/apple-pay/set-shipping without identifier @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/set-shipping').then(response => {
                    resolve({'data': response.data.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('success').should('eq', false)
                cy.wrap(data).its('error').should('contain', 'Please provide a Shipping Method identifier');
            });
        })

        it('/mollie/apple-pay/start-payment redirects to cart with invalid data @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/start-payment').then(response => {
                    resolve({'data': response});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('request.responseURL').should('contain', '/checkout/cart');
            });
        })

        it('/mollie/apple-pay/finish-payment redirects to cart with invalid data @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.get('/mollie/apple-pay/finish-payment').then(response => {
                    resolve({'data': response});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('request.responseURL').should('contain', '/checkout/cart');
            });
        })

        it('/mollie/apple-pay/restore-cart @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/restore-cart').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('success').should('eq', true)
            });
        })
    })
})

describe('Apple Pay Direct - UI Tests', () => {

    context('Config - Disabled', () => {

        before(function () {
            devices.setDevice(devices.getFirstDevice());
            configAction.setupShop(true, false, false);
        })

        beforeEach(function () {
            devices.setDevice(devices.getFirstDevice());
        })

        describe('PDP', () => {

            it('C4100: Apple Pay Direct hidden if available but not configured (PDP) @core', () => {

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('not.exist');
            })

        })

    })

    context('Config - Enabled', () => {

        before(function () {
            devices.setDevice(devices.getFirstDevice());
            configAction.setupShop(true, false, true);
        })

        beforeEach(function () {
            devices.setDevice(devices.getFirstDevice());
        })

        describe('PDP', () => {

            it('C4099: Apple Pay Direct visible if available and configured (PDP) @core', () => {

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('not.have.class', 'd-none');
            })

            it('C4085: Apple Pay Direct hidden if not available but configured (PDP) @core', () => {

                applePayFactory.registerApplePay(false);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('have.class', 'd-none');
            })

        })

    })

});