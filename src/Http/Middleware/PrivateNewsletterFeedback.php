<?php

namespace Tok3\SectionEngineNewsletter\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Pages that show a submitted address after a signup must never be stored by browsers or
 * shared caches. The newsletter tag marks such a request while rendering.
 */
class PrivateNewsletterFeedback
{
    public const ATTRIBUTE = 'section-engine-newsletter.private';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->attributes->get(self::ATTRIBUTE) === true) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
