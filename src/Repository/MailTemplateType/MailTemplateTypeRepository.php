<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\MailTemplateType;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;

class MailTemplateTypeRepository implements MailTemplateTypeRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $mailTemplateTypeRepository;

    /**
     * @param EntityRepository $mailTemplateTypeRepository
     */
    public function __construct($mailTemplateTypeRepository)
    {
        $this->mailTemplateTypeRepository = $mailTemplateTypeRepository;
    }


    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return IdSearchResult
     */
    public function searchIds(Criteria $criteria, Context $context): IdSearchResult
    {
        return $this->mailTemplateTypeRepository->searchIds($criteria, $context);
    }

    /**
     * @param array<mixed> $data
     * @param Context $context
     * @return EntityWrittenContainerEvent
     */
    public function update(array $data, Context $context): EntityWrittenContainerEvent
    {
        return $this->mailTemplateTypeRepository->update($data, $context);
    }
}
