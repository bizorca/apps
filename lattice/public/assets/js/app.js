// ── Unit Accordion ──────────────────────────────────────────────────────
document.querySelectorAll('.unit-header').forEach(header => {
  header.addEventListener('click', () => {
    const item   = header.closest('.unit-item');
    const body   = item.querySelector('.unit-body');
    const toggle = header.querySelector('.unit-toggle');
    const open   = !body.classList.contains('hidden');

    body.classList.toggle('hidden', open);
    if (toggle) toggle.textContent = open ? '+' : '−';
  });
});

// Auto-open units that have activity
document.querySelectorAll('.unit-item.is-open').forEach(item => {
  const body   = item.querySelector('.unit-body');
  const toggle = item.querySelector('.unit-toggle');
  if (body)   body.classList.remove('hidden');
  if (toggle) toggle.textContent = '−';
});
// Also open the first unit if nothing is open
const allUnits  = document.querySelectorAll('.unit-item');
const anyOpen   = Array.from(allUnits).some(u => !u.querySelector('.unit-body')?.classList.contains('hidden'));
if (!anyOpen && allUnits.length) {
  const first  = allUnits[0];
  const body   = first.querySelector('.unit-body');
  const toggle = first.querySelector('.unit-toggle');
  if (body)   body.classList.remove('hidden');
  if (toggle) toggle.textContent = '−';
}
