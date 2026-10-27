{{-- Print template MVP (App\Services\LegacyTags\StoryPrintRenderer). dompdf: no flex/grid, absolute mm boxes only. --}}
@php
    $meta = array_values(array_filter([$data['race'] ?? null, $data['distance'] ?? null, $data['time'] ?? null]));
    $place = array_values(array_filter([$data['date'] ?? null, $data['location'] ?? null]));
    $dark = $layout === 'poster';
@endphp
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
    @page { margin: 0; }
    * { margin: 0; padding: 0; }
    body { width: {{ $paperW }}mm; height: {{ $paperH }}mm; font-family: 'DejaVu Sans', sans-serif; background: {{ $dark ? '#171714' : '#f8f7f3' }}; color: {{ $dark ? '#f8f7f3' : '#171714' }}; }
    .photo { position: absolute; left: 0; top: 0; width: {{ $boxW }}mm; height: {{ $boxH }}mm; }
    .shade { position: absolute; left: 0; bottom: 0; width: {{ $paperW }}mm; height: 120mm; background: #171714; opacity: 0.72; }
    .text { position: absolute; left: {{ $layout === 'poster' ? 16 : 12 }}mm; right: {{ $layout === 'poster' ? 16 : 12 }}mm; }
    .eyebrow { font-size: 7pt; letter-spacing: 2pt; text-transform: uppercase; color: {{ $dark ? '#e8d6ad' : '#85662b' }}; }
    .title { font-family: 'DejaVu Serif', serif; font-size: {{ $layout === 'poster' ? 30 : 19 }}pt; line-height: 1.12; margin-top: 2mm; }
    .meta { font-size: {{ $layout === 'poster' ? 11 : 8.5 }}pt; margin-top: 2.5mm; color: {{ $dark ? '#e7e3da' : '#3b3934' }}; }
    .rule { width: 14mm; height: 0.6mm; background: #c9a45c; margin-top: 4mm; }
    .dedication { font-family: 'DejaVu Serif', serif; font-style: italic; font-size: {{ $layout === 'poster' ? 13 : 9.5 }}pt; line-height: 1.4; margin-top: 3.5mm; }
    .brand { position: absolute; bottom: {{ $layout === 'poster' ? 12 : 7 }}mm; left: {{ $layout === 'poster' ? 16 : 12 }}mm; font-size: 6.5pt; letter-spacing: 2.5pt; text-transform: uppercase; color: {{ $dark ? '#c9a45c' : '#85662b' }}; }
</style>
</head>
<body>
    <img class="photo" src="{{ $photo }}" alt="">
    @if ($dark)
        <div class="shade"></div>
    @endif
    <div class="text" style="top: {{ $dark ? $paperH - 108 : $boxH + 7 }}mm;">
        <div class="eyebrow">{{ $data['event_name'] ?? 'Finisher Legacy' }}</div>
        <div class="title">{{ $data['title'] }}</div>
        @if ($meta !== [])
            <div class="meta">{{ implode('  ·  ', $meta) }}</div>
        @endif
        @if ($place !== [])
            <div class="meta">{{ implode('  ·  ', $place) }}</div>
        @endif
        @if (! empty($data['dedication']))
            <div class="rule"></div>
            <div class="dedication">
                @if (! empty($data['recipient_label']))Para {{ $data['recipient_label'] }} — @endif{{ \Illuminate\Support\Str::limit($data['dedication'], $layout === 'classic' ? 220 : 320) }}
            </div>
        @endif
    </div>
    <div class="brand">Finisher Legacy</div>
</body>
</html>
