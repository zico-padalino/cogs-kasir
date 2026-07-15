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
    trigger.className = 'searchable-select__trigger form-input';
    trigger.setAttribute('aria-haspopup', 'listbox');
    trigger.setAttribute('aria-expanded', 'false');
    wrap.appendChild(trigger);

    const panel = document.createElement('div');
    panel.className = 'searchable-select__panel hidden';
    panel.setAttribute('role', 'listbox');

    const search = document.createElement('input');
    search.type = 'search';
    search.className = 'searchable-select__search form-input';
    search.placeholder = searchPlaceholder;
    search.autocomplete = 'off';
    search.setAttribute('aria-label', searchPlaceholder);
    panel.appendChild(search);

    const list = document.createElement('ul');
    list.className = 'searchable-select__list';
    panel.appendChild(list);

    const empty = document.createElement('p');
    empty.className = 'searchable-select__empty hidden';
    empty.textContent = 'Tidak ada yang cocok';
    panel.appendChild(empty);

    wrap.appendChild(panel);

    let open = false;
    let activeIndex = -1;
    let visibleItems = [];

    const syncTrigger = () => {
        const selected = select.options[select.selectedIndex];
        const hasValue = Boolean(select.value);
        trigger.textContent = hasValue && selected ? optionLabel(selected) : placeholder;
        trigger.classList.toggle('is-placeholder', ! hasValue);
    };

    const setOpen = (next) => {
        open = next;
        panel.classList.toggle('hidden', ! open);
        trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        wrap.classList.toggle('is-open', open);

        if (open) {
            search.value = '';
            renderList();
            requestAnimationFrame(() => search.focus());
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

        Array.from(select.options).forEach((option) => {
            if (! option.value) {
                return;
            }

            const label = optionLabel(option);
            if (query && ! normalize(label).includes(query)) {
                return;
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
        });

        empty.classList.toggle('hidden', visibleItems.length > 0);
        setActive(visibleItems.length ? 0 : -1);
    };

    trigger.addEventListener('click', () => setOpen(! open));

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
        if (open && ! wrap.contains(event.target)) {
            setOpen(false);
        }
    });

    select.addEventListener('change', syncTrigger);
    syncTrigger();
}

function initSearchableSelects(root = document) {
    root.querySelectorAll('select[data-searchable-select]').forEach(enhanceSelect);
}

document.addEventListener('DOMContentLoaded', () => initSearchableSelects());

export { initSearchableSelects };
