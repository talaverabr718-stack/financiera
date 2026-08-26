const debounce = (callback, delay = 300) => {
    let timer;
    return (...args) => { clearTimeout(timer); timer = setTimeout(() => callback(...args), delay); };
};

const appendHighlighted = (container, value, term) => {
    const index = value.toLocaleLowerCase('es').indexOf(term.toLocaleLowerCase('es'));
    if (index < 0 || !term) { container.textContent = value; return; }
    container.append(document.createTextNode(value.slice(0, index)));
    const mark = document.createElement('mark'); mark.textContent = value.slice(index, index + term.length); container.append(mark);
    container.append(document.createTextNode(value.slice(index + term.length)));
};

export const enhanceSearchableCombobox = (select) => {
    if (select.dataset.comboboxReady === 'true') return;
    select.dataset.comboboxReady = 'true';
    const endpoint = select.dataset.endpoint;
    const selected = select.options[select.selectedIndex];
    const root = document.createElement('div'); root.className = 'searchable-combobox';
    const input = document.createElement('input'); input.type = 'text'; input.className = 'searchable-combobox-input'; input.placeholder = select.dataset.placeholder || 'Escribe para buscar…'; input.autocomplete = 'off';
    if (select.required) input.setAttribute('aria-required', 'true'); select.required = false;
    input.value = selected?.value ? selected.textContent.split(' · ')[0].trim() : '';
    const listId = `combobox-${Math.random().toString(36).slice(2)}`; input.setAttribute('role', 'combobox'); input.setAttribute('aria-autocomplete', 'list'); input.setAttribute('aria-controls', listId); input.setAttribute('aria-expanded', 'false');
    const status = document.createElement('span'); status.className = 'searchable-combobox-status'; status.setAttribute('aria-hidden', 'true');
    const list = document.createElement('div'); list.id = listId; list.className = 'searchable-combobox-list hidden'; list.setAttribute('role', 'listbox');
    select.parentNode.insertBefore(root, select); root.append(input, status, list, select); select.classList.add('hidden');
    let results = [], active = -1, selectedText = input.value, requestController;
    const close = () => { list.classList.add('hidden'); input.setAttribute('aria-expanded', 'false'); input.removeAttribute('aria-activedescendant'); active = -1; };
    const setMessage = (message, type = '') => { list.replaceChildren(); const item = document.createElement('div'); item.className = `searchable-combobox-message ${type}`; item.textContent = message; list.append(item); list.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); };
    const choose = (item) => { select.value = String(item.id); select.dispatchEvent(new Event('change', { bubbles: true })); input.value = item.label; selectedText = item.label; close(); input.focus(); };
    const setActive = (next) => { active = Math.max(0, Math.min(next, results.length - 1)); list.querySelectorAll('[role="option"]').forEach((option, index) => { option.classList.toggle('is-active', index === active); option.setAttribute('aria-selected', index === active ? 'true' : 'false'); }); const option = list.querySelectorAll('[role="option"]')[active]; if (option) { input.setAttribute('aria-activedescendant', option.id); option.scrollIntoView({ block: 'nearest' }); } };
    const render = (term) => { list.replaceChildren(); active = -1; results.forEach((item, index) => { const option = document.createElement('div'); option.id = `${listId}-option-${index}`; option.className = 'searchable-combobox-result'; option.setAttribute('role', 'option'); option.setAttribute('aria-selected', 'false'); option.tabIndex = -1; const text = document.createElement('span'); text.className = 'searchable-combobox-result-text'; const label = document.createElement('strong'); appendHighlighted(label, item.label, term); text.append(label); if (item.description) { const description = document.createElement('small'); appendHighlighted(description, item.description, term); text.append(description); } const indicator = document.createElement('span'); indicator.className = 'searchable-combobox-result-indicator'; indicator.textContent = 'Seleccionar'; option.append(text, indicator); option.addEventListener('mousedown', event => { event.preventDefault(); choose(item); }); list.append(option); }); list.classList.remove('hidden'); input.setAttribute('aria-expanded', 'true'); };
    const search = debounce(async () => { const term = input.value.trim(); if (term.length < 2) { setMessage('Escribe al menos 2 caracteres.'); return; } requestController?.abort(); requestController = new AbortController(); status.classList.add('is-loading'); setMessage('Buscando resultados…', 'is-loading'); try { const url = new URL(endpoint, window.location.origin); url.searchParams.set('q', term); const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: requestController.signal }); if (!response.ok) throw new Error('request_failed'); const payload = await response.json(); results = [...new Map((payload.data || []).map(item => [String(item.id), item])).values()].slice(0, 15); results.length ? render(term) : setMessage('No se encontraron resultados.'); } catch (error) { if (error.name !== 'AbortError') { results = []; setMessage('No se pudo completar la búsqueda. Intenta nuevamente.', 'is-error'); } } finally { status.classList.remove('is-loading'); } }, 300);
    input.addEventListener('input', () => { if (input.value !== selectedText) { select.value = ''; select.dispatchEvent(new Event('change', { bubbles: true })); selectedText = ''; } search(); });
    input.addEventListener('focus', () => { if (input.value.trim().length >= 2) search(); });
    input.addEventListener('keydown', event => { if (event.key === 'Escape') { close(); return; } if (list.classList.contains('hidden') || !results.length) return; if (event.key === 'ArrowDown') { event.preventDefault(); setActive(active + 1); } else if (event.key === 'ArrowUp') { event.preventDefault(); setActive(active <= 0 ? results.length - 1 : active - 1); } else if (event.key === 'Enter' && active >= 0) { event.preventDefault(); choose(results[active]); } });
    document.addEventListener('pointerdown', event => { if (!root.contains(event.target)) close(); });
};

document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-searchable-combobox]').forEach(enhanceSearchableCombobox));
