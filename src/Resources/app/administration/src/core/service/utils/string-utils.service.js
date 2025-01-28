export default class StringUtils {

    /**
     *
     * @param value
     * @returns {boolean}
     */
    isNullOrEmpty(value) {

        if (value === undefined) {
            return true;
        }

        if (value === null) {
            return true;
        }

        if (value === '') {
            return true;
        }

        return false;
    }

    /**
     *
     * @param search
     * @param replaceWith
     * @param text
     * @returns {*}
     */
    replace(search, replaceWith, text) {

        if (!text) {
            return '';
        }
        const regex = new RegExp(search,'g');
        return text.replace(regex, replaceWith);
    }

}
