import { t, tc } from '@/i18n';

/**
 * "Seguido por Ana y 3 más" — built only from what the server sent
 * (App\Queries\Social\MutualConnections): the first names + the total.
 */
export function mutualsLabel(person: {
    mutual_count?: number;
    mutual_names?: string[];
}): string | null {
    const total = person.mutual_count ?? 0;
    const names = (person.mutual_names ?? []).slice(0, 2);

    if (total <= 0) {
        return null;
    }

    if (!names.length) {
        return tc(
            '{count} conexión en común|{count} conexiones en común',
            total,
        );
    }

    const rest = total - names.length;

    if (rest <= 0) {
        return names.length === 1
            ? t('Seguido por {name}', { name: names[0] })
            : t('Seguido por {first} y {second}', {
                  first: names[0],
                  second: names[1],
              });
    }

    return t('Seguido por {names} y {count} más', {
        names: names.join(', '),
        count: rest,
    });
}
