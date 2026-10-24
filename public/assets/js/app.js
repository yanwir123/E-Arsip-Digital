// Interaksi UI E-Arsip. Semua berbasis event listener (kompatibel CSP:
// script-src 'self', tanpa script inline).
(function () {
    'use strict';

    var reduceMotion = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    // ---------------------------------------------------------------------
    // Flash message: bisa ditutup klik, auto-hilang.
    // ---------------------------------------------------------------------
    function dismiss(el) {
        el.style.transition = 'opacity .35s ease, transform .35s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateY(-8px)';
        setTimeout(function () { el.remove(); }, 350);
    }

    document.querySelectorAll('.alert').forEach(function (el) {
        el.setAttribute('role', 'alert');
        el.addEventListener('click', function () { dismiss(el); });

        // Pesan berhasil/informasi boleh hilang otomatis; pesan kesalahan
        // dibiarkan tetap tampil agar pengguna sempat membacanya.
        var isError = el.classList.contains('alert-error');
        if (!reduceMotion && !isError) {
            setTimeout(function () { dismiss(el); }, 7000);
        }
    });

    // ---------------------------------------------------------------------
    // Menu navigasi mobile (hamburger).
    // ---------------------------------------------------------------------
    var navToggle = document.querySelector('[data-nav-toggle]');
    var nav = document.querySelector('[data-nav]');

    function setNav(open) {
        if (!navToggle || !nav) { return; }
        nav.classList.toggle('is-open', open);
        navToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    if (navToggle && nav) {
        navToggle.addEventListener('click', function (event) {
            event.stopPropagation();
            setNav(!nav.classList.contains('is-open'));
            closeMenu();
        });

        // Tutup saat salah satu link diklik.
        nav.addEventListener('click', function (event) {
            if (event.target.closest('a')) { setNav(false); }
        });

        // Tutup saat klik di luar nav.
        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-nav]') && !event.target.closest('[data-nav-toggle]')) {
                setNav(false);
            }
        });

        // Tutup dengan tombol Escape.
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') { setNav(false); }
        });

        // Kembali ke tampilan desktop saat lebar layar melewati breakpoint.
        var mqDesktop = window.matchMedia('(min-width: 1440px)');
        var onBreakpoint = function () { if (mqDesktop.matches) { setNav(false); } };
        if (mqDesktop.addEventListener) {
            mqDesktop.addEventListener('change', onBreakpoint);
        } else if (mqDesktop.addListener) {
            mqDesktop.addListener(onBreakpoint);
        }
    }

    // ---------------------------------------------------------------------
    // Dropdown pengguna.
    // ---------------------------------------------------------------------
    var menuBtn = document.querySelector('[data-menu-toggle]');
    var menu = document.querySelector('[data-menu]');

    function closeMenu() {
        if (menu) { menu.classList.remove('is-open'); }
        if (menuBtn) { menuBtn.setAttribute('aria-expanded', 'false'); }
    }

    if (menuBtn && menu) {
        menuBtn.addEventListener('click', function (event) {
            event.stopPropagation();
            var open = menu.classList.toggle('is-open');
            menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('.userbox')) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });
    }

    // ---------------------------------------------------------------------
    // Sorot menu aktif sesuai URL saat ini.
    // ---------------------------------------------------------------------
    var path = (location.pathname || '/').replace(/\/+$/, '') || '/';
    var best = null;
    var bestLen = -1;

    document.querySelectorAll('.nav-link').forEach(function (a) {
        var p;
        try {
            p = new URL(a.href, location.href).pathname.replace(/\/+$/, '') || '/';
        } catch (e) {
            return;
        }
        if (p === '/') { return; }
        if (path === p || path.indexOf(p + '/') === 0) {
            if (p.length > bestLen) { best = a; bestLen = p.length; }
        }
    });

    if (best) { best.classList.add('is-active'); }

    // ---------------------------------------------------------------------
    // Tombol lihat/sembunyikan password.
    // ---------------------------------------------------------------------
    document.querySelectorAll('input[type="password"]').forEach(function (input) {
        if (input.dataset.pwReady) { return; }
        input.dataset.pwReady = '1';

        var wrap = document.createElement('span');
        wrap.className = 'pw-wrap';
        input.parentNode.insertBefore(wrap, input);
        wrap.appendChild(input);

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'pw-toggle';
        btn.setAttribute('aria-label', 'Tampilkan password');
        btn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12z"/><circle cx="12" cy="12" r="3"/></svg>';
        btn.addEventListener('click', function () {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            btn.setAttribute('aria-label', show ? 'Sembunyikan password' : 'Tampilkan password');
            btn.classList.toggle('is-visible', show);
        });

        wrap.appendChild(btn);
    });

    // ---------------------------------------------------------------------
    // Nama file yang dipilih pada input file.
    // ---------------------------------------------------------------------
    document.querySelectorAll('input[type="file"]').forEach(function (input) {
        var out = document.createElement('span');
        out.className = 'file-chosen';
        input.parentNode.insertBefore(out, input.nextSibling);
        input.addEventListener('change', function () {
            out.textContent = (input.files && input.files.length) ? input.files[0].name : '';
        });
    });

    // ---------------------------------------------------------------------
    // Animasi angka statistik (count-up).
    // ---------------------------------------------------------------------
    function animateNumber(el) {
        if (reduceMotion) { return; }

        var target = parseInt((el.textContent || '').replace(/[^\d-]/g, ''), 10);
        if (isNaN(target) || target === 0) { return; }

        var start = null;
        var duration = 900;
        el.textContent = '0';

        function step(ts) {
            if (start === null) { start = ts; }
            var progress = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.round(eased * target).toLocaleString('id-ID');
            if (progress < 1) {
                requestAnimationFrame(step);
            } else {
                el.textContent = target.toLocaleString('id-ID');
            }
        }

        requestAnimationFrame(step);
    }

    var stats = document.querySelectorAll('.stat-value');
    if ('IntersectionObserver' in window) {
        var statObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    animateNumber(entry.target);
                    statObserver.unobserve(entry.target);
                }
            });
        }, { threshold: .4 });
        stats.forEach(function (el) { statObserver.observe(el); });
    } else {
        stats.forEach(animateNumber);
    }

    // ---------------------------------------------------------------------
    // Reveal saat scroll.
    // ---------------------------------------------------------------------
    var reveals = document.querySelectorAll('.reveal');
    if ('IntersectionObserver' in window && reveals.length) {
        var revObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    revObserver.unobserve(entry.target);
                }
            });
        }, { threshold: .12 });
        reveals.forEach(function (el) { revObserver.observe(el); });
    } else {
        reveals.forEach(function (el) { el.classList.add('is-visible'); });
    }

    // ---------------------------------------------------------------------
    // Bayangan topbar saat digulir.
    // ---------------------------------------------------------------------
    var topbar = document.querySelector('.topbar');
    if (topbar) {
        var onScroll = function () {
            topbar.classList.toggle('is-scrolled', window.scrollY > 8);
        };
        window.addEventListener('scroll', onScroll, { passive: true });
        onScroll();
    }

    // ---------------------------------------------------------------------
    // Konfirmasi sebelum submit (form[data-confirm]).
    // ---------------------------------------------------------------------
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!window.confirm(form.getAttribute('data-confirm'))) {
                event.preventDefault();
            }
        });
    });
})();
