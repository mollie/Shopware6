import {ApplePaySessionMockFactory} from "Services/applepay/ApplePay.Mock";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import Devices from "Services/utils/Devices";
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import PDPRepository from "Repositories/storefront/products/PDPRepository";
import StorefrontClient from "Services/shopware/StorefrontClient";
import PDPAction from "Actions/storefront/products/PDPAction";
import OffCanvasRepository from "Repositories/storefront/checkout/OffCanvasRepository";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import CartRepository from "Repositories/storefront/checkout/CartRepository";
import ListingRepository from "Repositories/storefront/products/ListingRepository";
import ShopConfiguration from "../../../support/models/ShopConfiguration";

const storefrontClient = new StorefrontClient();

const devices = new Devices();
const configAction = new ShopConfigurationAction();
const applePayFactory = new ApplePaySessionMockFactory();
const topMenu = new TopMenuAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();

const repoPDP = new PDPRepository();
const repoListing = new ListingRepository();
const repoOffcanvas = new OffCanvasRepository();
const repoCart = new CartRepository();


function beforeEachUIConfigDisabled() {
    cy.wrap(null).then(() => {
        devices.setDevice(devices.getFirstDevice());
        configAction.setupShop(true, false, false);
        devices.setDevice(devices.getFirstDevice());
    });
}

/**
 * @param {ShopConfiguration} shopConfiguration
 */
function beforeEachUIConfigEnabled(shopConfiguration) {
    cy.wrap(null).then(() => {
        devices.setDevice(devices.getFirstDevice());
        configAction.setupShop(true, false, true, shopConfiguration);
        devices.setDevice(devices.getFirstDevice());
    });
}

const privacyConfig = new ShopConfiguration();
privacyConfig.setDataPrivacy(true);


