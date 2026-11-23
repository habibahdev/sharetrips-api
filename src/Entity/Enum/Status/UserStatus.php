<?php
namespace App\Entity\Enum\Status;

/**
 * Représente les différents statuts d'un compte utilisateur.
 * Cette énumération permet de contrôler le cycle de vie d'un
 * compte utilisateur et de déterminer les accès associés à
 * chaque statut.
 */
enum UserStatus: string
{
    case ACTIVE = 'active';
    case BANNED = 'banned';

    case SUSPENDED = 'suspended';

    case DELETED = 'deleted';

    case DELETION_PENDING = 'deletion_pending';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Actif',
            self::BANNED => 'Banni',
            self::SUSPENDED => 'Suspendu',
            self::DELETED => 'Supprimé',
            self::DELETION_PENDING => 'Suppression en attente',
        };
    }
}
