<?php

declare(strict_types=1);

namespace App\Provider;

use App\Http\Inertia\InertiaHttpResponder;
use Waaseyaa\Foundation\Asset\ViteAssetManager;
use Waaseyaa\Foundation\Http\Inertia\InertiaFullPageRendererInterface;
use Waaseyaa\Foundation\ServiceProvider\ServiceProvider;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\Inertia\RootTemplateRenderer;

final class FrontendProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->singleton(InertiaHttpResponder::class, function (): InertiaHttpResponder {
            try {
                $renderer = $this->resolve(InertiaFullPageRendererInterface::class);
            } catch (\Throwable) {
                $renderer = null;
            }

            return new InertiaHttpResponder($renderer, $this->config);
        });

        $this->registerInertiaViteRenderer();
    }

    private function registerInertiaViteRenderer(): void
    {
        $projectRoot = dirname(__DIR__, 2);
        $rawDev = $_ENV['VITE_DEV_SERVER'] ?? getenv('VITE_DEV_SERVER');
        $devServerUrl = is_string($rawDev) && $rawDev !== '' ? $rawDev : null;

        $assetManager = new ViteAssetManager(
            basePath: $projectRoot . '/public',
            baseUrl: '',
            devServerUrl: $devServerUrl,
        );

        $template = static function (string $scriptTag) use ($assetManager): string {
            $scriptTag = str_replace('data-page="true"', 'data-page="app"', $scriptTag);
            $assetTags = $assetManager->assetTags();

            return <<<HTML
            <!DOCTYPE html>
            <html>
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                {$assetTags}
            </head>
            <body>
                <div id="app"></div>
                {$scriptTag}
            </body>
            </html>
            HTML;
        };

        $renderer = new RootTemplateRenderer(template: $template, assetManager: $assetManager);
        Inertia::setRenderer($renderer);
        Inertia::setVersion('giiken');
        $this->singleton(InertiaFullPageRendererInterface::class, static fn (): InertiaFullPageRendererInterface => $renderer);
    }
}
