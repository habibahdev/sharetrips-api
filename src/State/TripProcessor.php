<?php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Enum\Status\TripStatus;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class TripProcessor implements ProcessorInterface
{
    private const TRANSITIONS = [
        'published' => [TripStatus::IN_PROGRESS, TripStatus::CANCELLED],
        'full' => [TripStatus::IN_PROGRESS, TripStatus::CANCELLED],
        'in_progress' => [TripStatus::COMPLETED]
    ];

    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $entityManager,
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Trip
    {
        if ($data instanceof Trip) {
            if ($operation instanceof Post) {
                $this->prepareCreation($data);
            } else {
                $this->checkUpdate($data);
            }
        }
        return $this->processor->process($data, $operation, $uriVariables, $context);
    }

    private function prepareCreation(Trip $trip): void
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            throw new \LogicException('Un utilisateur authentifié est requis.');
        }
        $vehicle = $trip->getVehicle();
        if ($vehicle->getOwner()?->getId() !== $user->getId()) {
            throw new AccessDeniedHttpException('Ce véhicule ne vous appartient pas.');
        }
        if (!$vehicle->isActive()) {
            throw new UnprocessableEntityHttpException('Ce véhicule est désactivé.');
        }
        $this->assertSeatsFitVehicle($trip);
        $this->assertStopPositionUnique($trip);

        $trip->setDriver($user);
        $trip->setStatus(TripStatus::PUBLISHED);
    }

    private function checkUpdate(Trip $trip): void
    {
        $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($trip);
        $before = $original['status'] ?? null;
        if (\is_string($before)) {
            $before = TripStatus::from($before);
        }
        if (!$before instanceof TripStatus) {
            throw new \LogicException("Impossible de déterminer le statut d'origine du trajet.");
        }
        if (\in_array($before, [TripStatus::CANCELLED, TripStatus::COMPLETED], true)) {
            throw new UnprocessableEntityHttpException('Ce trajet est clos et ne peut plus être modifié.');
        }
        $after = $trip->getStatus();
        if ($after !== $before && !\in_array($after, self::TRANSITIONS[$before->value] ?? [], true)) {
            throw new UnprocessableEntityHttpException(sprintf('Changement de statut impossible %s -> %s.', $before->value, $after->value));
        }
        if ($trip->getSeatsOffered() < $trip->getSeatsBooked()) {
            throw new UnprocessableEntityHttpException('Des places sont déjà réservées : vous ne pouvez pas en proposer moins.');
        }
        $this->assertSeatsFitVehicle($trip);
    }

    private function assertSeatsFitVehicle(Trip $trip): void
    {
        $capacity = $trip->getVehicle()?->getSeats();
        if (null !== $capacity && $trip->getSeatsOffered() > $capacity) {
            throw new UnprocessableEntityHttpException(sprintf('Ce véhicule ne propose que %d place(s).', $capacity));
        }
    }

    private function assertStopPositionUnique(Trip $trip): void
    {
        $positions = $trip->getStops()->map(static fn ($stop) => $stop->getPosition())->toArray();
        if (\count($positions) !== \count(array_unique($positions))) {
            throw new UnprocessableEntityHttpException('Deux arrêts ne peuvent pas avoir la même position.');
        }
    }
}
