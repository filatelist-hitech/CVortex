<?php

namespace App\Mcp\Tools;

use App\Models\VacancyRequirement;
use App\Services\VacancyAnalysisDraftService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Throwable;

class VacancyAnalysisDraftSave extends BoundedTool
{
    protected string $name = 'vacancy_analysis_draft_save';

    protected string $description = 'Save one bounded structured vacancy analysis DRAFT for the authenticated owner and current source snapshot. No approval, fact mutation or application submission. Use exact source excerpts and only current CONFIRMED fact IDs; proposed matches remain untrusted until deterministic review. Reuse client_request_id for retries of the same payload.';

    public function toArray(): array
    {
        $tool = parent::toArray();
        $tool['annotations']['readOnlyHint'] = false;

        return $tool;
    }

    public function schema(JsonSchema $schema): array
    {
        $notes = fn () => $schema->array()->items($schema->string()->max(1000))->max(30)->required();

        return [
            'vacancy_id' => $schema->string()->pattern('^[0-9a-hjkmnp-tv-z]{26}$')->required(),
            'snapshot_id' => $schema->string()->pattern('^[0-9a-hjkmnp-tv-z]{26}$')->required(),
            'client_request_id' => $schema->string()->pattern('^[A-Za-z0-9_-]{1,128}$')->required(),
            'analysis' => $schema->object([
                'requirements' => $schema->array()->max(50)->items($schema->object([
                    'dimension' => $schema->string()->enum(VacancyRequirement::DIMENSIONS)->required(),
                    'importance' => $schema->string()->enum(['MANDATORY', 'PREFERRED', 'UNCERTAIN'])->required(),
                    'label' => $schema->string()->max(255)->required(), 'normalized_value' => $schema->string()->max(500)->nullable()->required(),
                    'source_excerpt' => $schema->string()->max(2000)->required(), 'confidence' => $schema->number()->min(0)->max(1)->required(),
                ])->withoutAdditionalProperties())->required(),
                'matches' => $schema->array()->max(50)->items($schema->object([
                    'requirement_index' => $schema->integer()->min(0)->max(49)->required(),
                    'career_fact_ids' => $schema->array()->max(20)->items($schema->string()->pattern('^[0-9a-hjkmnp-tv-z]{26}$'))->required(),
                ])->withoutAdditionalProperties())->required(),
                'gaps' => $notes(), 'risks' => $notes(), 'questions' => $notes(), 'recommendations' => $notes(),
            ])->withoutAdditionalProperties()->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return ['id' => $schema->string()->required(), 'status' => $schema->string()->enum(['DRAFT', 'APPROVED'])->required(),
            'vacancy_id' => $schema->string()->required(), 'snapshot_id' => $schema->string()->required(), 'origin' => $schema->string()->required()];
    }

    public function handle(Request $request, VacancyAnalysisDraftService $service): Response|ResponseFactory
    {
        try {
            $user = $this->principal($request);
            $args = $request->validate(['vacancy_id' => ['required', 'ulid'], 'snapshot_id' => ['required', 'ulid'],
                'client_request_id' => ['required', 'string', 'max:128'], 'analysis' => ['required', 'array']]);
            if (count($request->all()) !== 4) {
                return $this->error('VALIDATION_FAILED');
            }
            $saved = $service->save($user, $args['vacancy_id'], $args['snapshot_id'], $args['client_request_id'], $args['analysis']);

            return $this->success(['id' => $saved['id'], 'status' => $saved['status'], 'vacancy_id' => $saved['vacancy_id'],
                'snapshot_id' => $saved['vacancy_snapshot_id'], 'origin' => $saved['origin']]);
        } catch (Throwable $exception) {
            return $this->safeError($exception);
        }
    }
}
