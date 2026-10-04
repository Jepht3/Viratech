@props(['name', 'label', 'facing' => 'environment'])
{{-- Prise de photo avec la caméra du navigateur : aucun sélecteur de fichier n'est affiché. --}}
<div class="cam" data-name="{{ $name }}" data-facing="{{ $facing }}" style="margin-top:12px">
    <div class="b sm" style="margin-bottom:6px">{{ $label }}</div>
    <div style="background:#0f1417;border-radius:16px;overflow:hidden;aspect-ratio:4/3;display:grid;place-items:center;position:relative">
        <video playsinline autoplay muted hidden style="width:100%;height:100%;object-fit:cover"></video>
        <img class="shot" alt="" hidden style="width:100%;height:100%;object-fit:cover">
        <canvas hidden></canvas>
        <span class="xs" style="color:#8FA096;position:absolute">📷</span>
    </div>
    <div class="row" style="margin-top:8px;gap:8px">
        <button type="button" class="btn small ghost start">Ouvrir la caméra</button>
        <button type="button" class="btn small snap" hidden>📸 Prendre la photo</button>
        <button type="button" class="btn small ghost retake" hidden>Reprendre</button>
    </div>
    <div class="err msg"></div>
    <input type="file" name="{{ $name }}" accept="image/*" hidden tabindex="-1">
</div>