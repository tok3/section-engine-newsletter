<style data-sen-newsletter-styles>
:where(.sen-newsletter) {
    --sen-color: currentColor;
    --sen-border: 1px solid currentColor;
    --sen-radius: .375rem;
    --sen-gap: .75rem;
    --sen-padding: .625rem .75rem;
    /* Avoid currentColor background tokens: they resolve after the button's own color is applied. */
    --sen-button-bg: transparent;
    --sen-button-color: currentColor;
    --sen-focus: 2px solid currentColor;
    --sen-max-width: 32rem;
    color: var(--sen-color);
    font-family: inherit;
    font-size: inherit;
    max-width: var(--sen-max-width);
}
:where(.sen-newsletter, .sen-newsletter *) {
    box-sizing: border-box;
}
:where(.sen-newsletter__form, .sen-newsletter__field) {
    display: grid;
    gap: calc(var(--sen-gap) / 2);
}
:where(.sen-newsletter__form) {
    gap: var(--sen-gap);
}
:where(.sen-newsletter__label) {
    font: inherit;
    font-weight: 600;
}
:where(.sen-newsletter__input) {
    appearance: none;
    background: Canvas;
    border: 1px solid currentColor;
    border: var(--sen-border);
    border-radius: var(--sen-radius);
    color: inherit;
    font: inherit;
    min-height: 44px;
    padding: var(--sen-padding);
    width: 100%;
}
:where(.sen-newsletter__field--checkbox) {
    align-items: center;
    grid-template-columns: auto 1fr;
    min-height: 44px;
}
:where(.sen-newsletter__checkbox) {
    accent-color: currentColor;
    height: 1.25rem;
    margin: 0;
    width: 1.25rem;
}
:where(.sen-newsletter__field--checkbox .sen-newsletter__error) {
    grid-column: 1 / -1;
}
:where(.sen-newsletter__consent) {
    margin: 0;
}
:where(.sen-newsletter__button) {
    align-items: center;
    background: var(--sen-button-bg);
    border: var(--sen-border);
    border-radius: var(--sen-radius);
    color: var(--sen-button-color);
    cursor: pointer;
    display: inline-flex;
    font: inherit;
    font-weight: 600;
    justify-content: center;
    min-height: 44px;
    padding: var(--sen-padding);
    text-align: center;
}
:where(.sen-newsletter__button:hover) {
    background: var(--sen-button-hover-bg, transparent);
}
:where(.sen-newsletter__input:focus-visible, .sen-newsletter__checkbox:focus-visible, .sen-newsletter__button:focus-visible, .sen-newsletter a:focus-visible, .sen-newsletter__message:focus-visible) {
    outline: var(--sen-focus);
    outline-offset: 3px;
}
:where(.sen-newsletter__error) {
    font-size: .875em;
    font-weight: 600;
}
:where(.sen-newsletter__error:empty, .sen-newsletter__message:empty) {
    display: none;
}
:where(.sen-newsletter__message:not(:empty)) {
    border-inline-start: var(--sen-border);
    padding: var(--sen-padding);
}
:where(.sen-newsletter__honeypot) {
    height: 1px;
    left: -10000px;
    overflow: hidden;
    position: absolute;
    width: 1px;
}
:where(.sen-newsletter--compact .sen-newsletter__form) {
    align-items: end;
    grid-template-columns: minmax(12rem, 1fr) auto;
}
:where(.sen-newsletter--compact .sen-newsletter__field--email) {
    grid-column: 1;
    grid-row: 1;
}
:where(.sen-newsletter--compact .sen-newsletter__button) {
    grid-column: 2;
    grid-row: 1;
}
:where(.sen-newsletter--compact .sen-newsletter__consent, .sen-newsletter--compact .sen-newsletter__field--checkbox, .sen-newsletter--compact .sen-newsletter__message) {
    grid-column: 1 / -1;
}
:where(.sen-newsletter--page form) {
    display: grid;
    gap: var(--sen-gap);
}
@supports (color: color-mix(in srgb, currentColor 55%, transparent)) {
    :where(.sen-newsletter) {
        --sen-border: 1px solid color-mix(in srgb, currentColor 55%, transparent);
    }
    :where(.sen-newsletter__button:hover) {
        background: var(--sen-button-hover-bg, color-mix(in srgb, currentColor 8%, transparent));
    }
}
@media (max-width: 36rem) {
    :where(.sen-newsletter--compact .sen-newsletter__form) {
        grid-template-columns: 1fr;
    }
    :where(.sen-newsletter--compact .sen-newsletter__field--email, .sen-newsletter--compact .sen-newsletter__button) {
        grid-column: 1;
        grid-row: auto;
    }
}
</style>
