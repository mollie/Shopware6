import Devices from "Services/utils/Devices";
import Shopware from "Services/shopware/Shopware";
import {ApplePaySessionMockFactory} from "Services/ApplePay/ApplePay.Mock";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import DummyUserScenario from "Scenarios/DummyUserScenario";
import AccountAction from "Actions/storefront/account/AccountAction";


const devices = new Devices();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const paymentAction = new PaymentAction();
const accountAction = new AccountAction();

const applePayFactory = new ApplePaySessionMockFactory;

const scenarioDummyUser = new DummyUserScenario();
const scenarioDummyBasket = new DummyBasketScenario(1);

const device = devices.getFirstDevice();


context("Apple Pay", () => {

    before(function () {
        devices.setDevice(device);
        configAction.setupShop(true, false);
    })

    beforeEach(() => {
        devices.setDevice(device);
    });

    describe('Checkout', () => {

        it('Apple Pay available (Checkout)', () => {

            applePayFactory.registerApplePay(true);

            scenarioDummyBasket.execute();

            if (shopware.isVersionGreaterEqual(6.4)) {
                paymentAction.showAllPaymentMethods();
            } else {
                paymentAction.openPaymentsModal();
            }

            cy.wait(2000);
            cy.contains('Apple Pay').should('exist');
        })

        it('Apple Pay hidden (Checkout)', () => {

            applePayFactory.registerApplePay(false);

            scenarioDummyBasket.execute();

            if (shopware.isVersionGreaterEqual(6.4)) {
                paymentAction.showAllPaymentMethods();
            } else {
                paymentAction.openPaymentsModal();
            }

            cy.wait(2000);
            cy.contains('Apple Pay').should('not.exist');
        })
    })

    describe('Account', () => {

        it('Apple Pay hidden if available (Account)', () => {

            applePayFactory.registerApplePay(true);

            scenarioDummyUser.execute();
            accountAction.openPaymentMethods();

            cy.wait(2000);
            cy.contains('Apple Pay').should('not.exist');
        })

        it('Apple Pay hidden if not available (Account)', () => {

            applePayFactory.registerApplePay(false);

            scenarioDummyUser.execute();
            accountAction.openPaymentMethods();

            cy.wait(2000);
            cy.contains('Apple Pay').should('not.exist');
        })
    })
})
