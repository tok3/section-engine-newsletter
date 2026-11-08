<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ __('section-engine-newsletter::newsletter.confirm_button') }}</title></head><body><main>
@if($expired)
    <h1>{{ __('section-engine-newsletter::newsletter.expired') }}</h1>
    {!! $form !!}
@else
    <h1>{{ __('section-engine-newsletter::newsletter.confirm_button') }}</h1>
    <form method="post" action="{{ route('section-engine-newsletter.confirm', ['subscription' => $subscription, 'token' => $token]) }}">
        @csrf
        <button type="submit" style="min-height:44px">{{ __('section-engine-newsletter::newsletter.confirm_button') }}</button>
    </form>
@endif
</main></body></html>
