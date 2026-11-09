@php($id = 'newsletter-'.$data['form_id'])
@if($includeStyles)@include('section-engine-newsletter::form.styles')@endif
@if(isset($feedback['form_id']))<span id="newsletter-{{ $feedback['form_id'] }}"></span>@endif
<div class="sen-newsletter sen-newsletter--{{ $variant }}" lang="{{ $data['locale'] }}">
    @if($preview)<small class="sen-newsletter__preview">{{ __('section-engine-newsletter::newsletter.preview', ['list' => $list->name], $data['locale']) }}{{ $data['tag'] ? ', Tag: '.$data['tag'] : '' }}</small>@endif
    <form class="sen-newsletter__form" id="{{ $id }}" action="{{ url(trim(config('section-engine-newsletter.route_prefix'), '/').'/subscribe') }}" method="post" data-newsletter-form data-error-message="{{ __('section-engine-newsletter::newsletter.network_error', [], $data['locale']) }}">
        <input type="hidden" name="_token" value="{{ csrf_token() }}" @disabled($preview)>
        <input type="hidden" name="context" value="{{ $encryptedContext }}" @disabled($preview)>
        <div class="sen-newsletter__honeypot" aria-hidden="true">
            <label for="{{ $id }}-website">{{ __('section-engine-newsletter::newsletter.website', [], $data['locale']) }}</label>
            <input id="{{ $id }}-website" type="text" name="website" tabindex="-1" autocomplete="off" @disabled($preview)>
        </div>
        <div class="sen-newsletter__field sen-newsletter__field--email">
            <label class="sen-newsletter__label" for="{{ $id }}-email">{{ __('section-engine-newsletter::newsletter.email', [], $data['locale']) }}</label>
            <input class="sen-newsletter__input" id="{{ $id }}-email" type="email" name="email" autocomplete="email" maxlength="254" value="{{ $feedback['email'] ?? '' }}" @if(isset($feedback['errors']['email'])) aria-invalid="true" autofocus @endif required aria-describedby="{{ $id }}-email-error" @disabled($preview)>
            <div class="sen-newsletter__error" id="{{ $id }}-email-error" data-error="email">{{ implode(' ', $feedback['errors']['email'] ?? []) }}</div>
        </div>
        <p class="sen-newsletter__consent" id="{{ $id }}-consent-text">{{ $consent }}</p>
        @if(config('section-engine-newsletter.require_checkbox'))
            <div class="sen-newsletter__field sen-newsletter__field--checkbox">
                <input class="sen-newsletter__checkbox" id="{{ $id }}-consent" type="checkbox" name="consent" value="1" required @if(isset($feedback['errors']['consent'])) aria-invalid="true" autofocus @endif aria-describedby="{{ $id }}-consent-text {{ $id }}-consent-error" @disabled($preview)>
                <label class="sen-newsletter__label" for="{{ $id }}-consent">{{ $checkboxLabel }}</label>
                <div class="sen-newsletter__error" id="{{ $id }}-consent-error" data-error="consent">{{ implode(' ', $feedback['errors']['consent'] ?? []) }}</div>
            </div>
        @endif
        <button class="sen-newsletter__button" type="submit" @disabled($preview)>{{ __('section-engine-newsletter::newsletter.subscribe', [], $data['locale']) }}</button>
        <div class="sen-newsletter__message" id="{{ $id }}-status" role="status" aria-live="polite" tabindex="-1" data-newsletter-status @if(isset($feedback['message']) || isset($feedback['errors']['context'])) autofocus @endif>{{ $feedback['message'] ?? implode(' ', $feedback['errors']['context'] ?? []) }}</div>
    </form>
</div>
@if($includeScript)@include('section-engine-newsletter::form.script')@endif
