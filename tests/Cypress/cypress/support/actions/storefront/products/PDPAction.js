import PDPRepository from 'Repositories/storefront/products/PDPRepository';

class PDPAction {

    /**
     *
     */
    addToCart() {

        const repo = new PDPRepository();

        repo.getAddToCartButton().click();
    }

}

export default PDPAction;
