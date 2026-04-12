<?php

declare(strict_types=1);

namespace App\Entity\Community;

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
        // Spread $extra first so constructor-normalized fields (e.g. sovereignty_profile) are not
        // overwritten by a raw import bag that may contain invalid legacy strings.
        parent::__construct([
            ...$extra,
            'name' => $name,
            'slug' => $slug,
            'locale' => $locale,
            'sovereignty_profile' => $sovereigntyProfile->value,
            'created_at' => ($createdAt ?? CarbonImmutable::now())->toIso8601String(),
            'updated_at' => $updatedAt?->toIso8601String(),
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
        $values = self::sanitizeUnparseableTimestamps($values);
        $wikiSchema = $values['wiki_schema'] ?? null;

        $entity = new self(
            name: (string) ($values['name'] ?? ''),
            slug: (string) ($values['slug'] ?? ''),
            sovereigntyProfile: SovereigntyProfile::tryFrom((string) ($values['sovereignty_profile'] ?? 'local'))
                ?? SovereigntyProfile::Local,
            locale: (string) ($values['locale'] ?? 'en'),
            createdAt: self::parseCarbonOrNull($values['created_at'] ?? null),
            updatedAt: array_key_exists('updated_at', $values)
                ? self::parseCarbonOrNull($values['updated_at'])
                : null,
            extra: $values,
        );

        if (is_array($wikiSchema)) {
            $entity->set('wiki_schema', $wikiSchema);
        }

        return $entity;
    }

    /**
     * Replace timestamps that cannot be parsed so hydration and {@see $casts} never throw on corrupt rows.
     *
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    private static function sanitizeUnparseableTimestamps(array $values): array
    {
        foreach (['created_at', 'updated_at'] as $key) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $raw = $values[$key];
            if ($raw === null || $raw === '') {
                continue;
            }
            if (self::parseCarbonOrNull($raw) !== null) {
                continue;
            }
            $values[$key] = $key === 'created_at'
                ? self::fallbackTimestamp()->toIso8601String()
                : null;
        }

        return $values;
    }

    private static function fallbackTimestamp(): CarbonImmutable
    {
        return CarbonImmutable::createFromTimestampUTC(0);
    }

    private static function parseCarbonOrNull(mixed $value): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof CarbonImmutable) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }
        try {
            return CarbonImmutable::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    private static function parseCarbonOrFallback(mixed $value): CarbonImmutable
    {
        return self::parseCarbonOrNull($value) ?? self::fallbackTimestamp();
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
        $value = $this->get('sovereignty_profile');

        if ($value instanceof SovereigntyProfile) {
            return $value;
        }

        return SovereigntyProfile::tryFrom((string) ($value ?? 'local')) ?? SovereigntyProfile::Local;
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

        return self::parseCarbonOrFallback($v);
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

        return self::parseCarbonOrNull($v);
    }
}
