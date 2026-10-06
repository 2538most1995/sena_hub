'use strict';
const search = document.querySelector('#systemSearch');
if (search && !document.querySelector('#systems')?.dataset.serverSearch) {
  const catalog = document.querySelector('#systems');
  const cards = [...catalog.querySelectorAll('.system-card')];
  const home = document.querySelector('#homeContent');
  const category = catalog.dataset.initialCategory;
  const isHome = catalog.dataset.home === 'true';
  function filter() {
    const term = search.value.trim().toLocaleLowerCase('th');
    let count = 0;
    cards.forEach(card => { card.hidden = !((category === 'all' || card.dataset.category.split(' ').includes(category)) && card.dataset.search.toLocaleLowerCase('th').includes(term)); if (!card.hidden) count++; });
    catalog.hidden = isHome && !term;
    if (home) home.hidden = Boolean(term);
    document.querySelector('#resultCount').textContent = `${count} ระบบ`;
    document.querySelector('#emptyState').hidden = count !== 0;
  }
  search.addEventListener('input', filter);
  search.form.addEventListener('reset', event => { event.preventDefault(); search.value = ''; filter(); search.focus(); });
  filter();
}
document.querySelectorAll('[data-slide-target]').forEach(button => button.addEventListener('click', () => {
  document.querySelectorAll('[data-slide]').forEach(slide => { slide.hidden = slide.dataset.slide !== button.dataset.slideTarget; });
  document.querySelectorAll('[data-slide-target]').forEach(dot => dot.setAttribute('aria-pressed', String(dot === button)));
}));
document.addEventListener('click', event => { const menu = document.querySelector('.portal-menu'); if (menu && !menu.contains(event.target)) menu.open = false; });
document.addEventListener('keydown', event => { if (event.key === 'Escape') { const menu = document.querySelector('.portal-menu'); if (menu?.open) { menu.open = false; menu.querySelector('summary').focus(); } } });
document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!confirm(form.dataset.confirm)) event.preventDefault(); }));
document.querySelectorAll('[data-copy-input]').forEach(input => input.addEventListener('click', () => input.select()));

const adminSearch = document.querySelector('#adminSearch');
if (adminSearch) {
  const rows = [...document.querySelectorAll('.admin-row')];
  adminSearch.addEventListener('input', () => {
    const term = adminSearch.value.trim().toLocaleLowerCase('th');
    let visible = 0;
    rows.forEach(row => { row.hidden = !row.querySelector('.row-info').textContent.toLocaleLowerCase('th').includes(term); if (!row.hidden) visible++; });
    document.querySelector('#adminSearchResult').textContent = term ? (visible ? `พบ ${visible} ระบบ` : 'ไม่พบระบบที่ค้นหา ลองใช้คำค้นอื่น') : '';
  });
}
document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
  const input = document.getElementById(button.dataset.passwordToggle);
  const show = input.type === 'password';
  input.type = show ? 'text' : 'password';
  button.textContent = show ? 'ซ่อน' : 'แสดง';
  button.setAttribute('aria-pressed', String(show));
  button.setAttribute('aria-label', show ? 'ซ่อนรหัสผ่าน' : 'แสดงรหัสผ่าน');
}));
const adminLinks = [...document.querySelectorAll('.admin-sidebar nav a')];
function updateAdminNav() {
  const section = ['#settings','#security'].includes(location.hash) ? location.hash : '#catalog';
  adminLinks.forEach(link => { if (link.hash === section) link.setAttribute('aria-current','location'); else link.removeAttribute('aria-current'); });
}
if (adminLinks.length) { updateAdminNav(); window.addEventListener('hashchange', updateAdminNav); }

document.querySelectorAll('[data-service-favicon]').forEach(image => {
  const show = () => { if (image.naturalWidth > 0) image.parentElement.classList.add('has-favicon'); };
  image.addEventListener('load', show);
  image.addEventListener('error', () => { image.parentElement.classList.remove('has-favicon'); });
  if (image.complete) show();
});

const iconFile = document.querySelector('#iconFile');
if (iconFile) {
  let previewUrl;
  const preview = document.querySelector('#iconPreview');
  const existingSource = preview.getAttribute('src');
  const feedback = document.querySelector('#uploadFeedback');
  iconFile.addEventListener('change', () => {
    if (previewUrl) { URL.revokeObjectURL(previewUrl); previewUrl = null; }
    feedback.textContent = '';
    const file = iconFile.files[0];
    iconFile.setCustomValidity('');
    if (!file) {
      if (existingSource) preview.src = existingSource; else preview.removeAttribute('src');
      preview.parentElement.hidden = !existingSource;
      document.querySelector('#iconPreviewLabel').textContent = 'รูปที่อัปโหลดไว้ — เว้นช่องไฟล์ว่างเพื่อเก็บรูปนี้';
      return;
    }
    if (!['image/png','image/jpeg','image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
      const message = 'เลือกรูป PNG, JPG หรือ WebP ขนาดไม่เกิน 2 MB';
      iconFile.setCustomValidity(message); feedback.textContent = message;
      return;
    }
    document.querySelector('select[name="icon_mode"]').value = 'upload';
    previewUrl = URL.createObjectURL(file); preview.src = previewUrl;
    preview.parentElement.hidden = false;
    document.querySelector('#iconPreviewLabel').textContent = 'ตัวอย่างรูปใหม่ — กดบันทึกระบบเพื่อใช้งาน';
  });
}

// Native controls work without JavaScript; these add selection and form feedback.
document.querySelectorAll('[data-select-all]').forEach(control => control.addEventListener('change', () => {
  document.querySelectorAll(`.${control.dataset.selectAll}`).forEach(input => { input.checked = control.checked; });
}));
document.querySelectorAll('[data-avatar-input]').forEach(input => {
  let url;
  input.addEventListener('change', () => {
    if (url) URL.revokeObjectURL(url);
    const preview = document.querySelector('[data-avatar-preview]');
    const file = input.files[0];
    input.setCustomValidity(''); preview.hidden = true;
    if (!file) return;
    if (!['image/png', 'image/jpeg', 'image/webp'].includes(file.type) || file.size > 2 * 1024 * 1024) {
      input.setCustomValidity('เลือกรูป PNG, JPG หรือ WebP ขนาดไม่เกิน 2 MB'); input.reportValidity(); return;
    }
    url = URL.createObjectURL(file); preview.src = url; preview.hidden = false;
  });
});
let dirtyForm = false;
document.querySelectorAll('[data-dirty-form]').forEach(form => {
  form.addEventListener('input', () => { dirtyForm = true; });
  form.addEventListener('submit', () => { dirtyForm = false; });
});
window.addEventListener('beforeunload', event => { if (dirtyForm) { event.preventDefault(); event.returnValue = ''; } });
