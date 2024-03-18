import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';

export default class MollieBancomatPlugin extends Plugin {

    init() {

        const phoneField = document.getElementById('mollieBancomatPayPhone');
        if (phoneField === null) {
            console.log("phone field not found");
            return;
        }

        const inputFieldWrapper = document.querySelector('.mollie-bancomat-pay');
        console.log(inputFieldWrapper);
        const errorMessageElement = document.querySelector('.mollie-bancomat-pay [data-form-validation-invalid-phone="true"]');
        console.log(errorMessageElement);
        phoneField.addEventListener('focus',(e)=>{
            inputFieldWrapper.classList.add('was-validated');
            e.target.removeAttribute('invalid');
            errorMessageElement.classList.add('d-none');
            console.log("focus");
        });


        phoneField.addEventListener('blur', (e) => {
            const form = e.target.form;
            if(form.reportValidity() === false){
                e.target.setAttribute('invalid',true);
                errorMessageElement.classList.remove('d-none');
            }
            console.log("blur");
            return form.reportValidity();

        })

    }
}