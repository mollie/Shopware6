import {ApplePaySessionMockFactory} from "Services/stubs/applepay.stub";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import Devices from "Services/Devices";
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";


const devices = new Devices();
const configAction = new ShopConfigurationAction();
const mockFactory = new ApplePaySessionMockFactory();
const topMenu = new TopMenuAction();
const listing = new ListingAction();


const applePaySessionMock = mockFactory.buildMock();

context("Apple Pay Direct", () => {

    before(function () {
        devices.setDevice(devices.getFirstDevice());
        configAction.setupShop(true, false);
    })


    it('Apple Pay Direct available on PDP', () => {

        Cypress.on('window:before:load', (win) => {
            win.ApplePaySession = applePaySessionMock;
        })

        cy.visit('/');
        topMenu.clickOnClothing();
        listing.clickOnFirstProduct();

        cy.get('.mollie-apple-pay-direct > .btn').should('not.have.class', 'd-none');
    })
    
    it('Apple Pay Direct hidden on PDP', () => {

        cy.visit('/');
        topMenu.clickOnClothing();
        listing.clickOnFirstProduct();

        cy.get('.mollie-apple-pay-direct > .btn').should('have.class', 'd-none');
    })

});