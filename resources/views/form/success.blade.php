@if($includeStyles)@include('section-engine-newsletter::form.styles')@endif
<div class="sen-newsletter" lang="{{ $locale }}">
    <div class="sen-newsletter__message" id="newsletter-{{ $formId }}" role="status" aria-live="polite" tabindex="-1" data-newsletter-success autofocus>{{ $message }}</div>
</div>
@if($includeScript)@include('section-engine-newsletter::form.script')@endif
