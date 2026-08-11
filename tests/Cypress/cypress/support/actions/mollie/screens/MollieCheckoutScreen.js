const SUBMIT_BUTTON = 'button[type="submit"]:not([value])';
const METHOD_BUTTON = 'button[value]';

// a payment link can show a "continue to checkout" step and a mandate confirmation before the
// payment method list, plus one spare attempt for a click that got lost while the page was rendering
const MAX_ATTEMPTS = 3;

export default class MollieCheckoutScreen {

    /**
     * Submits the intermediate steps Mollie shows before the payment method list, such as the
     * "continue to checkout" confirmation or the mandate confirmation of a subscription order.
     * Those steps are optional, so we submit until the payment method list is on screen.
     *
     * The Mollie checkout is rendered on the client, so waiting for the document alone is not enough
     * to tell an intermediate step from the payment method list. We wait until one of them is visible
     * and only submit if it is not the method list, which never has to be submitted.
     *
     * @param {number} remainingAttempts
     */
    continueToCheckout(remainingAttempts = MAX_ATTEMPTS) {
        cy.get(SUBMIT_BUTTON + ':visible, ' + METHOD_BUTTON + ':visible', {timeout: 30000}).should('exist');

        cy.get('body').then(($body) => {
            const methods = $body.find(METHOD_BUTTON).filter(':visible');

            if (methods.length > 0 || remainingAttempts <= 0) {
                return;
            }

            const submit = $body.find(SUBMIT_BUTTON).filter(':visible');

            // The page keeps rendering on its own, so a step found here can be gone by the time a
            // cy command runs. Look it up and click it in the same tick, where it cannot detach, and
            // simply try again if there is nothing to submit anymore.
            if (submit.length > 0) {
                submit[0].click();
            }

            // The submitted step stays on screen for a moment while the next one renders, so give it
            // time to disappear before deciding whether another step follows.
            cy.wait(2000);

            this.continueToCheckout(remainingAttempts - 1);
        });
    }
}
