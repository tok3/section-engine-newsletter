<?php

namespace Tok3\SectionEngineNewsletter\Filament\Resources;

use Filament\Resources\Resource;
use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Tok3\SectionEngine\Support\SectionEngineAccess;

/**
 * @template TModel of Model
 *
 * @extends resource<TModel>
 */
abstract class AuthorizedResource extends Resource
{
    public static function getAuthorizationResponse(string|\UnitEnum $action, ?Model $record = null): Response
    {
        return app(SectionEngineAccess::class)->allows() ? Response::allow() : Response::deny();
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('section-engine-newsletter::admin.newsletter');
    }
}
