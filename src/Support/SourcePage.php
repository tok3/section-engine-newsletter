<?php

namespace Tok3\SectionEngineNewsletter\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\HtmlString;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class SourcePage
{
    public function html(Subscription $subscription): HtmlString
    {
        $page = null;
        $model = config('section-engine.models.page');
        if ($subscription->source_page_id && is_string($model) && is_subclass_of($model, Model::class)) {
            $query = (new $model)->newQuery();
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $query->withoutGlobalScope(SoftDeletingScope::class);
            }
            $page = $query->find($subscription->source_page_id);
        }
        $label = $page ? $page->getAttribute('title') : ($subscription->source_page_id ? __('section-engine-newsletter::admin.page_deleted') : __('section-engine-newsletter::admin.no_page'));
        $url = $subscription->source_url;
        $safe = is_string($url) && in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?: ''), ['https', 'http'], true);

        return new HtmlString(e($label).($url ? ' · '.($safe ? '<a href="'.e($url).'" rel="noreferrer">'.e($url).'</a>' : e($url)) : ''));
    }
}
