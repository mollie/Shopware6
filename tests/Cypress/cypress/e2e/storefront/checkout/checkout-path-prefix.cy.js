import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware"
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import DummyUserScenario from "Scenarios/DummyUserScenario";
import PDPAction from "Actions/storefront/products/PDPAction";
import MollieProductsAction from "Actions/storefront/products/MollieProductsAction";
import ShopConfiguration from "../../../support/models/ShopConfiguration";
import PluginConfiguration from "../../../support/models/PluginConfiguration";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();
const pdpAction = new PDPAction();
const mollieProductsAction = new MollieProductsAction();

const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();

const scenarioDummyUser = new DummyUserScenario();


const device = devices.getFirstDevice();

// The `mollie:fixtures:load` command adds a second storefront domain that serves the shop under
// this path prefix (a "virtual url" like http://localhost/mollie-e2e). See PrefixDomainFixture.php.
// The bugs this test protects against only appear when the domain carries such a prefix.
const PREFIX = 'mollie-e2e';

let originalBaseUrl = null;
let prefixedUrl = null;

let configured = false;


context("Checkout Tests", () => {

    describe('Successful Checkout on a path-prefixed sales channel domain', () => {
        context(devices.getDescription(device), () => {

            it('Pay with PayPal and return successfully under the /' + PREFIX + ' domain prefix', () => {

                cy.wrap(null).then(() => {
                    if (!configured) {
                        const shopConfig = new ShopConfiguration();
                        const pluginConfig = new PluginConfiguration();

                        // configure the shop while the base url still points at the shop root,
                        // so the admin api client keeps targeting <root>/api
                        configAction.configureEnvironment(shopConfig, pluginConfig);
                        configured = true;
                    }

                    originalBaseUrl = originalBaseUrl ?? Cypress.config('baseUrl');
                    prefixedUrl = originalBaseUrl.replace(/\/+$/, '') + '/' + PREFIX;

                    // route the whole storefront flow through the prefixed (fixture-provided) domain
                    Cypress.config('baseUrl', prefixedUrl);
                });

                session.resetBrowserSession();
                devices.setDevice(device);

                // guard: prove we are really browsing under the path prefix. If the fixture domain
                // is missing, or Cypress stopped preserving the base-url subpath, the flow would
                // silently run against the shop root and hide the very bug this test protects against.
                cy.url().should('include', '/' + PREFIX);

                scenarioDummyUser.execute();

                mollieProductsAction.openEcoProduct();
                pdpAction.addToCart(4);
                checkout.goToCheckout();

                checkout.changeBillingCountry('Germany');
                checkout.changeToMollieShippingMethod();

                paymentAction.switchPaymentMethod('PayPal');

                checkout.getTotalFromConfirm().then(total => {
                    cy.log("Cart Total: " + total);
                    cy.wrap(total.toString().trim()).as('totalSum')
                });

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                // we should now be on the Mollie payment screen for PayPal
                cy.url().should('include', 'https://www.mollie.com/checkout/');
                cy.url().should('include', 'paypal');

                // the amount displayed at Mollie must match the Shopware cart total
                cy.get('.header__amount').then(($headerAmount) => {
                    cy.get('@totalSum').then(totalSum => {
                        expect($headerAmount.text()).to.contain(totalSum);
                    });
                })

                mollieSandbox.initSandboxCookie();
                molliePayment.selectPaid();

                // the money assertion: the return url handed to Mollie carried the /prefix,
                // so the sales channel resolves and finalize completes on the finish page
                // instead of rendering "Sales Channel Not Found".
                cy.url().should('include', '/' + PREFIX + '/checkout/finish');
                cy.contains('Thank you for your order');
            })

            afterEach(() => {
                // restore the base url so the shared admin api client and any later spec
                // keep targeting the shop root, not the prefixed virtual url
                if (originalBaseUrl !== null) {
                    Cypress.config('baseUrl', originalBaseUrl);
                }
            });

        })
    })

})
