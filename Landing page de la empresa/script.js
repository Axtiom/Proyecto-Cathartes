/* ============================================================
   Axtiom — Script compartido
   ============================================================ */

/* ---------- Menú hamburguesa ---------- */
(function () {
  const toggle = document.querySelector('.nav__toggle');
  const links  = document.querySelector('.nav__links');
  if (!toggle || !links) return;

  toggle.addEventListener('click', () => {
    const open = toggle.classList.toggle('open');
    links.classList.toggle('open', open);
    toggle.setAttribute('aria-expanded', open);
  });

  /* Cerrar el menú al hacer clic en un enlace (UX móvil) */
  links.querySelectorAll('.nav__link').forEach(link => {
    link.addEventListener('click', () => {
      toggle.classList.remove('open');
      links.classList.remove('open');
      toggle.setAttribute('aria-expanded', false);
    });
  });

  /* Cerrar al hacer clic fuera del menú */
  document.addEventListener('click', e => {
    if (!e.target.closest('.nav')) {
      toggle.classList.remove('open');
      links.classList.remove('open');
      toggle.setAttribute('aria-expanded', false);
    }
  });
})();

/* ---------- Aparición al hacer scroll ---------- */
(function () {
  const els = document.querySelectorAll('[data-reveal]');
  if (!els.length) return;

  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('revealed');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.12 });

  els.forEach(el => observer.observe(el));
})();

/* ---------- Formulario de contacto → mailto ---------- */
(function () {
  const form = document.getElementById('contact-form');
  if (!form) return;

  form.addEventListener('submit', e => {
    e.preventDefault();
    const nombre  = form.querySelector('#name')?.value    || '';
    const email   = form.querySelector('#email')?.value   || '';
    const asunto  = form.querySelector('#subject')?.value || 'Contacto desde el sitio web';
    const mensaje = form.querySelector('#message')?.value || '';

    const cuerpo = encodeURIComponent(
      `Nombre: ${nombre}\nEmail: ${email}\n\n${mensaje}`
    );
    const mailto = `mailto:[TODO: email@axtiom.com]?subject=${encodeURIComponent(asunto)}&body=${cuerpo}`;
    window.location.href = mailto;
  });
})();
