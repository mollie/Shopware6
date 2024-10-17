import PDPRepository from 'Repositories/storefront/products/PDPRepository';
import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();
const repo = new PDPRepository();

export default class PDPAction {

    /**
     *
     * @param quantity
     */
    addToCart(quantity) {

        this.setQuantity(quantity);
        repo.getAddToCartButton().click();
    }

    setQuantity(quantity){
        if (shopware.isVersionGreaterEqual('6.5')) {

            const repetitions = quantity - 1; // its already 1 initially

            for (let i = 0; i < repetitions; i++) {
                repo.getQuantityBtnUp().click();
            }

        } else {
            repo.getQuantityDropdown().select(quantity + "");
        }
    }
}
