import {beforeEach, expect} from 'vitest'
import * as allure from 'allure-js-commons'

// The report groups by layer and feature. The layer is set on the reporter in
// config/vitest.config.ts; the feature is per spec, so it has to be set here. Without it a
// spec falls back to its file path, and every branch of the tree then starts with the same
// src > Resources > app prefix instead of the bundle the spec belongs to.
//
// This file lives in dev/ so that its import of allure-js-commons resolves out of
// dev/node_modules, the same way config/vitest.config.ts resolves the reporter.
beforeEach(async () => {
    // src/Resources/app/<bundle>/... - administration or storefront. Marked as JS because
    // these sit in the same Unit layer as the PHPUnit tests.
    const bundle = (expect.getState().testPath ?? '').split('/src/Resources/app/')[1]?.split('/')[0]

    if (bundle !== undefined) {
        await allure.feature(`${bundle} (JS)`)
    }
})
