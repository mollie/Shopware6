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
        return new Promise((resolve, reject) => {
            this._getCachedToken().then((token) => {
                if (token !== undefined && token !== null) {
                    console.log("Use existing Access Token: " + token);
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
                        window.localStorage.setItem('cachedAccessToken', token);
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
            const value = window.localStorage.getItem('cachedAccessToken');
            resolve(value);
        });
    }

}
