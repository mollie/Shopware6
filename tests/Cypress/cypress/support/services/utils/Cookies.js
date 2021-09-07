export default class Cookies {

    /**
     *
     * @param name
     */
    prepareCrossdomain(name) {

        cy.request(
            {
                url: '/'
            }
        ).then((res) => {

            const cookies = res.requestHeaders.cookie.split(/; */);

            cookies.forEach(cookie => {

                const parts = cookie.split('=');
                const key = parts[0]
                const value = parts[1];

                if (key === name) {
                    cy.setCookie(
                        name,
                        value,
                        {
                            sameSite: 'None',
                            secure: true
                        }
                    );
                }
            });

        });
    }

}