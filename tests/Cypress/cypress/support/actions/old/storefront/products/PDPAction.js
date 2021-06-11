import PDPRepository from 'Repositories/old/storefront/products/PDPRepository';

export default class PDPAction {


    /**
     *
     * @param quantity
     */
    addToCart(quantity) {

        const repo = new PDPRepository();

        repo.getQuantity().select(quantity + "");

        repo.getAddToCartButton().click();
    }

}
