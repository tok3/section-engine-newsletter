<?php

namespace Tok3\SectionEngineNewsletter\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Tok3\SectionEngine\Support\LiveTagUsageFinder;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;

/** @extends AuthorizedResource<NewsletterList> */
class NewsletterListResource extends AuthorizedResource
{
    protected static ?string $model = NewsletterList::class;

    protected static ?string $slug = 'newsletter-lists';

    public static function getModelLabel(): string
    {
        return __('section-engine-newsletter::admin.list');
    }

    public static function getPluralModelLabel(): string
    {
        return __('section-engine-newsletter::admin.lists');
    }

    /** @return Builder<NewsletterList> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'subscriptions as pending_count' => fn (Builder $q) => $q->where('status', 'pending'),
            'subscriptions as confirmed_count' => fn (Builder $q) => $q->where('status', 'confirmed'),
            'subscriptions as unsubscribed_count' => fn (Builder $q) => $q->where('status', 'unsubscribed'),
        ]);
    }

    public static function form(Schema $schema): Schema
    {
        $fields = [TextInput::make('key')->label(__('section-engine-newsletter::admin.key'))->required()->maxLength(255)->regex('/\A[a-z0-9_-]+\z/')->unique(ignoreRecord: true),
            TextInput::make('name')->label(__('section-engine-newsletter::admin.name'))->required()->maxLength(255),
            Textarea::make('description')->label(__('section-engine-newsletter::admin.description')),
            Toggle::make('active')->label(__('section-engine-newsletter::admin.active'))->default(true)];
        foreach (array_unique(['de', 'en', config('section-engine-newsletter.default_locale')]) as $locale) {
            $fields[] = Textarea::make('consent_text.'.$locale)->label(__('section-engine-newsletter::admin.consent').' ('.$locale.')')->required($locale === config('section-engine-newsletter.default_locale'));
            $fields[] = TextInput::make('confirmation_subject.'.$locale)->label(__('section-engine-newsletter::admin.subject').' ('.$locale.')')->maxLength(255);
            $fields[] = Textarea::make('confirmation_intro.'.$locale)->label(__('section-engine-newsletter::admin.intro').' ('.$locale.')');
        }
        $fields[] = Placeholder::make('usage')->label(__('section-engine-newsletter::admin.embedded_on'))->content(fn (?NewsletterList $record) => $record ? static::usageHtml($record) : '');

        return $schema->components($fields);
    }

    public static function usageHtml(NewsletterList $list): HtmlString
    {
        return new HtmlString(app(LiveTagUsageFinder::class)->usagesFor('newsletter', $list->key)->map(fn ($usage): string => '<p>'.($usage['url'] ? '<a href="'.e($usage['url']).'">'.e($usage['label']).'</a>' : e($usage['label'])).' ('.e($usage['status']).')</p>')->implode(''));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label(__('section-engine-newsletter::admin.name'))->searchable(),
            TextColumn::make('key')->label(__('section-engine-newsletter::admin.key'))->searchable(),
            TextColumn::make('confirmed_count')->label(__('section-engine-newsletter::newsletter.status.confirmed')),
            TextColumn::make('pending_count')->label(__('section-engine-newsletter::newsletter.status.pending')),
            TextColumn::make('unsubscribed_count')->label(__('section-engine-newsletter::newsletter.status.unsubscribed')),
            TextColumn::make('snippet')->label(__('section-engine-newsletter::admin.copy_snippet'))->state(fn (NewsletterList $record): string => '{{ newsletter:'.$record->key.' }}')->copyable(),
        ])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => Lists\Pages\ListNewsletterLists::route('/'), 'create' => Lists\Pages\CreateNewsletterList::route('/create'), 'edit' => Lists\Pages\EditNewsletterList::route('/{record}/edit')];
    }
}
