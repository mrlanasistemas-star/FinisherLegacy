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
¿Necesitas ayuda? Escríbenos a {!! \App\Mail\Support\BrandMail::supportEmail() !!}
Aviso de privacidad: {!! \App\Mail\Support\BrandMail::privacyUrl() !!}
Términos y condiciones: {!! \App\Mail\Support\BrandMail::termsUrl() !!}

© {{ date('Y') }} {!! config('app.name', 'Finisher Legacy') !!}. Todos los derechos reservados.
