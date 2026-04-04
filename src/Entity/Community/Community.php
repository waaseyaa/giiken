<?php

declare(strict_types=1);

namespace Giiken\Entity\Community;

use Waaseyaa\Entity\ContentEntityBase;

final class Community extends ContentEntityBase
{
    public const SOVEREIGNTY_PROFILES = ['local', 'self_hosted', 'northops'];

    protected string $entityTypeId = 'community';

    protected array $entityKeys = [
        'id'    => 'id',
        'uuid'  => 'uuid',
        'label' => 'name',
    ];

    /**
     * @param array<string, mixed> $values
     */
    public function __construct(array $values = [])
    {
        if (!isset($values['locale'])) {
            $values['locale'] = 'en';
        }

        if (!isset($values['created_at'])) {
            $values['created_at'] = date('c');
        }

        parent::__construct($values, $this->entityTypeId, $this->entityKeys);
    }

    public function getName(): string
    {
        return (string) ($this->get('name') ?? '');
    }

    public function getSlug(): string
    {
        return (string) ($this->get('slug') ?? '');
    }

    public function getSovereigntyProfile(): string
    {
        $value = (string) ($this->get('sovereignty_profile') ?? 'local');

        if (!in_array($value, self::SOVEREIGNTY_PROFILES, true)) {
            return 'local';
        }

        return $value;
    }

    public function getLocale(): string
    {
        return (string) ($this->get('locale') ?? 'en');
    }

    public function getContactEmail(): string
    {
        return (string) ($this->get('contact_email') ?? '');
    }

    /**
     * Returns the community's wiki schema as a decoded array, or an empty
     * array if none has been set. The schema is the per-community CLAUDE.md
     * equivalent — it governs how the LLM maintains this community's wiki.
     *
     * @return array<string, mixed>
     */
    public function getWikiSchema(): array
    {
        $raw = $this->get('wiki_schema');

        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_array($raw)) {
            return $raw;
        }

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode((string) $raw, true, 512, JSON_THROW_ON_ERROR);

            return $decoded;
        } catch (\JsonException) {
            return [];
        }
    }

    public function getCreatedAt(): string
    {
        return (string) ($this->get('created_at') ?? '');
    }

    public function getUpdatedAt(): string
    {
        return (string) ($this->get('updated_at') ?? '');
    }
}
