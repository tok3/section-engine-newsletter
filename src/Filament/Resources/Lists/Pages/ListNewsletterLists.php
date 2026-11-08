<?php

namespace Tok3\SectionEngineNewsletter\Filament\Resources\Lists\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Tok3\SectionEngineNewsletter\Filament\Resources\NewsletterListResource;

class ListNewsletterLists extends ListRecords
{
    protected static string $resource = NewsletterListResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
