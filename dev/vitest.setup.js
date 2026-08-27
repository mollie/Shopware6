import {beforeEach, expect} from 'vitest'
import * as allure from 'allure-js-commons'

// The report groups by layer and suite. Without both labels a spec falls back to its file
// path, and every branch of the tree then starts with the same src > Resources > app prefix
// instead of the bundle the spec belongs to.
//
// This file lives in dev/ so that its import of allure-js-commons resolves out of
// dev/node_modules, the same way config/vitest.config.ts resolves the reporter.
beforeEach(async () => {
    await allure.layer('Unit (JS)')

    // src/Resources/app/<bundle>/... - administration or storefront
    const bundle = (expect.getState().testPath ?? '').split('/src/Resources/app/')[1]?.split('/')[0]

    if (bundle !== undefined) {
        await allure.suite(bundle)
    }
})
