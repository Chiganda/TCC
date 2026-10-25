
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-confirm]').forEach((element) => {
        element.addEventListener('click', (event) => {
            const mensagem =
                element.dataset.confirm || 'Tens a certeza?';

            if (!window.confirm(mensagem)) {
                event.preventDefault();
            }
        });
    });
});
