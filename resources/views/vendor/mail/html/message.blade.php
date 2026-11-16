@php
    $systemName = \App\Services\EmailTemplates::systemName();
@endphp
<x-mail::layout>
{{-- Header: the UTeM logo (PNG, because most email clients do not show SVG) --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
<img src="{{ asset('images/email-logo.png') }}" alt="{{ $systemName }} | Universiti Teknikal Malaysia Melaka" width="180" height="102" style="display: block; width: 180px; max-width: 100%; height: auto; margin: 10px auto;">
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer: editable under Email & Notifications → Settings --}}
<x-slot:footer>
<x-mail::footer>
{{ \App\Services\EmailTemplates::footerText() }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
