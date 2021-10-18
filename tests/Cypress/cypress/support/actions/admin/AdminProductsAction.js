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
        cy.wait(200);
        repoMainMenu.getCatalogues().click();
        cy.wait(1500);
        repoMainMenu.getProductsOverview().click();
        cy.wait(2000);
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
