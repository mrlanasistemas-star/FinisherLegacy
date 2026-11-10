<?php

/*
|--------------------------------------------------------------------------
| Community content filter (App Store 1.2 / Google Play UGC policy)
|--------------------------------------------------------------------------
|
| Both stores require "a method for filtering objectionable material from
| being posted". App\Rules\NoObjectionableContent rejects text containing
| any of these terms as a whole word, after lower-casing, removing accents
| and undoing common character swaps (0→o, 1→i, 3→e, 4→a, 5→s, @→a, $→s)
| and repeated letters ("puuuta" → "puta").
|
| It is the first line of defence only: reports, blocks and the admin
| moderation queue handle everything a word list cannot. Keep the list to
| unambiguous slurs/insults/sexual terms — anything that is also a normal
| word in a sporting context ("negro", "cabrón" as "tough", "verga" in
| some regions…) would block legitimate runners. Extra terms can be added
| per environment with MODERATION_EXTRA_TERMS (comma separated).
|
*/

return [
    'blocked_terms' => array_values(array_filter(array_merge([
        // Español (MX) — insultos y términos sexuales inequívocos.
        'puta', 'puto', 'putos', 'putas', 'pendeja', 'pendejo', 'pendejos', 'pendejas',
        'chinga tu madre', 'chingatumadre', 'hijo de puta', 'hija de puta', 'hijodeputa',
        'culero', 'culera', 'culeros', 'panocha', 'puñetas',
        'maricon', 'maricones', 'joto', 'jotos', 'mayate', 'retrasado mental',
        'te voy a matar', 'muerete', 'suicidate', 'matate',
        'pornografia', 'porno', 'nudes',
        // English — slurs and sexual terms.
        'fuck', 'fucking', 'motherfucker', 'bitch', 'cunt', 'whore', 'slut',
        'faggot', 'nigger', 'nigga', 'retard', 'kill yourself', 'kys',
        'porn', 'pussy',
    ], explode(',', (string) env('MODERATION_EXTRA_TERMS', ''))))),
];
