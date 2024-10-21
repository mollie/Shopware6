export default class BuyElementRepository {
    find(button) {
        let buyElementContainer = button.closest('.product-action');

        if (buyElementContainer === null) {
            buyElementContainer = button.closest('.product-detail-form-container');
        }
        if(buyElementContainer === null){
            buyElementContainer = button.closest('.offcanvas-cart-actions');
        }

        if(buyElementContainer === null){
            buyElementContainer = button.closest('.checkout-aside-container');
        }
        if(buyElementContainer === null){
            buyElementContainer = button.closest('.checkout-main');
        }

        return buyElementContainer;
    }
}