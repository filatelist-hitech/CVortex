<?php

namespace App\Services;

use App\Models\CareerFact;
use App\Models\Claim;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancyMatchDimension;
use App\Models\VacancyMatchEvidence;
use App\Models\VacancyRequirement;
use App\Models\VacancySnapshot;
use Illuminate\Support\Facades\DB;

class VacancyMatchingService
{
    public const ANALYSIS_VERSION = '1.0.0';

    public function __construct(private readonly TrustedCareerQuery $career) {}

    public function careerSignature(User $user): string
    {
        $context = $this->career->forMatching($user);

        return $this->signatureForContext($context);
    }

    /** @param array{facts: list<CareerFact>, claims: list<Claim>} $context */
    private function signatureForContext(array $context): string
    {
        $values = [];
        foreach ($context['facts'] as $fact) {
            $values[] = 'fact:'.$fact->id.':'.$fact->updated_at?->toJSON().':'.$fact->approvedAssertion();
        }
        foreach ($context['claims'] as $claim) {
            $values[] = 'claim:'.$claim->id.':'.$claim->updated_at?->toJSON().':'.$claim->statement;
        }
        sort($values);

        return hash('sha256', implode("\n", $values));
    }

    public function analyze(User $user, Vacancy $vacancy, VacancySnapshot $snapshot): VacancyAnalysis
    {
        $context = $this->career->forMatching($user);
        $signature = $this->signatureForContext($context);
        $requirements = VacancyRequirement::query()
            ->where('owner_id', $user->id)
            ->where('vacancy_snapshot_id', $snapshot->id)
            ->orderBy('created_at')
            ->get();

        return DB::transaction(function () use ($user, $vacancy, $snapshot, $context, $signature, $requirements): VacancyAnalysis {
            $analysis = VacancyAnalysis::query()->firstOrCreate(
                ['vacancy_snapshot_id' => $snapshot->id, 'career_signature' => $signature],
                [
                    'owner_id' => $user->id,
                    'vacancy_id' => $vacancy->id,
                    'recommendation' => 'MAYBE',
                    'key_reasons' => [],
                    'material_gaps' => [],
                    'uncertainties' => [],
                    'analysis_version' => self::ANALYSIS_VERSION,
                ],
            );
            VacancyMatchDimension::query()->where('vacancy_analysis_id', $analysis->id)->delete();

            $dimensionResults = [];
            foreach (VacancyRequirement::DIMENSIONS as $dimension) {
                $dimensionRequirements = $requirements->where('dimension', $dimension)->values()->all();
                $dimensionResults[] = $this->evaluateDimension($dimension, $dimensionRequirements, $context['facts'], $context['claims']);
            }

            $recommendation = $this->recommendation($dimensionResults);
            $gaps = array_values(array_merge(...array_map(fn (array $item): array => $item['gaps'], $dimensionResults)));
            $uncertainties = array_values(array_merge(...array_map(fn (array $item): array => $item['uncertainties'], $dimensionResults)));
            $reasons = array_values(array_map(
                fn (array $item): string => $item['dimension'].': '.$item['explanation'],
                array_filter($dimensionResults, fn (array $item): bool => in_array($item['result'], ['MATCH', 'ADJACENT', 'BLOCKER'], true)),
            ));
            if ($reasons === []) {
                $reasons[] = 'Candidate evidence is incomplete, so the recommendation remains cautious.';
            }
            $analysis->forceFill([
                'recommendation' => $recommendation,
                'key_reasons' => $reasons,
                'material_gaps' => $gaps,
                'uncertainties' => $uncertainties,
                'analysis_version' => self::ANALYSIS_VERSION,
            ])->save();

            foreach ($dimensionResults as $item) {
                $dimension = VacancyMatchDimension::query()->create([
                    'owner_id' => $user->id,
                    'vacancy_analysis_id' => $analysis->id,
                    'dimension' => $item['dimension'],
                    'result' => $item['result'],
                    'explanation' => $item['explanation'],
                    'origin' => 'DETERMINISTIC',
                    'vacancy_requirement_ids' => $item['requirement_ids'],
                ]);
                foreach ($item['evidence'] as $evidence) {
                    VacancyMatchEvidence::query()->firstOrCreate([
                        'owner_id' => $user->id,
                        'vacancy_match_dimension_id' => $dimension->id,
                        'career_fact_id' => $evidence['type'] === 'fact' ? $evidence['id'] : null,
                        'claim_id' => $evidence['type'] === 'claim' ? $evidence['id'] : null,
                    ]);
                }
            }

            return $analysis->fresh();
        });
    }

