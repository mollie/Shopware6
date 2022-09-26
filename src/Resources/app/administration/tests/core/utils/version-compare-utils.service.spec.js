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

test('Greater Than works', () => {
    let result;
    result = VersionCompare.greater('6.4.14.0', '6.4.13.0');
    expect(result).toBe(true);

    result = VersionCompare.greater('6.4.14.0', '6.4.13');
    expect(result).toBe(true);

    result = VersionCompare.greater('6.4.14.0', '6.4.14.0');
    expect(result).toBe(false);

    result = VersionCompare.greater('6.4.14.0', '6.4.14');
    expect(result).toBe(false);

    result = VersionCompare.greater('6.4.14.1', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.greater('6.4.14.1', '6.4.14');
    expect(result).toBe(true);

    result = VersionCompare.greater('6.4.14.0', '6.4.2.0');
    expect(result).toBe(true);

    result = VersionCompare.greater('6.4.14.0', '6.4.2');
    expect(result).toBe(true);
});

test('Greater Or Equals works', () => {
    let result;
    result = VersionCompare.greaterOrEqual('6.4.14.0', '6.4.13.0');
    expect(result).toBe(true);

    result = VersionCompare.greaterOrEqual('6.4.14.0', '6.4.13');
    expect(result).toBe(true);

    result = VersionCompare.greaterOrEqual('6.4.14.0', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.greaterOrEqual('6.4.14.0', '6.4.14');
    expect(result).toBe(true);

    result = VersionCompare.greaterOrEqual('6.4.14.0', '6.4.2.0');
    expect(result).toBe(true);

    result = VersionCompare.greaterOrEqual('6.4.14.0', '6.4.2');
    expect(result).toBe(true);
});

test('Lesser Than works', () => {
    let result;
    result = VersionCompare.lesser('6.4.13.0', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.lesser('6.4.13.0', '6.4.14');
    expect(result).toBe(true);

    result = VersionCompare.lesser('6.4.14.0', '6.4.14.0');
    expect(result).toBe(false);

    result = VersionCompare.lesser('6.4.14.0', '6.4.14');
    expect(result).toBe(false);

    result = VersionCompare.lesser('6.4.13.999', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.lesser('6.4.13.999', '6.4.14');
    expect(result).toBe(true);

    result = VersionCompare.lesser('6.4.2.0', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.lesser('6.4.2', '6.4.14.0');
    expect(result).toBe(true);
});

test('Lesser Or Equals works', () => {
    let result;
    result = VersionCompare.lesserOrEqual('6.4.13.0', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.lesserOrEqual('6.4.13.0', '6.4.14');
    expect(result).toBe(true);

    result = VersionCompare.lesserOrEqual('6.4.14.0', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.lesserOrEqual('6.4.14.0', '6.4.14');
    expect(result).toBe(true);

    result = VersionCompare.lesserOrEqual('6.4.13.999', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.lesserOrEqual('6.4.13.999', '6.4.14');
    expect(result).toBe(true);

    result = VersionCompare.lesserOrEqual('6.4.2.0', '6.4.14.0');
    expect(result).toBe(true);

    result = VersionCompare.lesserOrEqual('6.4.2', '6.4.14.0');
    expect(result).toBe(true);
});
