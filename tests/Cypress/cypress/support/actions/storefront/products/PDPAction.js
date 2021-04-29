import PDPRepository from 'Repositories/storefront/products/PDPRepository';

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
