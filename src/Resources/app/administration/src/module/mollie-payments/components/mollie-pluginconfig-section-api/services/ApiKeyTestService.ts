export interface ApiKeyTestResult {
    key: string;
    mode: string;
    valid: boolean;
}

export interface ApiKeyMessageLabels {
    apiKey: string;
    isValid: string;
    isInvalid: string;
}

/**
 * Interprets the result of an API key test and builds its notification message.
 * Kept free of any Shopware/Vue/DOM dependency so it can be unit tested; the
 * translated label fragments are passed in by the component.
 */
export default class ApiKeyTestService {
    isValid(result: ApiKeyTestResult): boolean {
        return result.valid === true;
    }

    /**
     * Builds the notification message for a single API key test result, e.g.
     * `API key "live_xxx" (live) is valid.`
     */
    buildResultMessage(result: ApiKeyTestResult, labels: ApiKeyMessageLabels): string {
        const validity = this.isValid(result) ? labels.isValid : labels.isInvalid;

        return `${labels.apiKey} "${result.key}" (${result.mode}) ${validity}.`;
    }
}
