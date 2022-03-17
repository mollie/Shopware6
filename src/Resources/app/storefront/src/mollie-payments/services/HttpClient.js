const DEFAULT_CONTENT_TYPE = 'application/json';

export default class HttpClient {
    /**
     * Request GET
     * @param {string} url
     * @param {function} callbackSuccess
     * @param {function} callbackError
     * @param {string} contentType
     */
    get(url, callbackSuccess = null, callbackError = null, contentType = DEFAULT_CONTENT_TYPE) {
        this.send('GET', url, null, callbackSuccess, callbackError, contentType);
    }

    /**
     * Request POST
     * @param {string} url
     * @param {*} data
     * @param {function} callbackSuccess
     * @param {function} callbackError
     * @param {string} contentType
     */
    post(url, data = null, callbackSuccess = null, callbackError = null, contentType = DEFAULT_CONTENT_TYPE) {
        this.send('POST', url, data, callbackSuccess, callbackError, contentType);
    }

    /**
     * Sends an XMLHttpRequest
     * @param {string} type
     * @param {string} url
     * @param {*} data
     * @param {function} callbackSuccess
     * @param {function} callbackError
     * @param {string} contentType
     */
    send(type, url, data = null, callbackSuccess = null, callbackError = null, contentType = DEFAULT_CONTENT_TYPE)
    {
        const xhr = new XMLHttpRequest();
        xhr.open(type, url);
        xhr.setRequestHeader('Content-Type', contentType);

        xhr.onload = function () {
            if(!callbackSuccess || typeof callbackSuccess !== 'function') {
                return;
            }

            const responseType = xhr.getResponseHeader('content-type');
            const body = 'response' in xhr ? xhr.response : xhr.responseText

            if(responseType.indexOf('application/json') > -1) {
                callbackSuccess(JSON.parse(body));
            } else {
                callbackSuccess(body);
            }
        };

        xhr.onerror = function () {
            if(!callbackError || typeof callbackSuccess !== 'function') {
                return;
            }

            callbackError();
        };

        xhr.send(data);
    }
}
