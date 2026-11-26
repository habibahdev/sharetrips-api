<?php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Booking;
use App\Entity\Enum\Status\BookingStatus;
use App\Entity\Enum\Status\TripStatus;
use App\Entity\Trip;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class BookingProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private NotificationService $notificationService
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Booking
    {
        $user = $this->security->getUser();
        if (!$data instanceof Booking || !$user instanceof User) {
            throw new \LogicException('Une réservation et un utilisateur authentifié sont requis.');
        }
        return $this->entityManager->wrapInTransaction(
            function () use ($data, $operation, $user, $uriVariables, $context): Booking {
                $trip =$data->getTrip();
                // verrou sur le trajet pour éviter le surbooking en cas de requêtes concurrentes
                $this->entityManager->lock($trip, LockMode::PESSIMISTIC_WRITE);
                $cancelled = false;
                if ($operation instanceof Post) {
                    $this->prepareCreattion($data, $trip, $user);
                } else {
                    $cancelled = $this->prepareUpdate($data, $trip, $user);
                }
                $booking = $this->processor->process($data, $operation, $uriVariables, $context);
                if ($operation instanceof Post) {
                    $this->notifications->bookingCreated($booking);
                } elseif ($cancelled) {
                    $this->notifications->bookingCancelled($booking);
                }
                $this->entityManager->flush();
                return $booking;
            }
        );
    }

    private function prepareCreation(Booking $booking, Trip $trip, User $user): void
    {
        if ($trip->getDriver()?->getId() === $user->getId()) {
            throw new UnprocessableEntityHttpException('Vous ne pouvez pas réserver votre propre trajet.');
        }
        if (TripStatus::FULL === $trip->getStatus()) {
            throw new UnprocessableEntityHttpException('Ce trajet est complet.');
        }
        if (TripStatus::PUBLISHED !== $trip->getStatus() || $trip->getDepartureAt() <= new \DateTimeImmutable()) {
            throw new UnprocessableEntityHttpException("Ce trajet n'accepte plus de réservations.");
        }
        $alreadyBooked = (int) $this->entityManager->createQuery(
            'select count(b.id) from '. Booking::class . ' b
            where b.trip = :trip and b.passenger = :passenger and b.status = :status'
        )
            ->setParameter('trip', $trip)
            ->setParameter('passenger', $user)
            ->setParameter('status', BookingStatus::CONFIRMED->value)
            ->getSingleScalarResult();
        if ($alreadyBooked > 0) {
            throw new UnprocessableEntityHttpException('Vous avez déjà une réservation sur ce trajet');
        }

        $remaining = (int) $trip->getSeatsOffered() - $this->countSeatsTaken($trip);
        if ($booking->getSeatsBooked() > $remaining) {
            throw new UnprocessableEntityHttpException(sprintf('Il ne reste que %d place(s) sur ce trajet.', max(0, $remaining)));
        }
        $booking->setPassenger($user);
        $booking->setPricePerSear((string) $trip->getPricePerSeat());
        $booking->setStatus(BookingStatus::CONFIRMED);
        if ($booking->getSeatsBooked() === $remaining) {
            $trip->setStatus(TripStatus::FULL);
        }
    }

    private function prepareUpdate(Booking $booking, Trip $trip, User $user): bool
    {
        $original = $this->entityManager->getUnitOfWork()->getOriginalEntityData($booking);
        $before = $original['status'] ?? null;
        if (\is_string($before)) {
            $before = BookingStatus::from($before);
        }
        if (!$before instanceof BookingStatus) {
            throw new \LogicException("Impossible de déterminer le statut d'origine de la réservation.");
        }
        $after = $booking->getStatus();
        if ($after === $before) {
            return false;
        }
        if (BookingStatus::CONFIRMED !== $before) {
            throw new UnprocessableEntityHttpException('Cette réservation est clôturée et ne peut plus changer de statut.');
        }
        $isAdmin = $this->security->isGranted('ROLE_ADMIN');
        $isPassenger = $booking->getPassenger()?->getId() === $user->getId();
        $isDriver = $trip->getDriver()?->getId() === $user->getId();
        if (BookingStatus::CANCELLED === $after) {
            if (!$isPassenger && !$isAdmin) {
                throw new AccessDeniedHttpException('Seul le passager peut annuler sa réservation.');
            }
            if (!\in_array($trip->getStatus(), [TripStatus::PUBLISHED, TripStatus::FULL], true) || $trip->getDepartureAt() <= new \DateTimeImmutable()) {
                throw new UnprocessableEntityHttpException('Le trajet est déjà parti ou clos : annulation impossible.');
            }
            if (TripStatus::FULL === $trip->getStatus()) {
                $trip->setStatus(TripStatus::PUBLISHED);
            }
            return true;
        }
        if (\in_array($after, [BookingStatus::COMPLETED, BookingStatus::NO_SHOW], true)) {
            if (!$isDriver && !$isAdmin) {
                throw new AccessDeniedHttpException('Seul le conducteur peut clôturer une réservation.');
            }
            if (!\in_array($trip->getStatus(), [TripStatus::IN_PROGRESS, TripStatus::COMPLETED], true)) {
                throw new UnprocessableEntityHttpException('Le trajet doit être en cours ou terminé.');
            }
            return false;
        }
        throw new UnprocessableEntityHttpException(sprintf('Changement de statut impossible : %s -> %s.', $before->value, $after->value));
    }

    private function countSeatsTaken(Trip $trip): int
    {
        return (int) $this->entityManager->createQuery(
            'select coalesce(sum(b.seatsBooked), 0)
            from ' . Booking::class . 'b
            where b.trip = :trip and b.status in (:statuses)'
        )
            ->setParameter('trip', $trip)
            ->setParameter('statuses', [BookingStatus::CONFIRMED->value, BookingStatus::COMPLETED->value])
            ->getSingleScalarResult();
    }
}