    /** @param list<VacancyRequirement> $requirements
     * @param  list<CareerFact>  $facts
     * @param  list<Claim>  $claims
     * @return array<string, mixed>
     */
    private function evaluateDimension(string $dimension, array $requirements, array $facts, array $claims): array
    {
        if ($requirements === []) {
            return [
                'dimension' => $dimension,
                'result' => 'NOT_APPLICABLE',
                'explanation' => 'The vacancy snapshot contains no supported requirement for this dimension.',
                'requirement_ids' => [],
                'evidence' => [],
                'gaps' => [],
                'uncertainties' => [],
                'mandatory_gaps' => 0,
                'mandatory_unknowns' => 0,
                'preferred_gaps' => 0,
                'matches' => 0,
                'mandatory_count' => 0,
                'mandatory_matches' => 0,
            ];
        }

        $evidence = [];
        $gaps = [];
        $uncertainties = [];
        $matches = 0;
        $adjacent = 0;
        $mandatoryGaps = 0;
        $mandatoryUnknowns = 0;
        $preferredGaps = 0;
        $blockers = 0;
        $mandatoryCount = count(array_filter($requirements, fn (VacancyRequirement $item): bool => $item->importance === 'MANDATORY'));
        $mandatoryMatches = 0;

        foreach ($requirements as $requirement) {
            $support = $this->supportingEvidence($requirement, $facts, $claims);
            if ($support !== null) {
                $matches++;
                if ($requirement->importance === 'MANDATORY') {
                    $mandatoryMatches++;
                }
                $evidence[$support['type'].':'.$support['id']] = $support;

                continue;
            }

            $structured = $this->structuredComparison($requirement, $facts, $claims);
            if ($structured !== null) {
                if ($structured['result'] === 'MATCH') {
                    $matches++;
                    if ($requirement->importance === 'MANDATORY') {
                        $mandatoryMatches++;
                    }
                    $evidence[$structured['evidence']['type'].':'.$structured['evidence']['id']] = $structured['evidence'];
                } elseif ($structured['result'] === 'BLOCKER') {
                    $blockers++;
                    $gaps[] = $this->gap($requirement, 'structured incompatibility');
                } else {
                    $uncertainties[] = $this->uncertainty($requirement, 'candidate data is absent or incomplete');
                    if ($requirement->importance === 'MANDATORY') {
                        $mandatoryUnknowns++;
                    }
                }

                continue;
            }

            $weak = $this->adjacentEvidence($requirement, $facts, $claims);
            if ($weak !== null) {
                $adjacent++;
                $evidence[$weak['type'].':'.$weak['id']] = $weak;
                $gaps[] = $this->gap($requirement, 'adjacent or weak candidate evidence');
                if ($requirement->importance === 'MANDATORY') {
                    $mandatoryGaps++;
                } elseif ($requirement->importance === 'PREFERRED') {
                    $preferredGaps++;
                } else {
                    $uncertainties[] = $this->uncertainty($requirement, 'requirement importance is uncertain despite adjacent candidate evidence');
                }

                continue;
            }

            if ($requirement->importance === 'MANDATORY') {
                $mandatoryGaps++;
                $gaps[] = $this->gap($requirement, 'hard requirement unsupported');
            } elseif ($requirement->importance === 'PREFERRED') {
                $preferredGaps++;
                $gaps[] = $this->gap($requirement, 'preferred requirement unsupported');
            } else {
                $uncertainties[] = $this->uncertainty($requirement, 'requirement importance or candidate evidence is uncertain');
            }
        }

        $result = match (true) {
            $blockers > 0 => 'BLOCKER',
            $mandatoryGaps > 0 => 'GAP',
            $matches > 0 && ($adjacent > 0 || $preferredGaps > 0 || $uncertainties !== []) => 'ADJACENT',
            $matches > 0 => 'MATCH',
            $adjacent > 0 => 'ADJACENT',
            $preferredGaps > 0 => 'GAP',
            default => 'UNKNOWN',
        };
        $explanation = match ($result) {
            'MATCH' => 'Every supported requirement in this dimension has confirmed candidate evidence.',
            'ADJACENT' => 'Some confirmed evidence is relevant, but gaps or uncertainty remain and no experience was upgraded.',
            'GAP' => 'One or more vacancy requirements have no valid confirmed candidate evidence.',
            'BLOCKER' => 'Confirmed structured candidate data conflicts with a mandatory vacancy requirement.',
            default => 'The vacancy has a requirement here, but confirmed candidate data is insufficient.',
        };

        return [
            'dimension' => $dimension,
            'result' => $result,
            'explanation' => $explanation,
            'requirement_ids' => array_map(fn (VacancyRequirement $item): string => (string) $item->id, $requirements),
            'evidence' => array_values($evidence),
            'gaps' => $gaps,
            'uncertainties' => $uncertainties,
            'mandatory_gaps' => $mandatoryGaps,
            'mandatory_unknowns' => $mandatoryUnknowns,
            'preferred_gaps' => $preferredGaps,
            'matches' => $matches,
            'mandatory_count' => $mandatoryCount,
            'mandatory_matches' => $mandatoryMatches,
        ];
    }

    /** @param list<CareerFact> $facts
     * @param  list<Claim>  $claims
     * @return array{type: string, id: string}|null
     */
    private function supportingEvidence(VacancyRequirement $requirement, array $facts, array $claims): ?array
    {
        if (in_array($requirement->dimension, ['LOCATION', 'WORK_FORMAT', 'SALARY'], true)
            || ($requirement->dimension === 'EXPERIENCE' && (! blank($requirement->normalized_value) || $this->hasDurationExpression($requirement->source_excerpt)))) {
            return null;
        }
        $needle = $requirement->dimension === 'EXPERIENCE'
            ? $this->experienceEvidenceTerms($requirement)
            : $this->concreteLabelSubject($requirement->label);
        foreach ($facts as $fact) {
            foreach ($this->candidateClauses($fact->approvedAssertion()) as $clause) {
                $text = $this->normalize($clause);
                if ($this->directSupportAllowed($requirement, $text)
                    && ! $this->candidateEvidenceNegated($requirement, $text)
                    && $this->languageEvidenceAllowed($requirement, $text)
                    && $this->languageQualificationMatches($requirement, $text)
                    && $this->directSubjectMatches($requirement, $text, $needle)) {
                    return ['type' => 'fact', 'id' => (string) $fact->id];
                }
            }
        }
        foreach ($claims as $claim) {
            foreach ($this->candidateClauses($claim->statement) as $clause) {
                $text = $this->normalize($clause);
                if ($this->directSupportAllowed($requirement, $text)
                    && ! $this->candidateEvidenceNegated($requirement, $text)
                    && $this->languageEvidenceAllowed($requirement, $text)
                    && $this->languageQualificationMatches($requirement, $text)
                    && $this->directSubjectMatches($requirement, $text, $needle)) {
                    return ['type' => 'claim', 'id' => (string) $claim->id];
                }
            }
        }

        return null;
    }

