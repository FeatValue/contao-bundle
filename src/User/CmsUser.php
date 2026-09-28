<?php

declare(strict_types=1);

namespace Featvalue\ContaoBundle\User;

use Contao\BackendUser;
use Contao\FrontendUser;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Contao separates back end users (tl_user) and members (tl_member). Their IDs
 * collide, so cms_user_id carries the prefix be: or fe:.
 */
final class CmsUser
{
    public function __construct(private readonly Security $security)
    {
    }

    /**
     * @return array{cms_user_id: string, email: string, name: string}|null
     */
    public function current(): array|null
    {
        $user = $this->security->getUser();
        if ($user instanceof BackendUser) {
            return $this->fromBackend($user);
        }
        if ($user instanceof FrontendUser) {
            return $this->fromFrontend($user);
        }

        return null;
    }

    /**
     * @return array{cms_user_id: string, email: string, name: string}|null
     */
    public function fromBackend(BackendUser $user): array|null
    {
        $email = (string) $user->email;
        if ((int) $user->id < 1 || $email === '') {
            return null;
        }

        return [
            'cms_user_id' => 'be:'.(int) $user->id,
            'email' => $email,
            'name' => (string) ($user->name ?: $user->username),
        ];
    }

    /**
     * @return array{cms_user_id: string, email: string, name: string}|null
     */
    public function fromFrontend(FrontendUser $user): array|null
    {
        $email = (string) $user->email;
        if ((int) $user->id < 1 || $email === '') {
            return null;
        }
        $name = trim((string) $user->firstname.' '.(string) $user->lastname);

        return [
            'cms_user_id' => 'fe:'.(int) $user->id,
            'email' => $email,
            'name' => $name !== '' ? $name : (string) ($user->username ?: $email),
        ];
    }
}
