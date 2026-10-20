document.querySelectorAll('[data-confirmation-form]').forEach((form) => {
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        if (!button || !form.reportValidity()) {
            return;
        }

        button.disabled = true;
        button.classList.add('is-loading');
        button.setAttribute('aria-busy', 'true');

        const englishLabel = button.querySelector('[data-submit-label]');
        const arabicLabel = button.querySelector('[data-submit-label-ar]');
        const liveStatus = form.querySelector('[data-submit-status]');

        if (englishLabel) englishLabel.textContent = 'SUBMITTING';
        if (arabicLabel) arabicLabel.textContent = 'جارٍ الإرسال';
        if (liveStatus) liveStatus.textContent = 'Submitting attendance confirmation. جارٍ إرسال تأكيد الحضور.';
    });
});