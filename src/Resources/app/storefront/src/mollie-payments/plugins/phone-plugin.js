import Plugin from '../plugin';

const MOLLIE_PHONE_FIELD_ID = 'molliePayPhone';
const MOLLIE_PHONE_WRAPPER_SELECTOR = '.mollie-phone-field';
const MOLLIE_PHONE_ERROR_MESSAGE_SELECTOR = '.mollie-phone-field [data-form-validation-invalid-phone="true"]';

const DISPLAY_NONE_CLS = 'd-none';
const WAS_VALIDATED_CLS = 'was-validated';
const INVALID_ATTR = 'invalid';

export default class MolliePhonePlugin extends Plugin {
    init() {
        const phoneField = document.getElementById(MOLLIE_PHONE_FIELD_ID);
        if (phoneField === null) {
            return;
        }

        const inputFieldWrapper = document.querySelector(MOLLIE_PHONE_WRAPPER_SELECTOR);

        const errorMessageElement = document.querySelector(MOLLIE_PHONE_ERROR_MESSAGE_SELECTOR);

        phoneField.addEventListener('focus', (e) => {
            inputFieldWrapper.classList.add(WAS_VALIDATED_CLS);
            e.target.removeAttribute(INVALID_ATTR);
            errorMessageElement.classList.add(DISPLAY_NONE_CLS);
        });

        phoneField.addEventListener('blur', (e) => {
            const form = e.target.form;
            if (form.reportValidity() === false) {
                e.target.setAttribute(INVALID_ATTR, true);
                errorMessageElement.classList.remove(DISPLAY_NONE_CLS);
            }
            return form.reportValidity();
        });
    }
}
