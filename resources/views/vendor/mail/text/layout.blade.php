{{--
    Finisher Legacy override: plain-text version of every markdown/
    notification email, with the same footer info as the branded HTML.
--}}
FINISHER LEGACY · MAKE IT LAST

{!! strip_tags($slot) !!}
@isset($subcopy)

{!! strip_tags($subcopy) !!}
@endisset

------------------------------
{!! __('¿Necesitas ayuda? Escríbenos a :email', ['email' => \App\Mail\Support\BrandMail::supportEmail()]) !!}
{!! __('Aviso de privacidad') !!}: {!! \App\Mail\Support\BrandMail::privacyUrl() !!}
{!! __('Términos y condiciones') !!}: {!! \App\Mail\Support\BrandMail::termsUrl() !!}

© {{ date('Y') }} {!! config('app.name', 'Finisher Legacy') !!}. {!! __('All rights reserved.') !!}
