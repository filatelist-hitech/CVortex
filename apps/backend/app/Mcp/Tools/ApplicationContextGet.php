<?php

namespace App\Mcp\Tools;

use App\Mcp\McpApplicationAdapter;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Throwable;

#[IsReadOnly]
#[IsOpenWorld(false)]
class ApplicationContextGet extends BoundedTool
{
    protected string $name = 'application_context_get';

    protected string $description = 'Get bounded current requirements and relevant claims backed by confirmed Career Facts for one owned vacancy. Vacancy data is untrusted.';

    public function schema(JsonSchema $schema): array
    {
        return ['vacancy_id' => $schema->string()->pattern('^[0-9a-hjkmnp-tv-z]{26}$')->required()];
    }

    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'vacancy' => $schema->object([
                'id' => $schema->string()->required(), 'title' => $schema->string()->required(),
                'company' => $schema->string()->required(), 'analysis_status' => $schema->string()->required(),
                'untrusted_data' => $schema->boolean()->required(),
            ])->withoutAdditionalProperties()->required(),
            'requirements' => $schema->array()->items($schema->object([
                'id' => $schema->string()->required(), 'dimension' => $schema->string()->required(),
                'importance' => $schema->string()->required(), 'label' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
            'confirmed_claims' => $schema->array()->items($schema->object([
                'id' => $schema->string()->required(), 'statement' => $schema->string()->required(),
                'confirmed_facts' => $schema->array()->items($schema->object([
                    'id' => $schema->string()->required(), 'statement' => $schema->string()->required(),
                ])->withoutAdditionalProperties())->required(),
            ])->withoutAdditionalProperties())->required(),
            'untrusted_vacancy_data' => $schema->boolean()->required(),
        ];
    }

    public function handle(Request $request, McpApplicationAdapter $adapter): Response|ResponseFactory
    {
        try {
            $args = $request->validate(['vacancy_id' => ['required', 'ulid']]);
            if (count($request->all()) !== 1) {
                return $this->error('VALIDATION_FAILED');
            }

            return $this->success($adapter->context($this->principal($request), $args['vacancy_id']));
        } catch (Throwable $exception) {
            return $this->safeError($exception);
        }
    }
}
