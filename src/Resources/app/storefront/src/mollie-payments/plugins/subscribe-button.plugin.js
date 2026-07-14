import Plugin from '../plugin';

/**
 * Adds the product to the cart as a subscription without duplicating Shopware's buy form.
 *
 * The subscribe button lives inside the original buy form next to the default "add to cart"
 * button. On submit we inject a single hidden field (the product id) only when the subscribe
 * button was the submitter, so Shopware's own add-to-cart serialization sends it along. The
 * cart-item-add route decorator reads that field and rewrites the product line item into its
 * subscription variant. The field is removed again when the default button is used, so the two
 * buttons stay independent.
 */
export default class MollieSubscribeButtonPlugin extends Plugin {
    static options = {
        productId: '',
    };

    init() {
        this._form = this.el.closest('form');

        if (this._form === null) {
            return;
        }

        this._onSubmit = this._onSubmit.bind(this);

        // capture phase, so the hidden field exists before Shopware's add-to-cart plugin
        // serializes the form on the bubbling submit event.
        this._form.addEventListener('submit', this._onSubmit, true);
    }

    _onSubmit(event) {
        let input = this._form.querySelector(`input[name="${MollieSubscribeButtonPlugin.FIELD_NAME}"]`);

        if (event.submitter !== this.el) {
            if (input !== null) {
                input.remove();
            }
            return;
        }

        if (input === null) {
            input = document.createElement('input');
            input.type = 'hidden';
            input.name = MollieSubscribeButtonPlugin.FIELD_NAME;
            this._form.appendChild(input);
        }

        input.value = this.options.productId;
    }
}

MollieSubscribeButtonPlugin.FIELD_NAME = 'mollieSubscribe';
