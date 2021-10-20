import StringUtils from "../../../src/core/service/utils/string-utils.service";

const stringUtils = new StringUtils();


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