describe('Apple Pay Direct - Storefront Routes', () => {

    describe('Functional', () => {

        it('C4084: Domain Verification file has been downloaded @core', () => {
            cy.request('/.well-known/apple-developer-merchantid-domain-association');
        })
    })

    describe('Routes', () => {

        it('C266699: /mollie/apple-pay/available @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.get('/mollie/apple-pay/available').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('available').should('exist', true)
            });
        })

        it('C266700: /mollie/apple-pay/applepay-id @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.get('/mollie/apple-pay/applepay-id').then(response => {
                    resolve({'data': response.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('id').should('not.eq', '')
            });
        })

        it('C266701: /mollie/apple-pay/add-product without product ID @core', () => {

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

        it('C266702: /mollie/apple-pay/validate without data @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/validate').then(response => {
                    resolve({'data': response.data.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('success').should('eq', false)
            });
        })

        // somehow this route is not reliably working in PRs with filter @core, but only in Shopware 6.4.20.2
        // no shipping methods seem to be returned. let's just keep it at least in real CI pipelines where its working
        it('C266703: /mollie/apple-pay/shipping-methods', () => {

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

        it('C266704: /mollie/apple-pay/shipping-methods without country code @core', () => {

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

        it('C266705: /mollie/apple-pay/set-shipping without identifier @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/set-shipping').then(response => {
                    resolve({'data': response.data.data});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('success').should('eq', false)
                cy.wrap(data).its('error').should('contain', 'Missing shipping method identifier');
            });
        })

        it('C266706: /mollie/apple-pay/start-payment redirects to cart with invalid data @core', () => {

            const request = new Promise((resolve) => {
                storefrontClient.post('/mollie/apple-pay/start-payment').then(response => {
                    resolve({'data': response});
                });
            })

            cy.wrap(request).its('data').then(data => {
                cy.wrap(data).its('request.responseURL').should('contain', '/checkout/cart');
            });
        })


        it('C266708: /mollie/apple-pay/restore-cart @core', () => {

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

        describe('PDP', () => {

            it('C4100: Apple Pay Direct hidden if not configured but possible in browser (PDP) @core', () => {

                beforeEachUIConfigDisabled();

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('not.exist');
            })
        })

        describe('Listing', () => {

            it('C266709: Apple Pay Direct hidden if not configured but possible in browser (Listing) @core', () => {

                beforeEachUIConfigDisabled();

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();

                repoListing.getApplePayDirectButton().should('not.exist');
            })
        })

        describe('Offcanvas', () => {

            it('C266710: Apple Pay Direct hidden if not configured but possible in browser (Offcanvas) @core', () => {

                beforeEachUIConfigDisabled();

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);

                repoOffcanvas.getApplePayDirectButton().should('not.exist');
            })
        })

        describe('Cart', () => {

            it('C266711: Apple Pay Direct hidden if not configured but possible in browser (Cart) @core', () => {

                beforeEachUIConfigDisabled();

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);

                checkout.goToCartInOffCanvas();

                repoCart.getApplePayDirectButton().should('not.exist');
            })
        })

    })

    context('Config - Enabled', () => {

        describe('PDP', () => {

            it('C4099: Apple Pay Direct visible if configured and possible in browser (PDP) @core', () => {

                beforeEachUIConfigEnabled(new ShopConfiguration());

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('not.have.class', 'd-none');
            })

            it('C4085: Apple Pay Direct hidden if configured but not possible in browser (PDP) @core', () => {

                beforeEachUIConfigEnabled(new ShopConfiguration());

                applePayFactory.registerApplePay(false);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('have.class', 'd-none');
            })

            it('C4247541: Apple Pay Direct requires data protection to be accepted if enabled @core', () => {

                beforeEachUIConfigEnabled(privacyConfig);

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();

                // click and make sure data privacy is validated
                repoPDP.getApplePayDirectButton().click();
                repoPDP.getDataPrivacyCheckbox().should('have.class', 'is-invalid');

                // now click on checkbox
                repoPDP.getDataPrivacyCheckbox().check({force:true});
            })

        })

        describe('Listing', () => {

            it('C266712: Apple Pay Direct visible if configured and possible in browser (Listing) @core', () => {

                beforeEachUIConfigEnabled(new ShopConfiguration());

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();

                repoListing.getApplePayDirectButton().should('not.have.class', 'd-none');
            })

            it('C266713: Apple Pay Direct hidden if configured but not possible in browser (Listing) @core', () => {

                beforeEachUIConfigEnabled(new ShopConfiguration());

                applePayFactory.registerApplePay(false);

                cy.visit('/');
                topMenu.clickOnSecondCategory();

                repoListing.getApplePayDirectButton().should('have.class', 'd-none');
            })

            it('C4247542: Apple Pay Direct requires data protection to be accepted if enabled @core', () => {

                beforeEachUIConfigEnabled(privacyConfig);

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();

                // click and make sure data privacy is validated
                repoListing.getApplePayDirectButton().first().click();
                repoListing.getDataPrivacyCheckbox().first().should('have.class', 'is-invalid');

                // now click on checkbox
                repoListing.getDataPrivacyCheckbox().first().check({force:true});
            })

        })


        describe('Offcanvas', () => {

            it('C266714: Apple Pay Direct visible if configured and possible in browser (Offcanvas) @core', () => {

                beforeEachUIConfigEnabled(new ShopConfiguration());

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);

                repoOffcanvas.getApplePayDirectButton().should('not.have.class', 'd-none');
            })

            it('C266715: Apple Pay Direct hidden if configured but not possible in browser (Offcanvas) @core', () => {

                beforeEachUIConfigEnabled(new ShopConfiguration());

                applePayFactory.registerApplePay(false);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);

                repoOffcanvas.getApplePayDirectButton().should('have.class', 'd-none');
            })

            it('C4247544: Apple Pay Direct requires data protection to be accepted if enabled @core', () => {

                beforeEachUIConfigEnabled(privacyConfig);

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);

                // click and make sure data privacy is validated
                repoOffcanvas.getApplePayDirectButton().click();
                repoOffcanvas.getDataPrivacyCheckbox().should('have.class', 'is-invalid');

                // now click on checkbox
                repoOffcanvas.getDataPrivacyCheckbox().check({force:true});
            })

        })

        describe('Cart', () => {

            it('C266716: Apple Pay Direct visible if configured and possible in browser (Cart) @core', () => {

                beforeEachUIConfigEnabled(new ShopConfiguration());

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);

                checkout.goToCartInOffCanvas();

                repoCart.getApplePayDirectButton().should('not.have.class', 'd-none');
            })

            it('C266717: Apple Pay Direct hidden if configured but not possible in browser (Cart) @core', () => {

                beforeEachUIConfigEnabled(new ShopConfiguration());

                applePayFactory.registerApplePay(false);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);

                checkout.goToCartInOffCanvas();

                repoCart.getApplePayDirectButton().should('have.class', 'd-none');
            })

            it('C4247543: Apple Pay Direct requires data protection to be accepted if enabled @core', () => {

                beforeEachUIConfigEnabled(privacyConfig);

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnSecondCategory();
                listing.clickOnFirstProduct();
                pdp.addToCart(1);

                checkout.goToCartInOffCanvas();

                // click and make sure data privacy is validated
                repoCart.getApplePayDirectButton().click();
                repoCart.getDataPrivacyCheckbox().should('have.class', 'is-invalid');

                // now click on checkbox
                repoCart.getDataPrivacyCheckbox().check({force:true});
            })

        })

    })

});