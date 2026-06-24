import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session";
import Shopware from "Services/shopware/Shopware";
import AdminAPIClient from "Services/shopware/AdminAPIClient";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import DummyUserScenario from "Scenarios/DummyUserScenario";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import PDPAction from "Actions/storefront/products/PDPAction";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import CreditCardScreenAction from "../../../support/actions/mollie/screens/CreditCartScreen";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const topMenu = new TopMenuAction();
const pdp = new PDPAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const molliePayment = new PaymentScreenAction();
const mollieCreditCardForm = new CreditCardScreenAction();
const mollieProductsAction = new MollieProductsAction();
const mollieSandbox = new MollieSandbox();
const dummyUserScenario = new DummyUserScenario();

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
        session.resetBrowserSession();
    });
}


describe('Subscription price update notice', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            it('Storefront notice is rendered when a price update is pending and cancel still works', () => {

                beforeEach(device);

                buySubscription();

                // Seed the drift state via the admin API so the storefront notice
                // path can be exercised without running the scheduled detector
                // against a real product price change.
                const apiClient = new AdminAPIClient();

                cy.wrap(null).then(() => {
                    return apiClient.post('/search/mollie-subscription', {
                        limit: 1,
                        sort: [{field: 'createdAt', order: 'DESC'}],
                    });
                }).then((subscription) => {
                    expect(subscription).to.not.be.undefined;
                    expect(subscription.id).to.be.a('string');

                    const currentAmount = parseFloat(
                        (subscription.attributes && subscription.attributes.amount) ?? subscription.amount ?? 0
                    );
                    const newPrice = (Number.isFinite(currentAmount) ? currentAmount : 0) + 5;
                    const notifiedAt = new Date().toISOString();

                    return apiClient.bulkUpdate('mollie_subscription', [{
                        id: subscription.id,
                        priceUpdateState: 'notified',
                        nextNotifiedPrice: newPrice,
                        notifiedAt: notifiedAt,
                    }]);
                });

                cy.visit('/');
                topMenu.clickAccountWidgetSubscriptions();

                cy.get('[data-test="subscription-price-update-notice"]', {timeout: 10000}).first().should('be.visible');
                cy.get('[data-test="subscription-price-update-current"]').first().should('exist');
                cy.get('[data-test="subscription-price-update-new"]').first().should('exist');
                cy.get('[data-test="subscription-price-update-effective"]').first().should('exist');

                cy.get('[data-test="btn-subscription-price-update-cancel"]').first().click();

                cy.contains('Subscription has been canceled');
            });
        });
    });
});


function buySubscription() {
    dummyUserScenario.execute();

    mollieProductsAction.openSubscriptionProduct_Weekly3();
    pdp.addToCart(1);

    checkout.goToCheckout();
    paymentAction.switchPaymentMethod('Card');
    shopware.prepareDomainChange();
    checkout.placeOrderOnConfirm();

    mollieSandbox.initSandboxCookie();
    cy.wait(1000);
    mollieCreditCardForm.enterValidCard();
    mollieCreditCardForm.submitForm();
    molliePayment.selectPaid();
}
