<?php

declare(strict_types=1);

namespace Giiken\Entity\Community;

use Carbon\CarbonImmutable;
use Waaseyaa\Entity\ContentEntityBase;
use Waaseyaa\Entity\Hydration\HydratableFromStorageInterface;
use Waaseyaa\Entity\Hydration\HydrationContext;

final class Community extends ContentEntityBase implements HydratableFromStorageInterface
{
    protected string $entityTypeId = 'community';

    protected array $entityKeys = [
        'id'    => 'id',
        'uuid'  => 'uuid',
        'label' => 'name',
    ];

    /**
     * @var array<string, string|array<string, mixed>>
     */
    protected array $casts = [
        'wiki_schema' => 'array',
        'created_at'  => ['type' => 'datetime_immutable', 'domain' => 'carbon_immutable'],
        'updated_at'  => ['type' => 'datetime_immutable', 'domain' => 'carbon_immutable'],
        'sovereignty_profile' => SovereigntyProfile::class,
    ];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        string $name,
        string $slug,
        SovereigntyProfile $sovereigntyProfile = SovereigntyProfile::Local,
        string $locale = 'en',
        ?CarbonImmutable $createdAt = null,
        ?CarbonImmutable $updatedAt = null,
        array $extra = [],
    ) {
        parent::__construct([
            'name' => $name,
            'slug' => $slug,
            'locale' => $locale,
            'sovereignty_profile' => $sovereigntyProfile->value,
            'created_at' => ($createdAt ?? CarbonImmutable::now())->toIso8601String(),
            'updated_at' => $updatedAt?->toIso8601String(),
            ...$extra,
        ], $this->entityTypeId, $this->entityKeys);
    }

    public static function fromStorage(array $values, HydrationContext $context): static
    {
        return self::make($values);
    }

    /**
     * @param array<string, mixed> $values
     */
    public static function make(array $values): self
    {
        return new self(
            name: (string) ($values['name'] ?? ''),
            slug: (string) ($values['slug'] ?? ''),
            sovereigntyProfile: SovereigntyProfile::tryFrom((string) ($values['sovereignty_profile'] ?? 'local'))
                ?? SovereigntyProfile::Local,
            locale: (string) ($values['locale'] ?? 'en'),
            createdAt: isset($values['created_at'])
                ? CarbonImmutable::parse($values['created_at'])
                : null,
            updatedAt: isset($values['updated_at'])
                ? CarbonImmutable::parse($values['updated_at'])
                : null,
            extra: $values,
        );
    }

    protected function duplicateInstance(array $values): static
    {
        return static::fromStorage($values, new HydrationContext(
            entityTypeId: $this->entityTypeId,
            entityKeys: $this->entityKeys,
        ));
    }

    public function name(): string
    {
        return (string) $this->get('name');
    }

    public function slug(): string
    {
        return (string) $this->get('slug');
    }

    public function sovereigntyProfile(): SovereigntyProfile
    {
        return SovereigntyProfile::tryFrom((string) ($this->values['sovereignty_profile'] ?? 'local'))
            ?? SovereigntyProfile::Local;
    }

    public function locale(): string
    {
        return (string) ($this->get('locale') ?? 'en');
    }

    public function contactEmail(): string
    {
        return (string) ($this->get('contact_email') ?? '');
    }

    public function wikiSchema(): WikiSchema
    {
        /** @var array<string, mixed>|null $raw */
        $raw = $this->get('wiki_schema');

        return WikiSchema::fromArray(is_array($raw) ? $raw : []);
    }

    public function createdAt(): CarbonImmutable
    {
        $v = $this->get('created_at');
        if ($v instanceof CarbonImmutable) {
            return $v;
        }

        return CarbonImmutable::parse((string) $v);
    }

    public function updatedAt(): ?CarbonImmutable
    {
        $v = $this->get('updated_at');
        if ($v === null || $v === '') {
            return null;
        }

        if ($v instanceof CarbonImmutable) {
            return $v;
        }

        return CarbonImmutable::parse((string) $v);
    }
}
