<?php

namespace Tok3\SectionEngineNewsletter\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;

class SignupResponse
{
    /**
     * The notice names the submitted address (`:email` in the text). It is only part of this
     * response or of the one-time session flash, never of a URL, and never cached.
     *
     * @param  array<string, mixed>  $context
     */
    public function respond(Request $request, array $context, ?string $email = null): JsonResponse|RedirectResponse
    {
        $list = NewsletterList::query()->where('key', $context['list'])->where('active', true)->first();
        $message = FormContext::localized(
            $list?->success_text,
            $context['locale'],
            __('section-engine-newsletter::newsletter.success', [], $context['locale']),
        );
        $message = strtr($message, [':email' => (string) $email]);
        $title = __('section-engine-newsletter::newsletter.success_title', [], $context['locale']);
        $change = __('section-engine-newsletter::newsletter.change_address', [], $context['locale']);
        if ($request->expectsJson()) {
            return response()->json(['status' => 'success', 'title' => $title, 'message' => $message, 'email' => $email, 'change' => $change])
                ->header('Cache-Control', 'no-store, private');
        }

        return redirect()->to(explode('#', $context['url'], 2)[0].'#newsletter-'.$context['form_id'])
            ->with('section-engine-newsletter.'.$context['form_id'], ['title' => $title, 'message' => $message, 'email' => $email, 'context' => $context]);
    }
}
