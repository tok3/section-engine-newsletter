@if(config('section-engine-newsletter.styles', true))@include('section-engine-newsletter::form.styles')@endif
<section class="sen-newsletter sen-newsletter--page">
    <h1>{{ $title }}</h1>
    <form method="post" action="{{ request()->fullUrl() }}">
        @csrf
        <button class="sen-newsletter__button" type="submit">{{ $buttonLabel }}</button>
    </form>
</section>
