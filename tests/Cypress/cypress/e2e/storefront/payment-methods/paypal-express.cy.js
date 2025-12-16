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
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const configAction = new ShopConfigurationAction();
const topMenu = new TopMenuAction();
const listing = new ListingAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const mollieProductsAction = new MollieProductsAction();

const repoPDP = new PDPRepository();
const repoListing = new ListingRepository();
const repoOffcanvas = new OffCanvasRepository();
const repoCart = new CartRepository();
const registerRepo = new RegisterRepository();


function beforeEachNoPrivacy() {
    cy.wrap(null).then(() => {
        devices.setDevice(devices.getFirstDevice());

        const shopConfig = new ShopConfiguration();
        const pluginConfig = new PluginConfiguration();

        pluginConfig.setMollieFailureMode(true);

        configAction.configureEnvironment(shopConfig, pluginConfig);

    });
}

function beforeEachPrivacy() {
    cy.wrap(null).then(() => {
        devices.setDevice(devices.getFirstDevice());

        const shopConfig = new ShopConfiguration();
        const pluginConfig = new PluginConfiguration();

        shopConfig.setDataPrivacy(true);
        pluginConfig.setMollieFailureMode(true);

        configAction.configureEnvironment(shopConfig, pluginConfig);

    });
}


describe('Paypal Express - UI Tests', () => {

    describe('PDP', () => {

        it('C4247553: Paypal Express button is visible @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();

            repoPDP.getPayPalExpressButton().should('be.visible');
        })

        it('C4247549: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                pluginConfig.setPaypalExpressRestrictions(['pdp']);
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();

            repoPDP.getPayPalExpressButton().should('not.exist');
        })

        it('C4247548: PayPal Express requires data protection to be accepted if enabled @core', () => {

            beforeEachPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();

            // click and make sure data privacy is validated
            repoPDP.getPayPalExpressButton().click();
            repoPDP.getDataPrivacyCheckbox().should('have.class', 'is-invalid');
        })

    })

    describe('Listing', () => {

        it('C4247554: Paypal Express button is visible @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openListingRegularProducts();

            const button = repoListing.getPayPalExpressButton().first();
            button.should('be.visible');

        })

        it('C4247550: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                pluginConfig.setPaypalExpressRestrictions(['plp']);
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openListingRegularProducts();

            repoListing.getPayPalExpressButton().should('not.exist');
        })

        it('C4247547: PayPal Express requires data protection to be accepted if enabled @core', () => {

            beforeEachPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openListingRegularProducts();

            // click and make sure data privacy is validated
            repoListing.getPayPalExpressButton().first().click();
            repoListing.getDataPrivacyCheckbox().first().should('have.class', 'is-invalid');
        })

    })

    describe('Offcanvas', () => {

        it('C4247556: Paypal Express button is visible @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();
            pdp.addToCart(1);

            const button = repoOffcanvas.getPayPalExpressButton();
            button.should('be.visible');
        })

        it('C4247551: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                pluginConfig.setPaypalExpressRestrictions(['offcanvas']);
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();
            pdp.addToCart(1);

            repoOffcanvas.getPayPalExpressButton().should('not.exist');
        })

        it('C4247546: PayPal Express requires data protection to be accepted if enabled @core', () => {

            beforeEachPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();
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
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();
            pdp.addToCart(1);

            checkout.goToCartInOffCanvas();

            const button = repoCart.getPayPalExpressButton();
            button.should('be.visible');

        })

        it('C4247552: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                pluginConfig.setPaypalExpressRestrictions(['cart']);
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();
            pdp.addToCart(1);

            checkout.goToCartInOffCanvas();

            repoCart.getPayPalExpressButton().should('not.exist');
        })

        it('C4247545: PayPal Express requires data protection to be accepted if enabled @core', () => {

            beforeEachPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();
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
                const pluginConfig = new PluginConfiguration();
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();
            pdp.addToCart(1);

            checkout.goToCheckoutInOffCanvas();

            const button = registerRepo.getPayPalExpressButton();
            button.should('be.visible');

        })

        it('C4247558: Paypal Express button is hidden because of restriction @core', () => {

            beforeEachNoPrivacy();

            cy.wrap(null).then(() => {
                const pluginConfig = new PluginConfiguration();
                pluginConfig.setPaypalExpressRestrictions(['register']);
                configAction.configurePlugin(pluginConfig);
            });

            mollieProductsAction.openRegularProduct();
            pdp.addToCart(1);

            checkout.goToCheckoutInOffCanvas();

            registerRepo.getPayPalExpressButton().should('not.exist');
        })

    })

});