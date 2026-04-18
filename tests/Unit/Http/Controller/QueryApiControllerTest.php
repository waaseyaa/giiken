<?php

declare(strict_types=1);

namespace App\Tests\Unit\Http\Controller;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Http\Api\Ask\AskRequestValidator;
use App\Http\Controller\QueryApiController;
use App\Http\RateLimit\RateLimitResult;
use App\Http\RateLimit\RequestRateLimiterInterface;
use App\Query\QaCitation;
use App\Query\QaResponse;
use App\Query\QaServiceInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Waaseyaa\Access\AccountInterface;

#[CoversClass(QueryApiController::class)]
final class QueryApiControllerTest extends TestCase
{
    /** @var CommunityRepositoryInterface&MockObject */
    private CommunityRepositoryInterface $communityRepo;
    /** @var QaServiceInterface&MockObject */
    private QaServiceInterface $qaService;
    private AccountInterface $account;
    private RequestRateLimiterInterface $allowingLimiter;

    protected function setUp(): void
    {
        $this->communityRepo = $this->createMock(CommunityRepositoryInterface::class);
        $this->qaService = $this->createMock(QaServiceInterface::class);
        $this->account = $this->createMock(AccountInterface::class);
        $this->allowingLimiter = new class implements RequestRateLimiterInterface {
            public function consume(string $bucketKey): RateLimitResult
            {
                return new RateLimitResult(true, 2, 1, 0);
            }
        };
    }

    #[Test]
    public function ask_returns_answer_for_valid_request(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->with('test-community')->willReturn($community);
        $this->qaService->expects(self::once())
            ->method('ask')
            ->with('What happened?', 'comm-1', $this->account)
            ->willReturn(new QaResponse(
                answer: 'Here is the answer.',
                citedItemIds: ['42'],
                noRelevantItems: false,
                citations: [new QaCitation('42', 'Item', 'Excerpt')],
            ));

        $controller = $this->makeController($this->allowingLimiter);
        $request = $this->jsonRequest([
            'communitySlug' => 'test-community',
            'question' => '  What happened?  ',
        ]);

        $response = $controller->ask([], [], $this->account, $request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('2', $response->headers->get('X-RateLimit-Limit'));
        $payload = json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Here is the answer.', $payload['answer']);
    }

    #[Test]
    public function ask_rejects_missing_required_fields(): void
    {
        $controller = $this->makeController($this->allowingLimiter);
        $response = $controller->ask([], [], $this->account, $this->jsonRequest([
            'communitySlug' => 'test-community',
        ]));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('required', (string) $response->getContent());
    }

    #[Test]
    public function ask_rejects_overlong_questions(): void
    {
        $controller = new QueryApiController(
            communityRepo: $this->communityRepo,
            qaService: $this->qaService,
            reportService: null,
            synthesisService: null,
            askRequestValidator: new AskRequestValidator(10),
            askRateLimiter: $this->allowingLimiter,
        );

        $response = $controller->ask([], [], $this->account, $this->jsonRequest([
            'communitySlug' => 'test-community',
            'question' => 'This question is definitely too long.',
        ]));

        self::assertSame(422, $response->getStatusCode());
        self::assertStringContainsString('10 characters or fewer', (string) $response->getContent());
    }

    #[Test]
    public function ask_returns_429_when_rate_limit_is_exceeded(): void
    {
        $community = $this->makeCommunity();
        $this->communityRepo->method('findBySlug')->with('test-community')->willReturn($community);

        $limiter = new class implements RequestRateLimiterInterface {
            public function consume(string $bucketKey): RateLimitResult
            {
                return new RateLimitResult(false, 1, 0, 30);
            }
        };

        $controller = $this->makeController($limiter);
        $response = $controller->ask([], [], $this->account, $this->jsonRequest([
            'communitySlug' => 'test-community',
            'question' => 'What happened?',
        ]));

        self::assertSame(429, $response->getStatusCode());
        self::assertSame('30', $response->headers->get('Retry-After'));
        self::assertSame('0', $response->headers->get('X-RateLimit-Remaining'));
    }

    private function makeController(RequestRateLimiterInterface $rateLimiter): QueryApiController
    {
        return new QueryApiController(
            communityRepo: $this->communityRepo,
            qaService: $this->qaService,
            reportService: null,
            synthesisService: null,
            askRequestValidator: new AskRequestValidator(2_000),
            askRateLimiter: $rateLimiter,
        );
    }

    /**
     * @param array<string, mixed> $body
     */
    private function jsonRequest(array $body): HttpRequest
    {
        return HttpRequest::create(
            uri: '/api/v1/ask',
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            content: json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    private function makeCommunity(): Community
    {
        return Community::make([
            'id' => 'comm-1',
            'slug' => 'test-community',
            'name' => 'Test Community',
            'locale' => 'en',
        ]);
    }
}
