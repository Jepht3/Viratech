@php
    $first = now()->startOfMonth();
    $start = $first->copy()->startOfWeek(\Carbon\Carbon::MONDAY);
    $mark = $markDays ?? [];
@endphp
<div class="cal">
    <h4><span>{{ now()->translatedFormat('D j F') }}</span><span>✎</span></h4>
    <table>
        <tr>@foreach(['L','M','M','J','V','S','D'] as $d)<th>{{ $d }}</th>@endforeach</tr>
        @for($w = 0; $w < 6; $w++)
            @php $weekStart = $start->copy()->addWeeks($w); @endphp
            @if($weekStart->month !== $first->month && $weekStart->gt($first->copy()->endOfMonth())) @break @endif
            <tr>
                @for($d = 0; $d < 7; $d++)
                    @php $day = $weekStart->copy()->addDays($d); $in = $day->month === $first->month; @endphp
                    <td class="{{ ! $in ? 'out' : '' }} {{ $in && $day->isToday() ? 'today' : '' }} {{ $in && in_array($day->day, $mark) && ! $day->isToday() ? 'mark' : '' }}"><span>{{ $day->day }}</span></td>
                @endfor
            </tr>
        @endfor
    </table>
</div>
