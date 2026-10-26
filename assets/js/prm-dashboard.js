document.addEventListener('DOMContentLoaded', function() {
    const i18n = prmonData.i18n;

    // 1. Tab Switching Logic
    const tabLinks = document.querySelectorAll('.prm-tab-link');
    const tabContents = document.querySelectorAll('.prm-tab-content');

    tabLinks.forEach(link => {
        link.addEventListener('click', function() {
            const targetTab = this.getAttribute('data-tab');

            tabLinks.forEach(btn => btn.classList.remove('active'));
            tabContents.forEach(content => content.classList.remove('active'));

            this.classList.add('active');
            const targetEl = document.getElementById('prm-tab-' + targetTab);
            if (targetEl) {
                targetEl.classList.add('active');
            }
        });
    });

    // 2. Plugins Table Event Delegation (Delete and Source Dropdown)
    const pluginsTable = document.getElementById('prm-plugins-table');
    if (pluginsTable) {
        pluginsTable.addEventListener('change', function(e) {
            if (e.target && e.target.classList.contains('prm-type-select')) {
                const proCell = e.target.closest('tr').querySelector('.pro-fields-cell');
                proCell.classList.toggle('active', e.target.value === 'pro');
            }
        });

        pluginsTable.addEventListener('click', function(e) {
            if (e.target && e.target.classList.contains('prm-row-remove')) {
                const row = e.target.closest('tr');
                if (row) {
                    row.remove();
                }
            }
        });
    }

    // 3. Add Plugin Row Logic (clones the server-rendered row template)
    const addPluginBtn = document.getElementById('prm-add-plugin-btn');
    const rowTemplate = document.getElementById('prm-plugin-row-template');
    if (addPluginBtn && pluginsTable && rowTemplate) {
        const tbody = pluginsTable.querySelector('tbody');
        addPluginBtn.addEventListener('click', function() {
            let maxIndex = -1;
            tbody.querySelectorAll('.prm-plugin-row').forEach(row => {
                const idx = parseInt(row.getAttribute('data-index') || '-1', 10);
                if (idx > maxIndex) {
                    maxIndex = idx;
                }
            });

            const html = rowTemplate.innerHTML.replace(/__INDEX__/g, String(maxIndex + 1));
            tbody.insertAdjacentHTML('beforeend', html);
        });
    }

    // 4. Alert Helper
    function showAlert(message, isSuccess = true) {
        const container = document.getElementById('prm-alert-container');
        if (!container) return;

        container.innerHTML = '';

        const alertDiv = document.createElement('div');
        alertDiv.className = `prm-alert ${isSuccess ? 'success' : 'error'} animate-pop`;
        alertDiv.textContent = message;

        container.appendChild(alertDiv);

        // Auto remove alert after 4 seconds
        setTimeout(() => {
            alertDiv.style.opacity = '0';
            alertDiv.style.transform = 'scale(0.95)';
            alertDiv.style.transition = 'all 0.4s ease';
            setTimeout(() => alertDiv.remove(), 400);
        }, 4000);
    }

    // 5. AJAX Form Submissions
    function bindAjaxForm(formId, action, busyText, onSuccess) {
        const form = document.getElementById(formId);
        if (!form) return;

        form.addEventListener('submit', function(e) {
            e.preventDefault();
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="prm-spinner"></span> ';
            submitBtn.appendChild(document.createTextNode(busyText));

            const formData = new FormData(form);
            formData.append('action', action);

            fetch(prmonData.ajaxUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.data.message || i18n.saved);
                    if (onSuccess) onSuccess(data.data);
                } else {
                    showAlert((data.data && data.data.message) || i18n.error, false);
                }
            })
            .catch(() => {
                showAlert(i18n.networkError, false);
            })
            .finally(() => {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalHtml;
            });
        });
    }

    bindAjaxForm('prm-plugins-form', 'prmon_save_plugins', i18n.saving);
    bindAjaxForm('prm-settings-form', 'prmon_save_settings', i18n.saving);
    bindAjaxForm('prm-run-check-form', 'prmon_run_check', i18n.checking, function(data) {
        const overviewContent = document.getElementById('prm-overview-dynamic-content');
        if (overviewContent && data.html) {
            overviewContent.innerHTML = data.html;
        }
        const lastCheckedDate = document.getElementById('prm-last-checked-date');
        if (lastCheckedDate && data.last_checked) {
            lastCheckedDate.textContent = data.last_checked;
        }
    });
});
