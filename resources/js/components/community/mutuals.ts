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
        return total === 1
            ? '1 conexión en común'
            : `${total} conexiones en común`;
    }

    const rest = total - names.length;

    if (rest <= 0) {
        return `Seguido por ${names.join(' y ')}`;
    }

    return `Seguido por ${names.join(', ')} y ${rest} más`;
}
