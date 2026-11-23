<?php
namespace App\Entity\Enum\Types;

enum NotificationType: string
{
    case BOOKING_CONFIRMED = 'Booking Confirmed';
    case BOOKING_CANCELLED = 'Booking Cancelled';
    case PAYMENT_CONFIRMED = 'Payment Confirmed';
    case TRIP_UPDATED = 'Trip Updated';
    case TRIP_CANCELLED = 'Trip Cancelled';
    case NEW_MESSAGE = 'New Message';
    case NEW_REVIEW = 'New Review';

    public function label(): string
    {
        return match ($this) {
            self::BOOKING_CONFIRMED => 'Réservation confirmée',
            self::BOOKING_CANCELLED => 'Réservation annulée',
            self::PAYMENT_CONFIRMED => 'Paiement confirmé',
            self::TRIP_UPDATED => 'Trajet modifié',
            self::TRIP_CANCELLED => 'Trajet annulé',
            self::NEW_MESSAGE => 'Nouveau message',
            self::NEW_REVIEW => 'Nouvel avis',
        };
    }
}
