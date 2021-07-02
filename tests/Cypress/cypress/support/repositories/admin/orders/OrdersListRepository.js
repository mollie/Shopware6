export default class OrdersListRepository {

    /**
     *
     * @returns {string}
     */
    getLatestOrderStatusLabelSelector() {
        return '.sw-data-grid__row--0 > .sw-data-grid__cell--stateMachineState-name > .sw-data-grid__cell-content > .sw-label';
    }

    /**
     *
     * @returns {string}
     */
    getLatestPaymentStatusLabelSelector() {
        return ".sw-data-grid__row--0 > .sw-data-grid__cell--transactions-last\\(\\)-stateMachineState-name > .sw-data-grid__cell-content > .sw-label";
    }

}
