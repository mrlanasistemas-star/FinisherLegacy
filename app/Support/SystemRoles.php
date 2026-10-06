<?php

namespace App\Support;

/**
 * The 7 roles the product ships with — protected from deletion and (for
 * super_admin) from permission edits in /admin/roles. Custom roles created
 * later through that screen are anything not in this list.
 */
class SystemRoles
{
    /**
     * @var array<string, string>
     */
    public const LABELS = [
        'super_admin' => 'Super Admin',
        'admin' => 'Administrador',
        'event_manager' => 'Manager de evento',
        'event_operator' => 'Operador de evento',
        'production_operator' => 'Operador de producción',
        'athlete' => 'Atleta',
        'photographer' => 'Fotógrafo',
    ];

    public static function isSystem(string $name): bool
    {
        return array_key_exists($name, self::LABELS);
    }

    /**
     * @return list<string>
     */
    public static function names(): array
    {
        return array_keys(self::LABELS);
    }
}
