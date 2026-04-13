<?php

declare(strict_types=1);

namespace Tests\Unit\Pipeline\Provider;

use App\Pipeline\Provider\AnthropicLlmProvider;
use App\Pipeline\Provider\LlmProviderInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Waaseyaa\AI\Agent\Provider\MessageRequest;
use Waaseyaa\AI\Agent\Provider\MessageResponse;
use Waaseyaa\AI\Agent\Provider\ProviderInterface;

#[CoversClass(AnthropicLlmProvider::class)]
final class AnthropicLlmProviderTest extends TestCase
{
    #[Test]
    public function completeReturnsTextFromResponse(): void
    {
        $anthropic = $this->createMock(ProviderInterface::class);
        $anthropic->expects($this->once())
            ->method('sendMessage')
            ->with($this->callback(function (MessageRequest $req): bool {
                $this->assertSame('You are helpful.', $req->system);
                $this->assertSame('What is 2+2?', $req->messages[0]['content']);
                $this->assertSame('user', $req->messages[0]['role']);
                return true;
            }))
            ->willReturn(new MessageResponse(
                content: [['type' => 'text', 'text' => 'The answer is 4.']],
                stopReason: 'end_turn',
            ));

        $provider = new AnthropicLlmProvider($anthropic);

        $this->assertInstanceOf(LlmProviderInterface::class, $provider);
        $this->assertSame('The answer is 4.', $provider->complete('You are helpful.', 'What is 2+2?'));
    }

    #[Test]
    public function completeReturnsFallbackWhenNoTextBlock(): void
    {
        $anthropic = $this->createMock(ProviderInterface::class);
        $anthropic->method('sendMessage')
            ->willReturn(new MessageResponse(
                content: [],
                stopReason: 'end_turn',
            ));

        $provider = new AnthropicLlmProvider($anthropic);

        $this->assertSame(
            'The LLM returned no text content. Please try again.',
            $provider->complete('system', 'question'),
        );
    }

    #[Test]
    public function completePropagatesCurlException(): void
    {
        $anthropic = $this->createMock(ProviderInterface::class);
        $anthropic->method('sendMessage')
            ->willThrowException(new \RuntimeException('cURL error: Connection timed out'));

        $provider = new AnthropicLlmProvider($anthropic);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('cURL error: Connection timed out');

        $provider->complete('system', 'question');
    }
}
