<?php

declare(strict_types=1);

namespace App\Provider;

use App\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use App\Export\ExportService;
use App\Export\ExportServiceInterface;
use App\Http\Api\Ask\AskRequestValidator;
use App\Http\RateLimit\FileRequestRateLimiter;
use App\Http\RateLimit\RequestRateLimiterInterface;
use App\Pipeline\CompilationPipeline;
use App\Pipeline\Provider\AnthropicLlmProvider;
use App\Pipeline\Provider\EmbeddingProviderInterface;
use App\Pipeline\Provider\LlmProviderInterface;
use App\Pipeline\Provider\NullEmbeddingProvider;
use App\Pipeline\Provider\NullLlmProvider;
use App\Query\QaService;
use App\Query\QaServiceInterface;
use App\Query\Report\GovernanceSummaryReport;
use App\Query\Report\LandBriefReport;
use App\Query\Report\LanguageReport;
use App\Query\Report\ReportService;
use App\Query\Report\ReportServiceInterface;
use App\Query\SearchService;
use App\Query\SynthesisService;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Search\SearchProviderInterface;

final class QueryProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(EmbeddingProviderInterface::class, static fn (): EmbeddingProviderInterface => new NullEmbeddingProvider());
        $this->singleton(LlmProviderInterface::class, static function (): LlmProviderInterface {
            $provider = getenv('WAASEYAA_LLM_PROVIDER') ?: '';
            $apiKey = getenv('ANTHROPIC_API_KEY') ?: '';

            if ($provider === 'anthropic' && $apiKey !== '') {
                $model = getenv('WAASEYAA_ANTHROPIC_MODEL') ?: 'claude-sonnet-4-6';

                return new AnthropicLlmProvider(
                    new \Waaseyaa\AI\Agent\Provider\AnthropicProvider($apiKey, $model),
                );
            }

            return new NullLlmProvider();
        });

        $this->singleton(AskRequestValidator::class, function (): AskRequestValidator {
            /** @var mixed $maxQuestionLength */
            $maxQuestionLength = $this->config['api']['ask']['question_max_length'] ?? 2_000;

            return new AskRequestValidator(is_int($maxQuestionLength) ? $maxQuestionLength : 2_000);
        });

        $this->singleton(RequestRateLimiterInterface::class, function (): RequestRateLimiterInterface {
            /** @var mixed $maxAttempts */
            $maxAttempts = $this->config['api']['ask']['rate_limit']['max_attempts'] ?? 10;
            /** @var mixed $windowSeconds */
            $windowSeconds = $this->config['api']['ask']['rate_limit']['window_seconds'] ?? 60;

            return new FileRequestRateLimiter(
                storageDirectory: dirname(__DIR__, 2) . '/storage/framework/rate-limits/ask',
                maxAttempts: is_int($maxAttempts) ? $maxAttempts : 10,
                windowSeconds: is_int($windowSeconds) ? $windowSeconds : 60,
            );
        });

        $this->singleton(SearchService::class, function (): SearchService {
            return new SearchService(
                $this->resolve(SearchProviderInterface::class),
                $this->resolve(EmbeddingProviderInterface::class),
                $this->resolve(\App\Access\KnowledgeItemAccessPolicy::class),
                $this->resolve(KnowledgeItemRepositoryInterface::class),
            );
        });

        $this->singleton(QaServiceInterface::class, function (): QaServiceInterface {
            return new QaService(
                $this->resolve(SearchService::class),
                $this->resolve(LlmProviderInterface::class),
            );
        });

        $this->singleton(ReportServiceInterface::class, function (): ReportServiceInterface {
            return new ReportService(
                [
                    new GovernanceSummaryReport(),
                    new LanguageReport(),
                    new LandBriefReport(),
                ],
                $this->resolve(KnowledgeItemRepositoryInterface::class),
                $this->resolve(\App\Access\KnowledgeItemAccessPolicy::class),
                $this->resolve(\App\Access\CommunityRoleResolverInterface::class),
            );
        });

        $this->singleton(ExportServiceInterface::class, function (): ExportServiceInterface {
            return new ExportService($this->resolve(KnowledgeItemRepositoryInterface::class));
        });

        $this->singleton(SynthesisService::class, function (): SynthesisService {
            return new SynthesisService(
                $this->resolve(KnowledgeItemRepositoryInterface::class),
                $this->resolve(\App\Access\KnowledgeItemAccessPolicy::class),
            );
        });

        $this->singleton(CompilationPipeline::class, function (): CompilationPipeline {
            return new CompilationPipeline(
                $this->resolve(LlmProviderInterface::class),
                $this->resolve(EmbeddingProviderInterface::class),
                $this->resolve(KnowledgeItemRepositoryInterface::class),
            );
        });
    }
}
