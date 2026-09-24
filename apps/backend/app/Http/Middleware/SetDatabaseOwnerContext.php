<?php

namespace App\Http\Middleware;

use App\Services\DatabaseOwnerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetDatabaseOwnerContext
{
    public function __construct(private readonly DatabaseOwnerContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $ownerId = (string) $request->user()->id;
        $this->context->set($ownerId);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
