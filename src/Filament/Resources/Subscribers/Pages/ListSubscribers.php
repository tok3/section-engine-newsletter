<?php

namespace Tok3\SectionEngineNewsletter\Filament\Resources\Subscribers\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ListRecords;
use Tok3\SectionEngineNewsletter\Filament\Resources\SubscriberResource;
use Tok3\SectionEngineNewsletter\Models\NewsletterList;
use Tok3\SectionEngineNewsletter\Services\ManageSubscribers;
use Tok3\SectionEngineNewsletter\Support\FormContext;

class ListSubscribers extends ListRecords
{
    protected static string $resource = SubscriberResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('add')->label(__('section-engine-newsletter::admin.add'))->schema([
                TextInput::make('email')->mutateStateForValidationUsing(fn (?string $state): string => mb_strtolower(trim($state ?? '')))->label(__('section-engine-newsletter::newsletter.email'))->required()->email()->maxLength(254),
                Select::make('list_id')->label(__('section-engine-newsletter::admin.list'))->required()->options(fn () => NewsletterList::where('active', true)->pluck('name', 'id')),
                TextInput::make('locale')->label(__('section-engine-newsletter::admin.locale'))->required()->default(FormContext::defaultLocale()),
                TextInput::make('tag')->label(__('section-engine-newsletter::admin.tag')),
            ])->action(fn (array $data) => app(ManageSubscribers::class)->add($data)),
            Action::make('csv')->label(__('section-engine-newsletter::admin.csv'))->action(fn () => app(ManageSubscribers::class)->csv($this->getFilteredTableQuery(), $this->getTableFilterState('subscription') ?? [])),
        ];
    }
}
