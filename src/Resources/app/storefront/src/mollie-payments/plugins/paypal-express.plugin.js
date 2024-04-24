import Plugin from '@shopware-storefront-sdk/plugin-system/plugin.class';


export default class PayPalExpressPlugin extends Plugin {

    init() {

        // Shopware 6.4 has product-detail-quantity-select, shopware 6.5 product-detail-quantity-input
        let shopwareQuantityInput = document.querySelector('#productDetailPageBuyProductForm  *[class*="quantity"]:not(div)');
        if(shopwareQuantityInput === null){
            return;
        }


        const paypalExpressQuantityInput = document.querySelector('#molliePayPalExpressProductDetailForm input[name="quantity"]');
        if(paypalExpressQuantityInput === null){
            return;
        }

        shopwareQuantityInput.addEventListener('change',function (){

            paypalExpressQuantityInput.value = this.value;
        });

    }

}