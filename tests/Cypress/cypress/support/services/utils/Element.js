export default class Element {

    /**
     *
     * @param {Cypress.Chainable<JQuery<HTMLElement>>} element
     * @param haystack
     */
    containsText(element, haystack) {

        element.invoke('text').then(elementText => {

            haystack.forEach(currentText => {
                if (elementText.includes(currentText)) {
                    return true;
                }
            });

            return false;
        });
    }

}
