<?php

namespace Tok3\SectionEngineNewsletter\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Tok3\SectionEngineNewsletter\Http\Requests\SubscribeRequest;
use Tok3\SectionEngineNewsletter\Services\RequestSubscription;
use Tok3\SectionEngineNewsletter\Support\SignupLimiter;
use Tok3\SectionEngineNewsletter\Support\SignupResponse;

class SubscribeController
{
    public function __invoke(SubscribeRequest $request, SignupLimiter $limiter, RequestSubscription $subscriptions, SignupResponse $response): JsonResponse|RedirectResponse
    {
        if ($limiter->allows($request, $request->formContext)) {
            $subscriptions->handle($request->validated('email'), $request->formContext, $request);
        }

        return $response->respond($request, $request->formContext);
    }
}
