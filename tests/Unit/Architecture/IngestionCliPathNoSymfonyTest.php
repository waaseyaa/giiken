<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guardrails for Symfony-shaped code in the giiken:ingest:file path.
 *
 * @see ../../../../docs/specs/giiken-ingestion-cli-contract.md
 */
#[CoversNothing]
final class IngestionCliPathNoSymfonyTest extends TestCase
{
    private static function projectRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    /**
     * @return list<array{0: non-empty-string}>
     */
    public static function productionPhpFilesProvider(): array
    {
        $root = dirname(__DIR__, 3);
        $relative = [
            'src/Console/IngestFileCommand.php',
            'src/Ingestion/IngestionHandlerRegistry.php',
            'src/Ingestion/IngestionException.php',
            'src/Ingestion/Handler/CsvIngestionHandler.php',
            'src/Ingestion/Handler/HtmlIngestionHandler.php',
            'src/Ingestion/Handler/DocumentIngestionHandler.php',
            'src/Ingestion/Handler/MarkdownIngestionHandler.php',
            'src/Ingestion/Handler/MediaIngestionHandler.php',
            'src/Pipeline/CompilationPipeline.php',
            'src/Pipeline/PipelineException.php',
            'src/Pipeline/CompilationPayload.php',
            'src/Pipeline/SovereigntyConfig.php',
            'src/Pipeline/Step/TranscribeStep.php',
            'src/Pipeline/Step/ClassifyStep.php',
            'src/Pipeline/Step/StructureStep.php',
            'src/Pipeline/Step/LinkStep.php',
            'src/Pipeline/Step/EmbedStep.php',
            'src/Pipeline/Provider/LlmProviderInterface.php',
            'src/Pipeline/Provider/EmbeddingProviderInterface.php',
            'src/Pipeline/Provider/AnthropicLlmProvider.php',
            'src/Pipeline/Provider/NullLlmProvider.php',
            'src/Pipeline/Provider/NullEmbeddingProvider.php',
        ];

        $paths = [];
        foreach ($relative as $rel) {
            $paths[] = [$root . '/' . $rel];
        }

        return $paths;
    }

    #[Test]
    public function contract_spec_is_present_for_wp04_enforcement(): void
    {
        $path = self::projectRoot() . '/docs/specs/giiken-ingestion-cli-contract.md';
        self::assertFileExists($path);
        self::assertNotSame('', trim((string) file_get_contents($path)));
    }

    #[Test]
    #[DataProvider('productionPhpFilesProvider')]
    public function ingestion_cli_path_php_files_contain_no_symfony_use_statements(string $absolutePath): void
    {
        self::assertFileExists($absolutePath);
        $contents = (string) file_get_contents($absolutePath);
        if (preg_match_all('/^\s*use\s+Symfony\\\\[^;]+;/m', $contents, $m)) {
            self::fail(sprintf(
                'Forbidden Symfony import(s) in %s: %s',
                $absolutePath,
                implode(', ', $m[0]),
            ));
        }
    }
}
