import { initOrgDesigner } from './org-designer';

function bootOrgDesignerApp() {
    const root = document.getElementById('org-designer-root');
    const bootEl = document.getElementById('org-designer-boot');
    if (!root || !bootEl || root.dataset.booted === '1') {
        return;
    }

    const componentId = root.closest('[wire\\:id]')?.getAttribute('wire:id');
    if (!componentId || typeof Livewire === 'undefined') {
        return;
    }

    const component = Livewire.find(componentId);
    if (!component) {
        return;
    }

    let boot;
    try {
        boot = JSON.parse(bootEl.textContent);
    } catch {
        return;
    }

    root.dataset.booted = '1';
    initOrgDesigner(boot, component);
}

document.addEventListener('livewire:init', () => {
    queueMicrotask(bootOrgDesignerApp);
});
document.addEventListener('livewire:navigated', () => {
    const root = document.getElementById('org-designer-root');
    if (root) {
        delete root.dataset.booted;
    }
    queueMicrotask(bootOrgDesignerApp);
});
if (window.Livewire) {
    queueMicrotask(bootOrgDesignerApp);
}
