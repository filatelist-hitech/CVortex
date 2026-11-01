<?php

namespace Tests\Feature;

use App\AI\Contracts\LlmProvider;
use App\AI\Data\LlmRequest;
use App\AI\Data\LlmResponse;
use App\AI\Exceptions\VacancyOutputException;
use App\AI\RuntimeSkillRegistry;
use App\Models\User;
use App\Models\Vacancy;
use App\Models\VacancyAnalysis;
use App\Models\VacancyLlmRun;
use App\Models\VacancyRequirement;
use App\Services\CareerFactService;
use App\Services\VacancyAnalysisService;
use App\Services\VacancyIngestionService;
use App\Services\VacancyRequirementValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VacancyExtractionPrompt200Test extends TestCase
{
    use RefreshDatabase;

    public function test_skill_registry_loads_vacancy_extraction_v2_with_valid_schema(): void
    {
        $registry = app(RuntimeSkillRegistry::class);
        $skill = $registry->vacancyRequirementExtraction();

        $this->assertSame('vacancy.requirement-extraction', $skill->id);
        $this->assertSame('2.0.0', $skill->version);
        $this->assertSame('2.0.0', $skill->promptVersion);
        $this->assertStringContainsString('CVortex — Vacancy Extraction Prompt 2.0.0', $skill->trustedInstructions);
        $this->assertStringContainsString('schema_version', json_encode($skill->outputSchema));

        $this->assertFalse(
            $this->containsUnsupportedOpenAiStringBounds($skill->outputSchema),
            'Schema must avoid minLength/maxLength for OpenAI strict structured outputs compatibility.',
        );

        $v1Skill = $registry->vacancyRequirementExtractionV1();
        $this->assertSame('1.0.0', $v1Skill->version);
    }

    public function test_validator_accepts_v200_structured_output(): void
    {
        $sourceText = "Мы ищем Senior PHP Developer в команду платформы.\n"
            ."Требования:\n"
            ."- Отличные знания PHP 8 и Laravel\n"
            ."- Опыт работы с PostgreSQL от 3 лет\n"
            ."- Docker и Kubernetes будут плюсом\n"
            ."- Английский язык на уровне B2\n";

        $output = [
            'schema_version' => '2.0.0',
            'extraction_status' => 'SUCCESS',
            'vacancy' => [
                'title' => 'Senior PHP Developer',
                'company' => 'Платформа',
                'source_date' => null,
            ],
            'candidate_requirements' => [
                [
                    'id' => 'req-1',
                    'category' => 'Backend',
                    'label' => 'PHP',
                    'description' => 'Отличные знания PHP 8',
                    'source_excerpt' => 'Отличные знания PHP 8 и Laravel',
                    'source_section' => 'Требования',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'TECHNICAL_SKILL',
                    'normalized_value' => null,
                    'alternatives' => [],
                    'group_id' => 'group-1',
                    'confidence' => 'HIGH',
                ],
                [
                    'id' => 'req-2',
                    'category' => 'Backend',
                    'label' => 'Laravel',
                    'description' => 'Знания фреймворка Laravel',
                    'source_excerpt' => 'Отличные знания PHP 8 и Laravel',
                    'source_section' => 'Требования',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'TOOL',
                    'normalized_value' => null,
                    'alternatives' => [],
                    'group_id' => 'group-1',
                    'confidence' => 'HIGH',
                ],
                [
                    'id' => 'req-3',
                    'category' => 'Database',
                    'label' => 'PostgreSQL',
                    'description' => 'Опыт с PostgreSQL',
                    'source_excerpt' => 'Опыт работы с PostgreSQL от 3 лет',
                    'source_section' => 'Требования',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'EXPERIENCE',
                    'normalized_value' => 'years:3',
                    'alternatives' => [],
                    'group_id' => null,
                    'confidence' => 'HIGH',
                ],
                [
                    'id' => 'req-4',
                    'category' => 'DevOps',
                    'label' => 'Docker',
                    'description' => 'Опыт работы с Docker',
                    'source_excerpt' => 'Docker и Kubernetes будут плюсом',
                    'source_section' => 'Требования',
                    'importance' => 'PREFERRED',
                    'requirement_type' => 'TOOL',
                    'normalized_value' => null,
                    'alternatives' => [],
                    'group_id' => 'group-2',
                    'confidence' => 'MEDIUM',
                ],
                [
                    'id' => 'req-5',
                    'category' => 'Languages',
                    'label' => 'English',
                    'description' => 'Английский B2',
                    'source_excerpt' => 'Английский язык на уровне B2',
                    'source_section' => 'Требования',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'LANGUAGE',
                    'normalized_value' => 'b2',
                    'alternatives' => [],
                    'group_id' => null,
                    'confidence' => 'HIGH',
                ],
            ],
            'responsibilities' => [],
            'technologies' => [],
            'employer_context' => [],
            'employment_conditions' => [],
            'compensation' => [
                'salary_present' => false,
                'salary_min' => null,
                'salary_max' => null,
                'currency' => null,
                'period' => null,
                'gross_net' => null,
                'source_excerpt' => null,
                'confidence' => 'LOW',
            ],
            'location' => [],
            'work_format' => [
                'value' => 'UNSPECIFIED',
                'source_excerpt' => null,
                'confidence' => 'LOW',
            ],
            'schedule' => [],
            'other_information' => [],
            'security_flags' => [],
            'quality_control' => [
                'all_sections_inspected' => true,
                'requirements_separated_from_responsibilities' => true,
                'employer_stack_separated' => true,
                'alternatives_preserved' => true,
                'source_traceability_verified' => true,
                'unsupported_inferences_absent' => true,
                'potential_omissions' => [],
            ],
        ];

        $validator = app(VacancyRequirementValidator::class);
        $validated = $validator->validate($output, $sourceText);

        $this->assertCount(5, $validated);

        $labels = array_column($validated, 'label');
        $this->assertContains('PHP', $labels);
        $this->assertContains('Laravel', $labels);
        $this->assertContains('PostgreSQL', $labels);
        $this->assertContains('Docker', $labels);
        $this->assertContains('English', $labels);

        $docker = collect($validated)->firstWhere('label', 'Docker');
        $this->assertSame('PREFERRED', $docker['importance']);
        $this->assertSame(0.8, $docker['confidence']);

        $postgres = collect($validated)->firstWhere('label', 'PostgreSQL');
        $this->assertSame('years:3', $postgres['normalized_value']);
        $this->assertSame(0.95, $postgres['confidence']);
    }

    public function test_validator_extracts_compensation_work_format_and_location(): void
    {
        $sourceText = "Вакансия: Team Lead.\n"
            ."Локация: Офис в городе Белград.\n"
            ."Формат работы: Удаленный формат (remote).\n"
            ."Зарплата: 5000 EUR в месяц net.\n"
            ."Требования: Знание Go.\n";

        $output = [
            'schema_version' => '2.0.0',
            'extraction_status' => 'SUCCESS',
            'vacancy' => ['title' => 'Team Lead', 'company' => null, 'source_date' => null],
            'candidate_requirements' => [
                [
                    'id' => 'req-1',
                    'category' => 'Backend',
                    'label' => 'Go',
                    'description' => 'Знание Go',
                    'source_excerpt' => 'Требования: Знание Go.',
                    'source_section' => 'Требования',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'TECHNICAL_SKILL',
                    'normalized_value' => null,
                    'alternatives' => [],
                    'group_id' => null,
                    'confidence' => 'HIGH',
                ],
            ],
            'responsibilities' => [],
            'technologies' => [],
            'employer_context' => [],
            'employment_conditions' => [],
            'compensation' => [
                'salary_present' => true,
                'salary_min' => 5000,
                'salary_max' => 5000,
                'currency' => 'EUR',
                'period' => 'month',
                'gross_net' => 'net',
                'source_excerpt' => 'Зарплата: 5000 EUR в месяц net.',
                'confidence' => 'HIGH',
            ],
            'location' => [
                [
                    'city' => 'Белград',
                    'region' => null,
                    'country' => 'Сербия',
                    'office_address' => null,
                    'source_excerpt' => 'Локация: Офис в городе Белград.',
                ],
            ],
            'work_format' => [
                'value' => 'REMOTE',
                'source_excerpt' => 'Формат работы: Удаленный формат (remote).',
                'confidence' => 'HIGH',
            ],
            'schedule' => [],
            'other_information' => [],
            'security_flags' => [],
            'quality_control' => [
                'all_sections_inspected' => true,
                'requirements_separated_from_responsibilities' => true,
                'employer_stack_separated' => true,
                'alternatives_preserved' => true,
                'source_traceability_verified' => true,
                'unsupported_inferences_absent' => true,
                'potential_omissions' => [],
            ],
        ];

        $validator = app(VacancyRequirementValidator::class);
        $validated = $validator->validate($output, $sourceText);

        $this->assertCount(4, $validated);

        $dimensions = array_column($validated, 'dimension');
        $this->assertContains('TECHNICAL', $dimensions);
        $this->assertContains('SALARY', $dimensions);
        $this->assertContains('WORK_FORMAT', $dimensions);
        $this->assertContains('LOCATION', $dimensions);
    }

    public function test_validator_rejects_hallucinated_source_excerpt_in_v200(): void
    {
        $sourceText = 'Требуется знание Python.';
        $output = [
            'schema_version' => '2.0.0',
            'extraction_status' => 'SUCCESS',
            'vacancy' => ['title' => null, 'company' => null, 'source_date' => null],
            'candidate_requirements' => [
                [
                    'id' => 'req-1',
                    'category' => 'Backend',
                    'label' => 'Python',
                    'description' => 'Знание Python',
                    'source_excerpt' => 'Hallucinated requirement not in text',
                    'source_section' => 'Requirements',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'TECHNICAL_SKILL',
                    'normalized_value' => null,
                    'alternatives' => [],
                    'group_id' => null,
                    'confidence' => 'HIGH',
                ],
            ],
            'responsibilities' => [],
            'technologies' => [],
            'employer_context' => [],
            'employment_conditions' => [],
            'compensation' => [
                'salary_present' => false,
                'salary_min' => null,
                'salary_max' => null,
                'currency' => null,
                'period' => null,
                'gross_net' => null,
                'source_excerpt' => null,
                'confidence' => 'LOW',
            ],
            'location' => [],
            'work_format' => ['value' => 'UNSPECIFIED', 'source_excerpt' => null, 'confidence' => 'LOW'],
            'schedule' => [],
            'other_information' => [],
            'security_flags' => [],
            'quality_control' => [
                'all_sections_inspected' => true,
                'requirements_separated_from_responsibilities' => true,
                'employer_stack_separated' => true,
                'alternatives_preserved' => true,
                'source_traceability_verified' => true,
                'unsupported_inferences_absent' => true,
                'potential_omissions' => [],
            ],
        ];

        $this->expectException(VacancyOutputException::class);
        app(VacancyRequirementValidator::class)->validate($output, $sourceText);
    }

    public function test_full_pipeline_e2e_with_prompt200_and_vacancy_analysis_service(): void
    {
        $sourceText = "Компания Vortex ищет Senior QA Automation Engineer.\n"
            ."Требования:\n"
            ."- Опыт тестирования API через Postman\n"
            ."- Знание Java и Playwright\n";

        $prompt200Output = [
            'schema_version' => '2.0.0',
            'extraction_status' => 'SUCCESS',
            'vacancy' => [
                'title' => 'Senior QA Automation Engineer',
                'company' => 'Vortex',
                'source_date' => null,
            ],
            'candidate_requirements' => [
                [
                    'id' => 'req-1',
                    'category' => 'Testing',
                    'label' => 'Postman',
                    'description' => 'Опыт тестирования API',
                    'source_excerpt' => 'Опыт тестирования API через Postman',
                    'source_section' => 'Требования',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'TOOL',
                    'normalized_value' => null,
                    'alternatives' => [],
                    'group_id' => null,
                    'confidence' => 'HIGH',
                ],
                [
                    'id' => 'req-2',
                    'category' => 'Testing',
                    'label' => 'Playwright',
                    'description' => 'Знание Playwright',
                    'source_excerpt' => 'Знание Java и Playwright',
                    'source_section' => 'Требования',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'TOOL',
                    'normalized_value' => null,
                    'alternatives' => [],
                    'group_id' => 'group-lang',
                    'confidence' => 'HIGH',
                ],
                [
                    'id' => 'req-3',
                    'category' => 'Languages',
                    'label' => 'Java',
                    'description' => 'Знание Java',
                    'source_excerpt' => 'Знание Java и Playwright',
                    'source_section' => 'Требования',
                    'importance' => 'MANDATORY',
                    'requirement_type' => 'TECHNICAL_SKILL',
                    'normalized_value' => null,
                    'alternatives' => [],
                    'group_id' => 'group-lang',
                    'confidence' => 'HIGH',
                ],
            ],
            'responsibilities' => [],
            'technologies' => [],
            'employer_context' => [],
            'employment_conditions' => [],
            'compensation' => [
                'salary_present' => false,
                'salary_min' => null,
                'salary_max' => null,
                'currency' => null,
                'period' => null,
                'gross_net' => null,
                'source_excerpt' => null,
                'confidence' => 'LOW',
            ],
            'location' => [],
            'work_format' => ['value' => 'UNSPECIFIED', 'source_excerpt' => null, 'confidence' => 'LOW'],
            'schedule' => [],
            'other_information' => [],
            'security_flags' => [],
            'quality_control' => [
                'all_sections_inspected' => true,
                'requirements_separated_from_responsibilities' => true,
                'employer_stack_separated' => true,
                'alternatives_preserved' => true,
                'source_traceability_verified' => true,
                'unsupported_inferences_absent' => true,
                'potential_omissions' => [],
            ],
        ];

        $user = $this->user('prompt200-e2e@example.test');

        // Create a confirmed career fact for matching
        app(CareerFactService::class)->createManual($user, 'skill', 'Postman API testing in commercial projects.');

        $this->app->instance(LlmProvider::class, new VacancyFakeLlmProviderV2([$prompt200Output]));

        $ingested = app(VacancyIngestionService::class)->queue($user, $sourceText, null);
        $snapshot = $ingested['snapshot'];

        $analysis = app(VacancyAnalysisService::class)->analyze($user, $snapshot);

        $this->assertInstanceOf(VacancyAnalysis::class, $analysis);

        // Verify vacancy requirements created
        $requirements = VacancyRequirement::query()->where('vacancy_snapshot_id', $snapshot->id)->get();
        $this->assertCount(3, $requirements);
        foreach ($requirements as $req) {
            $this->assertSame('vacancy.requirement-extraction@2.0.0', $req->extracted_by);
        }

        // Verify LLM run metadata
        $run = VacancyLlmRun::query()->where('vacancy_snapshot_id', $snapshot->id)->firstOrFail();
        $this->assertSame('2.0.0', $run->skill_version);
        $this->assertSame('2.0.0', $run->prompt_version);
        $this->assertSame('COMPLETED', $run->status);
        $this->assertSame('PASS', $run->validation_result);
    }

    private function containsUnsupportedOpenAiStringBounds(array $schema): bool
    {
        foreach ($schema as $key => $value) {
            if (in_array($key, ['minLength', 'maxLength'], true)
                || (is_array($value) && $this->containsUnsupportedOpenAiStringBounds($value))) {
                return true;
            }
        }

        return false;
    }

    private function user(string $email): User
    {
        return User::query()->create(['email' => $email, 'password' => 'a very long safe passphrase'])->fresh();
    }
}

class VacancyFakeLlmProviderV2 implements LlmProvider
{
    /** @param list<array<string, mixed>> $responses */
    public function __construct(private array $responses) {}

    public function generateStructured(LlmRequest $request): LlmResponse
    {
        $payload = array_shift($this->responses);
        if ($payload === null) {
            throw new \RuntimeException('Exhausted test responses.');
        }

        return new LlmResponse($payload, 'fake', 'fake-model', 100, 50, 10, 'req-id', 5);
    }
}
