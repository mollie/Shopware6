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
        // TEMPORARY DIAGNOSTIC: send a client-known token on login to see whether the
        // login response echoes our request token or returns a rotated (bound) token.
        const sentToken = 'abcdefghijklmnopqrstuvwxyz012345';
        this.contextToken = sentToken;

        const response = await this.submitLogin(email, password);
        const context = await this.get('/context');
        throw new Error('LOGIN DIAGNOSTIC ' + JSON.stringify({
            sentToken,
            loginStatus: response.status,
            loginRespToken: response.headers.get('sw-context-token'),
            capturedToken: this.contextToken,
            contextRespToken: context.data?.token ?? null,
            customer: context.data?.customer ? 'PRESENT' : null,
        }));
    }

    async submitLogin(email, password) {
        const response = await this.post('/account/login', { email, password });

        // Only trust the token on a successful login. On a failed login Shopware's
        // ResponseHeaderListener echoes the anonymous request context token back in the
        // sw-context-token header. Capturing that would pass the "token not null" check
        // but resolve to a customer-less context, causing 403s on _loginRequired routes.
        if (response.status < 200 || response.status >= 300) {
            return response;
        }

        const token = response.headers.get('sw-context-token') ?? response.data?.contextToken;
        if (token) {
            this.setContextToken(token);
        }
        return response;
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
