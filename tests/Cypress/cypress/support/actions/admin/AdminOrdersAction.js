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
        cy.intercept('**').as('page')
        repoMainMenu.getOrders().click({force: true});
        cy.wait('@page');
        cy.intercept('**').as('page')
        repoMainMenu.getOrdersOverview().click({force: true});
        cy.wait('@page');
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
        cy.intercept('**').as('page')

        if (shopware.isVersionLower('6.5')) {
            // forceClick because if a Shopware update exists, that dialog is above our button
            repoOrdersDetails.getMollieActionsButton().click({force: true, waitForAnimations: false});
        }

        repoOrdersDetails.getMollieRefundManagerButton().click({force: true, waitForAnimations: false});
        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait('@page');
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
        cy.intercept('**').as('page')
        if (shopware.isVersionLower('6.5')) {
            repoOrdersDetails.getMollieActionsButton().click({force: true});
            cy.wait('@page');
        }

        repoOrdersDetails.getMollieActionButtonShipThroughMollie().should('not.have.class', 'sw-button--disabled');
        repoOrdersDetails.getMollieActionButtonShipThroughMollie().click({force: true});

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait('@page');
    }

    /**
     *
     * @param nthItem
     */
    openLineItemShipping(nthItem) {


        cy.intercept('**').as('page')
        repoOrdersDetails.getLineItemActionsButton(nthItem).click({force: true})

        repoOrdersDetails.getLineItemActionsButtonShipThroughMollie().should('not.have.class', 'is--disabled');

        repoOrdersDetails.getLineItemActionsButtonShipThroughMollie().click({force: true});

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait('@page');
    }

    /**
     *
     * @param trackingCode
     */
    setTrackingCode(trackingCode) {
        cy.intercept('**').as('page')
        if (shopware.isVersionLower('6.5')) {
            repoOrdersDetails.getEditButton().click();
        }



        // Tracking Code is added on OrderDetails Tab, therefore we need to open a new tab first
        // and navigating back after tracking code is set. since 6.5
        if (shopware.isVersionGreaterEqual('6.5')) {

            repoOrdersDetails.getOrderDetailsTab().click();
        }

        repoOrdersDetails.getTrackingCode(trackingCode).type(trackingCode, forceOption);
        repoOrdersDetails.getTrackingCodeAddButton().click();



        repoOrdersDetails.getSaveButton().click();

        if (shopware.isVersionGreaterEqual('6.5')) {

            repoOrdersDetails.getOrderDetailsGeneralTab().click();
        }

        // here are automatic reloads and things as it seems
        // I really want to test the real UX, so we just wait like a human
        cy.wait('@page');
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
