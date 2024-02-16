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


describe('Paypal Express - UI Tests', () => {


    before(function () {
        devices.setDevice(devices.getFirstDevice());
    })

    beforeEach(function () {
        devices.setDevice(devices.getFirstDevice());
    })

    describe('PDP', () => {

        it('Paypal Express button is visible @core', () => {
            configAction.setupPlugin(false,false,false,false,[]);

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            const quantity = 5;
            pdp.setQuantity(quantity);

            repoPDP.getPayPalExpressQuantity().should('have.value',quantity);

            const button = repoPDP.getPayPalExpressButton();
            button.should('be.visible');
            button.click();
            cy.url().should('include', 'paypal.com');

        })

        it('Paypal Express button is hidden because of restriction @core', () => {

            configAction.setupPlugin(false,false,false,false,['pdp']);

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();

            repoPDP.getPayPalExpressButton().should('not.exist');
        })

    })

    describe('Listing', () => {

        it('Paypal Express button is visible @core', () => {

            configAction.setupPlugin(false,false,false,false,[]);

            cy.visit('/');
            topMenu.clickOnSecondCategory();

            const button = repoListing.getPayPalExpressButton().first();
            button.should('be.visible');
            button.click();
            cy.url().should('include', 'paypal.com');

        })

        it('Paypal Express button is hidden because of restriction @core', () => {

            configAction.setupPlugin(false,false,false,false,['plp']);

            cy.visit('/');
            topMenu.clickOnSecondCategory();

            repoListing.getPayPalExpressButton().should('not.exist');
        })
    })


    describe('Offcanvas', () => {

        it('Paypal Express button is visible @core', () => {

            configAction.setupPlugin(false,false,false,false,[]);

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            const button = repoOffcanvas.getPayPalExpressButton();
            button.should('be.visible');
            button.click();
            cy.url().should('include', 'paypal.com');
        })

        it('Paypal Express button is hidden because of restriction @core', () => {

            configAction.setupPlugin(false,false,false,false,['offcanvas'])


            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            repoOffcanvas.getPayPalExpressButton().should('not.exist');
        })

    })

    describe('Cart', () => {

        it('Paypal Express button is visible @core', () => {

            configAction.setupPlugin(false,false,false,false,[]);

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCartInOffCanvas();

            const button = repoCart.getPayPalExpressButton();
            button.should('be.visible');
            button.click();
            cy.url().should('include', 'paypal.com');

        })

        it('Paypal Express button is hidden because of restriction @core', () => {

            configAction.setupPlugin(false,false,false,false,['cart']);

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCartInOffCanvas();

            repoCart.getPayPalExpressButton().should('not.exist');
        })

    })

    describe('Register Page', () => {

        it('Paypal Express button is visible @core', () => {

            configAction.setupPlugin(false,false,false,false,[]);

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCheckoutInOffCanvas();

            const button = registerRepo.getPayPalExpressButton();
            button.should('be.visible');
            button.click();
            cy.url().should('include', 'paypal.com');

        })

        it('Paypal Express button is hidden because of restriction @core', () => {

            configAction.setupPlugin(false,false,false,false,['register']);

            cy.visit('/');
            topMenu.clickOnSecondCategory();
            listing.clickOnFirstProduct();
            pdp.addToCart(1);

            checkout.goToCheckoutInOffCanvas();

            registerRepo.getPayPalExpressButton().should('not.exist');
        })

    })

});