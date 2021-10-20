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

}
