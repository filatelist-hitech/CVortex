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
        if (in_array($requirement->dimension, ['LOCATION', 'WORK_FORMAT', 'SALARY', 'EXPERIENCE'], true)) {
            return null;
        }
        $needle = $this->normalize($requirement->label);
        foreach ($facts as $fact) {
            $text = $this->normalize($fact->approvedAssertion());
            if ($this->directSupportAllowed($requirement, $text) && $this->containsRequirementTerms($text, $needle)) {
                return ['type' => 'fact', 'id' => (string) $fact->id];
            }
        }
        foreach ($claims as $claim) {
            $text = $this->normalize($claim->statement);
            if ($this->directSupportAllowed($requirement, $text) && $this->containsRequirementTerms($text, $needle)) {
                return ['type' => 'claim', 'id' => (string) $claim->id];
            }
        }

        return null;
    }

    private function directSupportAllowed(VacancyRequirement $requirement, string $candidateText): bool
    {
        if ($requirement->dimension !== 'EXPERIENCE') {
            return true;
        }

        return preg_match('/\b(familiar|aware|learning|studied|basic|beginner)\b|знаком|изуча|базов/iu', $candidateText) !== 1;
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
            $required = array_values(array_filter($family, fn (string $term): bool => str_contains($needle, $term)));
            if ($required === []) {
                continue;
            }
            foreach ([['type' => 'fact', 'items' => $facts], ['type' => 'claim', 'items' => $claims]] as $source) {
                foreach ($source['items'] as $item) {
                    $text = $this->normalize($item instanceof CareerFact ? $item->approvedAssertion() : $item->statement);
                    foreach (array_diff($family, $required) as $adjacent) {
                        if (str_contains($text, $adjacent)) {
                            return ['type' => $source['type'], 'id' => (string) $item->id];
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
            || blank($requirement->normalized_value)) {
            return null;
        }
        $candidates = [];
        foreach ($facts as $fact) {
            $text = $this->normalize($fact->approvedAssertion());
            if ($this->relevantStructuredEvidence($requirement, $text)) {
                $candidates[] = ['type' => 'fact', 'id' => (string) $fact->id, 'text' => $text];
            }
        }
        foreach ($claims as $claim) {
            $text = $this->normalize($claim->statement);
            if ($this->relevantStructuredEvidence($requirement, $text)) {
                $candidates[] = ['type' => 'claim', 'id' => (string) $claim->id, 'text' => $text];
            }
        }

        $expected = $this->normalize((string) $requirement->normalized_value);
        $incompatible = false;
        foreach ($candidates as $candidate) {
            $actual = $this->structuredCandidateValue($requirement->dimension, $candidate['text']);
            if ($actual === null) {
                continue;
            }
            if ($this->structuredCompatible($requirement->dimension, $expected, $actual)) {
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
        if ($requirement->dimension !== 'EXPERIENCE') {
            return true;
        }

        $tokens = $this->experienceSubjectTokens($requirement);
        if ($tokens === null) {
            return false;
        }

        foreach ($tokens as $token) {
            if (! $this->containsRequirementTerms($candidateText, $token)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string>|null */
    private function experienceSubjectTokens(VacancyRequirement $requirement): ?array
    {
        $subject = preg_replace('/\b(?:at\s+least|minimum)?\s*\d+(?:[.,]\d+)?\s*\+?\s*(?:years?|лет|года)\b/iu', ' ', $requirement->label.' '.$requirement->source_excerpt) ?? '';
        $tokens = array_values(array_filter(
            array_map(fn (string $token): string => trim($token, '.'), preg_split('/\s+/u', $this->normalize($subject)) ?: []),
            fn (string $token): bool => mb_strlen($token) > 2 && ! in_array($token, ['required', 'experience', 'commercial', 'years', 'least', 'minimum', 'with', 'for', 'and'], true),
        ));

        return $tokens === [] ? null : $tokens;
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

    private function structuredCandidateValue(string $dimension, string $text): ?string
    {
        $patterns = match ($dimension) {
            'LOCATION' => ['/\b(?:location|локация|город)\s*:\s*([\pL\pN .-]+)/u'],
            'WORK_FORMAT' => ['/\b(?:work format|формат работы)\s*:\s*(remote|hybrid|office|удаленно|гибрид|офис)/u'],
            'SALARY' => ['/\b(?:salary minimum|минимальная зарплата)\s*:\s*(\d+)\s*([a-z]{3}|₽|руб)/u'],
            'EXPERIENCE' => ['/\b(\d+(?:[.,]\d+)?)\s*(?:years?|лет|года)/u'],
            default => [],
        };
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $match) === 1) {
                return trim(implode(':', array_slice($match, 1)));
            }
        }

        return null;
    }

    private function structuredCompatible(string $dimension, string $expected, string $actual): bool
    {
        if ($dimension === 'EXPERIENCE') {
            preg_match('/(\d+(?:[.,]\d+)?)/', $expected, $expectedMatch);
            preg_match('/(\d+(?:[.,]\d+)?)/', $actual, $actualMatch);

            return isset($expectedMatch[1], $actualMatch[1])
                && (float) str_replace(',', '.', $actualMatch[1]) >= (float) str_replace(',', '.', $expectedMatch[1]);
        }
        if ($dimension === 'SALARY') {
            preg_match('/([a-z]{3}|₽|руб)[: ](\d+)(?::(\d+))?/u', $expected, $vacancy);
            preg_match('/(\d+):([a-z]{3}|₽|руб)/u', $actual, $candidate);
            if (! isset($vacancy[1], $vacancy[2], $candidate[1], $candidate[2]) || $vacancy[1] !== $candidate[2]) {
                return false;
            }
            $maximum = isset($vacancy[3]) ? (int) $vacancy[3] : (int) $vacancy[2];

            return (int) $candidate[1] <= $maximum;
        }

        $aliases = ['удаленно' => 'remote', 'гибрид' => 'hybrid', 'офис' => 'office'];
        $actual = $aliases[$actual] ?? $actual;

        return $expected === $actual || str_contains($actual, $expected) || str_contains($expected, $actual);
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
        return trim((string) preg_replace('/[^\pL\pN+#.:₽]+/u', ' ', mb_strtolower($value)));
    }
}
