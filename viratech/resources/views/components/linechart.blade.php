@props(['series', 'labels', 'height' => 200])
{{-- Courbe lissée (SVG) à partir de données réelles : $series = valeurs, $labels = étiquettes de l'axe. --}}
@php
    $w = 640; $h = $height; $padL = 6; $padB = 26; $padT = 14;
    $n = max(count($series), 2);
    $max = max(max($series ?: [0]), 1);
    $pts = [];
    foreach (array_values($series) as $i => $v) {
        $pts[] = [$padL + ($w - $padL * 2) * $i / ($n - 1), $padT + ($h - $padT - $padB) * (1 - $v / $max)];
    }
    $path = '';
    foreach ($pts as $i => $p) {
        if ($i === 0) { $path = 'M'.round($p[0], 1).' '.round($p[1], 1); continue; }
        $q = $pts[$i - 1]; $mx = ($q[0] + $p[0]) / 2;
        $path .= ' C'.round($mx, 1).' '.round($q[1], 1).' '.round($mx, 1).' '.round($p[1], 1).' '.round($p[0], 1).' '.round($p[1], 1);
    }
    $peak = array_search(max($series ?: [0]), array_values($series));
@endphp
<svg class="chart" viewBox="0 0 {{ $w }} {{ $h }}" width="100%" role="img" aria-label="Courbe">
    @for($g = 0; $g < 4; $g++)
        <line x1="0" x2="{{ $w }}" y1="{{ $padT + ($h - $padT - $padB) * $g / 3 }}" y2="{{ $padT + ($h - $padT - $padB) * $g / 3 }}" stroke="#EEF1F5"/>
    @endfor
    @if(count($pts) > 1)<path d="{{ $path }}" fill="none" stroke="#1C2429" stroke-width="2.6" stroke-linecap="round"/>@endif
    @if(max($series ?: [0]) > 0 && $peak !== false)
        <circle cx="{{ $pts[$peak][0] }}" cy="{{ $pts[$peak][1] }}" r="5" fill="#fff" stroke="#1C2429" stroke-width="2.4"/>
        <g transform="translate({{ min(max($pts[$peak][0] - 40, 0), $w - 80) }},{{ max($pts[$peak][1] - 40, 0) }})"><rect width="80" height="28" rx="8" fill="#1C2429"/><text x="40" y="18" text-anchor="middle" style="fill:#fff;font-size:11px;font-weight:600">{{ number_format(max($series), 0, ',', ' ') }} $</text></g>
    @endif
    @foreach($labels as $i => $l)
        <text x="{{ $padL + ($w - $padL * 2) * $i / ($n - 1) }}" y="{{ $h - 6 }}" text-anchor="middle">{{ $l }}</text>
    @endforeach
</svg>
