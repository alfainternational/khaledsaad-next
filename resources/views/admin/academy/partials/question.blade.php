<div class="card" id="question-{{ $q->id }}" style="margin-bottom:12px">
    <x-athar.kicker>{{ $isOpening ? 'السؤال الافتتاحي' : 'سؤال '.$q->position }}</x-athar.kicker>
    <div class="field"><label for="q-{{ $q->id }}-scenario">السيناريو</label><textarea id="q-{{ $q->id }}-scenario" name="questions[{{ $q->id }}][scenario]">{{ $q->scenario }}</textarea></div>
    <div class="field"><label for="q-{{ $q->id }}-prompt">التوجيه</label><input id="q-{{ $q->id }}-prompt" name="questions[{{ $q->id }}][prompt]" value="{{ $q->prompt }}"></div>
    @foreach($q->options ?? [] as $i => $opt)
    <div class="grid grid-2" style="gap:8px;margin-bottom:8px">
        <div class="field"><label for="q-{{ $q->id }}-o{{ $i }}">الخيار {{ $i + 1 }} @if(! $isOpening)<label class="small"><input type="radio" name="questions[{{ $q->id }}][best]" value="{{ $i }}" @checked(! empty($opt['is_best']))> الأفضل</label>@endif</label><input id="q-{{ $q->id }}-o{{ $i }}" name="questions[{{ $q->id }}][options][{{ $i }}][label]" value="{{ $opt['label'] ?? '' }}"></div>
        @if(! $isOpening)<div class="field"><label for="q-{{ $q->id }}-f{{ $i }}">التغذية الراجعة</label><input id="q-{{ $q->id }}-f{{ $i }}" name="questions[{{ $q->id }}][options][{{ $i }}][feedback]" value="{{ $opt['feedback'] ?? '' }}"></div>@endif
    </div>
    @endforeach
</div>
