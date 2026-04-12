<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Export\ExportServiceInterface;
use App\Http\Inertia\InertiaHttpResponder;
use App\Ingestion\IngestionException;
use App\Ingestion\IngestionHandlerRegistry;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Foundation\Http\Inbound\InboundHttpRequest;
use Waaseyaa\Inertia\Inertia;

final class ManagementController
{
    public function __construct(
        private readonly ?CommunityRepositoryInterface $communityRepo = null,
        private readonly ?InertiaHttpResponder $inertiaHttp = null,
        private readonly ?ExportServiceInterface $exportService = null,
        private readonly ?IngestionHandlerRegistry $handlerRegistry = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function dashboard(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Dashboard', [
                'community' => null,
                'bootError' => 'Management services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Dashboard', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function reports(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Reports', [
                'community' => null,
                'reportTypes' => ['governance_summary', 'language_report', 'land_brief'],
                'bootError' => 'Report services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Reports', [
            'community'   => $community !== null ? $this->serializeCommunity($community) : null,
            'reportTypes' => ['governance_summary', 'language_report', 'land_brief'],
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function users(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Users', [
                'community' => null,
                'bootError' => 'User services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Users', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ingestion(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Ingestion', [
                'community' => null,
                'bootError' => 'Ingestion services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Ingestion', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * POST endpoint that accepts a multipart file upload from the Management
     * Ingestion page, hands it to the appropriate handler via
     * {@see IngestionHandlerRegistry}, and re-renders the Ingestion page with
     * the result (success summary or error). The result is reported in the
     * page's Inertia props so the Vue page can render it inline.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ingestUpload(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null || $this->handlerRegistry === null) {
            return $this->page('Management/Ingestion', [
                'community' => null,
                'bootError' => 'Ingestion services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound       = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community     = $this->communityRepo->findBySlug($communitySlug);

        $baseProps = [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ];

        if ($community === null) {
            return $this->page('Management/Ingestion', $baseProps + [
                'uploadError' => 'Community not found.',
            ], $httpRequest, $account);
        }

        $upload = $httpRequest->files->get('file');
        if (!$upload instanceof UploadedFile) {
            return $this->page('Management/Ingestion', $baseProps + [
                'uploadError' => 'No file was attached. Choose a file and try again.',
            ], $httpRequest, $account);
        }

        $mimeType = (string) ($upload->getClientMimeType() ?: $upload->getMimeType());

        try {
            $raw = $this->handlerRegistry->handle(
                filePath:         $upload->getPathname(),
                mimeType:         $mimeType,
                originalFilename: $upload->getClientOriginalName(),
                community:        $community,
            );
        } catch (IngestionException $e) {
            return $this->page('Management/Ingestion', $baseProps + [
                'uploadError' => $e->getMessage(),
            ], $httpRequest, $account);
        }

        return $this->page('Management/Ingestion', $baseProps + [
            'uploadResult' => [
                'originalFilename' => $raw->originalFilename,
                'mimeType'         => $raw->mimeType,
                'mediaId'          => $raw->mediaId,
                'metadata'         => $raw->metadata,
            ],
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function exportPage(array $params, array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null) {
            return $this->page('Management/Export', [
                'community' => null,
                'bootError' => 'Export/import services are not configured yet.',
            ], $httpRequest, $account);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community = $this->communityRepo->findBySlug($communitySlug);

        return $this->page('Management/Export', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function exportDownload(
        array $params,
        array $query,
        AccountInterface $account,
        HttpRequest $httpRequest,
    ): Response {
        if ($this->communityRepo === null || $this->exportService === null) {
            return new Response('Export service is not configured.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $inbound = InboundHttpRequest::fromSymfonyRequest($httpRequest, $params, $query);
        $communitySlug = (string) $inbound->routeParam('communitySlug', '');
        $community     = $this->communityRepo->findBySlug($communitySlug);
        if ($community === null) {
            return new Response('Community not found.', 404);
        }

        try {
            $zipPath = $this->exportService->export($community, $account);
        } catch (\RuntimeException $e) {
            return new Response($e->getMessage(), 403);
        }

        $response = new BinaryFileResponse($zipPath);
        $response->setContentDisposition('attachment', 'giiken-export-' . $community->slug() . '.zip');
        $response->deleteFileAfterSend(true);

        return $response;
    }

    /**
     * @param array<string, mixed> $props
     */
    private function page(string $component, array $props, HttpRequest $httpRequest, AccountInterface $account): Response
    {
        if ($this->inertiaHttp === null) {
            return new Response('Giiken: InertiaHttpResponder is not registered.', 500, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        return $this->inertiaHttp->toResponse(
            Inertia::render($component, $props),
            $httpRequest,
            $account,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCommunity(Community $community): array
    {
        return [
            'id'     => $community->get('id'),
            'name'   => $community->name(),
            'slug'   => $community->slug(),
            'locale' => $community->locale(),
        ];
    }
}
