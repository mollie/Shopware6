import Shopware from "Services/shopware/Shopware";
import MainMenuRepository from "Repositories/admin/MainMenuRepository";
import ProductListRepository from "Repositories/admin/products/ProductListRepository";
import ProductDetailRepository from "Repositories/admin/products/ProductDetailRepository";

const repoMainMenu = new MainMenuRepository();
const repoProductsList = new ProductListRepository();
const repoProductDetail = new ProductDetailRepository();


export default class AdminProductsAction {

    /**
     *
     */
    openProducts() {

        cy.visit('/admin#/sw/product/index');

        // wait for page to appear
        cy.contains('h2', 'Products', {timeout: 20000});
    }

    /**
     *
     */
    openFirstProduct() {
        repoProductsList.getFirstProductTitle().click();
        cy.wait(1000);
    }

    /**
     *
     */
    openMollieTab() {
        repoProductDetail.getMollieTab().click({force: true});
    }

    /**
     *
     */
    saveProductDetail() {
        repoProductDetail.getSaveButton();
        cy.wait(1000);
    }

}
