<?php
namespace App\Security;

use App\Entity\Enum\Status\UserStatus;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof User) {
            return;
        }
        match ($user->getStatus()) {
            UserStatus::BANNED => throw new CustomUserMessageAccountStatusException('Ce compte est banni'),
            UserStatus::SUSPENDED => throw new CustomUserMessageAccountStatusException('Ce compte est suspendu'),
            UserStatus::DELETED => throw new CustomUserMessageAccountStatusException('Ce compte est supprimer'),
            default => null
        };
    }

    public function checkPostAuth(UserInterface $user, TokenInterface|null $token = null): void
    {}
}
