<?php
namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\Enum\Status\TripStatus;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class TripExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security) {}

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, ?Operation $operation = null, array $context = []): void
    {
        if (Trip::class !== $resourceClass || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }
        $user = $this->security->getUser();
        $alias = $queryBuilder->getRootAliases()[0];
        if (!$user instanceof User) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }
        $queryBuilder
            ->andWhere(sprintf('(%1$s.status IN (:visible_statuses) OR %1$s.driver = :current_driver)', $alias))
            ->setParameter('visible_statuses', [TripStatus::PUBLISHED->value, TripStatus::FULL->value])
            ->setParameter('current_driver', $user);
    }
}
