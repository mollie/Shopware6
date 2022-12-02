import StringUtils from "../../../src/core/service/utils/string-utils.service";

const stringUtils = new StringUtils();


describe('isNullOrEmpty', () => {
    test('Blank String is empty', () => {
        const isEmpty = stringUtils.isNullOrEmpty('');
        expect(isEmpty).toBe(true);
    });

    test('NULL is empty', () => {
        const isEmpty = stringUtils.isNullOrEmpty(null);
        expect(isEmpty).toBe(true);
    });

    test('Undefined is empty', () => {
        const isEmpty = stringUtils.isNullOrEmpty(undefined);
        expect(isEmpty).toBe(true);
    });

    test('String is not empty', () => {
        const isEmpty = stringUtils.isNullOrEmpty('abc');
        expect(isEmpty).toBe(false);
    });

    test('Space is not empty', () => {
        const isEmpty = stringUtils.isNullOrEmpty(' ');
        expect(isEmpty).toBe(false);
    });
})


describe('replace', () => {
    test('Replace string', () => {
        const text = stringUtils.replace('{ordernumber}', '1000', 'my {ordernumber} value');
        expect(text).toBe('my 1000 value');
    });
})