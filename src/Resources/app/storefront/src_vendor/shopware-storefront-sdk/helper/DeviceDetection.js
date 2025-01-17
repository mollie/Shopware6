"use strict";
Object.defineProperty(exports, "__esModule", { value: true });
exports.isTouchDevice = void 0;
function isTouchDevice() {
    return ('ontouchstart' in document.documentElement);
}
exports.isTouchDevice = isTouchDevice;
