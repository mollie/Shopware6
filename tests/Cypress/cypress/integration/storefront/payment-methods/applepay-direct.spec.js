import {ApplePaySessionMockFactory} from "Services/applepay/ApplePay.Mock";
import TopMenuAction from "Actions/storefront/navigation/TopMenuAction";
import ListingAction from "Actions/storefront/products/ListingAction";
import Devices from "Services/utils/Devices";
import ShopConfigurationAction from "Actions/admin/ShopConfigurationAction";
import PDPRepository from "Repositories/storefront/products/PDPRepository";


const devices = new Devices();
const configAction = new ShopConfigurationAction();
const applePayFactory = new ApplePaySessionMockFactory();
const topMenu = new TopMenuAction();
const listing = new ListingAction();

const repoPDP = new PDPRepository();


describe('Apple Pay Direct - Functional', () => {

    it('C5418: Domain Verification file has been downloaded', () => {
        cy.request('/.well-known/apple-developer-merchantid-domain-association');
    })
})


describe('Apple Pay Direct - UI Tests', () => {

    context('Config - Disabled', () => {

        before(function () {
            devices.setDevice(devices.getFirstDevice());
            configAction.setupShop(true, false, false);
        })

        beforeEach(function () {
            devices.setDevice(devices.getFirstDevice());
        })

        describe('PDP', () => {

            it('C5419: Apple Pay Direct not visible if available but not configured (PDP)', () => {

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnClothing();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('not.exist');
            })

        })

    })

    context('Config - Enabled', () => {

        before(function () {
            devices.setDevice(devices.getFirstDevice());
            configAction.setupShop(true, false, true);
        })

        beforeEach(function () {
            devices.setDevice(devices.getFirstDevice());
        })

        describe('PDP', () => {

            it('C6920: Apple Pay Direct visible if available and configured (PDP)', () => {

                applePayFactory.registerApplePay(true);

                cy.visit('/');
                topMenu.clickOnClothing();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('not.have.class', 'd-none');
            })

            it('C6921: Apple Pay Direct hidden if not available but configured (PDP)', () => {

                applePayFactory.registerApplePay(false);

                cy.visit('/');
                topMenu.clickOnClothing();
                listing.clickOnFirstProduct();

                repoPDP.getApplePayDirectButton().should('have.class', 'd-none');
            })

        })

    })

});