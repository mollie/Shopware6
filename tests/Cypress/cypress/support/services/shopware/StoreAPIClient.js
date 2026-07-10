export default class StoreAPIClient {

    constructor(salesChannelApiKey) {
        this.salesChannelApiKey = salesChannelApiKey;
        this.contextToken = null;
        this.baseURL = `${Cypress.config('baseUrl')}/store-api`;
    }

    setContextToken(token) {
        this.contextToken = token;
    }

    clearContextToken() {
        this.contextToken = null;
    }

    async login(email, password) {
        // TEMPORARY DIAGNOSTIC: is the http cache really off now, and does the login
        // token differ per login? Same token twice => cache still on.
        this.contextToken = null;
        const r1 = await this.post('/account/login', { email, password });
        const token1 = r1.headers.get('sw-context-token');

        this.contextToken = null;
        const r2 = await this.post('/account/login', { email, password });
        const token2 = r2.headers.get('sw-context-token');

        this.contextToken = token2;
        const context = await this.get('/context');

        throw new Error('LOGIN DIAGNOSTIC ' + JSON.stringify({
            loginStatus: r1.status,
            swCache: r1.headers.get('x-symfony-cache'),
            token1,
            token2,
            tokensDiffer: token1 !== token2,
            contextCustomer: context.data?.customer ? 'PRESENT' : null,
        }));
    }

    async get(url, params = {}) {
        return this.request('GET', url, null, params);
    }

    async post(url, data = null, params = {}) {
        return this.request('POST', url, data, params);
    }

    async put(url, data = null, params = {}) {
        return this.request('PUT', url, data, params);
    }

    async patch(url, data = null, params = {}) {
        return this.request('PATCH', url, data, params);
    }

    async delete(url, params = {}) {
        return this.request('DELETE', url, null, params);
    }

    async request(method, url, data = null, params = {}) {
        const queryString = Object.keys(params).length
            ? '?' + new URLSearchParams(params).toString()
            : '';

        const headers = {
            Accept: 'application/vnd.api+json',
            'sw-access-key': this.salesChannelApiKey,
            'Content-Type': 'application/json',
        };

        if (this.contextToken) {
            headers['sw-context-token'] = this.contextToken;
        }

        const options = { method, headers };
        if (data !== null) {
            options.body = JSON.stringify(data);
        }

        const res = await fetch(`${this.baseURL}${url}${queryString}`, options);
        const body = await res.json().catch(() => null);

        return { status: res.status, data: body, headers: res.headers };
    }

}