    private function directSupportAllowed(VacancyRequirement $requirement, string $candidateText): bool
    {
        if ($requirement->dimension === 'DOMAIN') {
            $subject = preg_quote($this->concreteLabelSubject($requirement->label), '/');
            if ($subject === '') {
                return false;
            }

            return preg_match('/\b'.$subject.'\s+(?:industry|sector|domain)\b|\b(?:industry|sector|domain)\s+(?:of\s+)?'.$subject.'\b/iu', $candidateText) === 1;
        }
        if ($requirement->dimension !== 'EXPERIENCE') {
            return true;
        }

        return preg_match('/\b(familiar|aware|learning|studied|basic|beginner)\b|знаком|изуча|базов/iu', $candidateText) !== 1;
    }

    private function candidateEvidenceNegated(VacancyRequirement $requirement, string $candidateText): bool
    {
        $generic = ['experience', 'required', 'mandatory', 'must', 'have', 'need', 'needed', 'with', 'of', 'for', 'and', 'or', 'skill', 'skills', 'knowledge', 'proficiency', 'level', 'language', 'languages', 'years', 'months', 'year', 'month', 'technology', 'technologies', 'technical', 'tech', 'stack', 'tool', 'tools', 'framework', 'frameworks', 'platform', 'platforms', 'competency', 'competencies', 'qualification', 'qualifications', 'ability', 'abilities'];
        $tokens = array_values(array_filter(
            preg_split('/\s+/u', $this->concreteLabelSubject($requirement->label), -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $token): bool => ! in_array($token, $generic, true) && ! is_numeric($token),
        ));
        if ($tokens === []) {
            return false;
        }
        $subject = implode('\\s+', array_map(fn (string $token): string => preg_quote($token, '/'), $tokens));

        return $this->subjectNegated($candidateText, $subject, $requirement->dimension);
    }

    private function concreteLabelSubject(string $label): string
    {
        $generic = ['skill', 'skills', 'knowledge', 'experience', 'proficiency', 'level', 'language', 'languages', 'technology', 'technologies', 'technical', 'framework', 'frameworks', 'platform', 'platforms', 'industry', 'sector', 'domain', 'competency', 'competencies', 'qualification', 'qualifications'];
        // Qualification words modify the leading subject; connector words inside
        // a technology name (Ruby on Rails) remain part of that subject.
        $label = preg_replace('/^(?:(?:strong|solid|good|excellent|deep|advanced|proven|practical|hands[ -]on|extensive|commercial|professional|confident|fluent)\s+)+/u', '', $this->normalize($label)) ?? $this->normalize($label);

        return implode(' ', array_filter(
            preg_split('/\s+/u', $label, -1, PREG_SPLIT_NO_EMPTY) ?: [],
            fn (string $token): bool => ! in_array($token, $generic, true),
        ));
    }

    private function subjectNegated(string $candidateText, string $subject, string $dimension): bool
    {
        if ($dimension === 'WORK_FORMAT'
            && preg_match('/\b(?:cannot|can\s+not|unable\s+to|(?:do|does|did|have|has|had)\s+not|never)\s+(?:work|be)\b.{0,40}\b(?:remote(?:ly)?|hybrid|on[ -]?site|office)\b/iu', $candidateText) === 1) {
            return true;
        }

        return preg_match('/\b(?:no|none|zero|0|without|never|not|cannot|can\s+not|unable\s+to|lack|lacking|do\s+not\s+have|does\s+not\s+have)\s+(?:(?:any|zero|0|\d+(?:[.,]\d+)?)\s+)?(?:(?:years?|months?)\s+(?:of\s+)?)?(?:experience\s+(?:with|in)\s+)?(?:[\pL\pN+#.-]+\s+){0,5}'.$subject.'\b|\b'.$subject.'\b.{0,40}\b(?:no|none|zero|0|without|never|not|cannot|can\s+not|unable\s+to|lack|lacking)\s+(?:experience|background|knowledge|skills?)\b/iu', $candidateText) === 1;
    }

