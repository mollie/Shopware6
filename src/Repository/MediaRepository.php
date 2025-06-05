<?php
declare(strict_types=1);

namespace Kiener\MolliePayments\Repository;

use Shopware\Core\Content\Media\MediaEntity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;

/**
 * @TODO: remove after dropping 6.4 this is only required because in 6.4 a decoredated class is provided
 */
class MediaRepository
{
    /**
     * @var EntityRepository<EntityCollection<MediaEntity>>
     */
    private $repository;

    /**
     * @param EntityRepository<EntityCollection<MediaEntity>> $repository
     */
    public function __construct($repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return EntityRepository<EntityCollection<MediaEntity>>
     */
    public function getRepository()
    {
        return $this->repository;
    }
}
