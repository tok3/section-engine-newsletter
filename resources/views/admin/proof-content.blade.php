<h1>{{ __('section-engine-newsletter::admin.proof') }}: {{ $subscriber->email }}</h1>
@foreach($subscriber->subscriptions as $subscription)
<section><h2>{{ $subscription->list->name }} · {{ $subscription->status->label() }}</h2>
<p>{!! app(\Tok3\SectionEngineNewsletter\Support\SourcePage::class)->html($subscription) !!}</p>
<pre>{{ json_encode($subscription->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
<h3>{{ __('section-engine-newsletter::admin.history') }}</h3>
@foreach($subscription->events as $event)
<pre>{{ json_encode($event->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
@endforeach
</section>
@endforeach
