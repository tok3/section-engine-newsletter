@if($includeStyles)@include('section-engine-newsletter::form.styles')@endif
<div class="sen-newsletter" lang="{{ $locale }}">
    <div class="sen-newsletter__message" role="status" tabindex="-1" data-newsletter-confirmed @if($autofocus) autofocus @endif>{{ $message }}</div>
</div>
@if($includeScript)@include('section-engine-newsletter::form.script')@endif