    /** @return list<string> */
    private function candidateClauses(string $text): array
    {
        // A migration from a negative source system to positive production use
        // describes two distinct occurrences of the same technology.
        $text = preg_replace('/(\b(?:migrated|moved|moving)\s+from\b[^.;!?\n]+?)\s+to\s+(?=[\pL])/iu', '$1; ', $text) ?? $text;
        $clauses = preg_split('/\.(?=\s|$|[A-ZА-Я])|[;!?\n\r]+|,\s*(?i:but|while)\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(array_map('trim', $clauses), fn (string $clause): bool => $clause !== ''));
    }

    private function languageEvidenceAllowed(VacancyRequirement $requirement, string $candidateText): bool
    {
        if ($requirement->dimension !== 'LANGUAGE') {
            return true;
        }

        $language = $this->languageToken($requirement);
        if ($language === null) {
            return false;
        }
        $languagePattern = $this->languagePattern($language);
        $qualification = '(?:a[1-2]|b[1-2]|c[1-2]|fluent|native|fluency|upper[ -]intermediate|professional[ -]working(?:[ -]proficiency)?)';

        return preg_match('/\b'.$languagePattern.'\s+(?:(?:at\s+)?'.$qualification.'(?:\s+level)?|(?:language\s+)?(?:proficiency|level)(?:\s+at)?\s+'.$qualification.')\b|\b'.$qualification.'(?:[ -]level)?\s+(?:(?:proficiency\s+)?in\s+)?'.$languagePattern.'\b|\b(?:proficiency|level)\s+(?:at\s+)?(?:in\s+)?'.$languagePattern.'(?:\s+'.$qualification.')?\b/iu', $candidateText) === 1;
    }

    private function languageQualificationMatches(VacancyRequirement $requirement, string $candidateText): bool
    {
        if ($requirement->dimension !== 'LANGUAGE') {
            return true;
        }
        $language = $this->languageToken($requirement);
        if ($language === null) {
            return false;
        }
        $required = $this->languageQualification($requirement->source_excerpt, $language);

        if ($required === null) {
            return true;
        }
        $actual = $this->languageQualification($candidateText, $language);
        $cefr = ['a1', 'a2', 'b1', 'b2', 'c1', 'c2'];
        if (in_array($required, $cefr, true) && in_array($actual, $cefr, true)) {
            return array_search($actual, $cefr, true) >= array_search($required, $cefr, true);
        }

        return $actual === $required;
    }

    private function languageToken(VacancyRequirement $requirement): ?string
    {
        $label = $this->normalize($requirement->label);
        foreach (['english', 'russian', 'german', 'french', 'spanish'] as $language) {
            if (preg_match('/\b'.$this->languagePattern($language).'\b/iu', $label) === 1) {
                return $language;
            }
        }

        return $this->qualifiedLanguageToken($label);
    }

    private function languagePattern(string $language): string
    {
        return match ($language) {
            'english' => '(?:english|английск\pL*)',
            'russian' => '(?:russian|русск\pL*)',
            'german' => '(?:german|немецк\pL*)',
            'french' => '(?:french|французск\pL*)',
            'spanish' => '(?:spanish|испанск\pL*)',
            default => preg_quote($language, '/'),
        };
    }

    private function languageQualification(string $text, string $language): ?string
    {
        $text = $this->normalize($text);
        $language = $this->languagePattern($language);
        $qualification = '(a[1-2]|b[1-2]|c[1-2]|fluent|native|fluency|upper[ -]intermediate|professional[ -]working(?:[ -]proficiency)?)';
        $patterns = [
            '/\b'.$language.'\s*(?:at\s+)?(?:(?:language\s+)?(?:proficiency|level)\s*(?:at\s+)?)?(?::|is|of)?\s*'.$qualification.'(?:\s+level)?\b/iu',
            '/\b'.$qualification.'(?:[ -]level)?\s+(?:(?:proficiency\s+)?in\s+)?'.$language.'\b/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return $this->normalize($match[1]);
            }
        }

        return null;
    }

    private function experienceEvidenceTerms(VacancyRequirement $requirement): string
    {
        $terms = array_map(
            fn (string $term): string => trim($term, '.'),
            preg_split('/\s+/u', $this->normalize($requirement->label.' '.$requirement->source_excerpt), -1, PREG_SPLIT_NO_EMPTY) ?: [],
        );
        $terms = array_filter($terms, fn (string $term): bool => ! in_array($term, ['required', 'mandatory', 'must', 'have', 'need', 'needed', 'require', 'requires', 'requiring', 'requirement', 'is', 'are'], true));

        return implode(' ', $terms);
    }

    private function qualifiedLanguageToken(string $text): ?string
    {
        $qualification = '(?:a[1-2]|b[1-2]|c[1-2]|fluent|native|fluency|upper[ -]intermediate|professional[ -]working(?:[ -]proficiency)?)';
        $language = '(?<language>[\\pL][\\pL-]{2,})';
        $patterns = [
            '/\\b'.$language.'\\s+(?:(?:at\\s+)?'.$qualification.'(?:\\s+level)?|(?:language\\s+)?(?:proficiency|level)(?:\\s+at)?\\s+'.$qualification.')\\b/iu',
            '/\\b'.$qualification.'(?:[ -]level)?\\s+(?:(?:proficiency\\s+)?in\\s+)?'.$language.'\\b/iu',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return $this->normalize($match['language']);
            }
        }

