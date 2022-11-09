import Shopware from "Services/shopware/Shopware";
import ShipThroughMollieRepository from "Repositories/admin/ship-through-mollie/ShipThroughMollieRepository";

const shopware = new Shopware();


const repoShipThroughMollie = new ShipThroughMollieRepository();

// we have to force it
// because due to scrolling it might not
// always be visible
const forceOption = {force: true};


export default class ShipThroughMollieAction {

    ship() {
        repoShipThroughMollie.getShipButton().click(forceOption);

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     * @param qty
     */
    shipLineItem(qty) {
        cy.get('#sw-field--shipQuantity').clear(forceOption).type(qty, forceOption);
        repoShipThroughMollie.getShipButton().click(forceOption);

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    openShipThroughMollie() {

    }
}
