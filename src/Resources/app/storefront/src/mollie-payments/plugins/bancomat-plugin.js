import Plugin from '../plugin';

const MOLLIE_BANCOMAT_PAY_PHONE_ID = 'mollieBancomatPayPhone';
const MOLLIE_BANCOMAT_WRAPPER_SELECTOR = '.mollie-bancomat-pay';
const MOLLIE_BANCOMAT_ERROR_MESSAGE_SELECTOR = '.mollie-bancomat-pay [data-form-validation-invalid-phone="true"]';

const DISPLAY_NONE_CLS = 'd-none';
const WAS_VALIDATED_CLS = 'was-validated';
const INVALID_ATTR = 'invalid';

export default class MollieBancomatPlugin extends Plugin {
    init() {
        const phoneField = document.getElementById(MOLLIE_BANCOMAT_PAY_PHONE_ID);
        if (phoneField === null) {
            return;
        }

        const inputFieldWrapper = document.querySelector(MOLLIE_BANCOMAT_WRAPPER_SELECTOR);

        const errorMessageElement = document.querySelector(MOLLIE_BANCOMAT_ERROR_MESSAGE_SELECTOR);

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
