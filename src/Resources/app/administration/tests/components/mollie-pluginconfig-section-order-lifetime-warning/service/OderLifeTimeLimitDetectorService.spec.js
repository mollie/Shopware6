import { expect, test } from 'vitest'
import OderLifeTimeLimitDetectorService from './../../../../../administration/src/module/mollie-payments/components/mollie-pluginconfig-section-order-lifetime-warning/services/OderLifeTimeLimitDetectorService';

const service = new OderLifeTimeLimitDetectorService();

test('No warnings are shown', () => {
    const oderLifeTime = 0;
    expect(service.isKlarnaOrderLifeTimeReached(oderLifeTime)).toBe(false);
    expect(service.isOderLifeTimeLimitReached(oderLifeTime)).toBe(false);
});

test('Klarna Limit reached', () => {
    const oderLifeTime = 29;
    expect(service.isKlarnaOrderLifeTimeReached(oderLifeTime)).toBe(true);
    expect(service.isOderLifeTimeLimitReached(oderLifeTime)).toBe(false);
});

test('Order Limit reached', () => {
    const oderLifeTime = 101;
    expect(service.isKlarnaOrderLifeTimeReached(oderLifeTime)).toBe(false);
    expect(service.isOderLifeTimeLimitReached(oderLifeTime)).toBe(true);
});