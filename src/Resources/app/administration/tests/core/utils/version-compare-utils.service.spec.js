import { expect, test } from 'vitest'
import versionCompare from '../../../src/core/service/utils/version-compare.utils';
const versionCompare = new versionCompare();
test('Equals works', () => {
    let result;
   
    result = versionCompare.equals('6.4.14.0', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.equals('6.4.14.0', '6.4.14');
    expect(result).toBe(true);

    result = versionCompare.equals('6.4.14.0', '6.4.13.0');
    expect(result).toBe(false);

    result = versionCompare.equals('6.4.14.0', '6.4.13');
    expect(result).toBe(false);
});

test('Not Equals works', () => {
    let result;
    result = versionCompare.notEquals('6.4.14.0', '6.4.13.0');
    expect(result).toBe(true);

    result = versionCompare.notEquals('6.4.14.0', '6.4.13');
    expect(result).toBe(true);

    result = versionCompare.notEquals('6.4.14.0', '6.4.14.0');
    expect(result).toBe(false);

    result = versionCompare.notEquals('6.4.14.0', '6.4.14');
    expect(result).toBe(false);
});

test('Greater Than works', () => {
    let result;
    result = versionCompare.greater('6.4.14.0', '6.4.13.0');
    expect(result).toBe(true);

    result = versionCompare.greater('6.4.14.0', '6.4.13');
    expect(result).toBe(true);

    result = versionCompare.greater('6.4.14.0', '6.4.14.0');
    expect(result).toBe(false);

    result = versionCompare.greater('6.4.14.0', '6.4.14');
    expect(result).toBe(false);

    result = versionCompare.greater('6.4.14.1', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.greater('6.4.14.1', '6.4.14');
    expect(result).toBe(true);

    result = versionCompare.greater('6.4.14.0', '6.4.2.0');
    expect(result).toBe(true);

    result = versionCompare.greater('6.4.14.0', '6.4.2');
    expect(result).toBe(true);
});

test('Greater Or Equals works', () => {
    let result;
    result = versionCompare.greaterOrEqual('6.4.14.0', '6.4.13.0');
    expect(result).toBe(true);

    result = versionCompare.greaterOrEqual('6.4.14.0', '6.4.13');
    expect(result).toBe(true);

    result = versionCompare.greaterOrEqual('6.4.14.0', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.greaterOrEqual('6.4.14.0', '6.4.14');
    expect(result).toBe(true);

    result = versionCompare.greaterOrEqual('6.4.14.0', '6.4.2.0');
    expect(result).toBe(true);

    result = versionCompare.greaterOrEqual('6.4.14.0', '6.4.2');
    expect(result).toBe(true);
});

test('Lesser Than works', () => {
    let result;
    result = versionCompare.lesser('6.4.13.0', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.lesser('6.4.13.0', '6.4.14');
    expect(result).toBe(true);

    result = versionCompare.lesser('6.4.14.0', '6.4.14.0');
    expect(result).toBe(false);

    result = versionCompare.lesser('6.4.14.0', '6.4.14');
    expect(result).toBe(false);

    result = versionCompare.lesser('6.4.13.999', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.lesser('6.4.13.999', '6.4.14');
    expect(result).toBe(true);

    result = versionCompare.lesser('6.4.2.0', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.lesser('6.4.2', '6.4.14.0');
    expect(result).toBe(true);
});

test('Lesser Or Equals works', () => {
    let result;
    result = versionCompare.lesserOrEqual('6.4.13.0', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.lesserOrEqual('6.4.13.0', '6.4.14');
    expect(result).toBe(true);

    result = versionCompare.lesserOrEqual('6.4.14.0', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.lesserOrEqual('6.4.14.0', '6.4.14');
    expect(result).toBe(true);

    result = versionCompare.lesserOrEqual('6.4.13.999', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.lesserOrEqual('6.4.13.999', '6.4.14');
    expect(result).toBe(true);

    result = versionCompare.lesserOrEqual('6.4.2.0', '6.4.14.0');
    expect(result).toBe(true);

    result = versionCompare.lesserOrEqual('6.4.2', '6.4.14.0');
    expect(result).toBe(true);
});
