export default class Tags {


    /**
     *
     * @param test
     */
    verifyTest(test) {

        let envTags = Cypress.env('tags');

        // we don't have any tags
        // then leave the test as it is
        if (!envTags) {
            return;
        }

        const tags = envTags.split(" ");

        this.verifyTags(tags, test);
    }

    /**
     *
     * @param tags
     * @param test
     */
    verifyTags(tags, test) {

        const runTest = tags.some((tag) => test.fullTitle().includes(tag));

        // we start with our lowest level
        // then we check our parent suites and groups,
        // if we then get found-tag in an upper level
        // we remove the pending again
        test.pending = !runTest;

        // immediately return
        // if we have a tag, then we don't need
        // to ask the higher levels
        if (!test.pending) {
            return;
        }

        if (test.parent !== undefined) {
            this.verifyTags(tags, test.parent)
        }
    }

}



