const DEFAULT_CONTENT_TYPE = 'application/json';

export default class HttpClientService {
    /**
     * Request GET
     * @param {string} url
     * @param {function} callbackSuccess
     * @param {function} callbackError
     * @param {string} contentType
     */
    get(url, callbackSuccess = null, callbackError = null, contentType = DEFAULT_CONTENT_TYPE) {
        return this.send('GET', url, null, callbackSuccess, callbackError, contentType);
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
        return this.send('POST', url, data, callbackSuccess, callbackError, contentType);
    }

    /**
     * Sends the request. JSON responses are parsed, everything else is passed
     * through as text. A network failure or a malformed JSON body rejects and
     * therefore lands in callbackError.
     * @param {string} type
     * @param {string} url
     * @param {*} data
     * @param {function} callbackSuccess
     * @param {function} callbackError
     * @param {string} contentType
     */
    send(type, url, data = null, callbackSuccess = null, callbackError = null, contentType = DEFAULT_CONTENT_TYPE) {
        return fetch(url, {
            method: type,
            headers: { 'Content-Type': contentType },
            body: data,
        })
            .then((response) =>
                (response.headers.get('content-type') || '').includes('application/json')
                    ? response.json()
                    : response.text(),
            )
            .then((payload) => {
                if (typeof callbackSuccess === 'function') {
                    callbackSuccess(payload);
                }
            })
            .catch((error) => {
                if (typeof callbackError === 'function') {
                    callbackError(error);
                }
            });
    }
}
