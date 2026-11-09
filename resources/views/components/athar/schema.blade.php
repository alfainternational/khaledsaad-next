{{-- JSON-LD واحد لكل استدعاء (§3.3): يُدفع إلى رأس الصفحة عبر structured-data. --}}
@props(['data'])
@push('structured-data')
<script type="application/ld+json" nonce="{{ $cspNonce }}">{!! json_encode(['@'.'context' => 'https://schema.org'] + $data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
@endpush
