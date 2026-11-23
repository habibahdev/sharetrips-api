<?php
namespace App\Entity\Enum\Status;

/**
 * Représente les différents statuts qu'une réservation peut avoir.
 */
enum BookingStatus: string
{
    case CONFIRMED = 'confirmed';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';
    case NO_SHOW = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            self::CONFIRMED => 'Confirmée',
            self::NO_SHOW => 'Absent'
        };
    }
}
