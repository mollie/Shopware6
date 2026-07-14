import Shopware from "Services/shopware/Shopware";
import OrdersListRepository from "Repositories/admin/orders/OrdersListRepository";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import MainMenuRepository from "Repositories/admin/MainMenuRepository";

const shopware = new Shopware();

const repoOrdersList = new OrdersListRepository();
const repoOrdersDetails = new OrderDetailsRepository();

const forceOption = {force: true};

export default class AdminOrdersAction {

    /**
     *
     */
    openOrders() {
        cy.visit('/admin#/sw/order/index');

        // wait for order list
        cy.contains('h2', 'Orders', {timeout: 20000});
    }

    /**
     *
     */
    openLastOrder() {
        repoOrdersList.getLatestOrderNumber().trigger('click');

        // wait for info panel on order detail page
        cy.contains( 'Info', {timeout: 20000});
    }


    /**
     *
     */
    openMollieTab() {
        repoOrdersDetails.getMollieTab().click();
        cy.wait(1000);
    }

    /**
     *
     */
    openRefundManager() {
        this.openMollieTab();

        repoOrdersDetails.getMollieRefundManagerButton().trigger('click');
        repoOrdersDetails.getMollieRefundManagerDialog().should('be.visible');
    }


    /**
     *
     * @param status
     */
    assertLatestOrderStatus(status) {

        this.openOrders();

        // match with case-insensitive option because shopware
        // switched from "In progress" to "In Progress" with 6.4.11.0 for example
        cy.contains(repoOrdersList.getLatestOrderStatusLabelSelector(), status, {matchCase: false});
    }

    /**
     *
     * @param status
     */
    assertLatestPaymentStatus(status) {

        this.openOrders();

        cy.contains(repoOrdersList.getLatestPaymentStatusLabelSelector(), status);
    }

    /**
     *
     */
    openShipThroughMollie() {
        this.openMollieTab();

        repoOrdersDetails.getMollieActionButtonShipThroughMollie().should('not.have.class', 'sw-button--disabled');
        repoOrdersDetails.getMollieActionButtonShipThroughMollie().trigger('click');
    }

    /**
     *
     * @param nthItem
     */
    openLineItemShipping(nthItem) {
        this.openMollieTab();

        repoOrdersDetails.getLineItemActionsButton(nthItem).trigger('click');
        repoOrdersDetails.getLineItemActionsButtonShipThroughMollie().should('not.have.class', 'is--disabled');
        repoOrdersDetails.getLineItemActionsButtonShipThroughMollie().click(forceOption);
    }

    /**
     *
     * @param trackingCode
     */
    setTrackingCode(trackingCode) {

        if (shopware.isVersionLower('6.5')) {
            repoOrdersDetails.getEditButton().click();
        }
        cy.wait(2000);


        // Tracking Code is added on OrderDetails Tab, therefore we need to open a new tab first
        // and navigating back after tracking code is set. since 6.5
        if (shopware.isVersionGreaterEqual('6.5')) {
            cy.wait(1000);
            repoOrdersDetails.getOrderDetailsTab().click();
        }

        // the order detail page grew taller, so the tracking-code input (and the "add" popover entry
        // it reveals) can sit below the fold. scroll it into view before interacting, and force the
        // add-button click since the popover entry may still be partly outside the viewport.
        repoOrdersDetails.getTrackingCode(trackingCode).scrollIntoView();
        repoOrdersDetails.getTrackingCode(trackingCode).type(trackingCode, forceOption);
        repoOrdersDetails.getTrackingCodeAddButton().click(forceOption);

        cy.wait(1000);

        repoOrdersDetails.getSaveButton().click();

        if (shopware.isVersionGreaterEqual('6.5')) {
            cy.wait(2000);
            repoOrdersDetails.getOrderDetailsGeneralTab().click();
        }

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    addTrackingCodeToLineItem(quantity, shippingMethodName, trackingCode) {
        cy.get('.sw-field--switch__content > .sw-field--switch__input').click();
        cy.get('#sw-field--shipQuantity').type(quantity);
        cy.get('#sw-field--tracking-carrier').type(shippingMethodName, forceOption);
        cy.get('#sw-field--tracking-code').type(trackingCode, forceOption);
        cy.get('.sw-modal__footer > .sw-button--primary').click();
        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

}
