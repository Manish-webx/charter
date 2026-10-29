/* Worldwide Charters — shared site behaviour (header, mobile menu, services nav). */
(function () {
  'use strict';

  // Header shadow once the page has scrolled.
  var header = document.querySelector('.site-header');
  if (header) {
    var onScroll = function () {
      header.classList.toggle('is-scrolled', window.scrollY > 8);
    };
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  // Mobile menu: keep the toggle label accurate, close on link selection and on Escape.
  var menu = document.getElementById('site-nav');
  var toggler = document.querySelector('.navbar-toggler');
  if (menu && toggler && window.bootstrap) {
    var collapse = window.bootstrap.Collapse.getOrCreateInstance(menu, { toggle: false });
    menu.addEventListener('shown.bs.collapse', function () {
      toggler.setAttribute('aria-label', 'Close main menu');
    });
    menu.addEventListener('hidden.bs.collapse', function () {
      toggler.setAttribute('aria-label', 'Open main menu');
    });
    menu.querySelectorAll('a').forEach(function (link) {
      link.addEventListener('click', function () {
        if (menu.classList.contains('show')) collapse.hide();
      });
    });
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && menu.classList.contains('show')) {
        collapse.hide();
        toggler.focus();
      }
    });
  }

  // Services page: highlight the in-page service link for the section in view.
  var serviceLinks = document.querySelectorAll('.service-nav .nav-link');
  if (serviceLinks.length && 'IntersectionObserver' in window) {
    var byId = {};
    serviceLinks.forEach(function (link) {
      byId[link.getAttribute('href').slice(1)] = link;
    });
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (!entry.isIntersecting) return;
        serviceLinks.forEach(function (l) { l.classList.remove('active'); l.removeAttribute('aria-current'); });
        var active = byId[entry.target.id];
        if (active) {
          active.classList.add('active');
          active.setAttribute('aria-current', 'location');
          var strip = active.closest('.nav');
          if (strip) strip.scrollLeft = active.offsetLeft - strip.offsetLeft - 16;
        }
      });
    }, { rootMargin: '-45% 0px -50% 0px' });
    Object.keys(byId).forEach(function (id) {
      var section = document.getElementById(id);
      if (section) observer.observe(section);
    });
  }

  // Current year in the footer.
  document.querySelectorAll('[data-year]').forEach(function (el) {
    el.textContent = String(new Date().getFullYear());
  });
})();
