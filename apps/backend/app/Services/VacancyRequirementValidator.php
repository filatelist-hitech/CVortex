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
            if ($this->isInstructionAttack($label.' '.$excerpt)) {
                continue;
            }
            $excerpt = $this->boundClause($label, $excerpt);
            if ($this->isMarketingNoise($excerpt)) {
                continue;
            }
            if ($this->hasNegatedRequirement($label, $excerpt)) {
                continue;
            }
            $dimension = $this->sourceDimension($label, $excerpt);
            $requiresSubjectIdentity = in_array($dimension, ['TECHNICAL', 'DOMAIN', 'EXPERIENCE', 'LANGUAGE'], true)
                || $this->hasCandidateDirectedCue($excerpt);
            $identifiesSubject = $dimension === 'LANGUAGE'
                ? $this->languageTokenFromLabel($label) !== null
                : $this->labelIdentifiesRequirement($label, $excerpt);
            if (! $this->labelSupportedByExcerpt($label, $excerpt)
                || ($requiresSubjectIdentity && ! $identifiesSubject)) {
                throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
            }

            $normalizedValue = $candidate['normalized_value'] === null ? null : trim($candidate['normalized_value']);
            if ($normalizedValue !== null && ! $this->normalizedValueSupported($dimension, $normalizedValue, $label, $excerpt)) {
                throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
            }

            $importance = $this->sourceImportance($label, $excerpt, $candidate['importance']);
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

    private function boundClause(string $label, string $excerpt): string
    {
        // Preserve key/value colons (Location: Berlin), but separate independent
        // sentence, list and semicolon clauses before deriving any semantics.
        $clauses = preg_split('/\.(?=\s|$|[A-ZА-Я])|[;!?\n\r]+|(?<=\s)[•●]+\s*/u', $excerpt, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $matches = array_values(array_filter(array_map('trim', $clauses),
            fn (string $clause): bool => $this->labelSupportedByExcerpt($label, $clause)));
        if (count($matches) !== 1) {
            throw new VacancyOutputException(VacancyOutputException::SEMANTIC_REJECTED);
        }

        return $matches[0];
    }

    private function labelSupportedByExcerpt(string $label, string $excerpt): bool
    {
        $labelTokens = array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', $this->normalize($label), -1, PREG_SPLIT_NO_EMPTY) ?: []);
        $excerptTokens = array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', $this->normalize($excerpt), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return $labelTokens !== [] && array_diff($labelTokens, $excerptTokens) === [];
    }

    private function labelIdentifiesRequirement(string $label, string $excerpt): bool
    {
        $generic = $this->genericRequirementTerms();
        $labelTokens = $this->requirementSubjectTokens($label, array_merge($generic, $this->requirementModifiers()));
        if ($labelTokens === [] || (count($labelTokens) === 1 && in_array($labelTokens[0], $this->roleFragments(), true) && $this->normalize($label) === $labelTokens[0])) {
            return false;
        }
        $cueSubjects = $this->requirementCueSubjectTokens($excerpt, $generic);
        if ($this->hasCandidateDirectedCue($excerpt)) {
            return $this->labelMatchesCueSubject($labelTokens, $cueSubjects);
        }

        return $cueSubjects === [] || $this->labelMatchesCueSubject($labelTokens, $cueSubjects);
    }

    /** @param list<string> $labelTokens
     * @param  list<list<string>>  $cueSubjects
     */
    private function labelMatchesCueSubject(array $labelTokens, array $cueSubjects): bool
    {
        foreach ($cueSubjects as $subject) {
            if (array_diff($labelTokens, $subject) === []) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function requirementModifiers(): array
    {
        return ['strong', 'solid', 'good', 'excellent', 'deep', 'advanced', 'proven', 'practical', 'hands', 'on', 'extensive', 'commercial', 'professional', 'confident', 'fluent'];
    }

    private function hasCandidateDirectedCue(string $excerpt): bool
    {
        return preg_match('/\b(?:needs?|must\s+(?:know|use|operate|have|work|be))\s+(?=[\pL\pN+#.-])/iu', $excerpt) === 1;
    }

    /** @param list<string> $generic
     * @return list<string>
     */
    private function requirementSubjectTokens(string $text, array $generic): array
    {
        return array_values(array_filter(
            array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', $this->normalize($text), -1, PREG_SPLIT_NO_EMPTY) ?: []),
            fn (string $token): bool => ! in_array($token, $generic, true)
                && ! is_numeric($token)
                && preg_match('/^\d+(?:[.,]\d+)?\+?$/u', $token) !== 1,
        ));
    }

    /** @return list<string> */
    private function genericRequirementTerms(): array
    {
        return ['experience', 'building', 'at', 'least', 'minimum', 'required', 'mandatory', 'must', 'have', 'need', 'needed', 'with', 'of', 'for', 'and', 'or', 'skill', 'skills', 'knowledge', 'proficiency', 'level', 'language', 'languages', 'years', 'months', 'year', 'month', 'technology', 'technologies', 'technical', 'tech', 'stack', 'tool', 'tools', 'framework', 'frameworks', 'platform', 'platforms', 'industry', 'sector', 'domain', 'competency', 'competencies', 'qualification', 'qualifications', 'ability', 'abilities'];
    }

    /** @return list<string> */
    private function roleFragments(): array
    {
        return ['backend', 'frontend', 'fullstack', 'full-stack', 'developer', 'engineer', 'engineering', 'development', 'software', 'web', 'mobile', 'data', 'role', 'position', 'team', 'project', 'department', 'environment', 'context'];
    }

    /** @param list<string> $generic
     * @return list<list<string>>
     */
    private function requirementCueSubjectTokens(string $excerpt, array $generic): array
    {
        $subjects = [];
        $subjectTerms = array_merge($generic, $this->requirementModifiers());
        $cue = '(?:required|mandatory|must\\s+have|need(?:ed)?\\s+to\\s+have|will\\s+be\\s+a\\s+plus|nice\\s+to\\s+have|preferred|desirable|optional)';
        preg_match_all('/(?<subject>(?:[\\pL\\pN+#.-]+\\s+){0,5}[\\pL\\pN+#.-]+)(?:\\s+(?:is|are|be))?\\s+'.$cue.'\\b/iu', $excerpt, $passive);
        foreach ($passive['subject'] as $subject) {
            $tokensForSubject = $this->requirementSubjectTokens($subject, array_merge($subjectTerms, ['is', 'are', 'be', 'using', 'this', 'that', 'role', 'position']));
            if ($tokensForSubject !== []) {
                $subjects[] = $tokensForSubject;
            }
        }

        preg_match_all('/\\b(?:this\\s+)?(?:[\\pL\\pN+#.-]+\\s+){0,4}(?:role|position)?\\s*requires\\s+(?<subject>(?:[\\pL\\pN+#.-]+\\s+){0,5}[\\pL\\pN+#.-]+)/iu', $excerpt, $active);
        foreach ($active['subject'] as $subject) {
            $tokensForSubject = $this->requirementSubjectTokens($subject, array_merge($subjectTerms, ['this', 'that', 'role', 'position']));
            if ($tokensForSubject !== []) {
                $subjects[] = $tokensForSubject;
            }
        }

        preg_match_all('/\b(?:needs?|must\s+(?:know|use|operate|have))\s+(?<subject>(?:[\pL\pN+#.-]+\s+){0,12}[\pL\pN+#.-]+)/iu', $excerpt, $candidateDirected);
        foreach ($candidateDirected['subject'] as $subject) {
            $subject = preg_replace('/\s+(?:for|in|on|to\s+succeed\s+in)\s+(?:(?:this|the|our)\s+)?(?:[\pL\pN+#.-]+\s+)?(?:role|position|team|project|department|environment|context)\b.*$/iu', '', $subject) ?? $subject;
            $tokensForSubject = $this->requirementSubjectTokens($subject, array_merge($subjectTerms, ['role', 'position', 'team', 'project', 'department', 'environment', 'context']));
            if ($tokensForSubject !== []) {
                $subjects[] = $tokensForSubject;
            }
        }

        preg_match_all('/\bmust\s+(?<subject>(?:work|be)\s+(?:[\pL\pN+#.-]+\s+){0,11}[\pL\pN+#.-]+)/iu', $excerpt, $arrangementDirected);
        foreach ($arrangementDirected['subject'] as $subject) {
            $tokensForSubject = $this->requirementSubjectTokens($subject, array_merge($subjectTerms, ['role', 'position', 'team', 'project', 'department', 'environment', 'context']));
            if ($tokensForSubject !== []) {
                $subjects[] = $tokensForSubject;
            }
        }

        return $subjects;
    }

    private function normalizedValueSupported(string $dimension, string $value, string $label, string $excerpt): bool
    {
        if ($dimension === 'SALARY') {
            return $this->salaryValueSupported($value, $excerpt);
        }
        if ($dimension === 'EXPERIENCE') {
            return $this->durationValueSupported($value, $excerpt);
        }
        if ($dimension === 'LANGUAGE') {
            return $this->languageValueSupported($value, $excerpt, $label);
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

    private function sourceImportance(string $label, string $excerpt, string $providerImportance): string
    {
        $cueText = $this->importanceCueText($label, $excerpt);
        if ($cueText === null) {
            // Multiple source clauses name the same subject. A provider enum
            // cannot resolve a conflicting source-strength interpretation.
            return 'UNCERTAIN';
        }

        $preferred = $this->hasPreferredCue($cueText);
        $mandatory = preg_match('/\b(?:required|requires?|mandatory|must\s+(?:have|know|use|operate|work|be)|needs?|need(?:ed)?\s+to\s+have|looking\s+for)\b|обязательн|требуется/iu', $cueText) === 1;
        if ($preferred && $mandatory) {
            return 'UNCERTAIN';
        }
        if ($preferred) {
            return 'PREFERRED';
        }
        if ($mandatory) {
            return 'MANDATORY';
        }

        return 'UNCERTAIN';
    }

    private function importanceCueText(string $label, string $excerpt): ?string
    {
        $subjectTokens = $this->requirementSubjectTokens($label, $this->genericRequirementTerms());
        if ($subjectTokens === []) {
            return $excerpt;
        }

        $matches = [];
        foreach (preg_split('/[;.!?\n]+/u', $excerpt, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $clause) {
            if ($this->labelSupportedByExcerpt(implode(' ', $subjectTokens), $clause)) {
                $matches[] = $clause;
            }
        }

        return count($matches) === 1 ? $matches[0] : null;
    }

    private function hasNegatedRequirement(string $label, string $excerpt): bool
    {
        $excerpt = str_replace('’', "'", mb_strtolower($excerpt));
        $excerpt = str_replace(
            ["don't", "doesn't", "didn't", "isn't", "aren't"],
            ['do not', 'does not', 'did not', 'is not', 'are not'],
            $excerpt,
        );
        $subjectTokens = $this->requirementSubjectTokens($label, $this->genericRequirementTerms());
        if ($subjectTokens === []) {
            return preg_match('/\bno\s+[\pL\s]{0,40}\brequired\b|\b(?:is|are)\s+not\s+(?:required|mandatory)\b|не\s+(?:требуется|обязател)/iu', $excerpt) === 1;
        }
        $subject = implode('\\s+', array_map(fn (string $token): string => preg_quote($token, '/'), $subjectTokens));

        return preg_match('/\bno\s+(?:[\pL\s]{0,40}\s)?'.$subject.'\b.{0,40}\brequired\b|\b'.$subject.'\b.{0,40}\b(?:is|are)\s+not\s+(?:required|mandatory|needed|necessary)\b|\b(?:is|are)\s+not\s+required\s+to\s+'.$subject.'\b|\b(?:do|does|did)\s+not\s+(?:require|need)\s+(?:any\s+)?'.$subject.'\b|\b(?:are|is)\s+not\s+looking\s+for\s+'.$subject.'\b|\b'.$subject.'\b.{0,40}\bне\s+(?:требуется|обязател)/iu', $excerpt) === 1;
    }

    private function sourceDimension(string $label, string $excerpt): string
    {
        $text = $this->normalize($excerpt);
        $label = $this->normalize($label);
        $place = preg_replace('/\s+residen(?:ce|cy)$/u', '', $label) ?? $label;
        $workFormat = $this->workFormatValue($excerpt) !== null;

        return match (true) {
            preg_match('/\b(?:salary|compensation|pay|зарплат)/iu', $text) === 1 || preg_match('/\b\d+[\d .]*(?:usd|eur|rub|руб|₽)\b/iu', $text) === 1 => 'SALARY',
            preg_match('/\b(?:location|based in|city|relocat(?:e|ion)?|локац|город)\b/iu', $text) === 1
                || ($place !== '' && preg_match('/\b'.preg_quote($place, '/').'\s+residen(?:ce|cy)\b|\bresiden(?:ce|cy|t)\s+(?:in|at|of)\s+'.preg_quote($place, '/').'\b/iu', $text) === 1)
                || ($workFormat && $place !== '' && ! in_array($place, ['remote', 'remotely', 'hybrid', 'office', 'on-site', 'onsite'], true)
                    && preg_match('/\b(?:in|at|within)\s+'.preg_quote($place, '/').'\b/iu', $text) === 1) => 'LOCATION',
            $workFormat => 'WORK_FORMAT',
            preg_match('/\b(?:industry|sector|domain)\s+experience\b|\bexperience\s+(?:in|within)\s+(?:the\s+)?[\pL\pN-]+\s+(?:industry|sector|domain)\b/iu', $text) === 1 => 'DOMAIN',
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
        $languages = '(?:english|russian|german|french|spanish|английск\w*|русск\w*|немецк\w*|французск\w*|испанск\w*)';
        $qualifier = '(?:a[1-2]|b[1-2]|c[1-2]|fluent|native|fluency|upper[ -]intermediate|professional[ -]working(?:[ -]proficiency)?)';
        $text = $this->normalize($label.' '.$excerpt);

        return preg_match('/\b'.$languages.'\s+(?:(?:at\s+)?'.$qualifier.'(?:\s+level)?|(?:language\s+)?(?:proficiency|level)(?:\s+at)?\s+'.$qualifier.'|is\s+required|required)\b|\b'.$qualifier.'(?:[ -]level)?\s+(?:proficiency\s+)?(?:in\s+)?'.$languages.'\b|\b(?:proficiency|level)\s+(?:at\s+)?(?:in\s+)?'.$languages.'\b|\b'.$languages.'\s+(?:language\s+)?(?:proficiency|level)\b/iu', $text) === 1
            || $this->qualifiedLanguageToken($text) !== null;
    }

    private function qualifiedLanguageToken(string $text): ?string
    {
        $qualifier = '(?:a[1-2]|b[1-2]|c[1-2]|fluent|native|fluency|upper[ -]intermediate|professional[ -]working(?:[ -]proficiency)?)';
        $language = '(?<language>[\\pL][\\pL-]{2,})';
        foreach ([
            '/\\b'.$language.'\\s+(?:(?:at\\s+)?'.$qualifier.'(?:\\s+level)?|(?:language\\s+)?(?:proficiency|level)(?:\\s+at)?\\s+'.$qualifier.')\\b/iu',
            '/\\b'.$qualifier.'(?:[ -]level)?\\s+(?:(?:proficiency\\s+)?in\\s+)?'.$language.'\\b/iu',
        ] as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return $this->normalize($match['language']);
            }
        }

        return null;
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
            $clause = substr($text, $clauseStart, $start - $clauseStart).' '.$match[0][0].' '.substr($after, 0, $clauseLength);
            $durations[] = [
                'amount' => $match['amount'][0],
                'unit' => $match['unit'][0],
                'experience_context' => preg_match('/\bexperience\b|опыт[\pL]*|\b(?:years?|months?)\s+(?:of|with)\s+[\pL][\pL\pN+#.-]*\s+(?:required|mandatory)\b/iu', $clause) === 1,
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
        // A quoted attack string in a bounded security qualification is data.
        // Other clauses, including directives after that example, stay visible.
        $text = preg_replace_callback('/(?:^|[.!?\n])[^.!?\n]*["“][^"”]{1,120}["”][^.!?\n]*/u', static function (array $match): string {
            $clause = $match[0];
            if (preg_match('/\b(?:experience|skill|ability)\b.{0,100}\b(?:detect(?:ing)?|identif(?:y|ying)|recogniz(?:e|ing)|prevent(?:ing)?|defend(?:ing)?\s+against)\b/iu', $clause) === 1
                && preg_match('/\bprompt[ -]injection\b/iu', $clause) === 1) {
                return preg_replace('/["“][^"”]+["”]/u', 'quoted attack example', $clause) ?? $clause;
            }

            return $clause;
        }, $text) ?? $text;
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
            '/\b(?:return|output|emit|print)\s+(?:an?\s+)?(?:empty\s+requirements?\s+(?:array|list)|empty\s+(?:array|list)\s+of\s+requirements?)\b/iu',
            '/(?:^|[.!?;\n]\s*)(?:please\s+)?(?:return|output|produce|emit)\s+no\s+requirements?\b/iu',
            '/\b(?:avoid|prevent|skip|omit|ignore|suppress|do\s+not|don[\'’]t|never)\s+(?:(?:any|all|the)\s+)?(?:extract(?:ing|ion)(?:\s+(?:of|any|the|all))*|pars(?:e|ing)|list(?:ing)?|identify(?:ing)?)\s+(?:(?:any|the|all)\s+)?requirements?\b/iu',
            '/\b(?:avoid|prevent|skip|omit|ignore|suppress|do\s+not|don[\'’]t|never)\s+(?:(?:the|any|all)\s+)?(?:requirement\s+)?(?:extraction|parsing)\b/iu',
            '/\b(?:avoid|prevent|skip|omit|ignore|suppress|do\s+not|don[\'’]t|never)\s+(?:requirement\s+)?pars(?:e|ing)\b.{0,100}\b(?:return|output|produce|emit)\s+(?:nothing|no\s+requirements?|\[\])/iu',
            '/\b(?:skip|omit|ignore|suppress|avoid|prevent)\s+(?:(?:any|all|the)\s+)?requirements?\b/iu',
            '/\b(?:leave|keep)\s+(?:the\s+)?requirements?\s+empty\b/iu',
            '/\b(?:return|output|produce|emit)\s+(?:nothing|no\s+requirements?|\[\]|empty\s+(?:array|list))(?=\s|$|[.!?,])[^.!?\n]{0,80}\b(?:requirements?|extraction|parsing|vacancy|job\s+description)\b|\b(?:requirements?|extraction|parsing|vacancy|job\s+description)\b[^.!?\n]{0,80}\b(?:return|output|produce|emit)\s+(?:nothing|no\s+requirements?|\[\]|empty\s+(?:array|list))(?=\s|$|[.!?,])/iu',
            '/\b(?:do\s+not|don[\'’]t|never)\s+(?:extract|parse|identify|list)\s+(?:any\s+)?(?:requirements?|items?|results?|anything)\b/iu',
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
        $amountPattern = '(\d{1,3}(?:[ \x{00A0}\x{202F}]\d{3})+|\d+(?:[.,]\d+)?)';
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
            $amounts = array_map(fn (string $amount): string => $this->normalizeSalaryAmount($amount), $amounts);
            if ($sourceCurrency === $currency && $amounts === $expectedAmounts[0]) {
                return true;
            }
        }

        return false;
    }

    private function languageValueSupported(string $value, string $excerpt, string $label): bool
    {
        $value = $this->normalize($value);
        $expectedLanguage = $this->languageTokenFromLabel($label);
        if ($expectedLanguage === null) {
            return false;
        }
        $language = '(?<language>[\\pL][\\pL-]{2,})';
        $qualification = '(?<qualification>a[1-2]|b[1-2]|c[1-2]|fluent|native|fluency|upper[ -]intermediate|professional[ -]working(?:[ -]proficiency)?)';
        $patterns = [
            '/\b'.$language.'\s*(?:at\s+)?(?:(?:language\s+)?(?:proficiency|level)\s*(?:at\s+)?)?(?::|is|of)?\s*'.$qualification.'(?:\s+level)?\b/iu',
            '/\b'.$qualification.'(?:[ -]level)?\s+(?:(?:proficiency\s+)?in\s+)?'.$language.'\b/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $excerpt, $match) === 1
                && $this->canonicalLanguageName($match['language']) === $expectedLanguage
                && $this->normalize($match['qualification']) === $value) {
                return true;
            }
        }

        return false;
    }

    private function languageTokenFromLabel(string $label): ?string
    {
        foreach (['english' => '(?:english|английск\\pL*)', 'russian' => '(?:russian|русск\\pL*)', 'german' => '(?:german|немецк\\pL*)', 'french' => '(?:french|французск\\pL*)', 'spanish' => '(?:spanish|испанск\\pL*)'] as $language => $pattern) {
            if (preg_match('/\\b'.$pattern.'\\b/iu', $this->normalize($label)) === 1) {
                return $language;
            }
        }

        return $this->qualifiedLanguageToken($this->normalize($label));
    }

    private function canonicalLanguageName(string $language): string
    {
        $language = $this->normalize($language);
        foreach (['english' => '(?:english|английск\\pL*)', 'russian' => '(?:russian|русск\\pL*)', 'german' => '(?:german|немецк\\pL*)', 'french' => '(?:french|французск\\pL*)', 'spanish' => '(?:spanish|испанск\\pL*)'] as $canonical => $pattern) {
            if (preg_match('/\\b'.$pattern.'\\b/iu', $language) === 1) {
                return $canonical;
            }
        }

        return $language;
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

    private function normalizeSalaryAmount(string $amount): string
    {
        return str_replace([' ', "\xC2\xA0", "\xE2\x80\xAF"], '', $amount);
    }

    private function isMarketingNoise(string $text): bool
    {
        // A grammatical requirement for the product, architecture or business
        // is not a candidate qualification.
        if (preg_match('/\b(?:our|the|this)\s+(?:mission|growth|success|business|product|service|architecture|system|stack)\s+(?:requires?|needs?|must)\b/iu', $text) === 1) {
            return true;
        }
        if (preg_match('/\b(?:candidates?|applicants?|you|engineers?|developers?|the\s+(?:role|position))\b.{0,100}\b(?:requires?|needs?|must|looking\s+for|required|mandatory)\b/iu', $text) === 1) {
            return false;
        }
        if (preg_match('/\b(?:we|our\s+(?:company|team))\b.{0,40}\b(?:looking\s+for|seek)\b.{0,100}\b(?:engineers?|developers?|candidates?|applicants?)\b.{0,60}\b(?:with|who\s+have)\b/iu', $text) === 1) {
            return false;
        }
        if (preg_match('/\b(?i:we|our\s+(?:company|team))\b.{0,40}\b(?i:requires?|needs?|looking\s+for|seek)\b.{0,100}\b(?:(?i:experience|skills?|knowledge|proficiency|years?|certification|engineers?|developers?)|[A-Z][\pL\pN+#.-]{2,})\b/u', $text) === 1) {
            return false;
        }

        return preg_match('/\b(?:we\s+are|our\s+(?:company|team|mission|growth|success|business)|world.class|market\s+leader|we\s+offer|benefits\s+include)\b|наша\s+(?:компания|миссия|команда)|мы\s+предлагаем/iu', $text) === 1;
    }

    private function normalize(string $value): string
    {
        return trim((string) preg_replace('/[^\pL\pN+#.]+/u', ' ', mb_strtolower($value)));
    }
}
