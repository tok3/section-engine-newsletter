<script data-newsletter-enhancement>
(() => {
    const notice = document.querySelector('[data-newsletter-confirmed][autofocus], [data-newsletter-success][autofocus]');
    if (notice) notice.focus();
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
                const notice = document.createElement('div');
                notice.className = 'sen-newsletter__message';
                notice.id = form.id;
                notice.setAttribute('role', 'status');
                notice.setAttribute('aria-live', 'polite');
                notice.setAttribute('tabindex', '-1');
                notice.setAttribute('data-newsletter-success', '');
                if (result.title) {
                    const title = document.createElement('h2');
                    title.className = 'sen-newsletter__title';
                    title.textContent = result.title;
                    const text = document.createElement('p');
                    text.className = 'sen-newsletter__text';
                    const message = String(result.message || '');
                    const at = result.email ? message.indexOf(result.email) : -1;
                    if (at === -1) {
                        text.textContent = message;
                    } else {
                        const mark = document.createElement('mark');
                        mark.className = 'sen-newsletter__email';
                        mark.textContent = result.email;
                        text.append(message.slice(0, at), mark, message.slice(at + result.email.length));
                    }
                    notice.append(title, text);
                    if (result.change) {
                        const change = document.createElement('p');
                        change.className = 'sen-newsletter__change';
                        const link = document.createElement('a');
                        link.href = form.parentElement && form.parentElement.id ? '#' + form.parentElement.id : '#';
                        link.setAttribute('data-newsletter-change', '');
                        link.textContent = result.change;
                        link.addEventListener('click', (click) => {
                            click.preventDefault();
                            notice.replaceWith(form);
                            const email = form.querySelector('[name="email"]');
                            if (email) { email.focus(); if (email.select) email.select(); }
                        });
                        change.append(link);
                        notice.append(change);
                    }
                } else {
                    notice.textContent = result.message;
                }
                form.replaceWith(notice);
                notice.focus();
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
