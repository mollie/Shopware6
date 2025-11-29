<?php
declare(strict_types=1);

namespace Mollie\Shopware\Unit\StatsUpdate;

use Kiener\MolliePayments\Controller\Storefront\Webhook\NotificationFacade;
use Mollie\Shopware\Component\StatusUpdate\UpdateStatusAction;
use Mollie\Shopware\Repository\OrderTransactionRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class UpdateStatusActionTest extends TestCase
{
    public function testNothingToUpdate(): void
    {
        $fakeRepository = $this->createMock(OrderTransactionRepositoryInterface::class);
        $fakeNotification = $this->createMock(NotificationFacade::class);
        $action = new UpdateStatusAction($fakeRepository, $fakeNotification);
        $result = $action->execute();

        $this->assertEquals(0, $result->getUpdated());
    }

    public function testOneTransactionUpdated(): void
    {
        $idResult = new IdSearchResult(1, [
            [
                'data' => [
                    'id' => 'test123'
                ],
                'primaryKey' => 'test123'
            ]
        ], new Criteria(), new Context(new SystemSource()));

        $fakeRepository = $this->createMock(OrderTransactionRepositoryInterface::class);
        $fakeRepository->method('findOpenTransactions')->willReturn($idResult);
        $fakeNotification = $this->createMock(NotificationFacade::class);
        $action = new UpdateStatusAction($fakeRepository, $fakeNotification);

        $result = $action->execute();

        $this->assertEquals(1, $result->getUpdated());
    }
}
