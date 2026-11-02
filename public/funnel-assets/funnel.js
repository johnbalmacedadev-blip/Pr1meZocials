/*
 * Behaviour for funnel pages: countdowns, popups, videos, the Philippine address
 * dropdowns and sending the order form to our own order endpoint.
 */
(function () {
    'use strict';

    var config = window.FUNNEL || { events: {}, countdowns: {} };
    var ADDRESS_COUNTRY = 63;

    function byId(id) {
        return document.getElementById('w-' + id);
    }

    /* Images load as they come near the screen. */
    function revealLazy() {
        var bottom = window.innerHeight * 2;
        document.querySelectorAll('.lazy').forEach(function (element) {
            var box = element.getBoundingClientRect();
            if (!box.width && !box.height) {
                return;
            }
            if (box.top < bottom && box.bottom > -window.innerHeight) {
                element.classList.remove('lazy');
            }
        });
    }

    function revealLazyIn(root) {
        root.querySelectorAll('.lazy').forEach(function (element) {
            element.classList.remove('lazy');
        });
    }

    /* Button and image animations start when the element scrolls into view. */
    function watchAnimations() {
        var elements = document.querySelectorAll('.is-animation');
        if (!('IntersectionObserver' in window)) {
            elements.forEach(function (element) {
                element.classList.add('animation');
                element.classList.remove('is-animation');
            });
            return;
        }
        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    observer.unobserve(entry.target);
                    entry.target.classList.add('animation');
                    entry.target.classList.remove('is-animation');
                }
            });
        });
        elements.forEach(function (element) {
            observer.observe(element);
        });
    }

    /* Each visitor gets their own timer, remembered between visits. */
    function storedStart(id) {
        var key = 'funnel-countdown-' + id;
        try {
            var saved = parseInt(window.localStorage.getItem(key), 10);
            if (saved) {
                return saved;
            }
            window.localStorage.setItem(key, String(Date.now()));
        } catch (error) {
            /* Private browsing: the timer simply restarts on reload. */
        }
        return Date.now();
    }

    function startCountdown(id, settings) {
        var element = byId(id);
        if (!element) {
            return;
        }
        var total = settings.minutes * 60;
        var started = storedStart(id);
        var parts = ['day', 'hour', 'minute', 'second'].map(function (name) {
            return element.querySelector('.countdown-item-' + name + ' > div:first-child');
        });
        function pad(value) {
            return (value < 10 ? '0' : '') + value;
        }
        function tick() {
            var elapsed = Math.floor((Date.now() - started) / 1000);
            var left = total - elapsed;
            if (left < 0) {
                left = settings.repeat ? total - (elapsed % total) : 0;
            }
            var values = [
                Math.floor(left / 86400),
                Math.floor((left % 86400) / 3600),
                Math.floor((left % 3600) / 60),
                left % 60,
            ];
            parts.forEach(function (part, index) {
                if (part) {
                    part.textContent = pad(values[index]);
                }
            });
        }
        tick();
        window.setInterval(tick, 1000);
    }

    function showTodayDates() {
        var today = new Date().toLocaleDateString('en-US', {
            weekday: 'long', year: 'numeric', month: 'long', day: 'numeric',
        });
        document.querySelectorAll('.timegroup-wrapper').forEach(function (element) {
            element.textContent = today;
        });
    }

    /* Popups */
    var backdrop = document.getElementById('popup-backdrop');
    var openPopups = [];

    function openPopup(id) {
        var popup = byId(id);
        if (!popup) {
            return;
        }
        openPopups = openPopups.filter(function (openId) { return openId !== id; });
        openPopups.push(id);
        popup.classList.remove('d-none');
        popup.style.zIndex = '1000002';
        popup.style.overflow = 'hidden auto';
        popup.style.maxHeight = 'calc(var(--viewport-height, 100vh) * 0.9)';
        revealLazyIn(popup);
        if (backdrop) {
            backdrop.classList.remove('d-none');
            backdrop.style.zIndex = '1000001';
        }
    }

    function closePopup(id) {
        var popup = byId(id);
        if (popup) {
            popup.classList.add('d-none');
        }
        openPopups = openPopups.filter(function (openId) { return openId !== id; });
        if (!openPopups.length && backdrop) {
            backdrop.classList.add('d-none');
        }
    }

    function scrollToElement(id) {
        var element = byId(id);
        if (element) {
            window.scrollTo({ top: element.getBoundingClientRect().top + window.scrollY, behavior: 'smooth' });
        }
    }

    function bindClickEvents() {
        Object.keys(config.events || {}).forEach(function (id) {
            var element = byId(id);
            if (!element) {
                return;
            }
            element.classList.add('cursor-pointer');
            element.addEventListener('click', function () {
                config.events[id].forEach(function (event) {
                    if (event.action === 'scroll_to') {
                        scrollToElement(event.target);
                    } else if (event.action === 'open_popup') {
                        openPopup(event.target);
                    } else if (event.action === 'close_popup') {
                        closePopup(event.target);
                    }
                });
            });
        });
        if (backdrop) {
            backdrop.addEventListener('click', function () {
                if (openPopups.length) {
                    closePopup(openPopups[openPopups.length - 1]);
                }
            });
        }
    }

    /* Videos: the first plays muted when it scrolls into view, the rest on tap. Only one plays at a time. */
    function setupVideos() {
        var videos = Array.prototype.slice.call(document.querySelectorAll('video[data-video]'));

        function hideCover(video) {
            var wrapper = video.parentElement;
            ['.video-background', '.icon-play-video'].forEach(function (selector) {
                var cover = wrapper.querySelector(selector);
                if (cover) {
                    cover.remove();
                }
            });
        }

        function load(video) {
            if (!video.getAttribute('src')) {
                video.src = video.getAttribute('data-video');
            }
            video.loop = video.getAttribute('data-loop') === '1';
            hideCover(video);
        }

        videos.forEach(function (video) {
            video.addEventListener('play', function () {
                if (!video.muted) {
                    videos.forEach(function (other) {
                        if (other !== video) {
                            other.pause();
                        }
                    });
                }
            });

            if (video.getAttribute('data-autoplay') === '1') {
                video.muted = true;
                video.setAttribute('muted', '');
                var button = document.createElement('div');
                button.className = 'player-btn-unmute';
                button.innerHTML = '<div></div>';
                button.addEventListener('click', function () {
                    video.muted = false;
                    if (video.getAttribute('data-controls') === '1') {
                        video.controls = true;
                    }
                    button.remove();
                    video.play().catch(function () {});
                });

                var start = function () {
                    load(video);
                    if (!button.parentElement) {
                        video.insertAdjacentElement('afterend', button);
                    }
                    video.play().catch(function () {
                        video.controls = true;
                    });
                };
                if ('IntersectionObserver' in window) {
                    var observer = new IntersectionObserver(function (entries) {
                        if (entries[0].isIntersecting) {
                            observer.disconnect();
                            start();
                        }
                    }, { rootMargin: '200px' });
                    observer.observe(video);
                } else {
                    start();
                }
                return;
            }

            var play = function () {
                load(video);
                if (video.getAttribute('data-controls') === '1') {
                    video.controls = true;
                }
                video.play().catch(function () {
                    video.controls = true;
                });
            };
            var wrapper = video.parentElement;
            wrapper.querySelectorAll('.video-background, .icon-play-video').forEach(function (cover) {
                cover.addEventListener('click', play);
            });
        });
    }

    /* Province → City → Barangay */
    function childrenOf(node) {
        return Object.keys(node || {}).filter(function (key) {
            return key.indexOf(ADDRESS_COUNTRY + '_') === 0;
        }).map(function (key) {
            return { id: key, name: node[key].name_en || node[key].name, node: node[key] };
        });
    }

    function fill(select, items) {
        var placeholder = select.options[0];
        select.innerHTML = '';
        select.appendChild(placeholder);
        select.value = '';
        items.forEach(function (item) {
            var option = document.createElement('option');
            option.value = item.id;
            option.textContent = item.name;
            select.appendChild(option);
        });
    }

    function setupAddress(form) {
        var province = form.querySelector('select[name="province_id"]');
        var city = form.querySelector('select[name="district_id"]');
        var barangay = form.querySelector('select[name="commune_id"]');
        if (!province) {
            return;
        }
        province.required = true;
        city.required = true;
        barangay.required = true;

        var data = function () {
            return (window.WebcakeAddresses && window.WebcakeAddresses[ADDRESS_COUNTRY]) || null;
        };
        var loadProvinces = function () {
            if (!data()) {
                window.setTimeout(loadProvinces, 200);
                return;
            }
            fill(province, childrenOf(data()));
        };
        loadProvinces();

        province.addEventListener('change', function () {
            var node = data()[province.value];
            fill(city, childrenOf(node));
            fill(barangay, []);
            barangay.required = true;
        });
        city.addEventListener('change', function () {
            var node = data()[province.value][city.value];
            var items = childrenOf(node);
            fill(barangay, items);
            barangay.required = items.length > 0;
        });
    }

    function selectedText(select) {
        return select && select.value ? select.options[select.selectedIndex].textContent : '';
    }

    /* Short message at the top of the screen. */
    function alertMessage(text) {
        var box = document.getElementById('webcake-alert-msg');
        if (!box) {
            window.alert(text);
            return;
        }
        box.style.setProperty('--color-alert', '#d0342c');
        box.textContent = text;
        box.style.display = 'flex';
        window.clearTimeout(alertMessage.timer);
        alertMessage.timer = window.setTimeout(function () {
            box.style.display = 'none';
        }, 6000);
    }

    function trackingParams() {
        var keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'fbclid', 'gclid', 'ttclid'];
        var params = new URLSearchParams(window.location.search);
        var source = {};
        keys.forEach(function (key) {
            if (params.get(key)) {
                source[key] = params.get(key);
            }
        });
        if (document.referrer) {
            source.referrer = document.referrer;
        }
        return source;
    }

    function setupOrderForm(form) {
        setupAddress(form);
        var popup = form.closest('.com-popup');
        var submitting = false;
        var trigger = null;

        form.querySelectorAll('.com-button .button-css').forEach(function (button) {
            if (button.querySelector('button[type="submit"]')) {
                trigger = button;
                button.classList.add('cursor-pointer');
                button.addEventListener('click', function (event) {
                    if (event.target.tagName !== 'BUTTON') {
                        form.requestSubmit();
                    }
                });
            }
        });

        function setBusy(busy) {
            submitting = busy;
            if (!trigger) {
                return;
            }
            var loader = trigger.querySelector('.button-loader');
            var label = trigger.querySelector('.button-text');
            if (loader) {
                loader.style.display = busy ? 'block' : 'none';
            }
            if (label) {
                label.style.visibility = busy ? 'hidden' : 'visible';
            }
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();
            if (submitting) {
                return;
            }
            setBusy(true);

            var field = function (name) {
                var input = form.querySelector('[name="' + name + '"]');
                return input ? input.value.trim() : '';
            };

            fetch(config.orderUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    full_name: field('full_name'),
                    phone_number: field('phone_number'),
                    address: field('address'),
                    province: selectedText(form.querySelector('select[name="province_id"]')),
                    city: selectedText(form.querySelector('select[name="district_id"]')),
                    barangay: selectedText(form.querySelector('select[name="commune_id"]')),
                    quantity: field('quantity'),
                    website: field('website'),
                    source: trackingParams(),
                }),
            }).then(function (response) {
                return response.json().catch(function () { return {}; }).then(function (body) {
                    return { status: response.status, body: body };
                });
            }).then(function (result) {
                if (result.status >= 200 && result.status < 300) {
                    form.reset();
                    fill(form.querySelector('select[name="district_id"]'), []);
                    fill(form.querySelector('select[name="commune_id"]'), []);
                    if (popup) {
                        closePopup(popup.id.replace('w-', ''));
                    }
                    openPopup('__popup_default__');
                    return;
                }
                if (result.status === 422 && result.body.errors) {
                    var first = Object.keys(result.body.errors)[0];
                    alertMessage(result.body.errors[first][0]);
                    return;
                }
                if (result.status === 429) {
                    alertMessage('Too many attempts. Please wait a minute and try again.');
                    return;
                }
                alertMessage('Your order could not be sent. Please try again.');
            }).catch(function () {
                alertMessage('Your order could not be sent. Please check your connection and try again.');
            }).finally(function () {
                setBusy(false);
            });
        });
    }

    function setViewportHeight() {
        document.documentElement.style.setProperty('--viewport-height', window.innerHeight + 'px');
    }

    setViewportHeight();
    window.addEventListener('resize', setViewportHeight);
    window.addEventListener('scroll', revealLazy, { passive: true });
    revealLazy();
    watchAnimations();
    showTodayDates();
    Object.keys(config.countdowns || {}).forEach(function (id) {
        startCountdown(id, config.countdowns[id]);
    });
    bindClickEvents();
    setupVideos();
    document.querySelectorAll('form[data-funnel-order]').forEach(setupOrderForm);
})();
