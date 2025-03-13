import Plugin from '../Plugin';

export default class MollieBancomatPlugin extends Plugin {
    init() {
        const phoneField = document.getElementById('mollieBancomatPayPhone');
        if (phoneField === null) {
            return;
        }

        const inputFieldWrapper = document.querySelector('.mollie-bancomat-pay');

        const errorMessageElement = document.querySelector(
            '.mollie-bancomat-pay [data-form-validation-invalid-phone="true"]',
        );

        phoneField.addEventListener('focus', (e) => {
            inputFieldWrapper.classList.add('was-validated');
            e.target.removeAttribute('invalid');
            errorMessageElement.classList.add('d-none');
        });

        phoneField.addEventListener('blur', (e) => {
            const form = e.target.form;
            if (form.reportValidity() === false) {
                e.target.setAttribute('invalid', true);
                errorMessageElement.classList.remove('d-none');
            }
            return form.reportValidity();
        });
    }
}
