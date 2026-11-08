<x-filament-widgets::widget><x-filament::section>
<h2>{{ __('section-engine-newsletter::admin.last_30_days') }}</h2>
@foreach($this->counts() as $kind => $rows)
<h3>{{ __('section-engine-newsletter::admin.'.$kind) }}</h3>
<table style="width:100%"><tbody>@foreach($rows as $label => $count)<tr><th scope="row" style="text-align:left">{{ $label }}</th><td>{{ $count }}</td></tr>@endforeach</tbody></table>
@endforeach
</x-filament::section></x-filament-widgets::widget>
