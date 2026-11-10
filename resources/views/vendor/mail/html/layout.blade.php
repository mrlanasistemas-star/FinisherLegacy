{{--
    Finisher Legacy override of Laravel's markdown mail layout: every
    framework email (verificar correo, restablecer contraseña, notificaciones
    MailMessage como TagActivationReminder) renders inside the same branded
    shell as our own Mailables (emails.partials.open/close). The theme CSS in
    themes/default.css is inlined into the Markdown output by Laravel.
--}}
@include('emails.partials.open', ['title' => config('app.name', 'Finisher Legacy'), 'preheader' => null, 'head' => $head ?? ''])
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
@include('emails.partials.close')
