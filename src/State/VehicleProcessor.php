<?php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Entity\Vehicle;
use Symfony\Bundle\SecurityBundle\Security;

final readonly class VehicleProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Vehicle
    {
        if ($operation instanceof Post && $data instanceof Vehicle) {
            $user = $this->security->getUser();
            if (!$user instanceof User) {
                throw new \LogicException('Un utilisateur authentifié est requis');
            }
            $data->setOwner($user);
        }
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }
}
