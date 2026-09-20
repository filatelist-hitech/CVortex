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
            if ($this->hasNegatedRequirement($excerpt)) {
                continue;
            }
            $dimension = $this->sourceDimension($label, $excerpt);
            if (! $this->labelSupportedByExcerpt($label, $excerpt)
                || (in_array($dimension, ['TECHNICAL', 'DOMAIN'], true) && ! $this->labelIdentifiesRequirement($label, $excerpt))) {
                throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
            }

            $normalizedValue = $candidate['normalized_value'] === null ? null : trim($candidate['normalized_value']);
            if ($normalizedValue !== null && ! $this->normalizedValueSupported($dimension, $normalizedValue, $excerpt)) {
                throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
            }

            $importance = $this->sourceImportance($excerpt, $candidate['importance']);
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

    private function labelIdentifiesRequirement(string $label, string $excerpt): bool
    {
        $generic = ['experience', 'required', 'mandatory', 'must', 'have', 'need', 'needed', 'with', 'of', 'for', 'and', 'or', 'skill', 'skills', 'knowledge', 'proficiency', 'level', 'language', 'languages', 'years', 'months', 'year', 'month'];
        $tokens = fn (string $text): array => array_values(array_filter(
            array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', $this->normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: []),
            fn (string $token): bool => ! in_array($token, $generic, true) && ! is_numeric($token),
        ));

        return $tokens($label) !== [] || $tokens($excerpt) === [];
    }

    private function normalizedValueSupported(string $dimension, string $value, string $excerpt): bool
    {
        if ($dimension === 'SALARY') {
            return $this->salaryValueSupported($value, $excerpt);
        }
        if ($dimension === 'EXPERIENCE') {
            return $this->durationValueSupported($value, $excerpt);
        }
        if ($dimension === 'LANGUAGE') {
            return $this->languageValueSupported($value, $excerpt);
        }
        if (preg_match('/^(?:years|months):\d+(?:[.,]\d+)?$/iu', trim($value)) === 1) {
            return false;
        }

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

        // Numeric/currency separators are structural; every semantic token must
        // still be present in the verbatim supporting excerpt.
        $tokens = preg_split('/\s+/u', (string) preg_replace('/[^\pL\pN+#.]+/u', ' ', $value), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $tokens !== [] && $this->labelSupportedByExcerpt(implode(' ', $tokens), $evidence);
    }

    private function hasPreferredCue(string $text): bool
    {
        return preg_match('/\b(will be a plus|nice to have|preferred|desirable|optional)\b|будет\s+плюсом|желательно|необязательно/iu', $text) === 1;
    }

    private function sourceImportance(string $excerpt, string $providerImportance): string
    {
        if ($this->hasPreferredCue($excerpt)) {
            return 'PREFERRED';
        }

        if (preg_match('/\b(?:required|mandatory|must\s+have|need(?:ed)?\s+to\s+have)\b|обязательн|требуется/iu', $excerpt) === 1) {
            return 'MANDATORY';
        }

        return $providerImportance;
    }

    private function hasNegatedRequirement(string $excerpt): bool
    {
        return preg_match('/\bno\s+[\pL\s]{0,40}\brequired\b|\b(?:is|are)\s+not\s+(?:required|mandatory)\b|не\s+(?:требуется|обязател)/iu', $excerpt) === 1;
    }

    private function sourceDimension(string $label, string $excerpt): string
    {
        $text = $this->normalize($excerpt);
        $label = $this->normalize($label);

        return match (true) {
            preg_match('/\b(?:salary|compensation|pay|зарплат)/iu', $text) === 1 || preg_match('/\b\d+[\d .]*(?:usd|eur|rub|руб|₽)\b/iu', $text) === 1 => 'SALARY',
            $this->workFormatValue($excerpt) !== null => 'WORK_FORMAT',
            preg_match('/\b(?:location|based in|city|relocat|локац|город)\b/iu', $text) === 1 => 'LOCATION',
            $this->hasExperienceDuration($excerpt)
                || preg_match('/\b(?:commercial|professional|production)\s+\w*\s*experience\b|\bexperience\s+(?:with|of)\b/iu', $text) === 1
                || preg_match('/\bexperience\s+(?:with|of)\b/iu', $label) === 1 => 'EXPERIENCE',
            $this->hasLanguageRequirement($label, $excerpt) => 'LANGUAGE',
            preg_match('/\b(?:domain|industry|fintech|e[ -]?commerce|healthcare|retail|banking|telecom)\b/iu', $label) === 1 => 'DOMAIN',
            default => 'TECHNICAL',
        };
    }

    private function hasLanguageRequirement(string $label, string $excerpt): bool
    {
        $languages = '(?:english|russian|german|french|spanish|английск\w*|русск\w*|немецк\w*|французск\w*)';
        $qualifier = '(?:a[1-2]|b[1-2]|c[1-2]|fluent|native|fluency|upper[ -]intermediate|professional[ -]working(?:[ -]proficiency)?)';
        $text = $this->normalize($label.' '.$excerpt);

        return preg_match('/\b'.$languages.'\s+(?:'.$qualifier.'|(?:language\s+)?(?:proficiency|level)|is\s+required|required)\b|\b'.$qualifier.'\s+(?:proficiency\s+)?(?:in\s+)?'.$languages.'\b|\b(?:proficiency|level)\s+(?:in\s+)?'.$languages.'\b|\b'.$languages.'\s+(?:language\s+)?(?:proficiency|level)\b/iu', $text) === 1;
    }

    private function hasExperienceDuration(string $text): bool
    {
        foreach ($this->durationExpressions($text) as $duration) {
            if ($duration['experience_context']) {
                return true;
            }
        }

        return false;
    }

    /** @return list<array{amount: string, unit: string, experience_context: bool}> */
    private function durationExpressions(string $text): array
    {
        preg_match_all('/(?<![\pL\pN])(?<amount>\d+(?:[.,]\d+)?)\s*\+?\s*(?<unit>years?|months?|лет|год(?:а|ов)?|месяц[\pL]*)(?!\pL)/iu', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $durations = [];
        foreach ($matches as $match) {
            $start = $match['amount'][1];
            $end = $match['unit'][1] + strlen($match['unit'][0]);
            $before = substr($text, 0, $start);
            $after = substr($text, $end);
            preg_match_all('/[,;.!?\n]/u', $before, $beforeBoundaries, PREG_OFFSET_CAPTURE);
            preg_match('/[,;.!?\n]/u', $after, $afterBoundary, PREG_OFFSET_CAPTURE);
            $lastBoundary = $beforeBoundaries[0] === [] ? null : $beforeBoundaries[0][count($beforeBoundaries[0]) - 1];
            $clauseStart = $lastBoundary === null ? 0 : $lastBoundary[1] + 1;
            $clauseLength = ($afterBoundary[0][1] ?? strlen($after));
            $clause = substr($text, $clauseStart, $start - $clauseStart).' '.substr($after, 0, $clauseLength);
            $durations[] = [
                'amount' => $match['amount'][0],
                'unit' => $match['unit'][0],
                'experience_context' => preg_match('/\bexperience\b|опыт[\pL]*/iu', $clause) === 1,
            ];
        }

        return $durations;
    }

    private function workFormatValue(string $text): ?string
    {
        $patterns = [
            'remote' => '/\b(?:work format|формат работы)\s*:\s*(?:remote|удаленно)\b|\b(?:fully\s+)?remote\s+(?:work|position|role|arrangement|schedule|job|required)\b|\bfully\s+remote\b|\bwork(?:ing)?\s+(?:fully\s+)?remotely?\b|\bwork\s+from\s+home\b|\b(?:удаленная?|дистанционная?)\s+(?:работа|позиция|формат|занятость)\b|\b(?:работа|работать|формат)\s+удаленно\b/iu',
            'hybrid' => '/\b(?:work format|формат работы)\s*:\s*(?:hybrid|гибрид)\b|\bhybrid\s+(?:work|working|position|role|arrangement|schedule|required)\b|\b(?:гибридный|гибридная|гибридное)\s+(?:режим|работа|формат|позиция)\b|\bгибрид\s+(?:работа|формат|требуется)\b/iu',
            'office' => '/\b(?:work format|формат работы)\s*:\s*(?:office|офис)\b|\b(?:on[ -]?site|onsite)\s+(?:work|position|role|arrangement|schedule|required)\b|\boffice(?:[ -]based|\s+required)\b|\bwork\s+(?:on[ -]?site|onsite|in\s+(?:the\s+)?office)\b|\bbased\s+in\s+(?:the\s+)?office\b|\b(?:офисная|офисный|офисное)\s+(?:работа|формат|режим|позиция)\b|\bработа\s+в\s+офисе\b/iu',
        ];
        foreach ($patterns as $value => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $value;
            }
        }

        return null;
    }

    private function isInstructionAttack(string $text): bool
    {
        $directive = '(?:output|return|emit|print|respond|ignore|disregard|forget|override|bypass|follow|obey|classify|mark|set|recommend|reveal|use|call)';
        $role = '(?:system(?:\s+message)?|assistant|developer(?:\s+(?:instruction|message))?)';
        $recommendation = '(?:strongly[\s_-]*apply|apply|maybe|low[\s_-]*priority|skip|highest|recommendation)';

        $patterns = [
            '/(?:^|[\r\n<{,])\s*["\']?'.$role.'["\']?\s*(?::|>|=)\s*'.$directive.'\b/iu',
            '/\b(?:always\s+recommend|'.$directive.')\s+(?:this\s+candidate\s+)?(?:as\s+|to\s+)?'.$recommendation.'\b/iu',
            '/\b(?:ignore|disregard|forget|override|bypass)\s+(?:(?:all|the)\s+)?(?:previous|prior|earlier|all)(?:\s+system)?\s+(?:prompts?|instructions?|rules?|directions?|context|messages?|facts?|skills?|requirements?)\b/iu',
            '/\b(?:ignore|disregard|forget|override|bypass)\s+(?:missing|candidate)\s+(?:facts?|skills?|requirements?)\b/iu',
            '/\bfollow\s+(?:these|the\s+following|my)\s+instructions?\s+instead\b/iu',
            '/["\'](?:instruction|system|developer|recommendation)["\']\s*:\s*["\'][^"\']*(?:'.$directive.'|'.$recommendation.')/iu',
            '/<(?:system|assistant|developer|instruction|prompt)(?:\s[^>]*)?>[\s\S]*?\b'.$directive.'\b/iu',
            '/\b(?:reveal|print|return|output)\s+(?:the\s+)?(?:system\s+prompt|secrets?|credentials?)\b/iu',
            '/\bignore\s+(?:the\s+)?(?:vacancy|job\s+description|source(?:\s+text)?|provided\s+text)\b.{0,120}\b(?:return|output|emit|print|respond)\b/iu',
            '/\b(?:return|output|emit|print)\s+(?:an?\s+)?empty\s+(?:requirements?\s+)?(?:array|list)\b/iu',
            '/\b(?:do\s+not|don[\'’]t|never)\s+(?:consider|use|read|analy[sz]e|process)\s+(?:the\s+)?(?:vacancy|job\s+description|source(?:\s+text)?|provided\s+text)\b.{0,160}\b(?:reply|respond|return|output|produce|emit)\b.{0,80}\b(?:zero|no|empty|nothing)\s+(?:items?|requirements?|results?|output)\b/iu',
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

    private function salaryValueSupported(string $value, string $excerpt): bool
    {
        if (preg_match('/(?:^|[^\pL])(?<currency>usd|eur|rub|руб|₽)(?=$|[^\pL])/iu', $value, $currency) !== 1) {
            return false;
        }
        preg_match_all('/\d+(?:[.,]\d+)?/u', $value, $expectedAmounts);
        if ($expectedAmounts[0] === []) {
            return false;
        }

        $currency = $this->salaryCurrency($currency['currency']);
        $currencyPattern = '(usd|eur|rub|руб|₽)';
        $amountPattern = '(\d+(?:[.,]\d+)?)';
        $salaryEvidence = $excerpt;
        if (preg_match('/\b(?:salary|compensation|pay|зарплата)\b\s*(?<tail>[^;!?\n]{0,160})/iu', $excerpt, $context) === 1) {
            $salaryEvidence = $context['tail'];
        }
        if (preg_match('/(?<!\d)\.(?!\d)/u', $salaryEvidence, $sentenceEnd, PREG_OFFSET_CAPTURE) === 1) {
            $salaryEvidence = substr($salaryEvidence, 0, $sentenceEnd[0][1]);
        }
        $expressions = [
            ['pattern' => '/'.$currencyPattern.'\s*[: ]?\s*'.$amountPattern.'(?:\s*(?:-|–|—|to)\s*'.$amountPattern.')?/iu', 'currency_index' => 1, 'amount_index' => 2],
            ['pattern' => '/'.$amountPattern.'(?:\s*(?:-|–|—|to)\s*'.$amountPattern.')?\s*'.$currencyPattern.'/iu', 'currency_index' => 3, 'amount_index' => 1],
        ];
        foreach ($expressions as $expression) {
            if (preg_match($expression['pattern'], $salaryEvidence, $match) !== 1) {
                continue;
            }
            $sourceCurrency = $this->salaryCurrency($match[$expression['currency_index']] ?? '');
            $amountOffset = $expression['amount_index'];
            $amounts = [$match[$amountOffset]];
            if (isset($match[$amountOffset + 1]) && $match[$amountOffset + 1] !== '') {
                $amounts[] = $match[$amountOffset + 1];
            }
            if ($sourceCurrency === $currency && $amounts === $expectedAmounts[0]) {
                return true;
            }
        }

        return false;
    }

    private function languageValueSupported(string $value, string $excerpt): bool
    {
        $value = $this->normalize($value);
        $language = '(?:english|russian|german|french|spanish|английск\pL*|русск\pL*|немецк\pL*|французск\pL*)';
        $qualification = '(a[1-2]|b[1-2]|c[1-2]|fluent|native|fluency|upper[ -]intermediate|professional[ -]working(?:[ -]proficiency)?)';
        $patterns = [
            '/\b'.$language.'\s*(?:(?:language\s+)?(?:proficiency|level)\s*)?(?::|is|of)?\s*'.$qualification.'\b/iu',
            '/\b'.$qualification.'\s+(?:(?:proficiency\s+)?in\s+)?'.$language.'\b/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $excerpt, $match) === 1 && $this->normalize($match[1]) === $value) {
                return true;
            }
        }

        return false;
    }

    private function durationValueSupported(string $value, string $excerpt): bool
    {
        if (preg_match('/^(years|months):(\d+(?:[.,]\d+)?)$/iu', trim($value), $normalized) !== 1) {
            return false;
        }
        $durations = array_values(array_filter($this->durationExpressions($excerpt), fn (array $item): bool => $item['experience_context']));
        if (count($durations) !== 1) {
            return false;
        }
        $source = $durations[0];

        return mb_strtolower($normalized[1]) === $this->durationUnit($source['unit'])
            && (float) str_replace(',', '.', $normalized[2]) === (float) str_replace(',', '.', $source['amount']);
    }

    private function durationUnit(string $unit): string
    {
        return preg_match('/^(?:years?|лет|год(?:а|ов)?)$/iu', $unit) === 1 ? 'years' : 'months';
    }

    private function salaryCurrency(string $currency): string
    {
        return in_array(mb_strtolower($currency), ['руб', '₽'], true) ? 'rub' : mb_strtolower($currency);
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
