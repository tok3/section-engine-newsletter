<?php

namespace Tok3\SectionEngineNewsletter\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlternatePage extends Model
{
    use SoftDeletes;

    protected $table = 'alternate_pages';
}
