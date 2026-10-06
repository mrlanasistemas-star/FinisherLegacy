import Swal from 'sweetalert2';

export function confirmDestructive(options: {
    title: string;
    text?: string;
    confirmButtonText?: string;
}) {
    return Swal.fire({
        title: options.title,
        text: options.text,
        icon: 'warning',
        iconColor: '#c9a45c',
        showCancelButton: true,
        confirmButtonText: options.confirmButtonText ?? 'Sí, continuar',
        cancelButtonText: 'Cancelar',
        background: '#ffffff',
        color: '#171714',
        confirmButtonColor: '#171714',
        cancelButtonColor: '#e7e3da',
        reverseButtons: true,
        customClass: {
            cancelButton: 'fl-swal-cancel',
        },
    });
}

/** Same dialog, resolved to a plain boolean. */
export async function confirmAction(options: {
    title: string;
    text?: string;
    confirmButtonText?: string;
}): Promise<boolean> {
    const result = await confirmDestructive(options);

    return result.isConfirmed;
}
