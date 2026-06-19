const header = document.querySelector('#site-header');
if (header) window.addEventListener('scroll', () => header.classList.toggle('scrolled', window.scrollY > 40), { passive: true });

const navToggle = document.querySelector('.nav-toggle');
const nav = document.querySelector('.main-nav');
navToggle?.addEventListener('click', () => {
  const open = nav.classList.toggle('open');
  navToggle.setAttribute('aria-expanded', String(open));
});

const observer = new IntersectionObserver(entries => entries.forEach(entry => {
  if (entry.isIntersecting) { entry.target.classList.add('visible'); observer.unobserve(entry.target); }
}), { threshold: 0.08 });
document.querySelectorAll('.reveal').forEach(element => observer.observe(element));

const search = document.querySelector('#character-search');
search?.addEventListener('input', () => {
  const query = search.value.toLowerCase();
  document.querySelectorAll('#character-grid .character-card').forEach(card => {
    card.hidden = !card.textContent.toLowerCase().includes(query);
  });
});

document.querySelector('[data-toggle-password]')?.addEventListener('click', event => {
  const input = document.querySelector('#password');
  const showing = input.type === 'text';
  input.type = showing ? 'password' : 'text';
  event.currentTarget.textContent = showing ? 'Show' : 'Hide';
});

document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => {
  if (!window.confirm(form.dataset.confirm)) event.preventDefault();
}));

const imageInput = document.querySelector('input[name="image_url"]');
imageInput?.addEventListener('input', () => {
  const preview = document.querySelector('#image-preview');
  if (!preview) return;
  const htmlMatch = imageInput.value.match(/<img\b[^>]*\bsrc\s*=\s*["']([^"']+)["']/i);
  preview.src = htmlMatch ? htmlMatch[1] : imageInput.value;
});

document.querySelector('[data-image-file]')?.addEventListener('change', event => {
  const file = event.currentTarget.files?.[0];
  const preview = document.querySelector('#image-preview');
  if (file && preview) preview.src = URL.createObjectURL(file);
});

const homeBackgroundInput = document.querySelector('[data-home-background-input]');
homeBackgroundInput?.addEventListener('input', () => {
  const preview = document.querySelector('[data-home-background-preview]');
  if (preview) preview.src = homeBackgroundInput.value || '/assets/images/hero-original.svg';
});

document.querySelector('.admin-menu')?.addEventListener('click', () => document.querySelector('.admin-sidebar')?.classList.toggle('open'));

document.querySelectorAll('[data-share="copy"]').forEach(button => button.addEventListener('click', async event => {
  await navigator.clipboard.writeText(location.href);
  const original = event.currentTarget.textContent;
  event.currentTarget.textContent = '✓ Copied';
  setTimeout(() => { event.currentTarget.textContent = original; }, 1400);
}));
