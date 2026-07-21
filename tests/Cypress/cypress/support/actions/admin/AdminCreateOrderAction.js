import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();

const forceOption = {force: true};

/**
 * Creates an order through the real admin UI - the same way a merchant would - so it can then be
 * paid via a payment link. Returns the created order id (read from the detail page URL).
 *
 * The selectors mix stable data-analytics-id hooks with generic text/component selectors, because
 * Shopware renames a lot of them between versions. Adjust the marked spots if a step breaks.
 */
export default class AdminCreateOrderAction {

    /**
     * @param {string} customerEmail
     * @param {string|string[]} products  one or more product numbers as shown in the product search
     * @param {string} paymentMethodName
     * @param {string} shippingMethodName
     * @returns {Cypress.Chainable<string>} the created order id
     */
    createOrder(customerEmail, products, paymentMethodName, shippingMethodName) {

        const productNumbers = Array.isArray(products) ? products : [products];

        // The dedicated create dialog spares us clicking through the order list.
        cy.visit('/admin#/sw/order/create/initial');

        // --- select the customer -------------------------------------------------------------
        cy.get('.sw-order-customer-grid, .sw-order-create-initial', {timeout: 20000}).should('exist');
        cy.get('.sw-order-customer-grid input, input.sw-simple-search__field', {timeout: 20000})
            .first()
            .clear()
            .type(customerEmail);

        // Pick the customer by clicking the radio input inside the matching grid row (clicking the
        // row itself does not select it).
        cy.contains('.sw-data-grid__row', customerEmail, {timeout: 20000})
            .find('input[type="radio"]')
            .click(forceOption);

        // --- sales channel popup -------------------------------------------------------------
        // Selecting a customer with access to multiple channels opens a modal to pick one. The
        // dropdown results render in a detached popover (outside the modal), so they are queried at
        // the top level, not within the modal. This step only exists from Shopware 6.7 on; earlier
        // versions go straight to the product tab after picking the customer.
        if (shopware.isVersionGreaterEqual('6.7')) {
            cy.get('.sw-order-customer-grid__sales-channel-selection-modal', {timeout: 20000}).should('be.visible');
            cy.get('.sw-order-customer-grid__sales-channel-selection .sw-select__selection', {timeout: 20000}).click();
            cy.contains('.sw-select-result', 'Storefront', {timeout: 20000}).click();
            cy.get('[data-analytics-id="sw-order-customer-grid.select-sales-channel"]', {timeout: 20000}).click();
        }

        // --- add the products ----------------------------------------------------------------
        cy.get('.sw-order-create-initial-modal__tab-product', {timeout: 20000}).click();

        // Each "Add product" prepends a new empty row at the top (row 0) and pushes the existing rows
        // down, so every product is filled in row 0. The store-api cart context call behind the
        // scenes sometimes fails once (returns a 403), which saves a row without a calculated price;
        // addProductRow redoes the inline edit until the line item shows a total.
        productNumbers.forEach((productNumber) => {
            cy.contains('button', 'Add product', {timeout: 20000}).click(forceOption);
            this.addProductRow(productNumber);
        });

        // --- options tab: payment + shipping method ------------------------------------------
        // The payment method must be a Mollie method so a payment link can be created for it, and
        // the shipping method must be the Mollie test shipment used across the E2E suite.
        cy.get('.sw-order-create-initial-modal__tab-options', {timeout: 20000}).click();
        this.selectSingleField('.sw-order-create-options__payment-method', paymentMethodName);
        this.selectSingleField('.sw-order-create-options__shipping-method', shippingMethodName);

        // --- preview + save ------------------------------------------------------------------
        // "Preview order" navigates to the create/general page and calculates the order. Only save
        // once we are on that page and the summary shows a non-zero total, otherwise an empty order
        // is saved before the calculation finished.
        cy.get('[data-analytics-id="sw-order-create-initial-modal.preview-order"], .sw-order-create-initial-modal__button-preview', {timeout: 20000}).click();
        cy.url({timeout: 20000}).should('include', '/sw/order/create/general');
        cy.get('.sw-order-create-summary', {timeout: 20000}).should('be.visible').and('not.contain', '€0.00');

        cy.get('[data-analytics-id="sw-order-create.save-order"], .smart-bar__actions .sw-button-process.sw-button--primary', {timeout: 20000}).click();

        // "Send order confirmation to customer?" reminder - we do not send a mail here.
        cy.contains('.sw-modal button', 'No', {timeout: 20000}).click(forceOption);

        // We land on the order detail page; the id is part of the URL.
        cy.url({timeout: 30000}).should('include', '/sw/order/detail/');

        return cy.url().then((url) => {
            const match = url.match(/\/sw\/order\/detail\/([0-9a-f]+)/);

            expect(match, 'order id in detail URL').to.not.be.null;

            return match[1];
        });
    }

