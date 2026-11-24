<?php
namespace App\Doctrine\Extension;

use ApiPlatform\Doctrine\Orm\Extension\QueryCollectionExtensionInterface;
use ApiPlatform\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Metadata\Operation;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\ORM\QueryBuilder;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class VehicleExtension implements QueryCollectionExtensionInterface
{
    public function __construct(private Security $security) {}

    public function applyToCollection(
        QueryBuilder $queryBuilder,
        QueryNameGeneratorInterface $queryNameGenerator,
        string $resourceClass,
        ?Operation $operation = null,
        array $context = []): void
    {
        if (Vehicle::class !== $resourceClass || $this->security->isGranted('ROLE_ADMIN')) {
            return;
        }
        $user = $this->security->getUser();
        $alias = $queryBuilder->getRootAliases()[0];
        if (!$user instanceof User) {
            $queryBuilder->andWhere('1 = 0');
            return;
        }
        $queryBuilder
            ->andWhere(sprintf('%s.owner = :current_user', $alias))
            ->setParameter('current_user', $user);
    }
}
