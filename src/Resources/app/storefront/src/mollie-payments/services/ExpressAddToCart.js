import BuyButtonRepository from '../repository/BuyButtonRepository';

export default class ExpressAddToCart {
    addItemToCart(button) {
        const buyButtonRepository = new BuyButtonRepository();
        const buyButton = buyButtonRepository.find(button);
        if (!(buyButton instanceof HTMLButtonElement)) {
            return;

        }
        const buyButtonForm = buyButton.closest('form');
        if (!(buyButtonForm instanceof HTMLFormElement)) {
            return;
        }

        const formData = new FormData(buyButtonForm);
        formData.delete('redirectTo');
        formData.append('isExpressCheckout', '1');

        fetch(buyButtonForm.action, {
            method: buyButtonForm.method,
            body: formData,
        }).finally(() =>{});
    }
}