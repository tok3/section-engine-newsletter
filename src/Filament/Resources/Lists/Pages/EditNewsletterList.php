<?php

namespace Tok3\SectionEngineNewsletter\Filament\Resources\Lists\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Tok3\SectionEngineNewsletter\Filament\Resources\NewsletterListResource;

class EditNewsletterList extends EditRecord
{
    protected static string $resource = NewsletterListResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
