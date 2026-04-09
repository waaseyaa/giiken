<?php

declare(strict_types=1);

namespace Giiken\Http\Controller;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Entity\EntityTypeManager;
use Waaseyaa\User\Http\AuthController;
use Waaseyaa\User\Middleware\CsrfMiddleware;

final class WebLoginController
{
    public function __construct(
        private readonly EntityTypeManager $entityTypeManager,
        private readonly Environment $twig,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function showForm(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        $redirectRaw = $query['redirect'] ?? '';
        $redirect = \is_string($redirectRaw) ? $redirectRaw : '';
        $errorRaw = $query['error'] ?? '';
        $error = \is_string($errorRaw) ? $errorRaw : '';

        $html = $this->twig->render('login.html.twig', [
            'redirect'        => $this->safeRedirectTarget($redirect),
            'error'           => $error,
            'alreadySignedIn' => $account->isAuthenticated(),
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function submit(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        $username = trim((string) $httpRequest->request->get('username', ''));
        $password = (string) $httpRequest->request->get('password', '');
        $redirectRaw = (string) $httpRequest->request->get('redirect', '');
        $safeRedirect = $this->safeRedirectTarget($redirectRaw);

        if ($username === '' || $password === '') {
            return new RedirectResponse(
                '/login?redirect=' . rawurlencode($safeRedirect) . '&error=missing',
                302,
            );
        }

        $storage = $this->entityTypeManager->getStorage('user');
        $user = (new AuthController())->findUserByName($storage, $username);

        if ($user === null || !$user->isActive() || !$user->checkPassword($password)) {
            return new RedirectResponse(
                '/login?redirect=' . rawurlencode($safeRedirect) . '&error=invalid',
                302,
            );
        }

        if (session_status() !== \PHP_SESSION_ACTIVE) {
            return new Response(
                'Session is not available; login cannot complete.',
                500,
                ['Content-Type' => 'text/plain; charset=UTF-8'],
            );
        }

        $_SESSION['waaseyaa_uid'] = $user->id();
        session_regenerate_id(true);
        CsrfMiddleware::regenerate();

        return new RedirectResponse($safeRedirect, 302);
    }

    private function safeRedirectTarget(string $redirect): string
    {
        if ($redirect === '' || !str_starts_with($redirect, '/') || str_starts_with($redirect, '//')) {
            return '/';
        }

        return $redirect;
    }
}
