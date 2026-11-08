<?php

namespace Tok3\SectionEngineNewsletter\LiveTags;

use Illuminate\Http\Request;
use Tok3\SectionEngine\Contracts\LiveTagProvider;
use Tok3\SectionEngine\Support\LiveTag;
use Tok3\SectionEngine\Support\LiveTokenRenderContext;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Support\FormContext;

class NewsletterLiveTagProvider implements LiveTagProvider
{
    /** @var \WeakMap<Request, bool>|null */
    private static ?\WeakMap $scripts = null;

    public function prefix(): string
    {
        return 'newsletter';
    }

    public function label(): string
    {
        return 'Newsletter';
    }

    public function render(LiveTag $tag, LiveTokenRenderContext $context): string
    {
        $list = NewsletterList::query()->where('key', $tag->argument)->where('active', true)->first();
        if (! $list) {
            return $context->editorPreview || config('section-engine.design_mode')
                ? '<mark data-section-engine-newsletter-missing="'.e($tag->argument).'">'.e($tag->placeholder).'</mark>' : '';
        }
        $variants = config('section-engine-newsletter.views.variants');
        $variant = array_key_exists($tag->option('variant', 'default'), $variants) ? $tag->option('variant', 'default') : 'default';
        $formContext = app(FormContext::class);
        $data = $formContext->create($list, $context, $tag->option('tag'));
        $scripts = self::$scripts ??= new \WeakMap;
        $includeScript = ! $context->editorPreview && ! isset($scripts[request()]);
        if ($includeScript) {
            $scripts[request()] = true;
        }

        $feedback = [];
        foreach (session('section-engine-newsletter', []) as $formId => $entry) {
            $previous = $entry['context'] ?? [];
            if (($previous['list'] ?? null) === $data['list'] && ($previous['tag'] ?? null) === $data['tag']
                && ($previous['url'] ?? null) === $data['url'] && ($previous['page_id'] ?? null) === $data['page_id']
                && ($previous['instance'] ?? null) === $data['instance']) {
                $feedback = $entry + ['form_id' => $formId];
                break;
            }
        }

        return view($variants[$variant], ['feedback' => $feedback,
            'list' => $list, 'data' => $data, 'encryptedContext' => $formContext->encrypt($data),
            'preview' => $context->editorPreview, 'variant' => $variant, 'includeScript' => $includeScript,
            'consent' => FormContext::localized($list->consent_text, $context->locale),
            'checkboxLabel' => __('section-engine-newsletter::newsletter.checkbox', [], $context->locale),
        ])->render();
    }

    public function insertOptions(): array
    {
        return NewsletterList::query()->where('active', true)->orderBy('name')->get()->map(fn (NewsletterList $list): array => [
            'label' => $list->name, 'snippet' => '{{ newsletter:'.$list->key.' }}',
        ])->all();
    }
}
