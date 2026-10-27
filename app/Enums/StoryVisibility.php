<?php

namespace App\Enums;

/**
 * Quién puede abrir un recuerdo. El tag físico y los enlaces de destinatario abren "no listado"; "privado" solo lo ve su dueño.
 */
enum StoryVisibility: string
{
    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Público',
            self::Unlisted => 'Solo con el tag o el enlace',
            self::Private => 'Privado',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
