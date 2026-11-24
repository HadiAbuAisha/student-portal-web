"use strict";
for (const search of document.querySelectorAll('input[data-filter]')) {
  search.addEventListener('input', () => {
    const table = document.getElementById(search.dataset.filter);
    const term = search.value.trim().toLocaleLowerCase();
    if (!table) return;
    for (const row of table.querySelectorAll('tbody tr')) {
      row.hidden = !row.textContent.toLocaleLowerCase().includes(term);
    }
  });
}
for (const form of document.querySelectorAll('form[data-confirm]')) {
  form.addEventListener('submit', (event) => {
    if (!window.confirm(form.dataset.confirm)) event.preventDefault();
  });
}
