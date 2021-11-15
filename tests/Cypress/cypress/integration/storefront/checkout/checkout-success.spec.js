import Devices from "Services/utils/Devices";
import Session from "Services/utils/Session"
import Shopware from "Services/shopware/Shopware"
import PaymentScreenAction from 'Actions/mollie/PaymentScreenAction';
import IssuerScreenAction from 'Actions/mollie/IssuerScreenAction';
import GiftCardsScreenAction from "Actions/mollie/GiftCardsScreenAction";
// ------------------------------------------------------
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
// ------------------------------------------------------
import CheckoutAction from 'Actions/storefront/checkout/CheckoutAction';
import PaymentAction from "Actions/storefront/checkout/PaymentAction";
import DummyBasketScenario from "Scenarios/DummyBasketScenario";
import VoucherScreenAction from "Actions/mollie/VoucherScreenAction";
import PaymentMethodsScreenAction from "Actions/mollie/PaymentMethodsScreenAction";


const devices = new Devices();
const session = new Session();
const shopware = new Shopware();

const configAction = new ShopConfigurationAction();
const checkout = new CheckoutAction();
const paymentAction = new PaymentAction();

const molliePayment = new PaymentScreenAction();
const mollieIssuer = new IssuerScreenAction();
const mollieVoucher = new VoucherScreenAction();
const mollieGiftCards = new GiftCardsScreenAction();
const molliePaymentMethods = new PaymentMethodsScreenAction();


const scenarioDummyBasket = new DummyBasketScenario(3);


const device = devices.getFirstDevice();


const payments = [
    {key: 'paypal', name: 'PayPal'},
    {key: 'klarnapaynow', name: 'Pay now'},
    {key: 'klarnapaylater', name: 'Pay later'},
    {key: 'klarnasliceit', name: 'Slice it'},
    {key: 'ideal', name: 'iDEAL'},
    {key: 'sofort', name: 'SOFORT'},
    {key: 'eps', name: 'eps'},
    {key: 'giropay', name: 'Giropay'},
    {key: 'mistercash', name: 'Bancontact'},
    {key: 'przelewy24', name: 'Przelewy24'},
    {key: 'kbc', name: 'KBC'},
    {key: 'belfius', name: 'Belfius'},
    {key: 'banktransfer', name: 'Banktransfer'},
    {key: 'giftcard', name: 'Gift cards'},
    {key: 'voucher', name: 'Voucher'},
];


context("Checkout Tests", () => {

    before(function () {
        devices.setDevice(device);

        // configure our shop
        configAction.setupShop(true, false, false);
        // configure our products for vouchers
        configAction.updateProducts('eco');
    })

    beforeEach(() => {
        session.resetBrowserSession();
        devices.setDevice(device);
    });

    describe('Successful Checkout', () => {
        context(devices.getDescription(device), () => {
            payments.forEach(payment => {

                it('Pay with ' + payment.name, () => {

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


                    molliePayment.initSandboxCookie();

                    if (payment.key === 'klarnapaylater' || payment.key === 'klarnapaynow' || payment.key === 'klarnasliceit') {

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

                    } else {

                        if (payment.key === 'kbc') {
                            mollieIssuer.selectKBC();
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
