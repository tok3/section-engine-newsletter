@php($id = 'newsletter-'.$data['form_id'])
@if(isset($feedback['form_id']))<span id="newsletter-{{ $feedback['form_id'] }}"></span>@endif
<div class="newsletter-form newsletter-form--{{ $variant }}" lang="{{ $data['locale'] }}">
    @if($preview)<small>{{ __('section-engine-newsletter::newsletter.preview', ['list' => $list->name], $data['locale']) }}{{ $data['tag'] ? ', Tag: '.$data['tag'] : '' }}</small>@endif
    <form id="{{ $id }}" action="{{ url(trim(config('section-engine-newsletter.route_prefix'), '/').'/subscribe') }}" method="post" data-newsletter-form>
        <input type="hidden" name="_token" value="{{ csrf_token() }}" @disabled($preview)>
        <input type="hidden" name="context" value="{{ $encryptedContext }}" @disabled($preview)>
        <div style="position:absolute;left:-10000px;width:1px;height:1px;overflow:hidden" aria-hidden="true">
            <label for="{{ $id }}-website">{{ __('section-engine-newsletter::newsletter.website', [], $data['locale']) }}</label>
            <input id="{{ $id }}-website" type="text" name="website" tabindex="-1" autocomplete="off" @disabled($preview)>
        </div>
        <div class="mb-3">
            <label class="form-label" for="{{ $id }}-email">{{ __('section-engine-newsletter::newsletter.email', [], $data['locale']) }}</label>
            <input class="form-control" id="{{ $id }}-email" type="email" name="email" autocomplete="email" maxlength="254" value="{{ $feedback['email'] ?? '' }}" @if(isset($feedback['errors']['email'])) aria-invalid="true" autofocus @endif required aria-describedby="{{ $id }}-email-error" @disabled($preview)>
            <div class="text-danger" id="{{ $id }}-email-error" data-error="email">{{ implode(' ', $feedback['errors']['email'] ?? []) }}</div>
        </div>
        <p id="{{ $id }}-consent-text">{{ $consent }}</p>
        @if(config('section-engine-newsletter.require_checkbox'))
            <div class="form-check mb-3">
                <input class="form-check-input" id="{{ $id }}-consent" type="checkbox" name="consent" value="1" required aria-describedby="{{ $id }}-consent-text {{ $id }}-consent-error" @disabled($preview)>
                <label class="form-check-label" for="{{ $id }}-consent">{{ $checkboxLabel }}</label>
                <div class="text-danger" id="{{ $id }}-consent-error" data-error="consent">{{ implode(' ', $feedback['errors']['consent'] ?? []) }}</div>
            </div>
        @endif
        <button class="btn btn-primary" type="submit" style="min-height:44px" @disabled($preview)>{{ __('section-engine-newsletter::newsletter.subscribe', [], $data['locale']) }}</button>
        <div id="{{ $id }}-status" role="status" aria-live="polite" tabindex="-1" data-newsletter-status>{{ $feedback['message'] ?? implode(' ', $feedback['errors']['context'] ?? []) }}</div>
    </form>
</div>
@if($includeScript)@include('section-engine-newsletter::form.script')@endif
