@if(config('section-engine-newsletter.styles', true))@include('section-engine-newsletter::form.styles')@endif
<section class="sen-newsletter sen-newsletter--page">
    <h1 class="sen-newsletter__message" role="status" aria-live="polite" tabindex="-1" autofocus>{{ $message }}</h1>
    @if(!empty($returnUrl))
        <p><a href="{{ $returnUrl }}">{{ $returnLabel }}</a></p>
    @endif
</section>
