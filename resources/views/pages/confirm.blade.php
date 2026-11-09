@if(config('section-engine-newsletter.styles', true))@include('section-engine-newsletter::form.styles')@endif
<section class="sen-newsletter sen-newsletter--page">
    <h1>{{ $title }}</h1>
    @if($expired)
        <p class="sen-newsletter__message">{{ $expiredMessage }}</p>
        {!! $form !!}
    @else
        <form method="post" action="{{ route('section-engine-newsletter.confirm', ['subscription' => $subscription, 'token' => $token]) }}">
            @csrf
            <button class="sen-newsletter__button" type="submit">{{ $confirmLabel }}</button>
        </form>
    @endif
</section>
