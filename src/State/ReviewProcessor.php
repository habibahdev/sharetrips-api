<?php
namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Enum\Status\BookingStatus;
use App\Entity\Review;
use App\Entity\User;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final readonly class ReviewProcessor implements ProcessorInterface
{
    public function __construct(
        private ProcessorInterface $processor,
        private Security $security,
        private EntityManagerInterface $entityManager,
        private NotificationService $notifications
    ) {}

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Review
    {
        $user = $this->security->getUser();
        if (!$data instanceof Review || !$user instanceof User) {
            throw new \LogicException('Un avis et un utilisateur authentifié sont requis.');
        }
        return $this->entityManager->wrapInTransaction(
            function () use ($data, $operation, $user, $uriVariables, $context): Review {
                $isCreation = $operation instanceof Post;
                if ($isCreation) {
                    $this->prepareCreation($data, $user);
                }
                $review = $this->processor->process($data, $operation, $context);
                $this->refreshRating($review->getReviewedUser());
                if ($isCreation) {
                    $this->notifications->reviewCreated($review);
                }
                $this->entityManager->flush();
                return $review;
            }
        );
    }

    private function prepareCreation(Review $review, User $user): void
    {
        $booking = $review->getBooking();
        if (BookingStatus::COMPLETED !== $booking->getStatus()) {
            throw new UnprocessableEntityHttpException('Seule une réservation terminée peut être évaluée.');
        }
        $passenger = $booking->getPassenger();
        $driver = $booking->getTrip()->getDriver();
        if ($user->getId() === $passenger->getId()) {
            $reviewed = $driver;
        } elseif ($user->getId() === $driver->getId()) {
            $reviewed = $passenger;
        } else {
            throw new AccessDeniedHttpException("Vous n'êtes pas concerné par cette réservation.");
        }
        $alreadyReviewed = (int) $this->entityManager->createQuery(
            'select count(r.id)
            from ' . Review::class . ' r
            where r.booking = :booking
            and r.author = :author'
        )
            ->setParameter('booking', $booking)
            ->setParameter('author', $user)
            ->getSingleScalarResult();
        if ($alreadyReviewed > 0) {
            throw new UnprocessableEntityHttpException('Vous avez déjà évalué cette réservation.');
        }
        $review->setAuthor($user);
        $review->setReviewedUser($reviewed);
    }

    private function refreshRating(User $user): void
    {
        $row = $this->entityManager->createQuery(
            'select avg(r.rating) as average, count(r.id) as total
            from ' . Review::class . ' r
            where r.reviewedUser = :user'
        )
            ->setParameter('user', $user)
            ->getSingleResult();

        $count = (int) $row['total'];
        $user->setRatingCount($count);
        $user->setRatingAverage($count > 0 ? number_format((float) $row['average'], 2, '.', '') : null);
    }
}
