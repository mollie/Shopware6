import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import CheckoutAction from "Actions/storefront/checkout/CheckoutAction";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();

const checkout = new CheckoutAction();

const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();


const testDevices = [devices.getFirstDevice()];

const scenarioDummyBasket = new DummyBasketScenario(1);


describe('SEPA Direct Debit', () => {

    testDevices.forEach(device => {

        context(devices.getDescription(device), () => {

            beforeEach(() => {
                devices.setDevice(device);
                session.resetBrowserSession();
            });

            it('C4131: Pay (subscription) with SEPA Direct Debit', () => {

                configAction.updateProducts('', true, 3, 'weeks');
                configAction.setupPlugin(true, false, false, true);
                cy.wait(2000);

                scenarioDummyBasket.execute();

                paymentAction.switchPaymentMethod('SEPA Direct Debit');


                // grab the total sum of our order from the confirm page.
                // we also want to test what the user has to pay in Mollie.
                // this has to match!
                checkout.getTotalFromConfirm().then(total => {
                    cy.log("Cart Total: " + total);
                    cy.wrap(total.toString().trim()).as('totalSum')
                });

                shopware.prepareDomainChange();
                checkout.placeOrderOnConfirm();

                // verify that we are on the mollie payment screen
                // and that our payment method is also visible somewhere in that url
                cy.url().should('include', 'https://www.mollie.com/checkout/');
                cy.url().should('include', 'directdebit');

                // verify that the price is really the one
                // that was displayed in Shopware
                cy.get('.header__amount').then(($headerAmount) => {
                    cy.get('@totalSum').then(totalSum => {
                        expect($headerAmount.text()).to.contain(totalSum);
                    });
                })

                mollieSandbox.initSandboxCookie();
                molliePayment.selectPaid();

                // we should now get back to the shop
                // with a successful order message
                cy.url().should('include', '/checkout/finish');
                cy.contains('Thank you for your order');
            })
        })
    })
})
