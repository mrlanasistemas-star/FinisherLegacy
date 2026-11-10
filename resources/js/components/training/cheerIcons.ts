/**
 * Entrenamientos: the fixed cheer icon set (App\Enums\CheerIcon) mapped to
 * Lucide glyphs + labels (cheerLabel). Shared by the public "Envíame ánimo"
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
import { t } from '@/i18n';

export const CHEER_ICONS: Record<string, { icon: LucideIcon }> = {
    heart: { icon: Heart },
    fire: { icon: Flame },
    medal: { icon: Medal },
    muscle: { icon: BicepsFlexed },
    star: { icon: Star },
    flag: { icon: Flag },
    sun: { icon: Sun },
    rocket: { icon: Rocket },
};

/** The icon's name in the active language (resolved when called). */
export function cheerLabel(key: string): string {
    const labels: Record<string, string> = {
        heart: t('Corazón'),
        fire: t('Fuego'),
        medal: t('Medalla'),
        muscle: t('Fuerza'),
        star: t('Estrella'),
        flag: t('Meta'),
        sun: t('Sol'),
        rocket: t('Cohete'),
    };

    return labels[key] ?? key;
}

export function cheerIcon(key: string): LucideIcon {
    return (CHEER_ICONS[key] ?? CHEER_ICONS.heart).icon;
}
