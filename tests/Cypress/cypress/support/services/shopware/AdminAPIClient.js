import axios from 'axios';

export default class AdminAPIClient {


    /**
     *
     */
    constructor() {
        this.basePath = '';

        this.client = axios.create({
            baseURL: `${Cypress.config('baseUrl')}/api`,
            timeout: 10000,
        });
    }

    /**
     *
     * @param url
     * @param params
     * @returns {*}
     */
    get(url, params = {}) {
        return this.request({
            method: 'get',
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    post(url, data, params = {}) {
        return this.request({
            method: 'post',
            url,
            data,
            params
        });
    }

    /**
     *
     * @param url
     * @param params
     * @returns {*}
     */
    delete(url, params = {}) {
        return this.request({
            method: 'delete',
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param params
     * @returns {*}
     */
    head(url, params = {}) {
        return this.request({
            method: 'head',
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param params
     * @returns {*}
     */
    options(url, params = {}) {
        return this.request({
            method: 'options',
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    put(url, data, params = {}) {
        return this.request({
            method: 'put',
            data,
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param data
     * @param params
     * @returns {*}
     */
    patch(url, data, params = {}) {
        return this.request({
            method: 'patch',
            data,
            url,
            params
        });
    }

    /**
     *
     * @param url
     * @param method
     * @param params
     * @param data
     * @returns {*}
     */
    request({url, method, params, data}) {

        return this.loginByUserName()
            .then((token) => {

                const requestConfig = {
                    headers: {
                        Accept: 'application/vnd.api+json',
                        Authorization: `Bearer ${token}`,
                        'Content-Type': 'application/json'
                    },
                    url,
                    method,
                    timeout: 10000,
                    params,
                    data
                };

                return this.client.request(requestConfig).then((response) => {
                    if (Array.isArray(response.data.data) && response.data.data.length === 1) {
                        return response.data.data[0];
                    }
                    return response.data.data;
                });
            })
            .catch(({response}) => {
                console.log(response);
            });
    }


    /**
     *
     * @param username
     * @param password
     * @returns {*}
     */
    loginByUserName(username = 'admin', password = 'shopware') {

        const me = this;

        return new Promise((resolve, reject) => {
            this._getCachedToken().then((token) => {
                if (token !== undefined && token !== null) {
                    console.log("Use existing Access Token");
                    resolve(token);
                    return;
                }

                const params = {
                    grant_type: 'password',
                    client_id: 'administration',
                    scopes: 'write',
                    username: username,
                    password: password
                };

                this.client
                    .post('/oauth/token', params)
                    .then((response) => {
                        const token = response.data.access_token;
                        var tokenTime = me._getTimestamp();

                        window.localStorage.setItem('cachedAccessToken', token);
                        window.localStorage.setItem('cachedAccessTokenTime', tokenTime);

                        resolve(token);
                    })
                    .catch((err) => {
                        reject(err);
                    });
            })
        });
    }

    /**
     *
     * @returns {*}
     * @private
     */
    _getCachedToken() {
        return new Promise((resolve, reject) => {
            var existingToken = window.localStorage.getItem('cachedAccessToken');
            const existingTokenTime = window.localStorage.getItem('cachedAccessTokenTime');

            // test if the token is already expired
            const currentTimestamp = this._getTimestamp();

            const diffMinutes = this._getTimeDiffMinutes(existingTokenTime, currentTimestamp);

            // the shopware token usually expires in 10 minutes
            if (diffMinutes >= 9) {
                existingToken = null;
            }

            resolve(existingToken);
        });
    }

    /**
     *
     * @returns {string}
     * @private
     */
    _getTimestamp() {
        var now = new Date();

        return now.getFullYear() + '/' + (now.getMonth() + 1) + '/' + now.getDate() + " " + now.getHours() + ":" + now.getMinutes();
    }

    /**
     *
     * @param dateString1
     * @param dateString2
     * @returns {number}
     * @private
     */
    _getTimeDiffMinutes(dateString1, dateString2) {
        const diff = Math.abs(new Date(dateString2) - new Date(dateString1));

        return Math.floor((diff / 1000) / 60);
    }

}
