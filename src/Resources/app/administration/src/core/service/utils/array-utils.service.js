export default class ArrayUtilsService {

    /**
     *
     * @param array
     * @param item
     * @param key
     */
    addUniqueItem(array, item, key) {

        const identifier = item[key];

        // check if we already have this item
        for (let i = 0; i < array.length; i++) {
            const existingItem = array[i];
            if (existingItem[key] === identifier) {
                return 2;
            }
        }

        array.push(item);
    }

    /**
     *
     * @param array
     * @param item
     * @param key
     */
    removeItem(array, item, key) {
        for (let i = 0; i < array.length; i++) {
            if (array[i][key] === item[key]) {
                array.splice(i, 1);
                return;
            }
        }
    }

}