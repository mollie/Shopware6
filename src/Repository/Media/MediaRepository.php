<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository\Media;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;

class MediaRepository implements MediaRepositoryInterface
{
    /**
     * @var EntityRepository
     */
    private $mediaRepository;

    /**
     * @param EntityRepository $mediaRepository
     */
    public function __construct($mediaRepository)
    {
        $this->mediaRepository = $mediaRepository;
    }

    /**
     * @param Criteria $criteria
     * @param Context $context
     * @return EntitySearchResult
     */
    public function search(Criteria $criteria, Context $context): EntitySearchResult
    {
        return $this->mediaRepository->search($criteria, $context);
    }
}
