<?php

namespace Tok3\SectionEngineNewsletter\Filament\Resources;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Tok3\SectionEngine\Support\LiveTagUsageFinder;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Support\ListTextDefaults;

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
        /** @var array<string, string> $locales */
        $locales = config('section-engine.locales.available', []);
        $defaultLocale = (string) config('section-engine.locales.default', 'en');
        $textComponents = count($locales) === 1
            ? self::localeFields((string) array_key_first($locales), $defaultLocale)
            : [Tabs::make()
                ->key('locale-tabs', isInheritable: false)
                ->tabs(collect($locales)->map(
                    fn (string $label, string $locale): Tab => Tab::make($label)
                        ->key('locale-'.$locale, isInheritable: false)
                        ->schema(self::localeFields($locale, $defaultLocale)),
                )->values()->all())];

        return $schema->components([
            Section::make(__('section-engine-newsletter::admin.list_section'))
                ->key('list-section', isInheritable: false)
                ->schema([
                    TextInput::make('key')->label(__('section-engine-newsletter::admin.key'))->helperText(__('section-engine-newsletter::admin.key_help'))->required()->maxLength(255)->regex('/\A[a-z0-9_-]+\z/')->unique(ignoreRecord: true),
                    TextInput::make('name')->label(__('section-engine-newsletter::admin.name'))->helperText(__('section-engine-newsletter::admin.name_help'))->required()->maxLength(255),
                    Toggle::make('active')->label(__('section-engine-newsletter::admin.active'))->helperText(__('section-engine-newsletter::admin.active_help'))->default(true),
                    Textarea::make('description')->label(__('section-engine-newsletter::admin.description'))->helperText(__('section-engine-newsletter::admin.description_help')),
                ]),
            Section::make(__('section-engine-newsletter::admin.texts_section'))
                ->key('texts-section', isInheritable: false)
                ->schema($textComponents),
            Section::make(__('section-engine-newsletter::admin.embedded_on'))
                ->key('usage-section', isInheritable: false)
                ->visibleOn('edit')
                ->schema([
                    Placeholder::make('usage')->hiddenLabel()->content(fn (?NewsletterList $record) => $record ? static::usageHtml($record) : ''),
                ]),
        ]);
    }

    /** @return array<int, Textarea|TextInput> */
    private static function localeFields(string $locale, string $defaultLocale): array
    {
        $defaults = ListTextDefaults::forLocale($locale);

        return [
            Textarea::make('consent_text.'.$locale)
                ->label(__('section-engine-newsletter::admin.consent'))
                ->helperText(__('section-engine-newsletter::admin.consent_help'))
                ->default(fn (string $operation): ?string => $operation === 'create' ? $defaults['consent_text'] : null)
                ->required($locale === $defaultLocale),
            Textarea::make('success_text.'.$locale)
                ->label(__('section-engine-newsletter::admin.success'))
                ->helperText(__('section-engine-newsletter::admin.success_help'))
                ->default(fn (string $operation): ?string => $operation === 'create' ? $defaults['success_text'] : null),
            TextInput::make('confirmation_subject.'.$locale)
                ->label(__('section-engine-newsletter::admin.subject'))
                ->helperText(__('section-engine-newsletter::admin.subject_help'))
                ->default(fn (string $operation): ?string => $operation === 'create' ? $defaults['confirmation_subject'] : null)
                ->maxLength(255),
            Textarea::make('confirmation_intro.'.$locale)
                ->label(__('section-engine-newsletter::admin.intro'))
                ->helperText(__('section-engine-newsletter::admin.intro_help'))
                ->default(fn (string $operation): ?string => $operation === 'create' ? $defaults['confirmation_intro'] : null),
        ];
    }

    public static function usageHtml(NewsletterList $list): HtmlString
    {
        $html = app(LiveTagUsageFinder::class)->usagesFor('newsletter', $list->key)->map(fn ($usage): string => '<p>'.($usage['url'] ? '<a href="'.e($usage['url']).'">'.e($usage['label']).'</a>' : e($usage['label'])).' ('.e($usage['status']).')</p>')->implode('');

        return new HtmlString($html !== '' ? $html : '<p>'.e(__('section-engine-newsletter::admin.no_embeds')).'</p>');
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
