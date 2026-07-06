/**
 * Alerts Manager Plugin JavaScript
 */
console.log('[AlertsManager] alertsmanager.js loaded!');

(function() {
    'use strict';
    console.log('[AlertsManager] IIFE started');

    // Alert management functions
    const AlertsManager = {
        formHandlersAttached: false,

        init: function() {
            console.log('[AlertsManager] Initializing AlertsManager...');
            this.setupEventListeners();
            this.setupFormHandlers();
            console.log('[AlertsManager] Initialization complete');
        },

        setupEventListeners: function() {
            // Setup close button handlers
            const closeButtons = document.querySelectorAll('.alert-box-close');
            closeButtons.forEach(button => {
                button.addEventListener('click', (e) => {
                    e.preventDefault();
                    this.hideAlert(e.target.closest('.alert-box'));
                });
            });

            // Setup checkbox handlers
            const checkAll = document.getElementById('checkall');
            if (checkAll) {
                checkAll.addEventListener('change', (e) => {
                    this.checkAllRows(e.target);
                });
            }
        },

        setupFormHandlers: function() {
            console.log('[AlertsManager] setupFormHandlers() called');

            const attach = () => {
                if (this.formHandlersAttached) {
                    return true;
                }

                const observedField = document.getElementById('alert_observed_field');
                const targetTypes = document.getElementById('alert_target_types');
                const targetSelects = document.querySelectorAll('.alertsmanager-target-select');

                console.log('[AlertsManager] observed_field element:', observedField);
                console.log('[AlertsManager] target_types element:', targetTypes);
                console.log('[AlertsManager] target select count:', targetSelects.length);

                if (!observedField || !targetTypes || targetSelects.length === 0) {
                    return false;
                }

                observedField.addEventListener('change', (e) => {
                    console.log('[AlertsManager] observed_field changed to:', e.target.value);
                    this.updateTriggerFields();
                });

                // Frequency field removed from form; listener disabled
                /*
                // Also react to frequency changes to show/hide start date when needed
                const frequencySelect = document.getElementById('frequency');
                if (frequencySelect) {
                    frequencySelect.addEventListener('change', (e) => {
                        console.log('[AlertsManager] frequency changed to:', e.target.value);
                        this.updateTriggerFields();
                    });
                }
                */

                targetTypes.addEventListener('change', () => {
                    this.updateTargetTypeBlocks();
                });

                targetSelects.forEach((select) => {
                    const selectedTargetIds = Array.from(select.selectedOptions || [])
                        .map(option => String(option.value || '').trim())
                        .filter(value => value !== '');
                    if (selectedTargetIds.length > 0) {
                        select.dataset.selectedTargets = selectedTargetIds.join(',');
                    }

                    select.addEventListener('change', () => {
                        const currentSelectedTargetIds = Array.from(select.selectedOptions || [])
                            .map(option => String(option.value || '').trim())
                            .filter(value => value !== '');
                        select.dataset.selectedTargets = currentSelectedTargetIds.join(',');
                    });
                });

                const testSendButton = document.querySelector('.alertsmanager-test-send');
                if (testSendButton) {
                    testSendButton.addEventListener('click', () => {
                        this.testSendMail(testSendButton);
                    });
                }

                this.formHandlersAttached = true;

                // Always update trigger fields visibility on load
                this.updateTriggerFields();
                this.updateTargetTypeBlocks();

                return true;
            };

            if (attach()) {
                return;
            }

            console.log('[AlertsManager] form fields not ready yet, waiting for DOM mutations');
            const observer = new MutationObserver(() => {
                if (attach()) {
                    observer.disconnect();
                }
            });

            observer.observe(document.documentElement, {
                childList: true,
                subtree: true,
            });

            // Setup preview listeners
            this.setupPreviewListeners();
            // If preview section visible on load, request initial preview
            const previewSection = document.querySelector('.preview-section');
            if (previewSection && !previewSection.classList.contains('d-none')) {
                this.reloadPreview();
            }
        },

        updateTriggerFields: function() {
            const observedField = document.getElementById('alert_observed_field');
            const frequencySelect = document.getElementById('frequency');
            const dateGroup = document.getElementById('date_trigger_group');
            const daysGroup = document.getElementById('date_trigger_days_group');
            const frequencyGroup = document.getElementById('frequency_trigger_group');
            const startDateGroup = document.getElementById('start_date_group');

            const hasObserved = observedField && observedField.value;
            const hasFrequency = frequencySelect && frequencySelect.value;

            if (hasObserved) {
                if (dateGroup) dateGroup.style.display = 'block';
                if (daysGroup) daysGroup.style.display = 'block';
                if (frequencyGroup) frequencyGroup.style.display = 'none';
            } else {
                if (dateGroup) dateGroup.style.display = 'none';
                if (daysGroup) daysGroup.style.display = 'none';
                if (frequencyGroup) frequencyGroup.style.display = 'block';
            }

            // start date removed from form; do not force display
            // if (startDateGroup) startDateGroup.style.display = 'block';
        },

        updateTargetTypeBlocks: function() {
            const targetTypes = document.getElementById('alert_target_types');
            if (!targetTypes) {
                return;
            }

            const selectedTypes = Array.from(targetTypes.selectedOptions || [])
                .map(option => String(option.value || '').trim())
                .filter(value => value !== '');

            document.querySelectorAll('.alertsmanager-target-block').forEach((block) => {
                const targetType = String(block.dataset.targetType || '').trim();
                const targetSelect = block.querySelector('.alertsmanager-target-select');
                const shouldShow = selectedTypes.includes(targetType);

                block.classList.toggle('d-none', !shouldShow);
                if (targetSelect) {
                    targetSelect.disabled = !shouldShow;
                    if (shouldShow) {
                        if (!targetSelect.dataset.loaded) {
                            this.loadTargets(targetType);
                        }
                    }
                }
            });
        },

        loadTargets: async function(targetType) {
            console.log('[AlertsManager] loadTargets() called with type:', targetType);
            const targetsSelect = document.querySelector('.alertsmanager-target-select[data-target-type="' + targetType + '"]');
            if (!targetsSelect) {
                console.error('[AlertsManager] alert_targets element not found!');
                return;
            }

            const selectedTargetIds = (targetsSelect.dataset.selectedTargets || '')
                .split(',')
                .map(value => value.trim())
                .filter(value => value !== '');

            targetsSelect.innerHTML = '';

            if (!targetType) {
                targetsSelect.appendChild(new Option('-- Select --', ''));
                return;
            }

            try {
                const query = new URLSearchParams({
                    target_type: targetType,
                    limit: '5000'
                });
                const url = '/plugins/alertsmanager/ajax/targets.php?' + query.toString();
                console.log('[AlertsManager] fetching targets from:', url);
                const resp = await fetch(url, { credentials: 'same-origin' });
                if (!resp.ok) {
                    throw new Error('Network response was not ok');
                }

                const data = await resp.json();
                console.log('[AlertsManager] received', data.length, 'targets');
                data.forEach(item => {
                    const optionEl = document.createElement('option');
                    optionEl.value = item.id;
                    optionEl.textContent = item.label;
                    if (selectedTargetIds.includes(String(item.id))) {
                        optionEl.selected = true;
                    }
                    targetsSelect.appendChild(optionEl);
                });
                targetsSelect.dataset.loaded = '1';
            } catch (e) {
                console.error('[AlertsManager] Failed to load targets:', e);
                targetsSelect.innerHTML = '';
                targetsSelect.appendChild(new Option('-- Error loading targets --', ''));
            }
        },
        // Preview handling (mail preview)
        reloadPreview: async function() {
            const form = document.querySelector('form[name=asset_form]') || document.querySelector('form');
            if (!form) return;

            // Save TinyMCE content to textarea if present
            if (typeof tinyMCE !== 'undefined') {
                try { tinyMCE.triggerSave(); } catch (e) { /* ignore */ }
            }

            const data = new FormData(form);

            try {
                const resp = await fetch('/plugins/alertsmanager/ajax/alert_preview.php', {
                    method: 'POST',
                    body: data,
                    credentials: 'same-origin',
                });
                if (!resp.ok) throw new Error('Network error');
                const html = await resp.text();
                const container = document.querySelector('.alert-preview');
                if (container) container.innerHTML = html;
            } catch (e) {
                console.error('Preview load failed', e);
            }
        },

        testSendMail: async function(button) {
            const alertId = button?.dataset?.alertId || '0';
            const resultContainer = document.querySelector('.alertsmanager-test-send-result');
            const form = button?.closest('form') || document.querySelector('form[name=asset_form]') || document.querySelector('form');

            if (!alertId || alertId === '0') {
                if (resultContainer) {
                    resultContainer.innerHTML = '<div class="alert alert-warning mb-0">Save the alert before sending a test mail.</div>';
                }
                return;
            }

            if (button) {
                button.disabled = true;
            }

            try {
                const formData = form ? new FormData(form) : new FormData();
                formData.append('alert_id', alertId);

                if (!formData.has('_glpi_csrf_token')) {
                    const csrfToken = document.querySelector('input[name="_glpi_csrf_token"]')?.value || '';
                    if (csrfToken) {
                        formData.append('_glpi_csrf_token', csrfToken);
                    }
                }

                const resp = await fetch('/plugins/alertsmanager/ajax/test_send.php', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                });

                const raw = await resp.text();
                let data = null;

                try {
                    data = JSON.parse(raw);
                } catch (parseError) {
                    throw new Error('HTTP ' + resp.status + ': ' + raw.slice(0, 300));
                }

                if (resultContainer) {
                    if (data.success) {
                        resultContainer.innerHTML = '<div class="alert alert-success mb-0">Test mail sent to ' + (data.recipients || []).join(', ') + '</div>';
                    } else {
                        resultContainer.innerHTML = '<div class="alert alert-danger mb-0">' + (data.error || 'Mail send failed') + '</div>';
                    }
                }

                await this.refreshCsrfToken(form);
            } catch (e) {
                if (resultContainer) {
                    resultContainer.innerHTML = '<div class="alert alert-danger mb-0">Unable to reach the test endpoint. ' + (e?.message || '') + '</div>';
                }
            } finally {
                if (button) {
                    button.disabled = false;
                }
            }
        },

        setupPreviewListeners: function() {
            const form = document.querySelector('form[name=asset_form]') || document.querySelector('form');
            if (!form) return;

            const inputs = form.querySelectorAll('input, textarea, select');
            const debounced = _.debounce(() => this.reloadPreview(), 400);
            inputs.forEach(i => i.addEventListener('input', debounced));

            // Also trigger on select change
            inputs.forEach(i => i.addEventListener('change', debounced));
        },

        refreshCsrfToken: async function(form) {
            const tokenInput = form?.querySelector('input[name="_glpi_csrf_token"]');
            if (!tokenInput) {
                return;
            }

            try {
                const resp = await fetch(window.location.href, {
                    method: 'GET',
                    credentials: 'same-origin',
                });

                if (!resp.ok) {
                    return;
                }

                const html = await resp.text();
                const doc = new DOMParser().parseFromString(html, 'text/html');
                const newToken = doc.querySelector('input[name="_glpi_csrf_token"]')?.value || '';
                if (newToken) {
                    tokenInput.value = newToken;
                }
            } catch (e) {
                console.warn('[AlertsManager] Failed to refresh CSRF token after test send:', e);
            }
        },

        checkAllRows: function(checkbox) {
            const checkboxes = document.querySelectorAll('input[name="ids[]"]');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
            });
        },

        hideAlert: function(alertElement) {
            // Hide alert with AJAX call
            if (alertElement) {
                alertElement.style.display = 'none';
            }
        },

        displayAlert: function(alertId, container) {
            // Display alert logic
            console.log('Displaying alert:', alertId);
        },

        hideAllAlerts: function() {
            // Hide all alerts logic
            const alerts = document.querySelectorAll('.alert-box');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        },
    };

    // Initialize on document ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            AlertsManager.init();
        });
    } else {
        AlertsManager.init();
    }

    // Export for external use
    window.AlertsManager = AlertsManager;
})();
