import BuyButtonRepository from '../repository/BuyButtonRepository';

export default class ExpressAddToCart {
    async addItemToCart(button) {
        const buyButtonRepository = new BuyButtonRepository();
        const buyButton = buyButtonRepository.find(button);
        if (!(buyButton instanceof HTMLButtonElement)) {
            return new Promise((resolve) => {
                resolve();
            })

        }
        const buyButtonForm = buyButton.closest('form');
        if (!(buyButtonForm instanceof HTMLFormElement)) {
            return new Promise((resolve) => {
                resolve();
            })
        }

        const formData = new FormData(buyButtonForm);
        formData.delete('redirectTo');
        formData.append('isExpressCheckout', '1');

        return fetch(buyButtonForm.action, {
            method: buyButtonForm.method,
            body: formData,
        });
    }
}