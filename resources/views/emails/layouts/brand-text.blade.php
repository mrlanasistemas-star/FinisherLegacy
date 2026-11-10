{{--
    Plain-text alternative layout for App\Mail\** (multipart/alternative).
    Same footer information as the HTML layout, no markup.
--}}
FINISHER LEGACY · MAKE IT LAST
==============================

@yield('content')

------------------------------
{!! __('¿Necesitas ayuda? Escríbenos a :email', ['email' => \App\Mail\Support\BrandMail::supportEmail()]) !!}
{!! __('Aviso de privacidad') !!}: {!! \App\Mail\Support\BrandMail::privacyUrl() !!}
{!! __('Términos y condiciones') !!}: {!! \App\Mail\Support\BrandMail::termsUrl() !!}

© {{ date('Y') }} {!! config('app.name', 'Finisher Legacy') !!}. {!! __('All rights reserved.') !!}
