<!doctype html>
<html lang="{{ app()->getLocale() }}"><head><meta charset="utf-8"><title>{{ __('section-engine-newsletter::newsletter.unsubscribe') }}</title></head><body><main>
<h1>{{ __('section-engine-newsletter::newsletter.unsubscribe') }}</h1>
<form method="post" action="{{ request()->fullUrl() }}">
    @csrf
    <button type="submit" style="min-height:44px">{{ __('section-engine-newsletter::newsletter.unsubscribe') }}</button>
</form>
</main></body></html>
