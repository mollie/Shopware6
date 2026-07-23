<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Mollie;

final class PaymentHydrator
{
    /**
     * @param array<mixed> $body
     */
    public function hydrate(array $body): Payment
    {
        $payment = new Payment($body['id']);

        $this->hydrateScalars($payment, $body);
        $this->hydrateAmounts($payment, $body);
        $this->hydrateVoucherAmount($payment, $body);
        $this->hydrateRoundingDiff($payment, $body);
        $this->hydratePaymentDetails($payment, $body);
        $this->hydrateRefunds($payment, $body);
        $this->hydrateStatus($payment, $body);

        return $payment;
    }

    /**
     * @param array<mixed> $body
     */
    private function hydrateScalars(Payment $payment, array $body): void
    {
        $paymentMethod = PaymentMethod::tryFrom($body['method'] ?? '');
        if ($paymentMethod !== null) {
            $payment->setMethod($paymentMethod);
        }

        $thirdPartyPaymentId = $body['details']['paypalReference'] ?? null;
        if ($thirdPartyPaymentId !== null) {
            $payment->setThirdPartyPaymentId($thirdPartyPaymentId);
        }

        $checkoutUrl = $body['_links']['checkout']['href'] ?? null;
        if ($checkoutUrl !== null) {
            $payment->setCheckoutUrl($checkoutUrl);
        }

        $changePaymentStateUrl = $body['_links']['changePaymentState']['href'] ?? null;
        if ($changePaymentStateUrl !== null) {
            $payment->setChangePaymentStateUrl($changePaymentStateUrl);
        }

        $customerId = $body['customerId'] ?? null;
        if ($customerId !== null) {
            $payment->setCustomerId($customerId);
        }

        $mandateId = $body['mandateId'] ?? null;
        if ($mandateId !== null) {
            $payment->setMandateId($mandateId);
        }

        $profileId = $body['profileId'] ?? null;
        if ($profileId !== null) {
            $payment->setProfileId($profileId);
        }

        $subscriptionId = $body['subscriptionId'] ?? null;
        if ($subscriptionId !== null) {
            $payment->setSubscriptionId($subscriptionId);
        }

        $createdAt = $body['createdAt'] ?? null;
        if ($createdAt !== null) {
            $createdAtDate = \DateTime::createFromFormat(\DateTimeInterface::ATOM, $createdAt);
            if ($createdAtDate instanceof \DateTimeInterface) {
                $payment->setCreatedAt($createdAtDate);
            }
        }

        $payment->setCancelable((bool) ($body['isCancelable'] ?? false));
    }

    /**
     * @param array<mixed> $body
     */
    private function hydrateAmounts(Payment $payment, array $body): void
    {
        $amount = $body['amount'] ?? null;
        $fallbackCurrency = (string) ($amount['currency'] ?? '');

        if (isset($body['amountCaptured'])) {
            $payment->setCapturedAmount(Money::fromArray($body['amountCaptured']));
        }
        if (isset($body['amountRemaining'])) {
            $payment->setAmountRemaining(Money::fromArray($body['amountRemaining']));
        }
        if ($amount !== null) {
            $payment->setAmount(Money::fromArray($amount));
        }
        if (isset($body['amountChargedBack'])) {
            $payment->setAmountChargedBack(Money::fromArray($body['amountChargedBack']));
        }

        $payment->setAmountRefunded(Money::fromArray($body['amountRefunded'] ?? [], $fallbackCurrency));
    }

    /**
     * @param array<mixed> $body
     */
    private function hydrateVoucherAmount(Payment $payment, array $body): void
    {
        $voucherAmount = 0.0;
        foreach ($body['details']['vouchers'] ?? [] as $voucher) {
            $voucherAmount += (float) ($voucher['amount']['value'] ?? 0.0);
        }

        $payment->setVoucherAmount($voucherAmount);
    }

    /**
     * @param array<mixed> $body
     */
    private function hydrateRoundingDiff(Payment $payment, array $body): void
    {
        $roundingDiff = 0.0;
        foreach ($body['lines'] ?? [] as $line) {
            $metadata = $line['metadata'] ?? [];
            if (is_string($metadata)) {
                $metadata = json_decode($metadata, true) ?: [];
            }
            $isRoundingLine = ($line['sku'] ?? '') === RoundingDifferenceFixer::SKU
                || ($metadata['type'] ?? '') === RoundingDifferenceFixer::METADATA_TYPE;
            if ($isRoundingLine) {
                $roundingDiff += (float) ($line['totalAmount']['value'] ?? 0.0);
            }
        }

        $payment->setRoundingDiff($roundingDiff);
    }

    /**
     * @param array<mixed> $body
     */
    private function hydratePaymentDetails(Payment $payment, array $body): void
    {
        $cardLabel = $body['details']['cardLabel'] ?? null;
        if ($cardLabel !== null) {
            $payment->setCreditCardLabel((string) $cardLabel);
            $payment->setCreditCardNumber((string) ($body['details']['cardNumber'] ?? ''));
            $payment->setCreditCardHolder((string) ($body['details']['cardHolder'] ?? ''));
        }

        $paypalPayerId = $body['details']['paypalPayerId'] ?? null;
        if ($paypalPayerId !== null) {
            $payment->setPaypalPayerId((string) $paypalPayerId);
        }

        $bankAccount = $body['details']['bankAccount'] ?? null;
        if ($bankAccount !== null) {
            $payment->setBankName((string) ($body['details']['bankName'] ?? ''));
            $payment->setBankAccount((string) $bankAccount);
            $payment->setBankBic((string) ($body['details']['bankBic'] ?? ''));
            $payment->setTransferReference((string) ($body['details']['transferReference'] ?? ''));
            $payment->setConsumerName((string) ($body['details']['consumerName'] ?? ''));
            $payment->setConsumerAccount((string) ($body['details']['consumerAccount'] ?? ''));
            $payment->setConsumerBic((string) ($body['details']['consumerBic'] ?? ''));
        }
    }

    /**
     * @param array<mixed> $body
     */
    private function hydrateRefunds(Payment $payment, array $body): void
    {
        foreach ($body['_embedded']['refunds'] ?? [] as $refundData) {
            $refund = Refund::createFromClientResponse($refundData);
            $payment->getRefunds()->add($refund);
        }
    }

    /**
     * The Mollie Payments API has no dedicated chargeback/refund status, it keeps "paid" and
     * only exposes the corresponding amounts. Derive the implicit status here, after all
     * amounts are populated, so the rest of the application relies on a single status value.
     *
     * @param array<mixed> $body
     */
    private function hydrateStatus(Payment $payment, array $body): void
    {
        $status = PaymentStatus::from($body['status']);
        if ($payment->isPartiallyRefunded()) {
            $status = PaymentStatus::PARTIALLY_REFUNDED;
        }
        if ($payment->isFullyRefunded()) {
            $status = PaymentStatus::REFUNDED;
        }
        if ($payment->hasChargeback()) {
            $status = PaymentStatus::CHARGEBACK;
        }
        $payment->setStatus($status);
    }
}
