<?php

namespace Tok3\SectionEngineNewsletter\Filament\Resources;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\ViewEntry;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Tok3\SectionEngineNewsletter\Enums\SubscriptionStatus;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Models\Subscriber;
use Tok3\SectionEngineNewsletter\Models\Subscription;
use Tok3\SectionEngineNewsletter\Services\ManageSubscribers;
use Tok3\SectionEngineNewsletter\Support\SourcePage;

/** @extends AuthorizedResource<Subscriber> */
class SubscriberResource extends AuthorizedResource
{
    protected static ?string $model = Subscriber::class;

    protected static ?string $slug = 'newsletter-subscribers';

    public static function getModelLabel(): string
    {
        return __('section-engine-newsletter::admin.subscriber');
    }

    public static function getPluralModelLabel(): string
    {
        return __('section-engine-newsletter::admin.subscribers');
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([ViewEntry::make('proof')->view('section-engine-newsletter::admin.proof-entry')->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('email')->label(__('section-engine-newsletter::newsletter.email'))->searchable()->sortable(),
            TextColumn::make('subscriptions.list.name')->label(__('section-engine-newsletter::admin.lists')),
            TextColumn::make('subscriptions.status')->label(__('section-engine-newsletter::admin.status'))->badge()->formatStateUsing(fn (SubscriptionStatus $state): string => $state->label())->color(fn (SubscriptionStatus $state): string => $state->color()),
            TextColumn::make('subscriptions.tag')->label(__('section-engine-newsletter::admin.tag')),
            TextColumn::make('sources')->label(__('section-engine-newsletter::admin.source'))->html()->state(fn (Subscriber $record): HtmlString => new HtmlString($record->subscriptions->map(fn ($subscription): string => app(SourcePage::class)->html($subscription)->toHtml())->implode('<br>'))),
            TextColumn::make('subscriptions.requested_at')->label(__('section-engine-newsletter::admin.requested_at'))->dateTime(),
            TextColumn::make('subscriptions.confirmed_at')->label(__('section-engine-newsletter::admin.confirmed_at'))->dateTime(),
            TextColumn::make('subscriptions.unsubscribed_at')->label(__('section-engine-newsletter::admin.unsubscribed_at'))->dateTime(),
        ])->modifyQueryUsing(fn (Builder $query) => $query->with(['subscriptions.list']))->filters([
            Filter::make('subscription')->label(__('section-engine-newsletter::admin.subscription'))->schema([
                Select::make('list_id')->label(__('section-engine-newsletter::admin.list'))->options(fn () => NewsletterList::withTrashed()->pluck('name', 'id')),
                Select::make('status')->label(__('section-engine-newsletter::admin.status'))->options(collect(SubscriptionStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all()),
                TextInput::make('tag')->label(__('section-engine-newsletter::admin.tag')),
                TextInput::make('source_page_id')->label(__('section-engine-newsletter::admin.source_page_id'))->numeric(),
                DatePicker::make('from')->label(__('section-engine-newsletter::admin.from')),
                DatePicker::make('until')->label(__('section-engine-newsletter::admin.until')),
            ])->query(fn (Builder $query, array $data): Builder => $query->whereIn('id', app(ManageSubscribers::class)->filter(Subscription::query(), $data)->select('subscriber_id'))),
        ])->recordActions([
            ViewAction::make(),
            Action::make('proof')->label(__('section-engine-newsletter::admin.proof'))->url(fn (Subscriber $record) => route('section-engine-newsletter.proof', $record))->openUrlInNewTab(),
            Action::make('unsubscribe')->label(__('section-engine-newsletter::newsletter.unsubscribe'))->requiresConfirmation()->schema([
                Select::make('subscription_id')->label(__('section-engine-newsletter::admin.subscription'))->required()->options(fn (Subscriber $record) => $record->subscriptions->mapWithKeys(fn ($s) => [$s->id => $s->list->name.' · '.$s->status->label()])->all()),
            ])->action(fn (Subscriber $record, array $data) => app(ManageSubscribers::class)->unsubscribe($record, (int) $data['subscription_id'])),
            DeleteAction::make()->label(__('section-engine-newsletter::admin.erase'))->requiresConfirmation()->action(fn (Subscriber $record) => app(ManageSubscribers::class)->erase($record)),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Subscribers\Pages\ListSubscribers::route('/'), 'view' => Subscribers\Pages\ViewSubscriber::route('/{record}')];
    }
}
