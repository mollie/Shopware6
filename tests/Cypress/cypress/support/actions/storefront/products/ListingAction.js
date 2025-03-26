import ListingRepository from 'Repositories/storefront/products/ListingRepository';

export default class ListingAction {

    /**
     *
     */
    clickOnFirstProduct() {

        const repo = new ListingRepository();

        repo.getFirstProduct().click({force:true});
    }

    /**
     *
     * @param n
     */
    clickOnNthProduct(n) {

        const repo = new ListingRepository();

        repo.getNthProduct(n).click({force:true});
    }

}
