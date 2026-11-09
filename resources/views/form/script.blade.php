<script data-newsletter-enhancement>
(() => {
    const confirmed = document.querySelector('[data-newsletter-confirmed][autofocus]');
    if (confirmed) confirmed.focus();
    if (!window.fetch || !window.FormData) return;
    document.addEventListener('submit', async (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || !form.matches('[data-newsletter-form]')) return;
        event.preventDefault();
        if (form.dataset.submitting) return;
        form.dataset.submitting = 'true';
        form.setAttribute('aria-busy', 'true');
        const buttons = [...form.querySelectorAll('[type="submit"]')];
        const disabled = buttons.map(button => button.disabled);
        const body = new FormData(form);
        buttons.forEach(button => { button.disabled = true; });
        form.querySelectorAll('[aria-invalid]').forEach(field => field.removeAttribute('aria-invalid'));
        form.querySelectorAll('[data-error]').forEach(error => { error.textContent = ''; });
        const status = form.querySelector('[data-newsletter-status]');
        status.textContent = '';
        try {
            const response = await fetch(form.action, { method: 'POST', body, headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            const result = await response.json();
            if (response.status === 422) {
                let first;
                for (const [name, messages] of Object.entries(result.errors || {})) {
                    const field = form.elements.namedItem(name);
                    const error = [...form.querySelectorAll('[data-error]')].find(node => node.dataset.error === name);
                    if (error) error.textContent = messages.join(' ');
                    if (field && field.type !== 'hidden') {
                        field.setAttribute('aria-invalid', 'true');
                        first ||= field;
                    }
                }
                status.textContent = result.message || form.dataset.errorMessage;
                (first || status).focus();
            } else {
                if (!response.ok) throw new Error('Request failed');
                status.textContent = result.message;
                status.focus();
            }
        } catch (error) {
            status.textContent = form.dataset.errorMessage;
            status.focus();
        } finally {
            delete form.dataset.submitting;
            form.removeAttribute('aria-busy');
            buttons.forEach((button, index) => { button.disabled = disabled[index]; });
        }
    });
})();
</script>
