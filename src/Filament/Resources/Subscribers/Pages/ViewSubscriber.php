<?php

namespace Tok3\SectionEngineNewsletter\Filament\Resources\Subscribers\Pages;

use Filament\Resources\Pages\ViewRecord;
use Tok3\SectionEngineNewsletter\Filament\Resources\SubscriberResource;

class ViewSubscriber extends ViewRecord
{
    protected static string $resource = SubscriberResource::class;
}
