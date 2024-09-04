<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Service\Tags;

use Closure;
use Kiener\MolliePayments\Components\Subscription\DAL\Subscription\SubscriptionEntity;
use Kiener\MolliePayments\Service\Tags\Exceptions\CouldNotTagOrderException;
use Kiener\MolliePayments\Struct\Tags\SubscriptionTag;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\Tag\TagCollection;
use Shopware\Core\System\Tag\TagEntity;

class OrderTagService
{
    /**
     * @var EntityRepository
     */
    private $orderRepository;

    /**
     * @var EntityRepository
     */
    private $tagRepository;

    public function __construct(
        EntityRepository $orderRepository,
        EntityRepository $tagRepository
    ) {
        $this->orderRepository = $orderRepository;
        $this->tagRepository = $tagRepository;
    }

    /**
     * @throws CouldNotTagOrderException
     */
    public function addTagToSubscriptionOrder(SubscriptionEntity $entity, Context $context): void
    {
        $orderId = $entity->getOrderId();
        $subscriptionTag = SubscriptionTag::create();

        // Fetch the order
        $criteria = new Criteria([$orderId]);
        $criteria->addAssociation('tags');

        /** @var null|OrderEntity $order */
        $order = $this->orderRepository->search($criteria, $context)->get($orderId);

        if (!$order instanceof OrderEntity) {
            throw CouldNotTagOrderException::forSubscription(sprintf('Order with ID "%s" not found', $orderId));
        }

        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('name', $subscriptionTag->getName()));
        /** @var null|TagEntity $tag */
        $tag = $this->tagRepository->search($criteria, $context)->first();

        if (!$tag instanceof TagEntity) {
            throw CouldNotTagOrderException::forSubscription(sprintf('Tag with name "%s" and ID "%s" not found', $subscriptionTag->getName(), $subscriptionTag->getId()));
        }

        $orderTags = $order->getTags();

        if (!$orderTags instanceof TagCollection) {
            throw CouldNotTagOrderException::forSubscription(sprintf('Order with ID "%s" does not provide its tag collection', $entity->getOrderId()));
        }

        $orderTags->add($tag);

        $this->orderRepository->update([
            [
                'id' => $orderId,
                'tags' => array_map(
                    Closure::fromCallable([$this, 'serializeTag']),
                    $orderTags->getElements()
                ),
            ],
        ], $context);
    }

    /**
     * @return array<string, string>
     */
    private function serializeTag(TagEntity $tag): array
    {
        return ['id' => $tag->getId()];
    }
}
