"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.toUpperCamelCase = exports.toLowerCamelCase = exports.lcFirst = exports.ucFirst = void 0;
function ucFirst(value) {
    return value.charAt(0).toUpperCase() + value.slice(1);
}
exports.ucFirst = ucFirst;
function lcFirst(value) {
    return value.charAt(0).toLowerCase() + value.slice(1);
}
exports.lcFirst = lcFirst;
function toLowerCamelCase(value, separator) {
    var upperCamelCase = toUpperCamelCase(value, separator);
    return lcFirst(upperCamelCase);
}
exports.toLowerCamelCase = toLowerCamelCase;
function toUpperCamelCase(value, separator) {
    if (!separator) {
        return ucFirst(value.toLowerCase());
    }
    var stringParts = value.split(separator);
    return stringParts.map(function (string) { return ucFirst(string.toLowerCase()); }).join('');
}
exports.toUpperCamelCase = toUpperCamelCase;
