@if(config('section-engine-newsletter.styles', true))@include('section-engine-newsletter::form.styles')@endif
<section class="sen-newsletter sen-newsletter--page">
    <h1 class="sen-newsletter__title" tabindex="-1" autofocus>{{ $title ?? $message }}</h1>
    @isset($title)
        <p class="sen-newsletter__text sen-newsletter__message" role="status">{{ $message }}</p>
    @endisset
    @if(!empty($returnUrl))
        <p><a href="{{ $returnUrl }}">{{ $returnLabel }}</a></p>
    @endif
</section>
