// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'
import axios from "axios";


Cypress.on('uncaught:exception', (err, runnable) => {
    // returning false here prevents Cypress from
    // failing the test because some third party apps
    // cause an error in the console which stops the test
    return false
})

Cypress.on('test:after:run', (data) => {

    const caseId = extractCaseId(data.title);

    if (caseId === '') {
        return;
    }

    let status = 1;

    if (data.state === 'passed') {
        status = 1;
    } else {
        status = 5;
    }

    const config = Cypress.env('testrail');

    if (config.domain === null || config.domain === '') {
        return;
    }

    sendResult(
        config.runId,
        caseId,
        status,
        config.domain,
        config.username,
        config.password
    )
    ;
})

function extractCaseId(title) {
    if (title.includes(':', 0)) {

        if (title.startsWith('C')) {

            const caseId = title.substring(1, title.indexOf(":"));
            return caseId;
        }
    }

    return '';
}

function sendResult(runID, caseId, status, domain, username, password) {

    const postData = {
        "results": [
            {
                "case_id": caseId,
                "status_id": status,
                "comment": 'Tested by Cypress'
            }
        ]
    };

    axios(
        {
            method: 'post',
            url: `https://${domain}/index.php?/api/v2/add_results_for_cases/${runID}`,
            headers: {'Content-Type': 'application/json'},
            auth: {
                username: username,
                password: password,
            },
            data: JSON.stringify(postData),
        })
        .then(response => {
            console.log("Sent TestRail result for TestCase " + caseId);
        })
        .catch(error => {
            console.error(error)
        });
}