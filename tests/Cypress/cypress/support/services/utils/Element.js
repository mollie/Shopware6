export default class Element {

    /**
     *
     * @param element
     * @param text
     * @returns {*}
     */
    assertContainsText(element, text) {
        return this.assertContainsTexts(element, [text]);
    }

    /**
     *
     * @param {Cypress.Chainable<JQuery<HTMLElement>>} element
     * @param haystack
     */
    assertContainsTexts(element, haystack) {
        element.invoke('text').then(elementText => {

            let found = false;

            haystack.forEach(currentText => {
                if (elementText.trim().includes(currentText.trim())) {
                    found = true;
                    return true;
                }
            });

            if (!found) {
                throw new Error('expected list of possible texts not found in element. Instead found text: ' + elementText.trim());
            }

        });
    }


}
