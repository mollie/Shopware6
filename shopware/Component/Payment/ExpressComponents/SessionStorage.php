<?php
declare(strict_types=1);

namespace Mollie\Shopware\Component\Payment\ExpressComponents;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Keeps the created Mollie session per product inside the PHP session, so a repeated
 * render of the same product detail page reuses the session instead of creating a new one.
 *
 * A session is scoped to the logged in customer, because a session cannot be edited
 * afterwards: a guest session carries requiredCustomerDetails, a customer session carries
 * the addresses. Logging in therefore has to fall back to creating a new session, which
 * happens automatically because the lookup key no longer matches. The abandoned session
 * is left to expire on Mollie's side.
 */
final class SessionStorage implements SessionStorageInterface
{
    private const SESSION_KEY = 'mollie_express_components_sessions';

    public function __construct(
        #[Autowire(service: 'request_stack')]
        private RequestStack $requestStack
    ) {
    }

    public function get(string $productId, ?string $customerId): ?string
    {
        $sessionId = $this->all()[$this->buildKey($productId, $customerId)] ?? null;
        if (! is_string($sessionId) || $sessionId === '') {
            return null;
        }

        return $sessionId;
    }

    public function set(string $productId, ?string $customerId, string $sessionId): void
    {
        $sessions = $this->all();
        $sessions[$this->buildKey($productId, $customerId)] = $sessionId;
        $this->save($sessions);
    }

    public function remove(string $productId, ?string $customerId): void
    {
        $sessions = $this->all();
        unset($sessions[$this->buildKey($productId, $customerId)]);
        $this->save($sessions);
    }

    private function buildKey(string $productId, ?string $customerId): string
    {
        return ($customerId ?? 'guest') . '_' . $productId;
    }

    /**
     * @return array<string, string>
     */
    private function all(): array
    {
        $session = $this->getSession();
        if (! $session instanceof SessionInterface) {
            return [];
        }

        $sessions = $session->get(self::SESSION_KEY, []);

        return is_array($sessions) ? $sessions : [];
    }

    /**
     * @param array<string, string> $sessions
     */
    private function save(array $sessions): void
    {
        $session = $this->getSession();
        if (! $session instanceof SessionInterface) {
            return;
        }

        $session->set(self::SESSION_KEY, $sessions);
    }

    /**
     * Requests without a PHP session (e.g. store-api) must not blow up here.
     */
    private function getSession(): ?SessionInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null || ! $request->hasSession()) {
            return null;
        }

        return $request->getSession();
    }
}
