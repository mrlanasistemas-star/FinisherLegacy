{{--
    Layout for Finisher Legacy's own Mailables (App\Mail\**): the shared
    branded shell (emails.partials.open/close) around a @section('content').
    Pass $title and optionally $preheader (inbox preview text).
--}}
@include('emails.partials.open', ['title' => $title ?? config('app.name'), 'preheader' => $preheader ?? null])
@yield('content')
@include('emails.partials.close')
