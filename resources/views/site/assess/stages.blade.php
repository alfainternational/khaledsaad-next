<ol class="stages" aria-label="مراحل التشخيص">
    <li @if($stage === 1) aria-current="step" @endif class="{{ $stage > 1 ? 'is-done' : '' }}">عن مشروعك</li>
    <li @if($stage === 2) aria-current="step" @endif class="{{ $stage > 2 ? 'is-done' : '' }}">ماذا تفعل الآن؟</li>
    <li @if($stage === 3) aria-current="step" @endif>خريطة أولوياتك</li>
</ol>
