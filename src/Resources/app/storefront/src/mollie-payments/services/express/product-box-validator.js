import BuyBoxRepository from '../../repository/buy-box-repository';

const DISABLED_ATTR = 'disabled';

/**
 * Validates whether express payment checkout is possible for a product box
 * by checking the state of associated buy buttons and forms
 */
export default class ProductBoxValidator {
    constructor() {
        this._repoBuyBox = new BuyBoxRepository();
    }

    isCheckoutPossible(expressPayButton) {
        // check our closest shopware buy button
        // and verify if buying is allowed for our product
        const closestShopwareBuyButton = this._repoBuyBox.findClosestShopwareBuyButton(expressPayButton);

        if (!(closestShopwareBuyButton instanceof HTMLButtonElement)) {
            return true;
        }

        if (closestShopwareBuyButton.hasAttribute(DISABLED_ATTR)) {
            return false;
        }

        return true;
    }
}
