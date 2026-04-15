<?php

declare(strict_types=1);

namespace App\Entity\KnowledgeItem\Source;

/**
 * What may be done with a knowledge item — license terms, consents, and
 * Indigenous data-sovereignty signals.
 *
 * Three layered concerns:
 *
 * 1. **Copyright status + license** — the conventional IP posture (CC-BY-4.0,
 *    fair dealing, public domain, etc.).
 * 2. **Consents** — platform-level booleans the pipeline MUST honor.
 *    `consentAiTraining=false` is a hard gate for the EmbedStep / LinkStep.
 * 3. **TK Labels + CARE flags** — Indigenous-specific protocols from the
 *    Local Contexts project and the Global Indigenous Data Alliance.
 *    These are advisory-but-enforceable: the platform surfaces them in the
 *    UI and uses them to drive access rules.
 *
 * Intentionally not modeled as enums (except copyrightStatus): TK Labels evolve
 * and licenses are a large open set. Strings are stored as SPDX where possible.
 */
final readonly class Rights
{
    /**
     * @param list<string> $tkLabels Local Contexts TK Labels (e.g. "TK Attribution", "TK Non-Commercial", "TK Community Voice")
     * @param array<string, mixed> $careFlags CARE-principle annotations (see spec doc)
     */
    public function __construct(
        public CopyrightStatus $copyrightStatus,
        public ?string $license = null,
        public bool $consentPublic = true,
        public bool $consentAiTraining = false,
        public array $tkLabels = [],
        public array $careFlags = [],
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $statusRaw = (string) ($data['copyright_status'] ?? CopyrightStatus::ExternalLink->value);
        $status = CopyrightStatus::tryFrom($statusRaw) ?? CopyrightStatus::ExternalLink;

        $tkLabels = [];
        if (isset($data['tk_labels']) && is_array($data['tk_labels'])) {
            $tkLabels = array_values(array_map(strval(...), $data['tk_labels']));
        }

        $careFlags = [];
        if (isset($data['care_flags']) && is_array($data['care_flags'])) {
            $careFlags = $data['care_flags'];
        }

        return new self(
            copyrightStatus: $status,
            license: self::nullableString($data['license'] ?? null),
            consentPublic: (bool) ($data['consent_public'] ?? true),
            consentAiTraining: (bool) ($data['consent_ai_training'] ?? false),
            tkLabels: $tkLabels,
            careFlags: $careFlags,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [
            'copyright_status' => $this->copyrightStatus->value,
            'consent_public' => $this->consentPublic,
            'consent_ai_training' => $this->consentAiTraining,
        ];

        if ($this->license !== null && $this->license !== '') {
            $out['license'] = $this->license;
        }
        if ($this->tkLabels !== []) {
            $out['tk_labels'] = $this->tkLabels;
        }
        if ($this->careFlags !== []) {
            $out['care_flags'] = $this->careFlags;
        }

        return $out;
    }

    /** Permissive default used by backfill: external link, public OK, AI-training gated off. */
    public static function default(): self
    {
        return new self(copyrightStatus: CopyrightStatus::ExternalLink);
    }

    private static function nullableString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (string) $value;
    }
}
