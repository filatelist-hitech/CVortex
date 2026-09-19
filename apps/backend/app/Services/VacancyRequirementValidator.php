<?php

namespace App\Services;

use App\AI\Exceptions\VacancyOutputException;
use App\Models\VacancyRequirement;

class VacancyRequirementValidator
{
    /** @param array<string, mixed> $output
     * @return list<array{dimension: string, importance: string, label: string, normalized_value: ?string, source_excerpt: string, confidence: float}>
     */
    public function validate(array $output, string $sourceText): array
    {
        $requirements = $output['requirements'] ?? null;
        if (array_keys($output) !== ['requirements'] || ! is_array($requirements)
            || ! array_is_list($requirements) || count($requirements) > 100) {
            throw new VacancyOutputException(VacancyOutputException::SCHEMA_INVALID);
        }

        $validated = [];
        foreach ($requirements as $candidate) {
            if (! is_array($candidate)
                || count($candidate) !== 6
                || array_diff(['dimension', 'importance', 'label', 'normalized_value', 'source_excerpt', 'confidence'], array_keys($candidate)) !== []
                || ! is_string($candidate['dimension'] ?? null)
                || ! in_array($candidate['dimension'], VacancyRequirement::DIMENSIONS, true)
                || ! is_string($candidate['importance'] ?? null)
                || ! in_array($candidate['importance'], ['MANDATORY', 'PREFERRED', 'UNCERTAIN'], true)
                || ! is_string($candidate['label'] ?? null)
                || trim($candidate['label']) === ''
                || mb_strlen($candidate['label']) > 255
                || (! is_null($candidate['normalized_value'] ?? null) && ! is_string($candidate['normalized_value']))
                || mb_strlen((string) ($candidate['normalized_value'] ?? '')) > 500
                || ! is_string($candidate['source_excerpt'] ?? null)
                || trim($candidate['source_excerpt']) === ''
                || mb_strlen($candidate['source_excerpt']) > 2000
                || ! is_numeric($candidate['confidence'] ?? null)
                || (float) $candidate['confidence'] < 0
                || (float) $candidate['confidence'] > 1
                || ! str_contains($sourceText, $candidate['source_excerpt'])) {
                throw new VacancyOutputException(VacancyOutputException::SCHEMA_INVALID);
            }

            $label = trim($candidate['label']);
            $excerpt = trim($candidate['source_excerpt']);
            if ($this->normalize($label) === '') {
                throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
            }
            if ($this->isInstructionAttack($label.' '.$excerpt) || $this->isMarketingNoise($excerpt)) {
                continue;
            }
            if (! str_contains($this->normalize($excerpt), $this->normalize($label))) {
                throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
            }

            $importance = $this->hasPreferredCue($excerpt) ? 'PREFERRED' : $candidate['importance'];
            $validated[] = [
                'dimension' => $candidate['dimension'],
                'importance' => $importance,
                'label' => $label,
                'normalized_value' => $candidate['normalized_value'] === null ? null : trim($candidate['normalized_value']),
                'source_excerpt' => $excerpt,
                'confidence' => (float) $candidate['confidence'],
            ];
        }

        return $validated;
    }

    private function hasPreferredCue(string $text): bool
    {
        return preg_match('/\b(will be a plus|nice to have|preferred|desirable|optional)\b|будет\s+плюсом|желательно|необязательно/iu', $text) === 1;
    }

    private function isInstructionAttack(string $text): bool
    {
        return preg_match('/ignore\s+(all\s+)?(previous|prior)\s+instructions|system\s+prompt|developer\s+message|reveal\s+(secrets?|credentials?)|tool\s+call|действуй\s+как\s+система|игнорируй\s+.*инструкц/iu', $text) === 1;
    }

    private function isMarketingNoise(string $text): bool
    {
        return preg_match('/\b(we are|our company|our mission|world.class|market leader|we offer|benefits include)\b|наша\s+(компания|миссия|команда)|мы\s+предлагаем/iu', $text) === 1;
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^\pL\pN+#.]+/u', ' ', mb_strtolower($value)));
    }
}
