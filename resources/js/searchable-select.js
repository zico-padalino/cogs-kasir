function normalize(text) {
    return String(text || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function optionLabel(option) {
    return (option.textContent || option.label || '').trim();
}

function enhanceSelect(select) {
    if (select.dataset.searchableBound === '1') {
        return;
    }

    select.dataset.searchableBound = '1';

    const placeholder = select.dataset.searchPlaceholder
        || select.options[0]?.textContent?.trim()
        || 'Pilih...';
    const searchPlaceholder = select.dataset.searchInputPlaceholder || 'Cari...';

    const wrap = document.createElement('div');
    wrap.className = 'searchable-select';
    wrap.dataset.searchableWrap = '1';

    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('searchable-select__native');
    select.tabIndex = -1;
    select.setAttribute('aria-hidden', 'true');

    const trigger = document.createElement('button');
    trigger.type = 'button';
    trigger.className = 'searchable-select__trigger';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');

    const triggerLabel = document.createElement('span');
    triggerLabel.className = 'searchable-select__trigger-label';
    trigger.appendChild(triggerLabel);

    const triggerChevron = document.createElement('span');
    triggerChevron.className = 'searchable-select__chevron';
    triggerChevron.setAttribute('aria-hidden', 'true');
    trigger.appendChild(triggerChevron);

    wrap.appendChild(trigger);

    const panel = document.createElement('div');
    panel.className = 'searchable-select__panel';
    panel.hidden = true;
    panel.setAttribute('role', 'listbox');

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'searchable-select__search';
    search.placeholder = searchPlaceholder;
    search.autocomplete = 'off';
    search.setAttribute('aria-label', searchPlaceholder);
    panel.appendChild(search);

    const list = document.createElement('ul');
    list.className = 'searchable-select__list';
    panel.appendChild(list);

    const empty = document.createElement('p');
    empty.className = 'searchable-select__empty';
    empty.hidden = true;
    empty.textContent = 'Tidak ada yang cocok';
    panel.appendChild(empty);

    document.body.appendChild(panel);

    let open = false;
    let activeIndex = -1;
    let visibleItems = [];

    const syncTrigger = () => {
        const selected = select.options[select.selectedIndex];
        const hasValue = Boolean(select.value);
        triggerLabel.textContent = hasValue && selected ? optionLabel(selected) : placeholder;
        trigger.classList.toggle('is-placeholder', ! hasValue);
    };

    const placePanel = () => {
        if (panel.parentElement !== document.body) {
            document.body.appendChild(panel);
        }

        const rect = trigger.getBoundingClientRect();
        const gap = 8;
        const viewportPad = 12;
        const preferred = 280;
        const spaceBelow = window.innerHeight - rect.bottom - gap - viewportPad;
        const spaceAbove = rect.top - gap - viewportPad;
        const openUp = spaceBelow < 220 && spaceAbove > spaceBelow;
        const available = openUp ? spaceAbove : spaceBelow;
        const height = Math.max(180, Math.min(preferred, Math.max(available, 180)));
        const width = Math.max(rect.width, 260);
        const left = Math.max(
            viewportPad,
            Math.min(rect.left, window.innerWidth - width - viewportPad),
        );

        panel.style.position = 'fixed';
        panel.style.zIndex = '9999';
        panel.style.width = `${width}px`;
        panel.style.left = `${left}px`;
        panel.style.maxHeight = `${height}px`;
        panel.style.height = 'auto';

        if (openUp) {
            panel.style.top = 'auto';
            panel.style.bottom = `${window.innerHeight - rect.top + gap}px`;
        } else {
            panel.style.bottom = 'auto';
            panel.style.top = `${rect.bottom + gap}px`;
        }
    };

    const setOpen = (next) => {
        open = next;
        panel.hidden = ! open;
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        wrap.classList.toggle('is-open', open);
        panel.classList.toggle('is-open', open);

        if (open) {
            search.value = '';
            // Pastikan trigger punya ruang di viewport sebelum panel diposisikan.
            trigger.scrollIntoView({ block: 'center', inline: 'nearest' });
            placePanel();
            renderList();
            requestAnimationFrame(() => {
                placePanel();
                search.focus({ preventScroll: true });
            });
        }
    };

    const choose = (value) => {
        if (select.value !== value) {
            select.value = value;
            select.dispatchEvent(new Event('change', { bubbles: true }));
        }
        syncTrigger();
        setOpen(false);
        trigger.focus();
    };

    const setActive = (index) => {
        activeIndex = index;
        list.querySelectorAll('.searchable-select__option').forEach((item, i) => {
            item.classList.toggle('is-active', i === activeIndex);
            if (i === activeIndex) {
                item.scrollIntoView({ block: 'nearest' });
            }
        });
    };

    const renderList = () => {
        const query = normalize(search.value);
        list.innerHTML = '';
        visibleItems = [];
        const seenValues = new Set();
        let lastGroup = null;

        const appendOption = (option, groupLabel) => {
            if (! option.value || seenValues.has(option.value)) {
                return;
            }

            const label = optionLabel(option);
            if (query && ! normalize(label).includes(query)) {
                return;
            }

            seenValues.add(option.value);

            if (groupLabel && groupLabel !== lastGroup) {
                const header = document.createElement('li');
                header.className = 'searchable-select__group';
                header.setAttribute('role', 'presentation');
                header.textContent = groupLabel;
                list.appendChild(header);
                lastGroup = groupLabel;
            }

            const li = document.createElement('li');
            li.className = 'searchable-select__option';
            li.setAttribute('role', 'option');
            li.dataset.value = option.value;
            li.textContent = label;

            if (option.value === select.value) {
                li.classList.add('is-selected');
            }

            li.addEventListener('mousedown', (event) => {
                event.preventDefault();
                choose(option.value);
            });

            list.appendChild(li);
            visibleItems.push(li);
        };

        Array.from(select.children).forEach((child) => {
            if (child.tagName === 'OPTGROUP') {
                Array.from(child.children).forEach((option) => {
                    if (option.tagName === 'OPTION') {
                        appendOption(option, child.label || '');
                    }
                });
                return;
            }

            if (child.tagName === 'OPTION') {
                appendOption(child, '');
            }
        });

        empty.hidden = visibleItems.length > 0;
        setActive(visibleItems.length ? 0 : -1);
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        setOpen(! open);
    });

    search.addEventListener('input', () => {
        renderList();
    });

    search.addEventListener('keydown', (event) => {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            if (visibleItems.length) {
                setActive(Math.min(activeIndex + 1, visibleItems.length - 1));
            }
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            if (visibleItems.length) {
                setActive(Math.max(activeIndex - 1, 0));
            }
        } else if (event.key === 'Enter') {
            event.preventDefault();
            if (activeIndex >= 0 && visibleItems[activeIndex]) {
                choose(visibleItems[activeIndex].dataset.value);
            }
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setOpen(false);
            trigger.focus();
        }
    });

    document.addEventListener('click', (event) => {
        if (! open) {
            return;
        }
        if (wrap.contains(event.target) || panel.contains(event.target)) {
            return;
        }
        setOpen(false);
    });

    window.addEventListener('resize', () => {
        if (open) {
            placePanel();
        }
    });

    window.addEventListener('scroll', () => {
        if (open) {
            placePanel();
        }
    }, true);

    select.addEventListener('change', syncTrigger);
    syncTrigger();
}

function initSearchableSelects(root = document) {
    root.querySelectorAll('select[data-searchable-select]').forEach(enhanceSelect);
}

document.addEventListener('DOMContentLoaded', () => initSearchableSelects());

window.initSearchableSelects = initSearchableSelects;

export { initSearchableSelects };
