export default class ArrayUtilsService {
    addUniqueItem(array: any[], item: any, key: string): number | undefined {
        const identifier = item[key];

        // skip if we already have this item
        if (array.some((existingItem) => existingItem[key] === identifier)) {
            return 2;
        }

        array.push(item);
    }

    removeItem(array: any[], item: any, key: string): void {
        const index = array.findIndex((existingItem) => existingItem[key] === item[key]);

        if (index !== -1) {
            array.splice(index, 1);
        }
    }
}
