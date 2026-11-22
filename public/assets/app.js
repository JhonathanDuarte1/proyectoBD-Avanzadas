document.querySelector('#search')?.addEventListener('input', e => {
  const term = e.target.value.toLocaleLowerCase('es');
  document.querySelectorAll('#records tbody tr').forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase('es').includes(term); });
});
document.querySelectorAll('.delete-form').forEach(form => form.addEventListener('submit', e => {
  const message = form.dataset.confirm || '¿Eliminar el proyecto y sus participaciones? Se conservarán los datos de los estudiantes y sus vínculos con otros proyectos.';
  if (!confirm(message)) e.preventDefault();
}));
