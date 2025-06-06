import BuyButtonRepository from "../../repository/buy-button-repository";


export default class AddToCartService {

    addItemToCartOrSkip(button) {

        const buyButtonRepository = new BuyButtonRepository();
        const buyButton = buyButtonRepository.find(button);

        if (!(buyButton instanceof HTMLButtonElement)) {
            return;
        }

        const buyButtonForm = buyButton.closest('form');

        if (!(buyButtonForm instanceof HTMLFormElement)) {
            return;
        }

        // Collect form data manually for IE compatibility
        const formElements = buyButtonForm.elements;
        const params = [];

        for (let i = 0; i < formElements.length; i++) {
            const element = formElements[i];
            if (element.name && element.name !== 'redirectTo') {
                params.push(encodeURIComponent(element.name) + '=' + encodeURIComponent(element.value));
            }
        }

        params.push('isExpressCheckout=1');

        const xhr = new XMLHttpRequest();
        xhr.open(buyButtonForm.method, buyButtonForm.action, false);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(params.join('&'));

        if (xhr.status >= 400) {
            throw new Error(`Request failed with status ${xhr.status}`);
        }
    }
}
