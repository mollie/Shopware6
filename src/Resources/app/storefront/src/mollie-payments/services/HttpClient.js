const DEFAULT_CONTENT_TYPE = 'application/json';

export default class HttpClient {

    /**
     * Constructor.
     */
    constructor() {
        this.request = null;
    }

    /**
     * Request GET
     * @param {string} url
     * @param {function} callbackSuccess
     * @param {function} callbackError
     * @param {string} contentType
     * @returns {XMLHttpRequest}
     */
    get(url, callbackSuccess, callbackError, contentType = DEFAULT_CONTENT_TYPE) {
        const xhr = new XMLHttpRequest();
        xhr.open('GET', url);
        xhr.setRequestHeader('Content-Type', contentType);

        xhr.onload = function (response) {
            callbackSuccess(response);
        };

        xhr.onerror = function (response) {
            callbackError(response);
        };

        xhr.send();
    }

}