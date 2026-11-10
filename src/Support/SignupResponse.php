<?php

namespace Tok3\SectionEngineNewsletter\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;

class SignupResponse
{
    /** @param array<string, mixed> $context */
    public function respond(Request $request, array $context): JsonResponse|RedirectResponse
    {
        $list = NewsletterList::query()->where('key', $context['list'])->where('active', true)->first();
        $message = FormContext::localized(
            $list?->success_text,
            $context['locale'],
            __('section-engine-newsletter::newsletter.success', [], $context['locale']),
        );
        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'message' => $message]);
        }

        return redirect()->to(explode('#', $context['url'], 2)[0].'#newsletter-'.$context['form_id'])
            ->with('section-engine-newsletter.'.$context['form_id'], ['message' => $message, 'context' => $context]);
    }
}
