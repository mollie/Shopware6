import SubscriptionService from '../../../../src/core/service/subscription/subscription.service';

const service = new SubscriptionService(null);

describe('Status Colors', () => {

    test('active status is success', () => {
        const color = service.getStatusColor('active');
        expect(color).toStrictEqual('success');
    });

    test('resumed status is success', () => {
        const color = service.getStatusColor('resumed');
        expect(color).toStrictEqual('success');
    });

    test('empty status is neutral', () => {
        const color = service.getStatusColor('');
        expect(color).toStrictEqual('neutral');
    });

    test('skipped status is info', () => {
        const color = service.getStatusColor('skipped');
        expect(color).toStrictEqual('info');
    });

    test('pending status is warning', () => {
        const color = service.getStatusColor('pending');
        expect(color).toStrictEqual('warning');
    });

    test('paused status is warning', () => {
        const color = service.getStatusColor('paused');
        expect(color).toStrictEqual('warning');
    });

    test('canceled status is neutral', () => {
        const color = service.getStatusColor('canceled');
        expect(color).toStrictEqual('neutral');
    });

    test('suspended status is neutral', () => {
        const color = service.getStatusColor('suspended');
        expect(color).toStrictEqual('neutral');
    });

    test('completed status is neutral', () => {
        const color = service.getStatusColor('completed');
        expect(color).toStrictEqual('neutral');
    });

    test('unknown status is danger', () => {
        const color = service.getStatusColor('my_abc');
        expect(color).toStrictEqual('danger');
    });

});

describe('Cancellation permissions', () => {

    test('active status allows cancellation', () => {
        const allowed = service.isCancellationAllowed('active');
        expect(allowed).toStrictEqual(true);
    });

    test('canceled status does not allow another cancellation', () => {
        const allowed = service.isCancellationAllowed('canceled');
        expect(allowed).toStrictEqual(false);
    });

})

describe('Skipping permissions', () => {

    test('active status allows skipping', () => {
        const allowed = service.isSkipAllowed('active');
        expect(allowed).toStrictEqual(true);
    });

    test('canceled status does not allow skipping', () => {
        const allowed = service.isSkipAllowed('canceled');
        expect(allowed).toStrictEqual(false);
    });

})

describe('Pausing permissions', () => {

    test('active status allows pausing', () => {
        const allowed = service.isPauseAllowed('active');
        expect(allowed).toStrictEqual(true);
    });

    test('canceled status does not allow pausing', () => {
        const allowed = service.isPauseAllowed('canceled');
        expect(allowed).toStrictEqual(false);
    });

    test('paused status does not allow pausing', () => {
        const allowed = service.isPauseAllowed('paused');
        expect(allowed).toStrictEqual(false);
    });

})

describe('Resuming permissions', () => {

    test('paused status allows resuming', () => {
        const allowed = service.isResumeAllowed('paused');
        expect(allowed).toStrictEqual(true);
    });

    test('canceled status allows resuming', () => {
        const allowed = service.isResumeAllowed('canceled');
        expect(allowed).toStrictEqual(true);
    });

    test('skipped status does not allow resuming', () => {
        const allowed = service.isResumeAllowed('skipped');
        expect(allowed).toStrictEqual(false);
    });

    test('active status does not allow resuming', () => {
        const allowed = service.isResumeAllowed('active');
        expect(allowed).toStrictEqual(false);
    });

})
