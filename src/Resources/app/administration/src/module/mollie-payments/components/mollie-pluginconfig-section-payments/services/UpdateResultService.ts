export interface UpdateResponse {
    success?: boolean;
    message?: string;
}

/**
 * Interprets the result of a payment method update.
 * Kept free of any Shopware/Vue dependency so it can be unit tested; the
 * translated label is passed in by the component.
 */
export default class UpdateResultService {
    isSuccess(response: UpdateResponse): boolean {
        return response?.success === true;
    }

    /**
     * Builds the error notification message: the translated failure label
     * followed by the exception details from the response.
     */
    buildErrorMessage(response: UpdateResponse, failedLabel: string): string {
        return `${failedLabel}\n\nException:\n${response?.message}`;
    }
}
