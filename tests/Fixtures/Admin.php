<?php

namespace Tok3\SectionEngineNewsletter\Tests\Fixtures;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User;

class Admin extends User implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->email === 'admin@example.org';
    }
}
