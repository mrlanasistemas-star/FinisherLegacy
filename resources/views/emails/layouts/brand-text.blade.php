{{--
    Plain-text alternative layout for App\Mail\** (multipart/alternative).
    Same footer information as the HTML layout, no markup.
--}}
FINISHER LEGACY · MAKE IT LAST
==============================

@yield('content')

------------------------------
¿Necesitas ayuda? Escríbenos a {!! \App\Mail\Support\BrandMail::supportEmail() !!}
Aviso de privacidad: {!! \App\Mail\Support\BrandMail::privacyUrl() !!}
Términos y condiciones: {!! \App\Mail\Support\BrandMail::termsUrl() !!}

© {{ date('Y') }} {!! config('app.name', 'Finisher Legacy') !!}. Todos los derechos reservados.
