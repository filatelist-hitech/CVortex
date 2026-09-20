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

        // If source text contains instructions aimed at the model, completeness
        // cannot be inferred from a possibly suppressed (even empty) response.
        if ($this->isInstructionAttack($sourceText)) {
            throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
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
            if (! $this->labelSupportedByExcerpt($label, $excerpt)) {
                throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
            }

            $normalizedValue = $candidate['normalized_value'] === null ? null : trim($candidate['normalized_value']);
            $dimension = $this->sourceDimension($label, $excerpt);
            if ($normalizedValue !== null && ! $this->normalizedValueSupported($dimension, $normalizedValue, $excerpt)) {
                throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
            }

            $importance = $this->hasPreferredCue($excerpt) ? 'PREFERRED' : $candidate['importance'];
            $validated[] = [
                // Provider enum values are untrusted derived data. Source wording
                // determines the persisted match dimension.
                'dimension' => $dimension,
                'importance' => $importance,
                'label' => $label,
                'normalized_value' => $normalizedValue,
                'source_excerpt' => $excerpt,
                'confidence' => (float) $candidate['confidence'],
            ];
        }

        return $validated;
    }

    private function labelSupportedByExcerpt(string $label, string $excerpt): bool
    {
        $labelTokens = array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', $this->normalize($label), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $excerptTokens = array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', $this->normalize($excerpt), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return $labelTokens !== [] && array_diff($labelTokens, $excerptTokens) === [];
    }

    private function normalizedValueSupported(string $dimension, string $value, string $excerpt): bool
    {
        $value = $this->normalize($value);
        $evidence = $this->normalize($excerpt);
        if ($dimension === 'WORK_FORMAT') {
            $aliases = [
                'remote' => ['remote', 'remotely', 'work from home', 'удаленно'],
                'hybrid' => ['hybrid', 'гибрид'],
                'office' => ['office', 'on-site', 'onsite', 'офис'],
            ];
            $supported = [];
            foreach ($aliases as $canonical => $forms) {
                foreach ($forms as $form) {
                    if (preg_match('/\b'.preg_quote($this->normalize($form), '/').'\b/iu', $evidence) === 1) {
                        $supported[$canonical] = true;
                    }
                }
            }

            return $value !== '' && count($supported) === 1 && isset($supported[$value]);
        }

        // Numeric/currency separators are structural, but value-bearing numbers
        // must retain the source order so a model cannot swap range endpoints.
        if (in_array($dimension, ['EXPERIENCE', 'SALARY'], true)) {
            preg_match_all('/\d+(?:[.,]\d+)?/u', $value, $valueNumbers);
            preg_match_all('/\d+(?:[.,]\d+)?/u', $evidence, $evidenceNumbers);
            if ($valueNumbers[0] === [] || array_slice($evidenceNumbers[0], 0, count($valueNumbers[0])) !== $valueNumbers[0]) {
                return false;
            }
            if ($dimension === 'EXPERIENCE') {
                return true;
            }
        }

        // Numeric/currency separators are structural; every semantic token must
        // still be present in the verbatim supporting excerpt.
        $tokens = preg_split('/\s+/u', (string) preg_replace('/[^\pL\pN+#.]+/u', ' ', $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $tokens !== [] && $this->labelSupportedByExcerpt(implode(' ', $tokens), $evidence);
    }

    private function hasPreferredCue(string $text): bool
    {
        return preg_match('/\b(will be a plus|nice to have|preferred|desirable|optional)\b|будет\s+плюсом|желательно|необязательно/iu', $text) === 1;
    }

    private function sourceDimension(string $label, string $excerpt): string
    {
        $text = $this->normalize($excerpt);
        $label = $this->normalize($label);

        return match (true) {
            preg_match('/\b(?:salary|compensation|pay|зарплат)/iu', $text) === 1 || preg_match('/\b\d+[\d .]*(?:usd|eur|rub|руб|₽)\b/iu', $text) === 1 => 'SALARY',
            preg_match('/\b(?:remote|hybrid|office|on site|onsite|work from home|удаленно|гибрид|офис)\b/iu', $text) === 1 => 'WORK_FORMAT',
            preg_match('/\b(?:location|based in|city|relocat|локац|город)\b/iu', $text) === 1 => 'LOCATION',
            preg_match('/\b(?:english|russian|german|french|spanish|язык|английск|русск|немецк|французск)\b/iu', $text) === 1 => 'LANGUAGE',
            preg_match('/\b(?:\d+(?:[.,]\d+)?\s*\+?\s*(?:years?|лет|года)|experience\s+(?:with|of)|(?:minimum|at least)\s+\d+|(?:commercial|professional|production)\s+\w*\s*experience)\b/iu', $label) === 1 => 'EXPERIENCE',
            preg_match('/\b(?:domain|industry|fintech|e[ -]?commerce|healthcare|retail|banking|telecom)\b/iu', $label) === 1 => 'DOMAIN',
            default => 'TECHNICAL',
        };
    }

    private function isInstructionAttack(string $text): bool
    {
        $directive = '(?:output|return|emit|print|respond|ignore|disregard|forget|override|bypass|follow|obey|classify|mark|set|recommend|reveal|use|call)';
        $role = '(?:system(?:\s+message)?|assistant|developer(?:\s+(?:instruction|message))?)';
        $recommendation = '(?:strongly[\s_-]*apply|apply|maybe|low[\s_-]*priority|skip|highest|recommendation)';

        $patterns = [
            '/(?:^|[\r\n<{,])\s*["\']?'.$role.'["\']?\s*(?::|>|=)\s*'.$directive.'\b/iu',
            '/\b(?:always\s+recommend|'.$directive.')\s+(?:this\s+candidate\s+)?(?:as\s+|to\s+)?'.$recommendation.'\b/iu',
            '/\b(?:ignore|disregard|forget|override|bypass)\s+(?:(?:all|the)\s+)?(?:previous|prior|all)(?:\s+system)?\s+(?:prompts?|instructions?|rules?|context|messages?|facts?|skills?|requirements?)\b/iu',
            '/\b(?:ignore|disregard|forget|override|bypass)\s+(?:missing|candidate)\s+(?:facts?|skills?|requirements?)\b/iu',
            '/\bfollow\s+(?:these|the\s+following|my)\s+instructions?\s+instead\b/iu',
            '/["\'](?:instruction|system|developer|recommendation)["\']\s*:\s*["\'][^"\']*(?:'.$directive.'|'.$recommendation.')/iu',
            '/<(?:system|assistant|developer|instruction|prompt)(?:\s[^>]*)?>[\s\S]*?\b'.$directive.'\b/iu',
            '/\b(?:reveal|print|return|output)\s+(?:the\s+)?(?:system\s+prompt|secrets?|credentials?)\b/iu',
            '/\bignore\s+(?:the\s+)?(?:vacancy|job\s+description|source(?:\s+text)?|provided\s+text)\b.{0,120}\b(?:return|output|emit|print|respond)\b/iu',
            '/\b(?:return|output|emit|print)\s+(?:an?\s+)?empty\s+(?:requirements?\s+)?(?:array|list)\b/iu',
            '/\b(?:invoke|execute|make)\s+(?:a\s+)?tool\s+call\b/iu',
            '/игнорируй\s+.*(?:инструкц|правил)|(?:системное\s+сообщение|ассистент|инструкция\s+разработчика)\s*:\s*(?:выведи|верни|игнорируй|оцени)/iu',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return true;
            }
        }

        return false;
    }

    private function isMarketingNoise(string $text): bool
    {
        if (preg_match('/\b(?:we are looking for|we seek|our team is looking for)\b/iu', $text) === 1
            && preg_match('/\b(?:with|who have|experience|skills?|proficiency|knowledge|degree|certification|required|must have)\b/iu', $text) === 1) {
            return false;
        }

        return preg_match('/\b(we are|our company|our mission|world.class|market leader|we offer|benefits include)\b|наша\s+(компания|миссия|команда)|мы\s+предлагаем/iu', $text) === 1;
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^\pL\pN+#.]+/u', ' ', mb_strtolower($value)));
    }
}
