@if($includeStyles)@include('section-engine-newsletter::form.styles')@endif
@php
    $email = isset($email) && $email !== '' ? $email : null;
    $at = $email === null ? false : mb_strpos($message, $email);
@endphp
<div class="sen-newsletter" @isset($anchor) id="{{ $anchor }}" @endisset lang="{{ $locale }}">
    <div class="sen-newsletter__message" id="newsletter-{{ $formId }}" role="status" aria-live="polite" tabindex="-1" data-newsletter-success autofocus>
        @if(!empty($title))<h2 class="sen-newsletter__title">{{ $title }}</h2>@endif
        <p class="sen-newsletter__text">@if($at === false){{ $message }}@else{{ mb_substr($message, 0, $at) }}<mark class="sen-newsletter__email">{{ $email }}</mark>{{ mb_substr($message, $at + mb_strlen($email)) }}@endif</p>
        @if(!empty($changeUrl) && !empty($changeLabel))
            <p class="sen-newsletter__change"><a href="{{ $changeUrl }}" data-newsletter-change>{{ $changeLabel }}</a></p>
        @endif
    </div>
</div>
@if($includeScript)@include('section-engine-newsletter::form.script')@endif
