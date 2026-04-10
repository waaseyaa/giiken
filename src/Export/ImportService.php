<?php

declare(strict_types=1);

namespace Giiken\Export;

use Giiken\Entity\Community\Community;
use Giiken\Entity\Community\CommunityRepositoryInterface;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use RuntimeException;
use Waaseyaa\Access\AccountInterface;
use ZipArchive;

final class ImportService implements ImportServiceInterface
{
    public function __construct(
        private readonly CommunityRepositoryInterface $communityRepository,
        private readonly KnowledgeItemRepositoryInterface $itemRepository,
    ) {}

    public function import(string $archivePath, AccountInterface $account): ImportResult
    {
        if (!$this->accountIsAdmin($account)) {
            throw new RuntimeException('Access denied: import requires admin role');
        }

        $tmpDir = sys_get_temp_dir() . '/giiken-import-' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        try {
            $this->extractZip($archivePath, $tmpDir);

            // Community.yaml may be at root or inside a prefix directory
            $communityYamlPath = $this->findFile($tmpDir, 'community.yaml');

            if ($communityYamlPath === null) {
                throw new RuntimeException('Archive does not contain community.yaml');
            }

            $archiveRoot = dirname($communityYamlPath);

            $communityData = $this->parseYaml(file_get_contents($communityYamlPath) ?: '');
            $community     = $this->upsertCommunity($communityData);
            $communityId   = (string) $community->get('id');

            $warnings      = [];
            $itemsImported = 0;

            // Import knowledge items
            $itemsDir = $archiveRoot . '/knowledge-items';

            if (is_dir($itemsDir)) {
                foreach (glob($itemsDir . '/*.md') ?: [] as $mdFile) {
                    $raw  = file_get_contents($mdFile) ?: '';
                    $item = $this->parseMarkdownItem($raw, $communityId);
                    $this->itemRepository->save($item);
                    $itemsImported++;
                }
            }

            // Skip embeddings.json
            if (file_exists($archiveRoot . '/embeddings.json')) {
                $warnings[] = 'embeddings.json skipped: re-embedding not supported during import';
            }

            // Skip users.yaml
            if (file_exists($archiveRoot . '/users.yaml')) {
                $warnings[] = 'users.yaml skipped: user provisioning not supported during import';
            }

            // Count media files (any non-standard files in archive root)
            $mediaLinked = 0;

            return new ImportResult(
                communityId: $communityId,
                itemsImported: $itemsImported,
                mediaLinked: $mediaLinked,
                warnings: $warnings,
            );
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    private function accountIsAdmin(AccountInterface $account): bool
    {
        foreach ($account->getRoles() as $role) {
            if (str_ends_with($role, '.admin')) {
                return true;
            }
        }

        return false;
    }

    private function extractZip(string $archivePath, string $targetDir): void
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            throw new RuntimeException("Cannot open ZIP archive: {$archivePath}");
        }

        $zip->extractTo($targetDir);
        $zip->close();
    }

    private function findFile(string $dir, string $filename): ?string
    {
        // Check root first
        if (file_exists($dir . '/' . $filename)) {
            return $dir . '/' . $filename;
        }

        // Check one level deep (prefix directory)
        foreach (glob($dir . '/*', GLOB_ONLYDIR) ?: [] as $subDir) {
            if (file_exists($subDir . '/' . $filename)) {
                return $subDir . '/' . $filename;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseYaml(string $yaml): array
    {
        $result = [];
        $lines  = explode("\n", $yaml);

        foreach ($lines as $line) {
            // Skip empty lines and comments
            $trimmed = trim($line);

            if ($trimmed === '' || str_starts_with($trimmed, '#')) {
                continue;
            }

            // Only handle flat key: value pairs
            if (!str_contains($trimmed, ':')) {
                continue;
            }

            [$rawKey, $rawValue] = explode(':', $trimmed, 2);
            $key   = trim($rawKey);
            $value = trim($rawValue);

            // Skip list markers and nested maps at this level
            if (str_starts_with($key, '-')) {
                continue;
            }

            $result[$key] = $this->parseYamlScalar($value);
        }

        return $result;
    }

    private function parseYamlScalar(string $value): mixed
    {
        if ($value === '~' || $value === 'null') {
            return null;
        }

        if ($value === 'true') {
            return true;
        }

        if ($value === 'false') {
            return false;
        }

        if ($value === '[]') {
            return [];
        }

        // Unquote double-quoted strings
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            return stripslashes(substr($value, 1, -1));
        }

        // Unquote single-quoted strings
        if (str_starts_with($value, "'") && str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        if (is_numeric($value)) {
            return str_contains($value, '.') ? (float) $value : (int) $value;
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function upsertCommunity(array $data): Community
    {
        $slug      = (string) ($data['slug'] ?? '');
        $community = $slug !== '' ? $this->communityRepository->findBySlug($slug) : null;

        if ($community === null) {
            $community = Community::make([
                'name'                => (string) ($data['name'] ?? ''),
                'slug'                => $slug,
                'locale'              => (string) ($data['locale'] ?? 'en'),
                'sovereignty_profile' => (string) ($data['sovereignty_profile'] ?? 'local'),
                'contact_email'       => (string) ($data['contact_email'] ?? ''),
            ]);
        } else {
            $community->set('name', (string) ($data['name'] ?? $community->name()));
            $community->set('locale', (string) ($data['locale'] ?? $community->locale()));
        }

        $this->communityRepository->save($community);

        return $community;
    }

    private function parseMarkdownItem(string $raw, string $communityId): KnowledgeItem
    {
        $frontmatter = [];
        $content     = $raw;

        // Extract YAML frontmatter between --- delimiters
        if (str_starts_with($raw, "---\n")) {
            $end = strpos($raw, "\n---\n", 4);

            if ($end !== false) {
                $yamlBlock   = substr($raw, 4, $end - 4);
                $frontmatter = $this->parseYaml($yamlBlock);
                $content     = ltrim(substr($raw, $end + 5));
            }
        }

        return new KnowledgeItem([
            'id'             => (string) ($frontmatter['id'] ?? ''),
            'community_id'   => $communityId,
            'title'          => (string) ($frontmatter['title'] ?? ''),
            'knowledge_type' => (string) ($frontmatter['knowledge_type'] ?? ''),
            'access_tier'    => (string) ($frontmatter['access_tier'] ?? 'members'),
            'allowed_roles'  => [],
            'allowed_users'  => [],
            'source_media_ids' => [],
            'content'        => rtrim($content),
            'created_at'     => (string) ($frontmatter['created_at'] ?? date('c')),
            'updated_at'     => (string) ($frontmatter['updated_at'] ?? ''),
        ]);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
