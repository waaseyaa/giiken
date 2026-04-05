<?php

declare(strict_types=1);

namespace Giiken\Export;

use Giiken\Entity\Community\Community;
use Giiken\Entity\KnowledgeItem\KnowledgeItem;
use Giiken\Entity\KnowledgeItem\KnowledgeItemRepositoryInterface;
use RuntimeException;
use Waaseyaa\Access\AccountInterface;
use ZipArchive;

final class ExportService implements ExportServiceInterface
{
    public function __construct(
        private readonly KnowledgeItemRepositoryInterface $itemRepository,
    ) {}

    public function export(Community $community, AccountInterface $account): string
    {
        $communityId = (string) $community->get('id');

        if (!$this->isAdmin($communityId, $account)) {
            throw new RuntimeException('Access denied: export requires admin role');
        }

        $tmpDir = sys_get_temp_dir() . '/giiken-export-' . uniqid('', true);
        mkdir($tmpDir, 0700, true);

        try {
            $this->writeCommunityYaml($tmpDir, $community);
            $this->writeKnowledgeItems($tmpDir, $communityId);
            $this->writeEmbeddings($tmpDir);
            $this->writeUsers($tmpDir);
            $this->writeReadme($tmpDir, $community);

            $zipPath = $tmpDir . '.zip';
            $this->createZip($tmpDir, $zipPath);

            return $zipPath;
        } finally {
            $this->removeDirectory($tmpDir);
        }
    }

    private function isAdmin(string $communityId, AccountInterface $account): bool
    {
        $adminRole = "giiken.community.{$communityId}.admin";

        foreach ($account->getRoles() as $role) {
            if ($role === $adminRole) {
                return true;
            }
        }

        return false;
    }

    private function writeCommunityYaml(string $tmpDir, Community $community): void
    {
        $wikiSchema = $community->getWikiSchema();

        $data = [
            'name'                => $community->getName(),
            'slug'                => $community->getSlug(),
            'locale'              => $community->getLocale(),
            'sovereignty_profile' => $community->getSovereigntyProfile(),
            'contact_email'       => $community->getContactEmail(),
            'wiki_schema'         => $wikiSchema === [] ? null : $wikiSchema,
        ];

        file_put_contents($tmpDir . '/community.yaml', $this->arrayToYaml($data));
    }

    private function writeKnowledgeItems(string $tmpDir, string $communityId): void
    {
        $itemsDir = $tmpDir . '/knowledge-items';
        mkdir($itemsDir, 0700, true);

        $items = $this->itemRepository->findByCommunity($communityId);

        foreach ($items as $item) {
            $uuid    = (string) $item->get('uuid');
            $content = $this->itemToMarkdown($item);
            file_put_contents($itemsDir . '/' . $uuid . '.md', $content);
        }
    }

    private function itemToMarkdown(KnowledgeItem $item): string
    {
        $frontmatter = [
            'id'            => (string) $item->get('id'),
            'title'         => $item->getTitle(),
            'knowledge_type' => $item->getKnowledgeType()?->value ?? '',
            'access_tier'   => $item->getAccessTier()->value,
            'allowed_roles' => $item->getAllowedRoles(),
            'allowed_users' => $item->getAllowedUsers(),
            'source_media'  => $item->getSourceMediaIds(),
            'created_at'    => $item->getCreatedAt(),
            'updated_at'    => $item->getUpdatedAt(),
        ];

        $yaml = $this->arrayToYaml($frontmatter);

        return "---\n" . $yaml . "---\n\n" . $item->getContent() . "\n";
    }

    private function writeEmbeddings(string $tmpDir): void
    {
        file_put_contents($tmpDir . '/embeddings.json', '[]');
    }

    private function writeUsers(string $tmpDir): void
    {
        file_put_contents($tmpDir . '/users.yaml', "users: []\n");
    }

    private function writeReadme(string $tmpDir, Community $community): void
    {
        $date    = date('Y-m-d');
        $name    = $community->getName();
        $content = <<<README
        # Giiken Export

        **Format version:** 1.0
        **Date:** {$date}
        **Community:** {$name}

        ## Contents

        - `community.yaml` — Community metadata (name, slug, locale, sovereignty profile, contact email, wiki schema)
        - `knowledge-items/` — Knowledge items as Markdown files with YAML frontmatter
        - `embeddings.json` — Embedding vectors (empty placeholder in this export)
        - `users.yaml` — Community user list (empty placeholder in this export)
        README;

        // Dedent the heredoc indentation
        $lines  = explode("\n", $content);
        $dedented = array_map(static fn (string $l) => ltrim($l, ' '), $lines);

        file_put_contents($tmpDir . '/README.md', implode("\n", $dedented));
    }

    private function createZip(string $sourceDir, string $zipPath): void
    {
        $zip = new ZipArchive();

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("Cannot create ZIP archive at {$zipPath}");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
        );

        foreach ($files as $file) {
            $filePath     = $file->getRealPath();
            $relativePath = substr($filePath, strlen($sourceDir) + 1);
            $zip->addFile($filePath, $relativePath);
        }

        $zip->close();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function arrayToYaml(array $data, int $indent = 0): string
    {
        $lines  = [];
        $prefix = str_repeat('  ', $indent);

        foreach ($data as $key => $value) {
            if ($value === null) {
                $lines[] = "{$prefix}{$key}: ~";
            } elseif (is_bool($value)) {
                $lines[] = "{$prefix}{$key}: " . ($value ? 'true' : 'false');
            } elseif (is_array($value)) {
                if ($value === []) {
                    $lines[] = "{$prefix}{$key}: []";
                } elseif (array_is_list($value)) {
                    $lines[] = "{$prefix}{$key}:";
                    foreach ($value as $item) {
                        $lines[] = "{$prefix}  - " . $this->scalarToYaml($item);
                    }
                } else {
                    $lines[] = "{$prefix}{$key}:";
                    $lines[] = rtrim($this->arrayToYaml($value, $indent + 1));
                }
            } else {
                $lines[] = "{$prefix}{$key}: " . $this->scalarToYaml($value);
            }
        }

        return implode("\n", $lines) . "\n";
    }

    private function scalarToYaml(mixed $value): string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        $str = (string) $value;

        // Quote strings that contain special characters or look like other types
        if (
            $str === '' ||
            preg_match('/[:#\[\]{},&*?|<>=!%@`\'"\\\\]/', $str) ||
            in_array(strtolower($str), ['true', 'false', 'null', '~'], true) ||
            is_numeric($str)
        ) {
            return '"' . addslashes($str) . '"';
        }

        return $str;
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