        return null;
    }

    /** @param list<CareerFact> $facts
     * @param  list<Claim>  $claims
     * @return array{type: string, id: string}|null
     */
    private function adjacentEvidence(VacancyRequirement $requirement, array $facts, array $claims): ?array
    {
        if (! in_array($requirement->dimension, ['TECHNICAL', 'DOMAIN'], true)) {
            return null;
        }
        $families = [
            ['laravel', 'symfony'], ['react', 'vue', 'angular'], ['postgresql', 'mysql', 'mariadb'],
            ['aws', 'gcp', 'azure'], ['php', 'python', 'ruby'],
        ];
        $needle = $this->normalize($requirement->label);
        foreach ($families as $family) {
            $required = array_values(array_filter($family, fn (string $term): bool => $this->containsRequirementTerms($needle, $term)));
            if ($required === []) {
                continue;
            }
            foreach ([['type' => 'fact', 'items' => $facts], ['type' => 'claim', 'items' => $claims]] as $source) {
                foreach ($source['items'] as $item) {
                    foreach ($this->candidateClauses($item instanceof CareerFact ? $item->approvedAssertion() : $item->statement) as $clause) {
                        $text = $this->normalize($clause);
                        foreach (array_diff($family, $required) as $adjacent) {
                            if ($this->containsRequirementTerms($text, $adjacent)
                                && ! $this->subjectNegated($text, preg_quote($adjacent, '/'), $requirement->dimension)) {
                                return ['type' => $source['type'], 'id' => (string) $item->id];
                            }
                        }
                    }
                }
            }
        }

        return null;
    }

    /** @param list<CareerFact> $facts
     * @param  list<Claim>  $claims
     * @return array{result: string, evidence?: array{type: string, id: string}}|null
     */
    private function structuredComparison(VacancyRequirement $requirement, array $facts, array $claims): ?array
    {
        if (! in_array($requirement->dimension, ['LOCATION', 'WORK_FORMAT', 'SALARY', 'EXPERIENCE'], true)
            || (blank($requirement->normalized_value) && ! ($requirement->dimension === 'EXPERIENCE' && $this->hasDurationExpression($requirement->source_excerpt)))) {
            return null;
        }
        if ($requirement->dimension === 'EXPERIENCE' && blank($requirement->normalized_value)) {
            return ['result' => 'UNKNOWN'];
        }
        $candidates = [];
        foreach ($facts as $fact) {
            foreach ($this->candidateClauses($fact->approvedAssertion()) as $rawText) {
                $text = $this->normalize($rawText);
                if ($this->relevantStructuredEvidence($requirement, $rawText) && ! $this->candidateEvidenceNegated($requirement, $text)) {
                    $candidates[] = ['type' => 'fact', 'id' => (string) $fact->id, 'text' => $text, 'raw_text' => $rawText];
                }
            }
        }
        foreach ($claims as $claim) {
            foreach ($this->candidateClauses($claim->statement) as $rawText) {
                $text = $this->normalize($rawText);
                if ($this->relevantStructuredEvidence($requirement, $rawText) && ! $this->candidateEvidenceNegated($requirement, $text)) {
                    $candidates[] = ['type' => 'claim', 'id' => (string) $claim->id, 'text' => $text, 'raw_text' => $rawText];
                }
            }
        }

        $expected = $this->normalize((string) $requirement->normalized_value);
        $incompatible = false;
        foreach ($candidates as $candidate) {
            if ($requirement->dimension === 'SALARY') {
                $candidateRange = $this->salaryRange($candidate['raw_text']);
                if ($candidateRange === null) {
                    continue;
                }
                $compatible = $this->salaryRangesOverlap($this->salaryRange($requirement->source_excerpt), $candidateRange);
                if ($compatible === null) {
                    continue;
                }
            } else {
                $candidateValueText = in_array($requirement->dimension, ['EXPERIENCE', 'SALARY'], true)
                    ? $candidate['raw_text']
                    : $candidate['text'];
                $actual = $this->structuredCandidateValue($requirement->dimension, $candidateValueText);
                if ($actual === null) {
                    continue;
                }
                $compatible = $this->structuredCompatible($requirement->dimension, $expected, $actual);
            }
            if ($compatible) {
                return ['result' => 'MATCH', 'evidence' => ['type' => $candidate['type'], 'id' => $candidate['id']]];
            }
            if ($requirement->importance === 'MANDATORY') {
                $incompatible = true;
            }
        }

        if ($incompatible) {
            return ['result' => 'BLOCKER'];
        }

        return ['result' => 'UNKNOWN'];
    }

    private function relevantStructuredEvidence(VacancyRequirement $requirement, string $candidateText): bool
    {
        if ($requirement->dimension === 'WORK_FORMAT') {
            return $this->candidateWorkFormatValue($candidateText) !== null;
        }
        if ($requirement->dimension !== 'EXPERIENCE') {
            return true;
        }

        $tokens = $this->experienceSubjectTokens($requirement);
        if ($tokens === null) {
            return false;
        }
        $duration = $this->candidateExperienceDuration($candidateText);
        if ($duration === null) {
            return false;
        }
        $experienceContext = $this->normalize($duration['context']);

        if (count($tokens) > 1) {
            return $this->containsRequirementPhrase($experienceContext, implode(' ', $tokens));
        }

        foreach ($tokens as $token) {
            if (! $this->containsRequirementTerms($experienceContext, $token)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string>|null */
    private function experienceSubjectTokens(VacancyRequirement $requirement): ?array
    {
        $tokens = $this->experienceSubjectTokensFrom($requirement->label);
        if ($tokens === []) {
            $tokens = $this->experienceSubjectTokensFrom($requirement->source_excerpt);
        }

        return $tokens === [] ? null : $tokens;
    }

    /** @return list<string> */
    private function experienceSubjectTokensFrom(string $text): array
    {
        $subject = preg_replace('/\b(?:at\s+least|minimum)?\s*\d+(?:[.,]\d+)?\s*\+?\s*(?:years?|months?|лет|год(?:а|ов)?|месяц[\pL]*)\b/iu', ' ', $text) ?? '';
        $tokens = [];
        $connector = null;
        $ignored = ['required', 'mandatory', 'require', 'requires', 'requiring', 'requirement', 'experience', 'commercial', 'years', 'months', 'least', 'minimum', 'with', 'for', 'and', 'we', 'candidate', 'must', 'have', 'need', 'needed'];

        foreach (array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', $this->normalize($subject)) ?: []) as $token) {
            if (in_array($token, ['on', 'in', 'of'], true)) {
                if ($tokens !== []) {
                    $connector = $token;
                }

                continue;
            }
            if (mb_strlen($token) <= 2 || in_array($token, $ignored, true)) {
                continue;
            }
            if ($connector !== null) {
                $tokens[] = $connector;
                $connector = null;
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    private function containsRequirementTerms(string $candidateText, string $requirementText): bool
    {
        $terms = preg_split('/\s+/u', $requirementText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        foreach ($terms as $term) {
            if (preg_match('/(?<![\pL\pN])'.preg_quote($term, '/').'(?![\pL\pN])/u', $candidateText) !== 1) {
                return false;
            }
        }

        return $terms !== [];
    }

    private function directSubjectMatches(VacancyRequirement $requirement, string $candidateText, string $needle): bool
    {
        $experienceSubjectTokens = $requirement->dimension === 'EXPERIENCE'
            ? ($this->experienceSubjectTokens($requirement) ?? [])
            : [];

        return match ($requirement->dimension) {
            'LANGUAGE' => true, // languageEvidenceAllowed binds the named language and its qualification.
            'EXPERIENCE' => count($experienceSubjectTokens) > 1
                ? $this->containsRequirementPhrase($candidateText, implode(' ', $experienceSubjectTokens))
                : $this->containsRequirementTerms($candidateText, $needle),
            default => $this->containsRequirementPhrase($candidateText, $needle),
        };
    }

    private function containsRequirementPhrase(string $candidateText, string $requirementText): bool
    {
        $terms = preg_split('/\s+/u', $requirementText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if ($terms === []) {
            return false;
        }
        $phrase = implode('\\s+', array_map(fn (string $term): string => preg_quote($term, '/'), $terms));

        return preg_match('/(?<![\pL\pN])'.$phrase.'(?![\pL\pN])/u', $candidateText) === 1;
    }

    private function structuredCandidateValue(string $dimension, string $text): ?string
    {
        $patterns = match ($dimension) {
            'LOCATION' => ['/(?:^|\b)(?:location|residence|локация|город)\s*:?\s*([\pL\pN .-]+?)(?=[.!?;,)]|\s+(?:and|but)\s+(?:(?:open|willing|available)\s+to\s+(?:relocat(?:e|ion)|move)\b)|$)/u', '/^\s*(?:based|located|living|lives|resident|residing)\s+(?:in|at|of)\s+(?:the\s+)?([\pL\pN .-]+?)(?=[.!?;,)]|\s+(?:and|but)\s+(?:(?:open|willing|available)\s+to\s+(?:relocat(?:e|ion)|move)\b)|$)/u'],
            'WORK_FORMAT' => [],
            'SALARY' => [],
            'EXPERIENCE' => [],
            default => [],
        };
        if ($dimension === 'WORK_FORMAT') {
            return $this->candidateWorkFormatValue($text);
        }
        if ($dimension === 'EXPERIENCE') {
            $duration = $this->candidateExperienceDuration($text);
            if ($duration === null) {
                return null;
            }

            return $duration['unit'].':'.$duration['amount'];
        }
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return rtrim(trim(implode(':', array_slice($match, 1))), '.,;:!?');
            }
        }

        return null;
    }

    private function structuredCompatible(string $dimension, string $expected, string $actual): bool
    {
        if ($dimension === 'EXPERIENCE') {
            preg_match('/^(years|months):(\d+(?:[.,]\d+)?)$/u', $expected, $expectedMatch);
            preg_match('/^(years|months):(\d+(?:[.,]\d+)?)$/u', $actual, $actualMatch);

            return isset($expectedMatch[1], $expectedMatch[2], $actualMatch[1], $actualMatch[2])
                && $this->durationMonths($actualMatch[1], $actualMatch[2]) >= $this->durationMonths($expectedMatch[1], $expectedMatch[2]);
        }
        $aliases = ['удаленно' => 'remote', 'гибрид' => 'hybrid', 'офис' => 'office'];
        $actual = $aliases[$actual] ?? $actual;

        if ($dimension === 'LOCATION') {
            return $expected === $actual;
        }

        return $expected === $actual;
    }

    private function durationMonths(string $unit, string $value): float
    {
        $months = (float) str_replace(',', '.', $value);

        return $unit === 'years' ? $months * 12 : $months;
    }

    private function hasDurationExpression(string $text): bool
    {
        return preg_match('/(?<![\pL\pN])\d+(?:[.,]\d+)?\s*\+?\s*(?:years?|months?|лет|год(?:а|ов)?|месяц[\pL]*)(?!\pL)/iu', $text) === 1;
    }

    /** @return array{amount: string, unit: string, context: string}|null */
    private function candidateExperienceDuration(string $text): ?array
    {
        preg_match_all('/(?<![\pL\pN])(?<amount>\d+(?:[.,]\d+)?)\s*\+?\s*(?<unit>years?|months?|лет|год(?:а|ов)?|месяц[\pL]*)(?!\pL)/iu', $text, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        if (count($matches) !== 1) {
            return null;
        }
        $match = $matches[0];
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
        if (preg_match('/\bexperience\b|опыт[\pL]*/iu', $clause) !== 1) {
            return null;
        }

        return [
            'amount' => $match['amount'][0],
            'unit' => preg_match('/^(?:years?|лет|год(?:а|ов)?)$/iu', $match['unit'][0]) === 1 ? 'years' : 'months',
            'context' => $clause,
        ];
    }

    private function candidateWorkFormatValue(string $text): ?string
    {
        $patterns = [
            'remote' => '/\b(?:work format|формат работы)\s*:\s*(?:remote|удаленно)\b|(?:^|[,;:])\s*(?:fully\s+)?remote\s+(?:employee|worker|candidate|professional)\b|\b(?:i|we|candidate|employee|worker)\s+(?:am|are|is|work|works|worked|working)\s+(?:a\s+)?(?:fully\s+)?(?:remote(?:ly)?(?:\s+(?:employee|worker))?|from\s+home)\b|\b(?:prefer|prefers|preferred|open\s+to|available\s+for|seeking|looking\s+for)\s+(?:fully\s+)?remote\s+(?:work|arrangement|schedule|position|role)\b/iu',
            'hybrid' => '/\b(?:work format|формат работы)\s*:\s*(?:hybrid|гибрид)\b|(?:^|[,;:])\s*hybrid\s+(?:employee|worker|candidate|arrangement|schedule|position|role)\b|\b(?:i|we|candidate|employee|worker)\s+(?:work|works|worked|working)\s+hybrid\b|\b(?:prefer|prefers|preferred|open\s+to|available\s+for|seeking|looking\s+for)\s+hybrid\s+(?:work|arrangement|schedule|position|role)\b/iu',
            'office' => '/\b(?:work format|формат работы)\s*:\s*(?:office|офис)\b|(?:^|[,;:])\s*(?:office[ -]based|on[ -]?site|onsite)\s+(?:employee|worker|candidate|professional)\b|\b(?:i|we|candidate|employee|worker)\s+(?:work|works|worked|working)\s+(?:on[ -]?site|onsite|in\s+(?:the\s+)?office)\b|\b(?:prefer|prefers|preferred|open\s+to|available\s+for|seeking|looking\s+for)\s+(?:office|on[ -]?site|onsite)\s+(?:work|arrangement|schedule|position|role)\b/iu',
        ];
        foreach ($patterns as $value => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return $value;
            }
        }

        return null;
    }

    /** @return array{currency: string, period: ?string, minimum: ?float, maximum: ?float}|null */
    private function salaryRange(string $text): ?array
    {
        if (preg_match('/\b(?:salary|compensation|pay|зарплата)\b\s*(?<tail>[^;!?\n]{0,160})/iu', $text, $context) !== 1) {
            return null;
        }
        $tail = ltrim(trim($context['tail']), ':= ');
        if (preg_match('/(?<!\d)\.(?!\d)/u', $tail, $sentenceEnd, PREG_OFFSET_CAPTURE) === 1) {
            $tail = substr($tail, 0, $sentenceEnd[0][1]);
        }
        $tail = preg_replace('/^(?:is|equals)\s+/iu', '', $tail) ?? $tail;
        if (preg_match('/(?<!\d)\d{1,3},\d{3}(?!\d)/u', $tail) === 1) {
            return null;
        }
        $bound = null;
        if (preg_match('/^(?<bound>minimum|min|from|starting(?:\s+at)?|at\s+least|maximum|max|up\s+to|range)\b\s*:?\s*/iu', $tail, $boundMatch) === 1) {
            $bound = mb_strtolower(preg_replace('/\s+/', ' ', $boundMatch['bound']) ?? $boundMatch['bound']);
            $tail = substr($tail, strlen($boundMatch[0]));
        }
        $currency = '(usd|eur|rub|руб|₽)';
        $amount = '(\d{1,3}(?:[ \x{00A0}\x{202F}]\d{3})+|\d+(?:[.,]\d+)?)';
        $pattern = '/^\s*(?:(?<prefix_currency>'.$currency.')\s*)?(?<first>'.$amount.')\s*(?<first_currency>'.$currency.')?(?:\s*(?:-|–|—|to)\s*(?:(?<range_currency>'.$currency.')\s*)?(?<second>'.$amount.')\s*(?<second_currency>'.$currency.')?)?/iu';
        if (preg_match($pattern, $tail, $match) !== 1) {
            return null;
        }
        $currencies = array_values(array_filter([
            $match['prefix_currency'],
            $match['first_currency'] ?? '',
            $match['range_currency'] ?? '',
            $match['second_currency'] ?? '',
        ]));
        if ($currencies === []) {
            return null;
        }
        $currencies = array_values(array_unique(array_map(fn (string $item): string => $this->salaryCurrency($item), $currencies)));
        if (count($currencies) !== 1) {
            return null;
        }
        $first = $this->salaryAmount($match['first']);
        $second = isset($match['second']) && $match['second'] !== '' ? $this->salaryAmount($match['second']) : $first;
        if (isset($match['second']) && $match['second'] !== '' && $second < $first) {
            return null;
        }
        $period = $this->salaryPeriod($text, $tail);
        if ($period === false) {
            return null;
        }
        $minimum = $first;
        $maximum = isset($match['second']) && $match['second'] !== '' ? $second : $first;
        if (in_array($bound, ['minimum', 'min', 'from', 'starting at', 'at least'], true)) {
            $maximum = null;
        } elseif (in_array($bound, ['maximum', 'max', 'up to'], true)) {
            $minimum = null;
        }

        return [
            'currency' => $currencies[0],
            'period' => $period,
            'minimum' => $minimum,
            'maximum' => $maximum,
        ];
    }

    private function salaryPeriod(string $text, string $salaryTail): string|false|null
    {
        $periods = [];
        $patterns = [
            'hour' => '/\b(?:per\s+hour|hourly|\/\s*h(?:our)?)\b|в\s+час|почасов\pL*/iu',
            'month' => '/\b(?:per\s+month|monthly|\/\s*month)\b|в\s+месяц|ежемесячн\pL*/iu',
            'year' => '/\b(?:per\s+year|yearly|annual(?:ly)?|per\s+annum|\/\s*year)\b|в\s+год|ежегодн\pL*/iu',
        ];
        foreach ($patterns as $period => $pattern) {
            if (preg_match($pattern, $salaryTail) === 1) {
                $periods[] = $period;
            }
        }
        $prefixPatterns = [
            'hour' => '/\b(?:hourly|per\s+hour)\s+(?:salary|compensation|pay|зарплата)\b/iu',
            'month' => '/\b(?:monthly|per\s+month)\s+(?:salary|compensation|pay|зарплата)\b/iu',
            'year' => '/\b(?:yearly|annual(?:ly)?|per\s+year|per\s+annum)\s+(?:salary|compensation|pay|зарплата)\b/iu',
        ];
        foreach ($prefixPatterns as $period => $pattern) {
            if (preg_match($pattern, $text) === 1) {
                $periods[] = $period;
            }
        }

        $periods = array_values(array_unique($periods));

        return count($periods) > 1 ? false : ($periods[0] ?? null);
    }

    /** @param array{currency: string, period: ?string, minimum: ?float, maximum: ?float}|null $vacancy
     * @param  array{currency: string, period: ?string, minimum: ?float, maximum: ?float}|null  $candidate
     */
    private function salaryRangesOverlap(?array $vacancy, ?array $candidate): ?bool
    {
        if ($vacancy === null || $candidate === null
            || $vacancy['currency'] !== $candidate['currency']
            || $vacancy['period'] !== $candidate['period']) {
            return null;
        }

        return ($vacancy['maximum'] === null || $candidate['minimum'] === null || $candidate['minimum'] <= $vacancy['maximum'])
            && ($candidate['maximum'] === null || $vacancy['minimum'] === null || $vacancy['minimum'] <= $candidate['maximum']);
    }

    private function salaryAmount(string $value): float
    {
        return (float) str_replace(',', '.', str_replace([' ', "\xC2\xA0", "\xE2\x80\xAF"], '', $value));
    }

    private function salaryCurrency(string $currency): string
    {
        return in_array(mb_strtolower($currency), ['руб', '₽'], true) ? 'rub' : mb_strtolower($currency);
    }

    /** @param list<array<string, mixed>> $dimensions */
    private function recommendation(array $dimensions): string
    {
        $blockers = count(array_filter($dimensions, fn (array $item): bool => $item['result'] === 'BLOCKER'));
        $mandatoryGaps = array_sum(array_column($dimensions, 'mandatory_gaps'));
        $preferredGaps = array_sum(array_column($dimensions, 'preferred_gaps'));
        $matches = array_sum(array_column($dimensions, 'matches'));
        $mandatoryCount = array_sum(array_column($dimensions, 'mandatory_count'));
        $mandatoryMatches = array_sum(array_column($dimensions, 'mandatory_matches'));
        $mandatoryUnknowns = array_sum(array_column($dimensions, 'mandatory_unknowns'));
        $uncertainties = array_sum(array_map(fn (array $item): int => count($item['uncertainties']), $dimensions));
        $gaps = array_sum(array_map(fn (array $item): int => count($item['gaps']), $dimensions));

        return match (true) {
            $blockers > 0 => 'SKIP',
            $mandatoryGaps >= 3 => 'LOW_PRIORITY',
            $mandatoryGaps > 0 => 'MAYBE',
            $mandatoryUnknowns > 0 => 'MAYBE',
            $mandatoryCount > 0 && $mandatoryMatches === $mandatoryCount && $preferredGaps === 0 && $uncertainties === 0 && $gaps === 0 => 'STRONGLY_APPLY',
            $matches > 0 => 'APPLY',
            default => 'MAYBE',
        };
    }

    /** @return array<string, string> */
    private function gap(VacancyRequirement $requirement, string $category): array
    {
        return ['requirement_id' => (string) $requirement->id, 'label' => $requirement->label, 'category' => $category];
    }

    /** @return array<string, string> */
    private function uncertainty(VacancyRequirement $requirement, string $reason): array
    {
        return ['requirement_id' => (string) $requirement->id, 'label' => $requirement->label, 'reason' => $reason];
    }

    private function normalize(string $value): string
    {
        $value = str_replace('’', "'", mb_strtolower($value));
        $value = str_replace(
            ["don't", "doesn't", "didn't", "haven't", "hasn't", "hadn't", "can't"],
            ['do not', 'does not', 'did not', 'have not', 'has not', 'had not', 'cannot'],
            $value,
        );

        return trim((string) preg_replace('/[^\pL\pN+#.:₽]+/u', ' ', $value));
    }
}
