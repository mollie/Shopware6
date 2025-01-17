export default class BuyElementRepository {
    find(target) {
        let buyElementContainer = target.closest('.product-action');

        if (buyElementContainer === null) {
            buyElementContainer = target.closest('.product-detail-form-container');
        }
        if(buyElementContainer === null){
            buyElementContainer = target.closest('.offcanvas-cart-actions');
        }

        if(buyElementContainer === null){
            buyElementContainer = target.closest('.checkout-aside-container');
        }
        if(buyElementContainer === null){
            buyElementContainer = target.closest('.checkout-main');
        }

        return buyElementContainer;
    }
}