import { describe, expect, it } from 'vitest';
import PrivacyNotesService from './privacy-notes.service';

/**
 * The data protection checkbox lives in the `.mollie-privacy-note` sibling of the express
 * button container, never inside it. These fakes mirror that structure so the lookup path
 * through the surrounding buy box is covered without a DOM implementation.
 */
function createBuyBox({ hasPrivacyNote = true, hasCheckbox = true, checked = false } = {}) {
    const checkbox = hasCheckbox ? { checked } : null;

    const privacyNote = hasPrivacyNote
        ? {
              querySelector: (selector) => (selector === 'input[name="acceptedDataProtection"]' ? checkbox : null),
          }
        : null;

    const buyBox = {
        querySelector: (selector) => (selector === '.mollie-privacy-note' ? privacyNote : null),
    };

    return {
        expressButton: {
            closest: (selector) => (selector === '.product-action' ? buyBox : null),
        },
    };
}

describe('PrivacyNotesService.getAcceptedDataProtection', () => {
    it('returns 1 for a checked checkbox in the surrounding buy box', () => {
        const service = new PrivacyNotesService({});
        const { expressButton } = createBuyBox({ checked: true });

        expect(service.getAcceptedDataProtection(expressButton)).toBe(1);
    });

    it('returns 0 for an unchecked checkbox instead of its value attribute', () => {
        const service = new PrivacyNotesService({});
        const { expressButton } = createBuyBox({ checked: false });

        expect(service.getAcceptedDataProtection(expressButton)).toBe(0);
    });

    it('returns 0 when the shop does not render a privacy note', () => {
        const service = new PrivacyNotesService({});
        const { expressButton } = createBuyBox({ hasPrivacyNote: false });

        expect(service.getAcceptedDataProtection(expressButton)).toBe(0);
    });

    it('returns 0 when the privacy note holds no checkbox', () => {
        const service = new PrivacyNotesService({});
        const { expressButton } = createBuyBox({ hasCheckbox: false });

        expect(service.getAcceptedDataProtection(expressButton)).toBe(0);
    });

    it('returns 0 when the express button has no buy box at all', () => {
        const service = new PrivacyNotesService({});

        expect(service.getAcceptedDataProtection({ closest: () => null })).toBe(0);
    });

    it('uses the checkbox of the clicked buy box on listings with multiple products', () => {
        const service = new PrivacyNotesService({});
        const accepted = createBuyBox({ checked: true });
        const declined = createBuyBox({ checked: false });

        expect(service.getAcceptedDataProtection(accepted.expressButton)).toBe(1);
        expect(service.getAcceptedDataProtection(declined.expressButton)).toBe(0);
    });
});
