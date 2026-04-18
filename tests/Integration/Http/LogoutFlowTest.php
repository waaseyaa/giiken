<?php

declare(strict_types=1);

namespace App\Tests\Integration\Http;

use App\Tests\Integration\Support\AppKernelIntegrationTestCase;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\User\Middleware\CsrfMiddleware;

#[CoversNothing]
final class LogoutFlowTest extends AppKernelIntegrationTestCase
{
    protected function tearDown(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            $_SESSION = [];
            session_destroy();
        }

        parent::tearDown();
    }

    #[Test]
    public function get_logout_no_longer_terminates_the_session(): void
    {
        $this->beginAuthenticatedSession();

        $response = $this->handleRequest('/logout', 'GET');

        self::assertNotSame(302, $response->getStatusCode());
        self::assertSame('1', (string) ($_SESSION['waaseyaa_uid'] ?? ''));
    }

    #[Test]
    public function post_logout_without_csrf_token_is_rejected(): void
    {
        $this->beginAuthenticatedSession();

        $response = $this->handleRequest('/logout', 'POST');

        self::assertSame(403, $response->getStatusCode());
        self::assertSame('1', (string) ($_SESSION['waaseyaa_uid'] ?? ''));
    }

    #[Test]
    public function post_logout_with_csrf_token_clears_the_session_and_redirects_home(): void
    {
        $this->beginAuthenticatedSession();
        $token = CsrfMiddleware::token();

        $response = $this->handleRequest('/logout', 'POST', [
            '_csrf_token' => $token,
        ]);

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/', $response->headers->get('Location'));
        self::assertTrue(session_status() !== \PHP_SESSION_ACTIVE || empty($_SESSION));
    }

    private function beginAuthenticatedSession(): void
    {
        if (session_status() === \PHP_SESSION_ACTIVE) {
            session_destroy();
        }

        session_id('logout-test-' . bin2hex(random_bytes(5)));
        session_start();
        $_SESSION = [
            'waaseyaa_uid' => '1',
        ];
    }

    /**
     * @param array<string, string> $post
     */
    private function handleRequest(string $uri, string $method, array $post = []): Response
    {
        $savedServer = $_SERVER;
        $savedGet = $_GET;
        $savedPost = $_POST;

        $queryString = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
        $_SERVER = array_merge($savedServer, [
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => $uri,
            'QUERY_STRING' => $queryString,
            'HTTP_HOST' => 'localhost',
            'SCRIPT_NAME' => '/index.php',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => '80',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ]);
        parse_str($queryString, $parsedQuery);
        /** @var array<string, mixed> $parsedQuery */
        $_GET = $parsedQuery;
        $_POST = $post;

        try {
            $response = self::kernel()->handle();
            self::assertInstanceOf(Response::class, $response);

            return $response;
        } finally {
            $_SERVER = $savedServer;
            $_GET = $savedGet;
            $_POST = $savedPost;
        }
    }
}
