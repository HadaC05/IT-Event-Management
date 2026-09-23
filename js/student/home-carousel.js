(() => {
  'use strict';

  const carousel = document.querySelector('[data-student-home-carousel]');
  if (!carousel) return;

  const slidesHost = carousel.querySelector('[data-home-carousel-slides]');
  const intro = carousel.querySelector('[data-home-intro]');
  const controls = carousel.querySelector('[data-home-carousel-controls]');
  const dotsHost = carousel.querySelector('[data-home-carousel-dots]');
  const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  let slides = [intro];
  let dots = [];
  let current = 0;
  let timer = null;

  const dateLabel = value => {
    if (!value) return 'Date to be announced';
    const date = new Date(String(value).replace(' ', 'T'));
    return Number.isNaN(date.getTime())
      ? 'Date to be announced'
      : new Intl.DateTimeFormat('en-PH', {month: 'long', day: 'numeric', year: 'numeric'}).format(date);
  };

  const stop = () => { if (timer) clearInterval(timer); timer = null; };
  const show = index => {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, position) => {
      const active = position === current;
      slide.classList.toggle('is-active', active);
      slide.setAttribute('aria-hidden', active ? 'false' : 'true');
      slide.inert = !active;
    });
    dots.forEach((dot, position) => dot.setAttribute('aria-current', position === current ? 'true' : 'false'));
  };
  const start = () => {
    stop();
    if (slides.length > 1 && !reducedMotion.matches && !document.hidden && !carousel.matches(':hover') && !carousel.contains(document.activeElement)) {
      timer = setInterval(() => show(current + 1), 6500);
    }
  };

  const eventSlide = (event, number, total) => {
    const slide = document.createElement('article');
    slide.className = 'student-stage student-home-slide student-home-event';
    slide.dataset.homeSlide = '';
    slide.setAttribute('aria-roledescription', 'slide');
    slide.setAttribute('aria-label', `Featured event ${number} of ${total}: ${event.title || 'Campus event'}`);
    slide.setAttribute('aria-hidden', 'true');
    slide.inert = true;
    const poster = document.createElement('img');
    poster.className = 'student-home-event__poster';
    poster.src = event.poster_path;
    poster.alt = '';
    poster.loading = number === 1 ? 'eager' : 'lazy';
    slide.append(poster);
    const content = document.createElement('div');
    content.className = 'student-home-event__content';
    const eyebrow = document.createElement('p');
    eyebrow.className = 'student-stage__eyebrow';
    eyebrow.textContent = event.is_featured ? 'Featured campus event' : 'From the campus calendar';
    const title = document.createElement('h2');
    title.className = 'student-stage__title';
    title.textContent = event.title || 'Campus event';
    const description = document.createElement('p');
    description.className = 'student-stage__copy';
    description.textContent = event.description || 'A new campus moment is on the way.';
    const details = document.createElement('p');
    details.className = 'student-home-event__details';
    details.textContent = [dateLabel(event.start_at), event.location].filter(Boolean).join(' · ');
    const actions = document.createElement('div');
    actions.className = 'student-stage__actions';
    const link = document.createElement('a');
    link.className = 'student-stage__action student-stage__action--primary';
    link.href = 'pages/student/events.html';
    link.textContent = 'Explore events →';
    actions.append(link);
    content.append(eyebrow, title, description, details, actions);
    slide.append(content);
    return slide;
  };

  const render = events => {
    stop();
    slidesHost.querySelectorAll('[data-home-slide]:not([data-home-intro])').forEach(slide => slide.remove());
    const featured = Array.isArray(events)
      ? events.filter(event => event.poster_path).slice(0, 6)
      : [];
    intro.hidden = featured.length > 0;
    intro.inert = intro.hidden;
    intro.classList.toggle('is-active', !intro.hidden);
    intro.setAttribute('aria-hidden', intro.hidden ? 'true' : 'false');
    featured.forEach((event, index) => slidesHost.append(eventSlide(event, index + 1, featured.length)));
    slides = intro.hidden ? [...slidesHost.querySelectorAll('.student-home-event')] : [intro];
    intro.setAttribute('aria-label', 'No event photos available');
    controls.hidden = slides.length < 2;
    dotsHost.replaceChildren();
    dots = slides.map((slide, index) => {
      const dot = document.createElement('button');
      dot.type = 'button';
      dot.setAttribute('aria-label', featured.length ? `Show ${featured[index].title || 'campus event'}` : 'Show event photo placeholder');
      dot.addEventListener('click', () => { show(index); start(); });
      dotsHost.append(dot);
      return dot;
    });
    show(0);
    start();
  };

  carousel.querySelector('[data-home-carousel-prev]').addEventListener('click', () => { show(current - 1); start(); });
  carousel.querySelector('[data-home-carousel-next]').addEventListener('click', () => { show(current + 1); start(); });
  carousel.addEventListener('mouseenter', stop);
  carousel.addEventListener('mouseleave', start);
  carousel.addEventListener('focusin', stop);
  carousel.addEventListener('focusout', () => setTimeout(start, 0));
  document.addEventListener('visibilitychange', start);
  reducedMotion.addEventListener('change', start);
  window.StudentHomeCarousel = {render};
})();
