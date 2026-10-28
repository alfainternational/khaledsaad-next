{{-- التنبيه (§1.4 رقم 10): نجاح / خطأ / معلومة في نفس المكان أعلى المحتوى دائماً --}}
@if(session('status'))<x-athar.alert type="success">{{ session('status') }}</x-athar.alert>@endif
@if(session('info'))<x-athar.alert type="info">{{ session('info') }}</x-athar.alert>@endif
@if($errors->any())<x-athar.alert type="error"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></x-athar.alert>@endif
