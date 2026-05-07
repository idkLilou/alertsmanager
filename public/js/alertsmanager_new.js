/**
 * Alerts Manager Plugin JavaScript
 */

(function() {
    'use strict';

    // Alert management functions
    const AlertsManager = {
        init: function() {
            this.setupEventListeners();
            this.setupFormHandlers();
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
            // Observed field change handler
            const observedField = document.getElementById('alert_observed_field');
            if (observedField) {
                observedField.addEventListener('change', (e) => {
                    this.updateTriggerFields(e.target.value);
                });
            }

            // Target type change handler
            const targetType = document.getElementById('alert_target_type');
            if (targetType) {
                targetType.addEventListener('change', (e) => {
                    this.updateTargetOptions(e.target.value);
                });
            }
        },

        updateTriggerFields: function(value) {
            const dateGroup = document.getElementById('date_trigger_group');
            const daysGroup = document.getElementById('date_trigger_days_group');
            const frequencyGroup = document.getElementById('frequency_trigger_group');

            if (value) {
                // Show date-based trigger fields
                if (dateGroup) dateGroup.style.display = 'block';
                if (daysGroup) daysGroup.style.display = 'block';
                if (frequencyGroup) frequencyGroup.style.display = 'none';
            } else {
                // Show frequency-based trigger fields
                if (dateGroup) dateGroup.style.display = 'none';
                if (daysGroup) daysGroup.style.display = 'none';
                if (frequencyGroup) frequencyGroup.style.display = 'block';
            }
        },

        updateTargetOptions: function(targetType) {
            const targetsSelect = document.getElementById('alert_targets');
            if (!targetsSelect) return;

            // Clear existing options
            targetsSelect.innerHTML = '';

            // Here you would typically make an AJAX call to fetch the available options
            // For now, we'll add some placeholder options
            const options = {
                'User': [
                    { value: 'user1', label: 'John Doe' },
                    { value: 'user2', label: 'Jane Smith' },
                ],
                'Group': [
                    { value: 'group1', label: 'Support Team' },
                    { value: 'group2', label: 'Admin Team' },
                ],
                'Profile': [
                    { value: 'profile1', label: 'Administrator' },
                    { value: 'profile2', label: 'User' },
                    { value: 'profile3', label: 'Technician' },
                ],
            };

            if (options[targetType]) {
                options[targetType].forEach(option => {
                    const optionEl = document.createElement('option');
                    optionEl.value = option.value;
                    optionEl.textContent = option.label;
                    targetsSelect.appendChild(optionEl);
                });
            }
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

        checkAllRows: function(checkbox) {
            const checkboxes = document.querySelectorAll('input[name="ids[]"]');
            checkboxes.forEach(cb => {
                cb.checked = checkbox.checked;
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
