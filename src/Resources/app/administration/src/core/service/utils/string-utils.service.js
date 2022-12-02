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

        if (text === undefined || text === null) {
            return '';
        }

        return text.split(search).join(replaceWith);
    }

}
