import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';


export default class PayPalExpressPlugin extends Plugin {

    init() {

        console.log("Paypal express initialized");
        // Shopware 6.4 has product-detail-quantity-select, shopware 6.5 product-detail-quantity-input
        const shopwareQuantityInput = document.querySelector('#productDetailPageBuyProductForm  *[class*="product-detail-quantity"]');
        if(shopwareQuantityInput === null){

            console.log('shopware quantity input not found')
            return;
        }
        const paypalExpressQuantityInput = document.querySelector('#molliePayPalExpressProductDetailForm input[name="quantity"]');
        if(paypalExpressQuantityInput === null){
            console.log("paypal express quantity not found");
            return;
        }

        shopwareQuantityInput.addEventListener('change',function (){
            console.log("value changed "+this.value);
            paypalExpressQuantityInput.value = this.value;
        });

    }

}