export default class TextFieldValidator {

    /**
     *
     * @param element
     */
    constructor(element) {
        this._element = element;
    }

    /**
     *
     * @param text
     */
    equalsValue(text) {
        this._element.should('have.value', text);
    }

    /**
     *
     */
    emptyValue() {
        this._element.should('have.value', '');
    }

    /**
     *
     */
    notEmptyValue() {
        this._element.should('not.have.value', '');
    }

    /**
     *
     * @param text
     */
    notEqualsValue(text) {
        this._element.should('not.have.value', text);
    }

    /**
     *
     * @param text
     */
    containsValue(text) {
        this._element.should('contain.value', text);
    }

    /**
     *
     * @param text
     */
    notContainsValue(text) {
        this._element.should('not.contain.value', text);
    }

}