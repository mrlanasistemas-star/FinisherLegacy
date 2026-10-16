/**
 * Opens the ⌘K command menu from anywhere (topbar button, mobile "Más"
 * sheet) without a shared module-level ref — the SSR process serves many
 * users, so the open state lives inside CommandMenu.vue and is reached
 * through a DOM event instead.
 */
export const COMMAND_MENU_EVENT = 'fl:command-menu:open';

export function openCommandMenu(): void {
    if (typeof window === 'undefined') {
        return;
    }

    window.dispatchEvent(new CustomEvent(COMMAND_MENU_EVENT));
}
