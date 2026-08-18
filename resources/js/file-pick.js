/**
 * Custom image-pick buttons: open the hidden file input from a user tap.
 * Overlaying a visible-sized file input on mobile shows the native
 * "Pilih file" control on top of the button.
 */
function initFilePickButtons() {
    document.addEventListener('click', (event) => {
        const target = event.target;
        if (! (target instanceof Element)) {
            return;
        }

        const label = target.closest('.order-proof-pick-btn, .file-pick-btn');
        if (! (label instanceof HTMLElement)) {
            return;
        }

        const input = label.querySelector('input[type="file"]');
        if (! (input instanceof HTMLInputElement) || target === input) {
            return;
        }

        event.preventDefault();

        if (window.__cogsVvh) {
            window.__cogsVvh.pickerUntil = Date.now() + 8000;
        }

        input.click();
    });
}

document.addEventListener('DOMContentLoaded', initFilePickButtons);
