<?php

namespace Tok3\SectionEngineNewsletter\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Tok3\SectionEngineNewsletter\Models\Subscription;

class PruneCommand extends Command
{
    protected $signature = 'section-engine-newsletter:prune';

    protected $description = 'Apply newsletter retention periods';

    public function handle(): int
    {
        DB::transaction(function (): void {
            Subscription::query()->where('status', 'pending')->whereNull('confirmed_at')->where('requested_at', '<', now()->subDays(config('section-engine-newsletter.prune.pending_days')))->delete();
            $old = Subscription::query()->where('status', 'unsubscribed')->where('unsubscribed_at', '<', now()->subYears(config('section-engine-newsletter.prune.unsubscribed_proof_years')));
            DB::table('section_engine_newsletter_events')->whereIn('subscription_id', (clone $old)->select('id'))->update(['ip' => null, 'user_agent' => null]);
            $old->update(['requested_ip' => null, 'requested_user_agent' => null, 'confirmed_ip' => null, 'confirmed_user_agent' => null, 'unsubscribed_ip' => null]);
        });

        return self::SUCCESS;
    }
}
