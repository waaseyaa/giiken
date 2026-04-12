<?php

declare(strict_types=1);

namespace App\Http\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;

final class WebLogoutController
{
    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function logout(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_regenerate_id(true);
            session_destroy();
        }

        return new RedirectResponse('/', 302);
    }
}
