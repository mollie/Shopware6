import Shopware from "Services/shopware/Shopware";
import OrdersListRepository from "Repositories/admin/orders/OrdersListRepository";
import OrderDetailsRepository from "Repositories/admin/orders/OrderDetailsRepository";
import MainMenuRepository from "Repositories/admin/MainMenuRepository";

const shopware = new Shopware();

const repoMainMenu = new MainMenuRepository();
const repoOrdersList = new OrdersListRepository();
const repoOrdersDetails = new OrderDetailsRepository();

const forceOption = {force: true};

export default class AdminOrdersAction {

    /**
     *
     */
    openOrders() {
        cy.wait(1000);
        repoMainMenu.getOrders().click({force: true});
        cy.wait(1000);
        repoMainMenu.getOrdersOverview().click({force: true});
        cy.wait(4000);
    }

    /**
     *
     */
    openLastOrder() {
        repoOrdersList.getLatestOrderNumber().click({force: true});
    }


    /**
     *
     */
    openRefundManager() {
        cy.wait(1000);
        // forceClick because if a Shopware update exists, that dialog is above our button
        repoOrdersDetails.getMollieActionsButton().click({force: true, waitForAnimations: false});
        cy.wait(2000);
        repoOrdersDetails.getMollieRefundManagerButton().click({force: true, waitForAnimations: false});
        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }


    /**
     *
     * @param status
     */
    assertLatestOrderStatus(status) {

        this.openOrders();

        cy.wait(800);

        // match with case insensitive option because shopware
        // switched from "In progress" to "In Progress" with 6.4.11.0 for example
        cy.contains(repoOrdersList.getLatestOrderStatusLabelSelector(), status, {matchCase: false});
    }

    /**
     *
     * @param status
     */
    assertLatestPaymentStatus(status) {

        this.openOrders();

        cy.wait(800);
        cy.contains(repoOrdersList.getLatestPaymentStatusLabelSelector(), status);
    }

    /**
     *
     */
    openShipThroughMollie() {

        if (shopware.isVersionLower('6.5')) {
            repoOrdersDetails.getMollieActionsButton().click({force: true});
            cy.wait(2000);
        }

        // it will be lazy-loaded enabled
        cy.wait(2000);
        repoOrdersDetails.getMollieActionButtonShipThroughMollie().click({force: true});

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
    }

    /**
     *
     * @param nthItem
     */
    openLineItemShipping(nthItem) {

        repoOrdersDetails.getLineItemActionsButton(nthItem).click({force: true})

        cy.wait(4000);

        repoOrdersDetails.getLineItemActionsButtonShipThroughMollie().click({force: true});

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait(4000);
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

        repoOrdersDetails.getTrackingCode(trackingCode).type(trackingCode, forceOption);
        repoOrdersDetails.getTrackingCodeAddButton().click();

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
