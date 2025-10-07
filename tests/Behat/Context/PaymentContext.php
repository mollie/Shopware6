<?php

namespace Mollie\Behat;
use Behat\Behat\Context\Context;
use Behat\Behat\Tester\Exception\PendingException;


final class PaymentContext implements Context
{
    /**
     * @Given user :arg1 exists
     */
    public function userExists($arg1): void
    {
        throw new PendingException();
    }

    /**
     * @Given iam loggedin as user :arg1
     */
    public function iamLoggedinAsUser($arg1): void
    {
        throw new PendingException();
    }

    /**
     * @Given payment method :arg1 exists and active
     */
    public function paymentMethodExistsAndActive($arg1): void
    {
        throw new PendingException();
    }

    /**
     * @Given product :arg1 with quantity :arg2 is in cart
     */
    public function productWithQuantityIsInCart($arg1, $arg2): void
    {
        throw new PendingException();
    }

    /**
     * @When i start checkout with payment method :arg1
     */
    public function iStartCheckoutWithPaymentMethod($arg1): void
    {
        throw new PendingException();
    }

    /**
     * @When select payment status :arg1
     */
    public function selectPaymentStatus($arg1): void
    {
        throw new PendingException();
    }

    /**
     * @Then i see success page
     */
    public function iSeeSuccessPage(): void
    {
        throw new PendingException();
    }
}