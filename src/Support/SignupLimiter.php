<?php

namespace Tok3\SectionEngineNewsletter\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class SignupLimiter
{
    /** @param array<string, mixed> $context */
    public function allows(Request $request, array $context): bool
    {
        $request->attributes->set('newsletter_list', $context['list']);
        $limits = RateLimiter::limiter('section-engine-newsletter')($request);
        foreach ($limits as $limit) {
            if (RateLimiter::tooManyAttempts($limit->key, $limit->maxAttempts)) {
                return false;
            }
        }
        foreach ($limits as $limit) {
            RateLimiter::hit($limit->key, $limit->decaySeconds);
        }

        return true;
    }
}
