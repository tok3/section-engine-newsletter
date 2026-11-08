<!doctype html>
<html lang="{{ app()->getLocale() }}"><body>
<p>{{ $intro }}</p>
<p><a href="{{ $confirmationUrl }}">{{ __('section-engine-newsletter::newsletter.confirm_button') }}</a></p>
<p>{{ __('section-engine-newsletter::newsletter.mail_ignore') }}</p>
</body></html>
