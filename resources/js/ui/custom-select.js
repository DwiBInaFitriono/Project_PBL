export function initCustomSelects() {
    let active = null;
    const closeActive = () => active?.close();
    document.addEventListener('pointerdown', (event) => {
        if (active && !active.root.contains(event.target)) closeActive();
    });
    window.addEventListener('resize', closeActive);
    window.addEventListener('scroll', (event) => {
        if (active && !active.list.contains(event.target)) closeActive();
    }, true);

    for (const select of document.querySelectorAll('select[data-custom-select]')) {
        if (select.multiple || select.dataset.enhanced === 'true') continue;
        const label = select.labels[0];
        const wrapper = document.createElement('div');
        wrapper.className = 'custom-select';
        const trigger = document.createElement('button');
        trigger.type = 'button';
        trigger.className = 'custom-select-trigger';
        trigger.id = `${select.id}-trigger`;
        trigger.setAttribute('role', 'combobox');
        trigger.setAttribute('aria-label', label?.textContent.trim() || select.name);
        trigger.setAttribute('aria-haspopup', 'listbox');
        trigger.setAttribute('aria-expanded', 'false');
        trigger.disabled = select.disabled;
        for (const attribute of ['aria-invalid', 'aria-describedby']) {
            if (select.hasAttribute(attribute)) trigger.setAttribute(attribute, select.getAttribute(attribute));
        }
        const value = document.createElement('span');
        value.className = 'custom-select-value';
        trigger.append(value);
        const arrow = document.createElement('span');
        arrow.className = 'custom-select-arrow';
        arrow.setAttribute('aria-hidden', 'true');
        trigger.append(arrow);
        const list = document.createElement('div');
        list.className = 'custom-select-list';
        list.id = `${select.id}-listbox`;
        list.setAttribute('role', 'listbox');
        list.setAttribute('aria-label', label?.textContent.trim() || select.name);
        list.hidden = true;
        trigger.setAttribute('aria-controls', list.id);
        const options = [...select.options];
        const items = options.map((option, index) => {
            const item = document.createElement('div');
            item.className = 'custom-select-option';
            item.id = `${select.id}-option-${index}`;
            item.setAttribute('role', 'option');
            item.setAttribute('aria-selected', String(option.selected));
            if (option.disabled) item.setAttribute('aria-disabled', 'true');
            item.textContent = option.textContent;
            list.append(item);
            return item;
        });
        let current = select.selectedIndex;
        let opened = false;
        let search = '';
        let lastKeyTime = 0;

        function sync() {
            current = select.selectedIndex;
            value.textContent = options[current]?.textContent || '';
            items.forEach((item, index) => item.setAttribute('aria-selected', String(index === current)));
        }

        function highlight(index) {
            current = index;
            items.forEach((item, i) => item.classList.toggle('is-active', i === index));
            if (index < 0) return;
            trigger.setAttribute('aria-activedescendant', items[index].id);
            const item = items[index];
            if (item.offsetTop < list.scrollTop) list.scrollTop = item.offsetTop;
            else if (item.offsetTop + item.offsetHeight > list.scrollTop + list.clientHeight) list.scrollTop = item.offsetTop + item.offsetHeight - list.clientHeight;
        }

        function close() {
            opened = false;
            list.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.removeAttribute('aria-activedescendant');
            wrapper.classList.remove('is-open');
            if (active?.root === wrapper) active = null;
        }

        function open() {
            if (trigger.disabled) return;
            closeActive();
            opened = true;
            active = { root: wrapper, list, close };
            wrapper.classList.add('is-open');
            const box = trigger.getBoundingClientRect();
            const below = window.innerHeight - box.bottom - 12;
            const above = box.top - 12;
            const upward = below < 200 && above > below;
            wrapper.classList.toggle('opens-up', upward);
            list.style.maxHeight = `${Math.max(44, Math.min(260, upward ? above : below))}px`;
            list.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            highlight(select.selectedIndex);
        }

        function commit(index) {
            if (index < 0 || options[index].disabled) return;
            select.selectedIndex = index;
            select.dispatchEvent(new Event('input', { bubbles: true }));
            select.dispatchEvent(new Event('change', { bubbles: true }));
            sync();
            close();
            trigger.focus({ preventScroll: true });
        }

        function step(direction) {
            let next = current + direction;
            while (next >= 0 && next < options.length) {
                if (!options[next].disabled) { highlight(next); return; }
                next += direction;
            }
        }

        trigger.addEventListener('click', () => {
            trigger.focus({ preventScroll: true });
            if (opened) close(); else open();
        });
        trigger.addEventListener('blur', close);
        // Preserve focus for mouse clicks without cancelling WebKit touch activation or scrolling.
        list.addEventListener('mousedown', (event) => event.preventDefault());
        items.forEach((item, index) => item.addEventListener('click', () => commit(index)));
        trigger.addEventListener('keydown', (event) => {
            if (event.key === 'Tab') { close(); return; }
            if (event.key === 'Escape') {
                if (opened) { event.preventDefault(); event.stopPropagation(); close(); }
                return;
            }
            if (['Enter', ' '].includes(event.key)) {
                event.preventDefault();
                if (opened) commit(current); else open();
                return;
            }
            if (['ArrowDown', 'ArrowUp', 'Home', 'End'].includes(event.key)) {
                event.preventDefault();
                const wasOpen = opened;
                if (!opened) open();
                if (event.key === 'Home') { current = -1; step(1); }
                else if (event.key === 'End') { current = options.length; step(-1); }
                else if (wasOpen) step(event.key === 'ArrowDown' ? 1 : -1);
                return;
            }
            if (event.key.length === 1 && !event.ctrlKey && !event.metaKey && !event.altKey) {
                event.preventDefault();
                const now = performance.now();
                search = now - lastKeyTime > 700 ? event.key : search + event.key;
                lastKeyTime = now;
                if (!opened) open();
                const index = options.findIndex((option) => !option.disabled && option.textContent.trim().toLocaleLowerCase('id-ID').startsWith(search.toLocaleLowerCase('id-ID')));
                if (index >= 0) highlight(index);
            }
        });
        select.addEventListener('change', sync);
        select.form?.addEventListener('reset', () => queueMicrotask(() => { sync(); close(); }));
        window.addEventListener('pageshow', () => { sync(); close(); });
        select.before(wrapper);
        wrapper.append(select, trigger, list);
        if (label) label.htmlFor = trigger.id;
        select.hidden = true;
        select.dataset.enhanced = 'true';
        sync();
    }
}
