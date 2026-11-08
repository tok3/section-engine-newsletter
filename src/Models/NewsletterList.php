<?php

namespace Tok3\SectionEngineNewsletter\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Tok3\SectionEngineNewsletter\Database\Factories\NewsletterListFactory;

class NewsletterList extends Model
{
    /** @use HasFactory<NewsletterListFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $table = 'section_engine_newsletter_lists';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['active' => 'boolean', 'consent_text' => 'array', 'confirmation_subject' => 'array', 'confirmation_intro' => 'array'];
    }

    protected static function newFactory(): NewsletterListFactory
    {
        return NewsletterListFactory::new();
    }

    /** @return HasMany<Subscription, $this> */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'list_id');
    }
}
