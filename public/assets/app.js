document.querySelector('#search')?.addEventListener('input', e => {
  const term = e.target.value.toLocaleLowerCase('es');
  document.querySelectorAll('#records tbody tr').forEach(row => { row.hidden = !row.textContent.toLocaleLowerCase('es').includes(term); });
});
document.querySelectorAll('.delete-form').forEach(form => form.addEventListener('submit', e => {
  const message = form.dataset.confirm || '¿Eliminar el proyecto y sus participaciones? Se conservarán los datos de los estudiantes y sus vínculos con otros proyectos.';
  if (!confirm(message)) e.preventDefault();
}));

// Los errores se muestran en el formulario actual: no se reemplaza ni se vacía su contenido.
document.querySelectorAll('form[method="post"]').forEach((form, index) => {
  form.querySelectorAll('input, select').forEach(input => {
    input.addEventListener('input', () => {
      input.setCustomValidity('');
      input.removeAttribute('aria-invalid');
      input.removeAttribute('aria-describedby');
      input.closest('label')?.querySelector('.field-error')?.remove();
    });
    input.addEventListener('invalid', () => {
      const name = input.closest('label')?.firstChild?.textContent.trim() || 'Este campo';
      if (input.validity.valueMissing) input.setCustomValidity(`${name}: completa este campo.`);
      else if (input.validity.typeMismatch) input.setCustomValidity(`${name}: ingresa un correo válido.`);
      else if (input.validity.rangeUnderflow) input.setCustomValidity(`${name}: el valor mínimo es ${input.min}.`);
      else if (input.validity.badInput) input.setCustomValidity(`${name}: ingresa un valor válido.`);
    });
  });
  form.addEventListener('submit', async event => {
    if (event.defaultPrevented) return; // Se canceló una confirmación de eliminación.
    event.preventDefault();
    if (form.dataset.sending === 'true') return;
    form.querySelectorAll('.form-feedback, .field-error').forEach(node => node.remove());
    form.querySelectorAll('[aria-invalid]').forEach(input => {
      input.removeAttribute('aria-invalid');
      input.removeAttribute('aria-describedby');
    });
    document.querySelectorAll('.workspace > p[role="alert"], .login-form > p.error').forEach(node => node.remove());
    const data = new FormData(form);
    if (event.submitter?.name) data.append(event.submitter.name, event.submitter.value);
    const buttons = [...form.querySelectorAll('button, input[type="submit"]')];
    form.dataset.sending = 'true';
    buttons.forEach(button => { button.disabled = true; });
    function showError(message, field) {
      const alert = document.createElement('p');
      alert.className = 'error form-feedback';
      alert.setAttribute('role', 'alert');
      alert.textContent = message;
      form.prepend(alert);
      const input = field ? form.elements.namedItem(field) : null;
      if (input?.closest) {
        const detail = document.createElement('small');
        detail.className = 'field-error';
        detail.id = `form-${index}-${field}-error`;
        detail.textContent = message;
        input.closest('label')?.append(detail);
        input.setAttribute('aria-invalid', 'true');
        input.setAttribute('aria-describedby', detail.id);
        input.focus({ preventScroll: true });
      }
      alert.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
    try {
      const response = await fetch(form.getAttribute('action') || window.location.href, {
        method: 'POST', body: data, credentials: 'same-origin', redirect: 'error',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
      });
      const result = await response.json();
      if (response.ok && result.ok && result.redirect) {
        window.location.assign(result.redirect);
        return;
      }
      showError(result.message || 'No se pudo guardar. Revisa el formulario.', result.field);
    } catch {
      showError('No se pudo confirmar la respuesta del servidor. Tus campos se conservaron.', '');
    } finally {
      form.dataset.sending = 'false';
      buttons.forEach(button => { button.disabled = false; });
    }
  });
});
