import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware"
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
// ------------------------------------------------------
import MollieSandbox from "cypress-mollie/src/actions/MollieSandbox";
import PaymentScreenAction from "cypress-mollie/src/actions/screens/PaymentStatusScreen";
import VoucherScreenAction from "cypress-mollie/src/actions/screens/VoucherScreen";
import PaymentMethodsScreenAction from "cypress-mollie/src/actions/screens/PaymentListScreen";
import KBCScreen from "cypress-mollie/src/actions/screens/KBCScreen";
import GiftCardsScreenAction from "cypress-mollie/src/actions/screens/GiftCardsScreen";
import CreditCardScreen from "cypress-mollie/src/actions/screens/CreditCardScreen";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();

const mollieSandbox = new MollieSandbox();
const molliePayment = new PaymentScreenAction();
const mollieKBC = new KBCScreen();
const mollieVoucher = new VoucherScreenAction();
const mollieGiftCards = new GiftCardsScreenAction();
const molliePaymentMethods = new PaymentMethodsScreenAction();
const mollieCreditCard = new CreditCardScreen();

const scenarioDummyBasket = new DummyBasketScenario(4);


const device = devices.getFirstDevice();


const payments = [
    {caseId: 'C4101', key: 'credit-card', name: 'Card'},
    {caseId: 'C4111', key: 'paypal', name: 'PayPal'},
    {caseId: 'C466903', key: 'billie', name: 'Billie'},
    {caseId: 'C4114', key: 'klarnapaynow', name: 'Pay now'},
    {caseId: 'C4115', key: 'klarnapaylater', name: 'Pay later'},
    {caseId: 'C4117', key: 'klarnasliceit', name: 'Slice it'},
    {caseId: 'C4118', key: 'ideal', name: 'iDEAL'},
    {caseId: 'C4116', key: 'sofort', name: 'SOFORT'},
    {caseId: 'C4120', key: 'eps', name: 'eps'},
    {caseId: 'C4122', key: 'giropay', name: 'Giropay'},
    {caseId: 'C4123', key: 'mistercash', name: 'Bancontact'},
    {caseId: 'C4125', key: 'przelewy24', name: 'Przelewy24'},
    {caseId: 'C4126', key: 'kbc', name: 'KBC'},
    {caseId: 'C4128', key: 'banktransfer', name: 'Banktransfer'},
    {caseId: 'C4127', key: 'belfius', name: 'Belfius'},
    {caseId: 'C4121', key: 'giftcard', name: 'Gift cards'},
    {caseId: 'C4143', key: 'voucher', name: 'Voucher'},
    {caseId: 'C1341120', key: 'pointofsale', name: 'POS Terminal'},
    // unfortunately address and product prices need to match, so we cannot do in3 automatically for now
    // {caseId: '', key: 'in3', name: 'in3'},
];


context("Checkout Tests", () => {

    before(function () {
        devices.setDevice(device);

        // configure our shop
        configAction.setupShop(true, false, false);
        // configure our products for vouchers
        configAction.updateProducts('eco', false, '', '');
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    describe('Successful Checkout', () => {
        context(devices.getDescription(device), () => {
            payments.forEach(payment => {

                it(payment.caseId + ': Pay with ' + payment.name, () => {

                    scenarioDummyBasket.execute();

                    paymentAction.switchPaymentMethod(payment.name);


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
                    cy.url().should('include', payment.key);

                    // verify that the price is really the one
                    // that was displayed in Shopware
                    cy.get('.header__amount').then(($headerAmount) => {
                        cy.get('@totalSum').then(totalSum => {
                            expect($headerAmount.text()).to.contain(totalSum);
                        });
                    })


                    mollieSandbox.initSandboxCookie();

                    if (payment.key === 'klarnapaylater' || payment.key === 'klarnapaynow' || payment.key === 'klarnasliceit') {

                        molliePayment.selectAuthorized();

                    } else if (payment.key === 'billie') {

                        molliePayment.selectAuthorized();

                    } else if (payment.key === 'voucher') {

                        // the sandbox voucher is 10 EUR
                        // our prices are usually higher
                        // so Mollie forces us to select another payment method
                        // to pay the rest of the total amount
                        mollieVoucher.selectMonizze();
                        molliePayment.selectPaid();
                        molliePaymentMethods.selectPaypal();
                        molliePayment.selectPaid();

                    } else if (payment.key === 'giftcard') {

                        mollieGiftCards.selectBeautyCards();
                        molliePayment.selectPaid();
                        molliePaymentMethods.selectPaypal();
                        molliePayment.selectPaid();

                    } else if (payment.key === 'credit-card') {

                        cy.wait(2000);

                        mollieCreditCard.enterValidCard();
                        mollieCreditCard.submitForm();
                        molliePayment.selectPaid();

                    } else {

                        if (payment.key === 'kbc') {
                            mollieKBC.selectKBC();
                        }

                        molliePayment.selectPaid();
                    }

                    // we should now get back to the shop
                    // with a successful order message
                    cy.url().should('include', '/checkout/finish');
                    cy.contains('Thank you for your order');
                })

            })
        })
    })

})
