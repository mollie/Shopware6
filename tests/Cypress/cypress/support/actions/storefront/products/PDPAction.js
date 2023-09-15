import PDPRepository from 'Repositories/storefront/products/PDPRepository';
import Shopware from "Services/shopware/Shopware";

const shopware = new Shopware();


export default class PDPAction {


    /**
     *
     * @param quantity
     */
    addToCart(quantity) {

        const repo = new PDPRepository();

        if (shopware.isVersionGreaterEqual('6.5')) {

            const repetitions = quantity - 1; // its already 1 initially

            for (let i = 0; i < repetitions; i++) {
                repo.getQuantityBtnUp().click();
            }

        } else {
            repo.getQuantityDropdown().select(quantity + "");
        }


        repo.getAddToCartButton().click();
    }

}
