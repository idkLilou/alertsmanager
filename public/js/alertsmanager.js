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
                const targetType = document.getElementById('alert_target_type');
                const targetsSelect = document.getElementById('alert_targets');

                console.log('[AlertsManager] observed_field element:', observedField);
                console.log('[AlertsManager] target_type element:', targetType);
                console.log('[AlertsManager] targets element:', targetsSelect);

                if (!observedField || !targetType || !targetsSelect) {
                    return false;
                }

                observedField.addEventListener('change', (e) => {
                    console.log('[AlertsManager] observed_field changed to:', e.target.value);
                    this.updateTriggerFields();
                });

                // Also react to frequency changes to show/hide start date when needed
                const frequencySelect = document.getElementById('frequency');
                if (frequencySelect) {
                    frequencySelect.addEventListener('change', (e) => {
                        console.log('[AlertsManager] frequency changed to:', e.target.value);
                        this.updateTriggerFields();
                    });
                }

                targetType.addEventListener('change', (e) => {
                    console.log('[AlertsManager] target_type changed to:', e.target.value);
                    this.loadTargets(e.target.value);
                });

                this.formHandlersAttached = true;

                // Always update trigger fields visibility on load
                this.updateTriggerFields();

                if (targetType.value) {
                    this.loadTargets(targetType.value);
                }

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

            if (startDateGroup) startDateGroup.style.display = 'block';
        },

        loadTargets: async function(targetType) {
            console.log('[AlertsManager] loadTargets() called with type:', targetType);
            const targetsSelect = document.getElementById('alert_targets');
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
                targetsSelect.appendChild(new Option('-- Select --', ''));
                data.forEach(item => {
                    const optionEl = document.createElement('option');
                    optionEl.value = item.id;
                    optionEl.textContent = item.label;
                    if (selectedTargetIds.includes(String(item.id))) {
                        optionEl.selected = true;
                    }
                    targetsSelect.appendChild(optionEl);
                });
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

        setupPreviewListeners: function() {
            const form = document.querySelector('form[name=asset_form]') || document.querySelector('form');
            if (!form) return;

            const inputs = form.querySelectorAll('input, textarea, select');
            const debounced = _.debounce(() => this.reloadPreview(), 400);
            inputs.forEach(i => i.addEventListener('input', debounced));

            // Also trigger on select change
            inputs.forEach(i => i.addEventListener('change', debounced));
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
