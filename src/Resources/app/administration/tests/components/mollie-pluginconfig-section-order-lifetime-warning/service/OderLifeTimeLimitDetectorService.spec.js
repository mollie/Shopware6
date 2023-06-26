import OderLifeTimeLimitDetectorService from './../../../../../administration/src/module/mollie-payments/components/mollie-pluginconfig-section-order-lifetime-warning/services/OderLifeTimeLimitDetectorService';

const service = new OderLifeTimeLimitDetectorService();

test('No warnings are shown', () => {
    service.checkValue(0);
    expect(service.isKlarnaOrderLifeTimeReached()).toBe(false);
    expect(service.isOderLifeTimeLimitReached()).toBe(false);
});

test('Klarna Limit reached', () => {
    service.checkValue(29);
    expect(service.isKlarnaOrderLifeTimeReached()).toBe(true);
    expect(service.isOderLifeTimeLimitReached()).toBe(false);
});

test('Order Limit reached', () => {
    service.checkValue(101);
    expect(service.isKlarnaOrderLifeTimeReached()).toBe(false);
    expect(service.isOderLifeTimeLimitReached()).toBe(true);
});