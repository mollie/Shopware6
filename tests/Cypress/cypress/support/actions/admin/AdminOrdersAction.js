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
        cy.wait(200);
        repoMainMenu.getOrders().click();
        cy.wait(1000);
        repoMainMenu.getOrdersOverview().click();
        cy.wait(1000);
    }

    /**
     *
     */
    openLastOrder() {
        repoOrdersList.getLatestOrderNumber().click();
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
        repoOrdersDetails.getMollieActionsButton().click({force: true});
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
        cy.wait(2000);
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
        repoOrdersDetails.getEditButton().click();
        repoOrdersDetails.getTrackingCode(trackingCode).type(trackingCode, forceOption);
        repoOrdersDetails.getSaveButton().click();
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
