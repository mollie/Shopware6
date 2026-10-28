# Support

Recurring merchant questions and where to look first.

## "The payment method is not visible in the checkout"

A recurring support question with several possible causes. Verify them in this order before
looking at code:

1. Is the payment method active in the shop at all?
2. Is it assigned to the sales channel?
3. Is **"Use Mollie's availability rules for payment methods"** (`useMolliePaymentMethodLimits`)
   enabled in the plugin configuration?

The plugin hides a method **only** when that setting is on
(`Component/Payment/MethodRemover/AvailabilityPaymentMethodRemover`). If it is off and the method
is still missing, the cause is in Shopware, not in this plugin.

Why the feature exists: a method must additionally be activated in the **Mollie dashboard**, and
that is not immediate — the merchant clicks activate and then waits for the approval, which is
easily overlooked; only an actually ticked checkbox counts. Mollie also restricts methods by
currency, by billing country and by a minimum and a maximum order amount. A merchant who assigns
iDEAL to the sales channel without having it approved at Mollie sends the customer into a
checkout that must fail. The availability rules prevent exactly that: they ask Mollie which
methods are usable for this cart amount, currency and billing country, and remove the rest.

So "why is method X missing although the rules are switched on" is answered at the Mollie
dashboard, at the amount limits or at the billing country — rarely in the plugin.

## Apple Pay Direct: it is almost always the `.well-known` file

The most frequent cause of "Apple Pay Direct does not work" is the domain verification file.
It is downloaded automatically as soon as Apple Pay Direct is enabled in the plugin
configuration, and `bin/console mollie:applepay:download-verification` fetches it again.

The file lands in the shop's `public/.well-known/` folder — and in setups with a CDN or a
split document root it is not reachable from the outside from there. So the check is always
the same: call
`https://<shop-domain>/.well-known/apple-developer-merchantid-domain-association` in a browser.
Only if that file is served does Apple Pay Direct work. A merchant reporting the feature as
broken should be asked for that URL before anything in the code is investigated.

Release-folder deployments (Deployer and similar) do not share `public/.well-known/`, so the file
is gone after every release; the session request therefore downloads it again when it is missing.
When it is missing, Shopware handles the path itself and another plugin may answer it: SwagPayPal
serves its own Apple Pay file there. Apple accepts only one file per domain, so Mollie and PayPal
cannot both offer Apple Pay on the same domain. Mollie then answers the session request with 422
"Apple is not able to verify your domain".
