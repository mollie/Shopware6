import TextFieldValidator from "Services/utils/VueJs/TextFieldValidator";

export default class VueJs {

    /**
     *
     * @param element
     * @returns {TextFieldValidator}
     */
    textField(element) {
        return new TextFieldValidator(element);
    }

}