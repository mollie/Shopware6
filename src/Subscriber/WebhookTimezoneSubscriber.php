<?php declare(strict_types=1);

namespace Kiener\MolliePayments\Subscriber;

use Kiener\MolliePayments\Service\Router\RoutingDetector;
use Kiener\MolliePayments\Service\TransactionService;
use Kiener\MolliePayments\Struct\Order\OrderAttributes;
use Psr\Log\LoggerInterface;
use Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Framework\Twig\TwigDateRequestListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class WebhookTimezoneSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents()
    {
        return [
            # Route gets matched in a subscriber with priority 32, so we need to have a lower priority than that.
            # But priority needs to be higher than 0 as Shopware's timezone listener will run at that priority.
            KernelEvents::REQUEST => ['fixWebhookTimezone', 31],
        ];
    }

    /**
     * @var TransactionService
     */
    private $transactionService;

    /**
     * @var RoutingDetector
     */
    private $routeDetector;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @param TransactionService $transactionService
     * @param RoutingDetector $routeDetector
     * @param LoggerInterface $logger
     */
    public function __construct(TransactionService $transactionService, RoutingDetector $routeDetector, LoggerInterface $logger)
    {
        $this->transactionService = $transactionService;
        $this->routeDetector = $routeDetector;
        $this->logger = $logger;
    }


    /**
     * @param RequestEvent $event
     * @return void
     */
    public function fixWebhookTimezone(RequestEvent $event): void
    {
        # we only fix the timezone when being called from the
        # Storefront Webhook Route or API Webhook Route (headless).
        if (!$this->routeDetector->isStorefrontWebhookRoute() && !$this->routeDetector->isApiWebhookRoute()) {
            return;
        }

        $request = $event->getRequest();
        $routeParams = $request->get('_route_params');

        $transactionId = $routeParams['swTransactionId'] ?? '';

        if (!Uuid::isValid($transactionId)) {
            $this->logger->warning(sprintf('Webhook Timezone Fixer: TransactionId %s is not valid', $transactionId), [
                'transactionId' => $transactionId,
            ]);
            return;
        }

        $transaction = $this->transactionService->getTransactionById($transactionId);

        if (!$transaction instanceof OrderTransactionEntity) {
            $this->logger->error(sprintf('Transaction for id %s does not exist', $transactionId));
            return;
        }

        $order = $transaction->getOrder();

        if (!$order instanceof OrderEntity) {
            $this->logger->error(sprintf('Could not get order from transaction %s', $transactionId));
            return;
        }

        $orderAttributes = new OrderAttributes($order);

        if (empty($orderAttributes->getTimezone())) {
            return;
        }

        // Set the timezone cookie on the request and let Shopware handle the rest.
        $request->cookies->set(TwigDateRequestListener::TIMEZONE_COOKIE, $orderAttributes->getTimezone());
    }
}
