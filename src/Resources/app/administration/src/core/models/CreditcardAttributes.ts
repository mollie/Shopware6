export default class CreditcardAttributes {
    private readonly _audience: string;
    private readonly _countryCode: string;
    private readonly _feeRegion: string;
    private readonly _holder: string;
    private readonly _label: string;
    private readonly _number: string;
    private readonly _security: string;

    constructor(mollieData: Record<string, any> | null) {
        this._audience = '';
        this._countryCode = '';
        this._feeRegion = '';
        this._holder = '';
        this._label = '';
        this._number = '';
        this._security = '';

        if (mollieData === null) {
            return;
        }

        this._audience = this._convertString(mollieData['creditCardAudience']);
        this._countryCode = this._convertString(mollieData['creditCardCountryCode']);
        this._feeRegion = this._convertString(mollieData['creditCardFeeRegion']);
        this._holder = this._convertString(mollieData['creditCardHolder']);
        this._label = this._convertString(mollieData['creditCardLabel']);
        this._number = this._convertString(mollieData['creditCardNumber']);
        this._security = this._convertString(mollieData['creditCardSecurity']);
    }

    /**
     * Helper method to decide if an object has credit card data.
     */
    hasCreditCardData(): boolean {
        return (
            !!this._audience &&
            !!this._countryCode &&
            !!this._feeRegion &&
            !!this._holder &&
            !!this._label &&
            !!this._number
        );
    }

    getAudience(): string {
        return this._audience;
    }

    getCountryCode(): string {
        return this._countryCode;
    }

    getFeeRegion(): string {
        return this._feeRegion;
    }

    getHolder(): string {
        return this._holder;
    }

    getLabel(): string {
        return this._label;
    }

    getNumber(): string {
        return this._number;
    }

    getSecurity(): string {
        return this._security;
    }

    private _convertString(value: any): string {
        if (value === undefined || value === null) {
            return '';
        }

        return String(value);
    }
}
