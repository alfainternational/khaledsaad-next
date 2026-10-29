@extends('layouts.platform')
@section('title', 'استوديو الأدوات — الإدارة — أثر')
@section('noindex', '1')
@section('content')
<section class="app-hero"><div class="shell"><x-athar.kicker>الإدارة</x-athar.kicker><h1>استوديو الأدوات</h1><p class="muted">مقياس الجودة الأول: <b>معدل الحفظ</b> = الأصول المحفوظة ÷ الأصول المولَّدة. المخرج الذي يُحفظ أكثر هو الأفضل.</p>@include('admin.partials.nav')</div></section>
<section class="section-tight"><div class="shell stack" style="gap:24px">
    <x-athar.flash />
    <div class="card">
        <x-athar.table caption="الأدوات"><thead><tr><th>الأداة</th><th>النوع</th><th>الفئة</th><th>الحالة</th><th>رصيد التحسين</th><th>مولَّد</th><th>محفوظ</th><th>معدل الحفظ</th><th>A/B</th><th></th></tr></thead><tbody>
            @foreach($tools as $tool)@php($r = $rates[$tool->id] ?? ['generated' => 0, 'saved' => 0, 'rate' => null])
            <tr data-tool="{{ $tool->slug }}">
                <td><b>{{ $tool->name }}</b><br><span class="muted small">{{ $tool->slug }}</span></td>
                <td>{{ $tool->kindLabel() }}</td><td>{{ $tool->category }}</td>
                <td>{{ $tool->is_published ? 'منشورة' : 'مخفية' }}</td><td>{{ $tool->kind === 'generator' ? $tool->credit_cost : '—' }}</td>
                <td>{{ $r['generated'] }}</td><td>{{ $r['saved'] }}</td>
                <td data-save-rate>{{ $r['rate'] === null ? '—' : $r['rate'].'٪' }}</td>
                <td>{{ isset($experiments[$tool->id]) ? 'قيد التشغيل' : '—' }}</td>
                <td><a class="btn btn-secondary" href="{{ route('admin.tools.show', $tool) }}">الاستوديو</a></td>
            </tr>
            @endforeach
        </tbody></x-athar.table>
    </div>
</div></section>
@endsection
