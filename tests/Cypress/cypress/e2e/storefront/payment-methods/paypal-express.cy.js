import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import Devices from "Services/utils/Devices";
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import PDPRepository from "Repositories/storefront/products/PDPRepository";
import PDPAction from "Actions/storefront/products/PDPAction";
import OffCanvasRepository from "Repositories/storefront/checkout/OffCanvasRepository";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import CartRepository from "Repositories/storefront/checkout/CartRepository";
import ListingRepository from "Repositories/storefront/products/ListingRepository";
import RegisterRepository from "Repositories/storefront/checkout/RegisterRepository";
import ShopConfiguration from "../../../support/models/ShopConfiguration";


const devices = new Devices();
const configAction = new ShopConfigurationAction();
const topMenu = new TopMenuAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();

const repoPDP = new PDPRepository();
const repoListing = new ListingRepository();
const repoOffcanvas = new OffCanvasRepository();
const repoCart = new CartRepository();
const registerRepo = new RegisterRepository();


function beforeEachNoPrivacy() {
    cy.wrap(null).then(() => {
        devices.setDevice(devices.getFirstDevice());
        configAction.setupShop(true, false, false, new ShopConfiguration());
    });
}

function beforeEachPrivacy() {
    cy.wrap(null).then(() => {
        devices.setDevice(devices.getFirstDevice());
        const config = new ShopConfiguration();
        config.setDataPrivacy(true);
        configAction.setupShop(true, false, false, config);
    });
}


describe('Paypal Express - UI Tests', () => {

    describe('PDP', () => {

        it('C4247553: Paypal Express button is visible @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();

            repoPDP.getPayPalExpressButton().should('be.visible');
        })

        it('C4247549: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, ['pdp']);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();

            repoPDP.getPayPalExpressButton().should('not.exist');
        })

        it('C4247548: PayPal Express requires data protection to be accepted if enabled @core', () => {

            beforeEachPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();

            // click and make sure data privacy is validated
            repoPDP.getPayPalExpressButton().click();
            repoPDP.getDataPrivacyCheckbox().should('have.class', 'is-invalid');
        })

    })

    describe('Listing', () => {

        it('C4247554: Paypal Express button is visible @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();

            const button = repoListing.getPayPalExpressButton().first();
            button.should('be.visible');

        })

        it('C4247550: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, ['plp']);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();

            repoListing.getPayPalExpressButton().should('not.exist');
        })

        it('C4247547: PayPal Express requires data protection to be accepted if enabled @core', () => {

            beforeEachPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();

            // click and make sure data privacy is validated
            repoListing.getPayPalExpressButton().first().click();
            repoListing.getDataPrivacyCheckbox().first().should('have.class', 'is-invalid');
        })

    })

    describe('Offcanvas', () => {

        it('C4247556: Paypal Express button is visible @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            const button = repoOffcanvas.getPayPalExpressButton();
            button.should('be.visible');
        })

        it('C4247551: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, ['offcanvas'])
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            repoOffcanvas.getPayPalExpressButton().should('not.exist');
        })

        it('C4247546: PayPal Express requires data protection to be accepted if enabled @core', () => {

            beforeEachPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            // click and make sure data privacy is validated
            repoOffcanvas.getPayPalExpressButton().click();
            repoOffcanvas.getDataPrivacyCheckbox().should('have.class', 'is-invalid');
        })

    })

    describe('Cart', () => {

        it('C4247555: Paypal Express button is visible @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCartInOffCanvas();

            const button = repoCart.getPayPalExpressButton();
            button.should('be.visible');

        })

        it('C4247552: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, ['cart']);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCartInOffCanvas();

            repoCart.getPayPalExpressButton().should('not.exist');
        })

        it('C4247545: PayPal Express requires data protection to be accepted if enabled @core', () => {

            beforeEachPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCartInOffCanvas();

            // click and make sure data privacy is validated
            repoCart.getPayPalExpressButton().click();
            repoCart.getDataPrivacyCheckbox().should('have.class', 'is-invalid');
        })

    })

    describe('Register Page', () => {

        it('C4247557: Paypal Express button is visible @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, []);
            });

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCheckoutInOffCanvas();

            const button = registerRepo.getPayPalExpressButton();
            button.should('be.visible');

        })

        it('C4247558: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                configAction.setupPlugin(false, false, false, false, ['register']);
            });
            
            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCheckoutInOffCanvas();

            registerRepo.getPayPalExpressButton().should('not.exist');
        })

    })

});