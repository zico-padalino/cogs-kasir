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

function debounce(fn, wait) {
    let timer = null;
    return (...args) => {
        clearTimeout(timer);
        timer = setTimeout(() => fn(...args), wait);
    };
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
    const remoteUrl = (select.dataset.remoteUrl || '').trim();
    const isRemote = remoteUrl !== '';

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

    const footer = document.createElement('div');
    footer.className = 'searchable-select__footer';
    footer.hidden = true;
    panel.appendChild(footer);

    const loadMoreBtn = document.createElement('button');
    loadMoreBtn.type = 'button';
    loadMoreBtn.className = 'searchable-select__load-more';
    loadMoreBtn.textContent = 'Muat lagi';
    footer.appendChild(loadMoreBtn);

    document.body.appendChild(panel);

    let open = false;
    let activeIndex = -1;
    let visibleItems = [];
    let remotePage = 0;
    let remoteHasMore = false;
    let remoteLoading = false;
    let remoteQuery = '';
    let remoteAbort = null;

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
        const preferred = 320;
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
        panel.style.zIndex = '11000';
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
            search.value = isRemote ? remoteQuery : '';
            trigger.scrollIntoView({ block: 'center', inline: 'nearest' });
            placePanel();
            if (isRemote) {
                if (remotePage === 0) {
                    loadRemotePage(1, true);
                } else {
                    renderRemoteList();
                }
            } else {
                renderLocalList();
            }
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
        } else {
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

    const upsertRemoteOption = (item) => {
        const value = String(item.id);
        let option = Array.from(select.options).find((opt) => opt.value === value);

        if (! option) {
            option = document.createElement('option');
            option.value = value;
            select.appendChild(option);
        }

        const label = item.unit_label
            ? `${item.name} (${item.unit_label})`
            : item.name;
        option.textContent = label;

        if (item.units) {
            option.dataset.units = JSON.stringify(item.units);
        }
        if (item.type) {
            option.dataset.type = item.type;
        }

        return option;
    };

    const renderRemoteList = () => {
        list.innerHTML = '';
        visibleItems = [];

        Array.from(select.options).forEach((option) => {
            if (option.value === '' || option.hidden || option.disabled) {
                return;
            }

            const li = document.createElement('li');
            li.className = 'searchable-select__option';
            li.setAttribute('role', 'option');
            li.dataset.value = option.value;
            li.textContent = optionLabel(option);

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

        empty.hidden = visibleItems.length > 0 || remoteLoading;
        empty.textContent = remoteLoading ? 'Memuat...' : 'Tidak ada yang cocok';
        footer.hidden = ! remoteHasMore;
        loadMoreBtn.disabled = remoteLoading;
        loadMoreBtn.textContent = remoteLoading ? 'Memuat...' : 'Muat lagi';
        setActive(visibleItems.length ? 0 : -1);
    };

    const loadRemotePage = async (page, reset = false) => {
        if (remoteLoading) {
            return;
        }

        remoteLoading = true;
        footer.hidden = false;
        loadMoreBtn.disabled = true;
        loadMoreBtn.textContent = 'Memuat...';
        if (reset) {
            empty.hidden = false;
            empty.textContent = 'Memuat...';
            list.innerHTML = '';
            visibleItems = [];
        }

        if (remoteAbort) {
            remoteAbort.abort();
        }
        remoteAbort = new AbortController();

        const params = new URLSearchParams();
        params.set('page', String(page));
        params.set('per_page', select.dataset.remotePerPage || '20');
        if (select.dataset.remoteType) {
            params.set('type', select.dataset.remoteType);
        }
        if (remoteQuery) {
            params.set('q', remoteQuery);
        }

        try {
            const response = await fetch(`${remoteUrl}?${params.toString()}`, {
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                signal: remoteAbort.signal,
            });

            if (! response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const payload = await response.json();
            const rows = Array.isArray(payload.data) ? payload.data : [];
            const meta = payload.meta || {};

            if (reset) {
                const keepValue = select.value;
                Array.from(select.options).forEach((option) => {
                    if (option.value !== '' && option.value !== keepValue) {
                        option.remove();
                    }
                });
            }

            rows.forEach((item) => upsertRemoteOption(item));

            remotePage = Number(meta.page || page);
            remoteHasMore = Boolean(meta.has_more);
            renderRemoteList();
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }
            empty.hidden = false;
            empty.textContent = 'Gagal memuat bahan. Coba lagi.';
            footer.hidden = true;
            visibleItems = [];
        } finally {
            remoteLoading = false;
            loadMoreBtn.disabled = false;
            loadMoreBtn.textContent = 'Muat lagi';
            footer.hidden = ! remoteHasMore;
        }
    };

    const renderLocalList = () => {
        const query = normalize(search.value);
        list.innerHTML = '';
        visibleItems = [];
        const seenValues = new Set();
        let lastGroup = null;

        const appendOption = (option, groupLabel) => {
            if (option.hidden || option.disabled) {
                return;
            }

            const valueKey = option.value === '' ? '__empty__' : option.value;
            if (seenValues.has(valueKey)) {
                return;
            }

            const label = optionLabel(option);
            if (! label) {
                return;
            }
            if (query && ! normalize(label).includes(query)) {
                return;
            }

            seenValues.add(valueKey);

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
        footer.hidden = true;
        setActive(visibleItems.length ? 0 : -1);
    };

    const onRemoteSearch = debounce(() => {
        remoteQuery = search.value.trim();
        remotePage = 0;
        remoteHasMore = false;
        loadRemotePage(1, true);
    }, 280);

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        if (select.disabled) {
            return;
        }
        setOpen(! open);
    });

    search.addEventListener('input', () => {
        if (isRemote) {
            onRemoteSearch();
        } else {
            renderLocalList();
        }
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

    loadMoreBtn.addEventListener('mousedown', (event) => {
        event.preventDefault();
    });

    loadMoreBtn.addEventListener('click', (event) => {
        event.preventDefault();
        if (! isRemote || ! remoteHasMore || remoteLoading) {
            return;
        }
        loadRemotePage(remotePage + 1, false);
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

    const syncDisabled = () => {
        const isDisabled = select.disabled;
        trigger.disabled = isDisabled;
        wrap.classList.toggle('is-disabled', isDisabled);
        if (isDisabled && open) {
            setOpen(false);
        }
    };

    syncDisabled();

    const attrObserver = new MutationObserver(() => {
        syncDisabled();
        syncTrigger();
    });
    attrObserver.observe(select, { attributes: true, attributeFilter: ['disabled', 'required'] });
}

function initSearchableSelects(root = document) {
    root.querySelectorAll('select[data-searchable-select]').forEach(enhanceSelect);
}

document.addEventListener('DOMContentLoaded', () => initSearchableSelects());

window.initSearchableSelects = initSearchableSelects;

export { initSearchableSelects };
