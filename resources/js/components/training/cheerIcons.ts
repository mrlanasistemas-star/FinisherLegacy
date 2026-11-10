/**
 * Entrenamientos: the fixed cheer icon set (App\Enums\CheerIcon) mapped to
 * Lucide glyphs + Spanish labels. Shared by the public "Envíame ánimo"
 * page and the athlete's player.
 */
import type { LucideIcon } from '@lucide/vue';
import {
    BicepsFlexed,
    Flag,
    Flame,
    Heart,
    Medal,
    Rocket,
    Star,
    Sun,
} from '@lucide/vue';

export const CHEER_ICONS: Record<string, { icon: LucideIcon; label: string }> =
    {
        heart: { icon: Heart, label: 'Corazón' },
        fire: { icon: Flame, label: 'Fuego' },
        medal: { icon: Medal, label: 'Medalla' },
        muscle: { icon: BicepsFlexed, label: 'Fuerza' },
        star: { icon: Star, label: 'Estrella' },
        flag: { icon: Flag, label: 'Meta' },
        sun: { icon: Sun, label: 'Sol' },
        rocket: { icon: Rocket, label: 'Cohete' },
    };

export function cheerIcon(key: string): LucideIcon {
    return (CHEER_ICONS[key] ?? CHEER_ICONS.heart).icon;
}
