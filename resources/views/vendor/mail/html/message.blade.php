{{--
    Finisher Legacy override: markdown message wrapper. Header and footer
    (logo, gold rule, MAKE IT LAST, support, legal links) come from the
    branded layout, so this only forwards the body and the subcopy.
--}}
<x-mail::layout>
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
</x-mail::layout>