    /**
     * Fills the (already added) product row at the given index: makes it editable via double-click,
     * searches the product and confirms it. Occasionally the row is saved without a calculated price
     * because the underlying store-api cart context call failed once - the row then shows a zero
     * total. In that case we simply redo the inline edit, which is what recovers it when done by hand.
     *
     * @param {string} productName
     * @param {number} rowIndex
     * @param {number} remainingAttempts
     */
    addProductRow(productName, rowIndex = 0, remainingAttempts = 3) {

        const row = '.sw-data-grid__row--' + rowIndex;

        // The new row is not editable yet - double-clicking the item cell turns it into an
        // inline-edit row that then shows the product select.
        cy.get(row + ' .sw-data-grid__cell--label', {timeout: 20000}).dblclick();

        // Open the product select in the item column and search.
        cy.get(row + ' .sw-order-product-select .sw-select__selection', {timeout: 20000}).click();
        cy.get(row + ' .sw-order-product-select input.sw-entity-single-select__selection-input')
            .clear()
            .type(productName);

        // Narrow the suggestions down to the single matching product and pick it.
        this.pickSingleProductResult(rowIndex);

        cy.get(row + ' .sw-data-grid__inline-edit-save', {timeout: 20000}).click();
        cy.get(row + '.is--inline-edit').should('not.exist');

        // Give the async price calculation a moment to settle. On success the total is populated
        // quickly; on the 403 race it stays at zero, so a short settle is enough to tell them apart.
        cy.wait(2000);

        cy.get(row + ' .sw-data-grid__cell--totalPrice', {timeout: 20000}).then(($cell) => {
            const priceText = $cell.text();
            const hasPrice = priceText.includes('€') && !priceText.includes('€0.00');

            if (!hasPrice && remainingAttempts > 1) {
                this.addProductRow(productName, rowIndex, remainingAttempts - 1);
            }
        });
    }

    /**
     * Waits for the product suggestions of the row at the given index to narrow to a single result
     * and clicks it. The debounced search does not always filter after typing, so while more (or
     * none) than one result is shown we delete one character from the input to re-trigger it.
     *
     * @param {number} rowIndex
     * @param {number} remainingAttempts
     */
    pickSingleProductResult(rowIndex = 0, remainingAttempts = 6) {

        const input = '.sw-data-grid__row--' + rowIndex + ' .sw-order-product-select input.sw-entity-single-select__selection-input';

        // give the debounced search a moment before inspecting the result list
        cy.wait(500);

        cy.get('body').then(($body) => {
            const results = $body.find('.sw-select-result');

            if (results.length === 1) {
                cy.get('.sw-select-result').first().click();
                cy.get('.sw-select-result').should('not.exist');
                return;
            }

            if (remainingAttempts <= 1) {
                // out of attempts: pick the first result if there is one, otherwise let the price
                // retry in addProductRow redo the whole row.
                if (results.length > 1) {
                    cy.get('.sw-select-result').first().click();
                    cy.get('.sw-select-result').should('not.exist');
                }
                return;
            }

            // delete one character to re-trigger the search, give it a second to filter, then check
            // the results again
            cy.get(input).type('{backspace}');
            cy.wait(1000);

            this.pickSingleProductResult(rowIndex, remainingAttempts - 1);
        });
    }

    /**
     * Opens the Shopware single-select identified by its component class, filters by the option label
     * and picks the matching result from the detached results popover. The component class is stable
     * across versions and languages, unlike the input's aria-label/placeholder which are translated.
     */
    selectSingleField(fieldSelector, optionLabel) {
        cy.get(`${fieldSelector} input.sw-entity-single-select__selection-input`, {timeout: 20000}).click(forceOption).clear(forceOption).type(optionLabel);
        cy.contains('.sw-select-result', optionLabel, {timeout: 20000}).click();
    }

}
