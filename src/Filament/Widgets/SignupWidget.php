<?php

namespace Tok3\SectionEngineNewsletter\Filament\Widgets;

use Filament\Widgets\Widget;
use Tok3\SectionEngine\Support\SectionEngineAccess;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class SignupWidget extends Widget
{
    protected string $view = 'section-engine-newsletter::admin.signup-widget';

    public static function canView(): bool
    {
        return app(SectionEngineAccess::class)->allows();
    }

    /** @return array{lists: array<string, int>, tags: array<string, int>} */
    public function counts(): array
    {
        abort_unless(static::canView(), 403);
        $subscriptions = Subscription::query()->where('requested_at', '>=', now()->subDays(30))->with('list')->get();

        return ['lists' => $subscriptions->countBy(fn ($s) => $s->list->name)->all(), 'tags' => $subscriptions->countBy(fn ($s) => $s->tag ?? __('section-engine-newsletter::admin.no_tag'))->all()];
    }
}
