<?php
namespace App\Service;

use App\Entity\Booking;
use App\Entity\Enum\Status\BookingStatus;
use App\Entity\Enum\Types\NotificationType;
use App\Entity\Notification;
use App\Entity\Review;
use App\Entity\Trip;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

final readonly class NotificationService
{
    public function __construct(private EntityManagerInterface $entityManager) {}

    public function bookingCreated(Booking $booking): void
    {
        $trip = $booking->getTrip();
        $passenger = $booking->getPassenger();
        $this->notify($passenger, NotificationType::BOOKING_CONFIRMED, 'Réservation confirmée', sprintf('Votre réservation de %d place(s) pour le trajet %s est confirmée.', $booking->getSeatsBooked(), $this->route($trip)), 'booking', $booking->getId());
        $this->notify($trip->getDriver(), NotificationType::BOOKING_CONFIRMED, 'Nouvelle réservation', sprintf('%s a réservé %d place(s) sur votre trajet %s.', $passenger->getFirstName(), $booking->getSeatsBooked(), $this->route($trip)), 'booking', $booking->getId());
    }

    public function bookingCancelled(Booking $booking): void
    {
        $trip = $booking->getTrip();
        $this->notify($trip->getDriver(), NotificationType::BOOKING_CANCELLED, 'Réservation annulée', sprintf('%s a annulé sa réservation de %d place(s) sur votre trajet %s.', $booking->getPassenger()->getFirstName(), $booking->getSeatsBooked(), $this->route($trip)), 'booking', $booking->getId());
    }

    public function tripCancelled(Trip $trip, array $passengers): void
    {
        foreach ($passengers as $passenger) {
            $this->notify($passenger, NotificationType::TRIP_CANCELLED, 'Trajet annulé', sprintf('Le trajet %s a été annulé par le conducteur.', $this->route($trip)), 'trip', $trip->getId());
        }
    }

    public function tripUpdated(Trip $trip): void
    {
        foreach ($trip->getBookings() as $booking) {
            if (BookingStatus::CONFIRMED !== $booking->getStatus()) {
                continue;
            }
            $this->notify($booking->getPassenger(), NotificationType::TRIP_UPDATED, 'Trajet modifié', sprintf('Le conducteur a modifié le trajet %s : consultez les nouveaux détails.', $this->route($trip)), 'trip', $trip->getId());
        }
    }

    public function reviewCreated(Review $review): void
    {
        $this->notify($review->getReviewedUser(), NotificationType::NEW_REVIEW, 'Nouvel avis', sprintf('%s vous a attribué %d/5.', $review->getAuthor()->getFirstName(), $review->getRating()), 'review', $review->getId());
    }

    private function notify(User $recipient, NotificationType $type, string $title, string $message, ?string $relatedEntityType = null, ?int $relatedId = null): void
    {
        $notification = new Notification()
            ->setRecipient($recipient)
            ->setType($type)
            ->setTitle($title)
            ->setMessage($message)
            ->setRelatedEntityType($relatedEntityType)
            ->setRelatedEntityId($relatedId);
        $this->entityManager->persist($notification);
    }

    private function route(Trip $trip): string
    {
        return sprintf('%s -> %s', $trip->getDeparture(), $trip->getDestination());
    }
}
