{{-- Étapes réelles : chaque ligne reflète un événement vérifié (heure et auteur réels), jamais une progression simulée. --}}
@php
    $cur = $order->isActive() ? $order->currentStep() : null;
@endphp
<div class="tlv">
    @foreach($order->steps as $s)
        @php
            $isCur = $cur && $cur->id === $s->id;
            $cls = $s->status === 'done' ? 'done' : ($isCur ? ($s->status === 'blocked' ? 'blk' : 'cur') : 'todo');
        @endphp
        <div class="st {{ $cls }}">
            <div>
                @if($s->status === 'done')
                    <b>{{ $s->label }}</b>
                    <div class="xs mut">{{ $s->done_at->format('d/m H:i') }} · {{ $s->actorLabel() }}@if($s->note && $s->key !== 'created') · {{ $s->note }}@endif</div>
                @elseif($isCur)
                    <b>{{ $s->pending_label }}</b>
                    @if($s->status === 'blocked')
                        <div class="xs" style="color:var(--badfg)">Bloquée : {{ $s->note }}</div>
                    @else
                        <div class="xs mut">
                            @if($s->started_at)Depuis {{ $s->started_at->diffForHumans(null, true) }} · @endif
                            {{ ['client' => 'Action attendue de votre part', 'operator' => 'Traité par un opérateur', 'system' => 'Confirmation automatique ou par un opérateur'][$s->actor] }}
                            @if($s->note) · {{ $s->note }}@endif
                        </div>
                    @endif
                @else
                    <b>{{ $s->label }}</b>
                @endif
            </div>
        </div>
    @endforeach
</div>
