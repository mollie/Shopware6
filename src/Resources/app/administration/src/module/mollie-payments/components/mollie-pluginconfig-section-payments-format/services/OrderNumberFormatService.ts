import StringUtils from '../../../../../core/service/utils/string-utils.service';

/**
 * Builds the order number preview by replacing the {ordernumber} and
 * {customernumber} placeholders in the configured format template.
 * Kept free of any Shopware/Vue dependency so it can be unit tested.
 */
export default class OrderNumberFormatService {
    private readonly stringUtils = new StringUtils();

    format(formatTemplate: string, orderNumber: string, customerNumber: string): string {
        let text = this.stringUtils.replace('{ordernumber}', orderNumber, formatTemplate);
        text = this.stringUtils.replace('{customernumber}', customerNumber, text);

        return text;
    }
}
