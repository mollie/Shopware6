<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\Payment\Fake;

use Mollie\Shopware\Component\Mollie\CreateSession;
use Mollie\Shopware\Component\Mollie\Gateway\SessionGatewayInterface;
use Mollie\Shopware\Component\Mollie\Session;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

final class FakeSessionGateway implements SessionGatewayInterface
{
    /** @var list<CreateSession> */
    private array $createSessionPayloads = [];
    private ?Session $existingSession = null;
    private ?\Throwable $getSessionException = null;

    public function __construct(private Session $session)
    {
    }

    /**
     * The session getSession() answers with, when it differs from the one a create returns - a
     * stored session is loaded again before it is reused.
     */
    public function setExistingSession(Session $existingSession): void
    {
        $this->existingSession = $existingSession;
    }

    public function failGetSessionWith(\Throwable $exception): void
    {
        $this->getSessionException = $exception;
    }

    public function getCreateSessionCount(): int
    {
        return count($this->createSessionPayloads);
    }

    public function getLastCreateSession(): CreateSession
    {
        if ($this->createSessionPayloads === []) {
            throw new \RuntimeException('FakeSessionGateway has no create session payload recorded.');
        }

        return $this->createSessionPayloads[array_key_last($this->createSessionPayloads)];
    }

    public function createSession(CreateSession $createSession, SalesChannelContext $salesChannelContext): Session
    {
        $this->createSessionPayloads[] = $createSession;

        return $this->session;
    }

    public function createPaypalExpressSession(Cart $cart, SalesChannelContext $salesChannelContext): Session
    {
        return $this->session;
    }

    public function getSession(string $sessionId, SalesChannelContext $salesChannelContext): Session
    {
        if ($this->getSessionException instanceof \Throwable) {
            throw $this->getSessionException;
        }

        return $this->existingSession ?? $this->session;
    }

    public function loadSession(string $sessionId, SalesChannelContext $salesChannelContext): Session
    {
        return $this->session;
    }

    public function cancelSession(string $sessionId, SalesChannelContext $salesChannelContext): Session
    {
        return $this->session;
    }
}
