<?php
namespace App\Entity\Enum\Methods;

enum PaymentMethod: string
{
    case CARD = 'card';
    case CASH = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::CARD => 'Carte',
            self::CASH => 'Espèces',
        };
    }
}
