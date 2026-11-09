<x-mail::message>
@if($logoUrl)
<img src="{{ $logoUrl }}" alt="{{ $brandName }}">
@endif

# {{ $greeting }}

{{ $listText }}

{{ $intro }}

<x-mail::button :url="$confirmationUrl">
{{ $confirmLabel }}
</x-mail::button>

{{ $fallbackLabel }}

[{{ $confirmationUrl }}]({{ $confirmationUrl }})

{{ $ignoreNotice }}

@if($footer)
---

{{ $footer }}
@endif
</x-mail::message>
