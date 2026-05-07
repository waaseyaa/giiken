<?php

declare(strict_types=1);

namespace App\Http\Controller;

use App\Access\CommunityRole;
use App\Access\CommunityRoleResolver;
use App\Access\CommunityRoleResolverInterface;
use App\Entity\Community\Community;
use App\Entity\Community\CommunityRepositoryInterface;
use App\Export\ExportServiceInterface;
use App\Http\Inertia\InertiaHttpResponder;
use App\Ingestion\IngestionException;
use App\Ingestion\IngestionHandlerRegistry;
use App\Ingestion\Upload\UploadValidatorInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response;
use Waaseyaa\Access\AccountInterface;
use Waaseyaa\Foundation\Http\Inbound\InboundHttpRequest;
use Waaseyaa\Inertia\Inertia;
use Waaseyaa\SSR\Attribute\MapQuery;
use Waaseyaa\SSR\Attribute\MapRoute;

final class ManagementController
{
    public function __construct(
        private readonly ?CommunityRepositoryInterface $communityRepo = null,
        private readonly ?InertiaHttpResponder $inertiaHttp = null,
        private readonly ?ExportServiceInterface $exportService = null,
        private readonly ?IngestionHandlerRegistry $handlerRegistry = null,
        private readonly ?CommunityRoleResolverInterface $roleResolver = null,
        private readonly ?UploadValidatorInterface $uploadValidator = null,
    ) {}

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function dashboard(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $httpRequest): Response
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
        $authorization = $this->authorizeStaffAccess($community, $account);
        if ($authorization !== null) {
            return $authorization;
        }

        return $this->page('Management/Dashboard', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function reports(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $httpRequest): Response
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
        $authorization = $this->authorizeStaffAccess($community, $account);
        if ($authorization !== null) {
            return $authorization;
        }

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
    public function users(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $httpRequest): Response
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
        $authorization = $this->authorizeStaffAccess($community, $account);
        if ($authorization !== null) {
            return $authorization;
        }

        return $this->page('Management/Users', [
            'community' => $community !== null ? $this->serializeCommunity($community) : null,
            'bootError' => null,
        ], $httpRequest, $account);
    }

    /**
     * @param array<string, mixed> $params
     * @param array<string, mixed> $query
     */
    public function ingestion(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $httpRequest): Response
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
        $authorization = $this->authorizeStaffAccess($community, $account);
        if ($authorization !== null) {
            return $authorization;
        }

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
    public function ingestUpload(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $httpRequest): Response
    {
        if ($this->communityRepo === null || $this->handlerRegistry === null || $this->uploadValidator === null) {
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

        $authorization = $this->authorizeStaffAccess($community, $account);
        if ($authorization !== null) {
            return $authorization;
        }

        $upload = $httpRequest->files->get('file');
        if (!$upload instanceof UploadedFile) {
            return $this->page('Management/Ingestion', $baseProps + [
                'uploadError' => 'No file was attached. Choose a file and try again.',
            ], $httpRequest, $account);
        }

        try {
            $validatedUpload = $this->uploadValidator->validate($upload);
            $raw = $this->handlerRegistry->handle(
                filePath:         $validatedUpload->path,
                mimeType:         $validatedUpload->mimeType,
                originalFilename: $validatedUpload->originalFilename,
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
    public function exportPage(#[MapRoute] array $params, #[MapQuery] array $query, AccountInterface $account, HttpRequest $httpRequest): Response
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
        $authorization = $this->authorizeStaffAccess($community, $account);
        if ($authorization !== null) {
            return $authorization;
        }

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
        #[MapRoute] array $params,
        #[MapQuery] array $query,
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

        $authorization = $this->authorizeStaffAccess($community, $account);
        if ($authorization !== null) {
            return $authorization;
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

    private function authorizeStaffAccess(?Community $community, AccountInterface $account): ?Response
    {
        if ($community === null) {
            return new Response('Community not found.', 404, [
                'Content-Type' => 'text/plain; charset=UTF-8',
            ]);
        }

        $role = ($this->roleResolver ?? new CommunityRoleResolver())->resolve((string) $community->get('id'), $account);
        if ($role->rank() >= CommunityRole::Staff->rank()) {
            return null;
        }

        return new Response('Forbidden.', 403, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
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
