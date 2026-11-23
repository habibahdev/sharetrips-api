<?php
namespace App\Entity\Enum\Status;

/**
 * Représente les différents status que peut prendre un trajet.
 */
enum TripStatus: string
{
    case PUBLISHED = 'published';
    case FULL = 'full';
    case IN_PROGRESS = 'in_progress';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::CANCELLED => 'Annulé',
            self::COMPLETED => 'Terminé',
            self::FULL => 'Complet',
            self::IN_PROGRESS => 'En cours',
            self::PUBLISHED => 'Publié'
        };
    }
}
