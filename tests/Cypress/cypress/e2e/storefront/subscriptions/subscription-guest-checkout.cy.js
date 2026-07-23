import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";
import RegisterRepository from "Repositories/storefront/checkout/RegisterRepository";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();

const configAction = new ShopConfigurationAction();
const pdp = new PDPAction();
const mollieProducts = new MollieProductsAction();

const repoRegister = new RegisterRepository();

const testDevices = [devices.getFirstDevice()];


let beforeAllCalled = false;

function beforeEach(device) {
    cy.wrap(null).then(() => {
        if (!beforeAllCalled) {

            const shopConfig = new ShopConfiguration();

            const pluginConfig = new PluginConfiguration();
            pluginConfig.setSubscriptionIndicator(true);

            configAction.configureEnvironment(shopConfig, pluginConfig);

            beforeAllCalled = true;
        }
        devices.setDevice(device);
        // reset session so every test starts as an anonymous (not logged in) guest
        session.resetBrowserSession();
    });
}


describe('Subscription - Guest Checkout', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            describe('Storefront', () => {

                // TODO: prefix the test name with the TestRail case id (e.g. "C123456: ...") once created
                it('Subscription in cart forces account creation on the guest checkout page', () => {

                    beforeEach(device);

                    // add a subscription product to the cart as an anonymous visitor
                    mollieProducts.openSubscriptionProduct_Weekly3();
                    cy.contains('.btn', 'Subscribe');
                    pdp.addToCart(1);

                    // open the checkout register page (still not logged in)
                    cy.visit('/checkout/register');
                    cy.url().should('include', '/checkout/register');

                    // our override must inject the hidden field that forces account creation
                    repoRegister.getForcedCreateAccountInput()
                        .should('exist')
                        .and('have.value', 'true');

                    // and the customer must NOT see a toggle to continue as guest:
                    // the core checkbox is preserved (parent()) but visually hidden
                    repoRegister.getVisibleCreateAccountControls().should('not.exist');

                    // the register form itself must still render normally
                    repoRegister.getRegisterSubmitButton().should('be.visible');
                })

                // TODO: prefix the test name with the TestRail case id (e.g. "C123456: ...") once created
                it('Regular cart keeps the guest checkout option available', () => {

                    beforeEach(device);

                    // add a regular (non-subscription) product to the cart as a guest
                    mollieProducts.openRegularProduct();
                    pdp.addToCart(1);

                    cy.visit('/checkout/register');
                    cy.url().should('include', '/checkout/register');

                    // no forced field, because there is no subscription in the cart
                    repoRegister.getForcedCreateAccountInput().should('not.exist');

                    // the standard "create a customer account" control is visible and usable
                    repoRegister.getVisibleCreateAccountControls().should('exist');

                    repoRegister.getRegisterSubmitButton().should('be.visible');
                })

            })
        })
    })
})
