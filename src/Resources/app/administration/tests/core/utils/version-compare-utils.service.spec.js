const VersionCompare = require('../../../src/core/service/utils/version-compare.utils').default;

test('Equals works', () => {
    let result;
    result = VersionCompare.equals('6.4.14.0', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.equals('6.4.14.0', '6.4.14');
    expect(result).toBe(true);

    result = VersionCompare.equals('6.4.14.0', '6.4.13.0');
    expect(result).toBe(false);

    result = VersionCompare.equals('6.4.14.0', '6.4.13');
    expect(result).toBe(false);
});

test('Not Equals works', () => {
    let result;
    result = VersionCompare.notEquals('6.4.14.0', '6.4.13.0');
    expect(result).toBe(true);

    result = VersionCompare.notEquals('6.4.14.0', '6.4.13');
    expect(result).toBe(true);

    result = VersionCompare.notEquals('6.4.14.0', '6.4.14.0');
    expect(result).toBe(false);

    result = VersionCompare.notEquals('6.4.14.0', '6.4.14');
    expect(result).toBe(false);
});
