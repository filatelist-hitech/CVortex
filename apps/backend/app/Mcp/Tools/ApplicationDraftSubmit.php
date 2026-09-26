<?php

namespace App\Mcp\Tools;

use App\Mcp\McpApplicationAdapter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsDestructive;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Throwable;

#[IsDestructive(false)]
#[IsOpenWorld(false)]
class ApplicationDraftSubmit extends BoundedTool
{
    protected string $name = 'application_draft_submit';

    protected string $description = 'Submit one cover draft for CVortex Truth Guard validation. A passing draft remains pending human review; this tool cannot approve or send it.';

    public function schema(JsonSchema $schema): array
    {
        return [
            'vacancy_id' => $schema->string()->pattern('^[0-9a-hjkmnp-tv-z]{26}$')->required(),
            'variant' => $schema->string()->enum(['SHORT', 'STANDARD'])->required(),
            'content' => $schema->string()->min(1)->max(6000)->required(),
            'claim_usages' => $schema->array()->items($schema->object([
                'assertion' => $schema->string()->min(1)->max(6000)->required(),
                'claim_ids' => $schema->array()->items($schema->string()->pattern('^[0-9a-hjkmnp-tv-z]{26}$'))->min(1)->max(8)->required(),
            ])->withoutAdditionalProperties())->max(30)->required(),
        ];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'draft_id' => $schema->string()->required(),
            'preparation_id' => $schema->string()->required(),
            'validation_result' => $schema->string()->enum(['PASS'])->required(),
            'review_state' => $schema->string()->enum(['PENDING_REVIEW'])->required(),
            'requires_cvortex_approval' => $schema->boolean()->required(),
        ];
    }

    public function handle(Request $request, McpApplicationAdapter $adapter): Response|ResponseFactory
    {
        try {
            $args = $request->validate([
                'vacancy_id' => ['required', 'ulid'], 'variant' => ['required', 'in:SHORT,STANDARD'],
                'content' => ['required', 'string', 'min:1', 'max:6000'],
                'claim_usages' => ['present', 'array', 'max:30'],
                'claim_usages.*' => ['required', 'array:assertion,claim_ids'],
                'claim_usages.*.assertion' => ['required', 'string', 'max:6000'],
                'claim_usages.*.claim_ids' => ['required', 'array', 'min:1', 'max:8'],
                'claim_usages.*.claim_ids.*' => ['required', 'ulid'],
            ]);
            if (count($request->all()) !== 4 || ! array_is_list($args['claim_usages'])) {
                return $this->error('VALIDATION_FAILED');
            }

            return $this->success($adapter->submitDraft(
                $this->principal($request), $args['vacancy_id'], $args['variant'],
                $args['content'], $args['claim_usages'],
            ));
        } catch (Throwable $exception) {
            return $this->safeError($exception);
        }
    }
}
