import LineItemShippingRepository from "Repositories/admin/ship-through-mollie/LineItemShippingRepository";
import FullShippingRepository from "Repositories/admin/ship-through-mollie/FullShippingRepository";

const repoShippingFull = new FullShippingRepository();
const repoShippingItem = new LineItemShippingRepository();

// we have to force it
// because due to scrolling it might not
// always be visible
const forceOption = {force: true};


export default class ShipThroughMollieAction {


    /**
     *
     */
    shipFullOrder() {

        cy.wait(2000);

        // select all items, otherwise
        // nothing would be shipped
        repoShippingFull.getSelectAllItemsButton().click();

        repoShippingFull.getShippingButton().click(forceOption);

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     */
    shipBatchOrder() {

        cy.wait(2000);

        // select our first item
        repoShippingFull.getFirstItemSelectCheckbox().click();

        repoShippingFull.getShippingButton().click(forceOption);

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     * @param qty
     */
    shipLineItem(qty) {
        repoShippingItem.getInputQuantity().clear(forceOption).type(qty, forceOption);
        repoShippingItem.getShippingButton().click(forceOption);

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

}
