export default class StringUtils {
    isNullOrEmpty(value: any): boolean {
        return value === undefined || value === null || value === '';
    }

    replace(search: string, replaceWith: string, text: string): string {
        if (!text) {
            return '';
        }

        const regex = new RegExp(search, 'g');

        return text.replace(regex, replaceWith);
    }
}
