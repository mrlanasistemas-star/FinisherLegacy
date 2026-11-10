{{-- Finisher Legacy override: plain-text message wrapper (header/footer come from the text layout). --}}
<x-mail::layout>
{{ $slot }}

@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{{ $subcopy }}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset
</x-mail::layout>
