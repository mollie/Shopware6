import BuyButtonRepository from '../repository/buy-button-repository';

export default class ExpressAddToCart {
    addItemToCartOrSkip(button) {
        return new Promise((resolve, reject) => {
            const buyButtonRepository = new BuyButtonRepository();
            const buyButton = buyButtonRepository.find(button);

            if (!(buyButton instanceof HTMLButtonElement)) {
                resolve();
            }

            const buyButtonForm = buyButton.closest('form');

            if (!(buyButtonForm instanceof HTMLFormElement)) {
                resolve();
            }

            const formData = new FormData(buyButtonForm);
            formData.delete('redirectTo');
            formData.append('isExpressCheckout', '1');

            fetch(buyButtonForm.action, {
                method: buyButtonForm.method,
                body: formData,
            })
                .then(() => {
                    resolve();
                })
                .catch((error) => reject(error));
        });
    }
}
