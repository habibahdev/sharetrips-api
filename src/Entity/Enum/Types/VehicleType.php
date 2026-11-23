<?php
namespace App\Entity\Enum\Types;

/**
 * Représente les différents types de véhicules autorisés
 * à pouvoir effectuer des trajets.
 */
enum VehicleType: string
{
    case CAR = 'car';
    case VAN = 'van';

    public function label(): string
    {
        return match ($this) {
            self::CAR => 'Voiture',
            self::VAN => 'Van',
        };
    }
}
