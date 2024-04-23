import ArrayUtilsService from '../../../src/core/service/utils/array-utils.service';

const utils = new ArrayUtilsService();

test('Struct can be added', () => {

    const array = [];

    const data = {
        id: 1,
        name: 'test',
    };

    utils.addUniqueItem(array, data, 'id');

    expect(array.length).toBe(1);
});


test('Struct cannot be added twice', () => {

    const array = [];

    const data = {
        id: 1,
        name: 'test',
    };

    utils.addUniqueItem(array, data, 'id');
    utils.addUniqueItem(array, data, 'id');

    expect(array.length).toBe(1);
});

test('Struct can be removed again', () => {

    const array = [];

    const data = {
        id: 1,
        name: 'test',
    };

    utils.addUniqueItem(array, data, 'id');
    utils.removeItem(array, data, 'id');

    expect(array.length).toBe(0);
});

test('Remove on empty struct does not throw exception', () => {

    const array = [];

    const data = {
        id: 1,
        name: 'test',
    };

    utils.removeItem(array, data, 'id');

    expect(array.length).toBe(0);
});