import BuyBoxRepository from '../../repository/buy-box-repository';

export default class AddToCartService {
    constructor() {
        this._repoBuyBox = new BuyBoxRepository();
    }

    addItemToCart(button) {
        const buyButton = this._repoBuyBox.findClosestShopwareBuyButton(button);

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

        // we need all parameters except the redirectTo parameter
        for (let i = 0; i < formElements.length; i++) {
            const element = formElements[i];
            if (element.name && element.name !== 'redirectTo') {
                params.push(encodeURIComponent(element.name) + '=' + encodeURIComponent(element.value));
            }
        }

        // this is needed to trigger the express checkout
        params.push('isExpressCheckout=1');

        // always use the shopware default add-to-cart URL
        const swActionURL = buyButtonForm.action;
        const swActionMethod = buyButtonForm.method;

        const xhr = new XMLHttpRequest();
        xhr.open(swActionMethod, swActionURL, false);
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(params.join('&'));

        if (xhr.status >= 400) {
            throw new Error(`Request failed with status ${xhr.status}`);
        }
    }
}
