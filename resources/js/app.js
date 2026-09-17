import { initTheme } from './ui/theme.js';
import { initPasswordVisibility } from './ui/password-visibility.js';
import { initSidebar } from './ui/sidebar.js';
import { initCustomSelects } from './ui/custom-select.js';
import { initMonitoring } from './monitoring/index.js';

initTheme();
initPasswordVisibility();
initSidebar();
initCustomSelects();

const monitoringRoot = document.querySelector('[data-monitoring-root]');
if (monitoringRoot) {
    try {
        initMonitoring(monitoringRoot);
    } catch {
        // Isolate dashboard payload failures from authentication and theme controls.
        const error = monitoringRoot.querySelector('[data-monitoring-error]');
        if (error) error.hidden = false;
        for (const button of monitoringRoot.querySelectorAll('[data-select-sensor], [data-select-range]')) {
            button.disabled = true;
        }
    }
}
