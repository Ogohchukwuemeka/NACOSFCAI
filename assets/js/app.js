(function () {
    const lucideToFaMap = {
        'alert-circle': 'fa-circle-exclamation',
        'archive': 'fa-box-archive',
        'arrow-left': 'fa-arrow-left',
        'arrow-right': 'fa-arrow-right',
        'award': 'fa-award',
        'badge-check': 'fa-shield-check',
        'bar-chart-2': 'fa-chart-simple',
        'bar-chart-3': 'fa-chart-simple',
        'bell': 'fa-bell',
        'bell-ring': 'fa-bell',
        'book': 'fa-book',
        'book-open': 'fa-book-open',
        'book-open-check': 'fa-book-open',
        'briefcase': 'fa-briefcase',
        'building-2': 'fa-building',
        'calendar': 'fa-calendar',
        'calendar-days': 'fa-calendar-days',
        'calendar-plus': 'fa-calendar-plus',
        'check': 'fa-check',
        'check-circle': 'fa-circle-check',
        'check-circle-2': 'fa-circle-check',
        'chevron-left': 'fa-chevron-left',
        'chevron-right': 'fa-chevron-right',
        'chevron-up': 'fa-chevron-up',
        'circle-dot': 'fa-dot-circle',
        'clock': 'fa-clock',
        'clock-3': 'fa-clock',
        'credit-card': 'fa-credit-card',
        'droplet': 'fa-droplet',
        'eye': 'fa-eye',
        'file-pdf': 'fa-file-pdf',
        'file-spreadsheet': 'fa-file-excel',
        'file-text': 'fa-file-lines',
        'flag': 'fa-flag',
        'folder-open': 'fa-folder-open',
        'graduation-cap': 'fa-graduation-cap',
        'heart-handshake': 'fa-handshake-angle',
        'help-circle': 'fa-circle-question',
        'home': 'fa-house-chimney',
        'id': 'fa-id-card',
        'image': 'fa-image',
        'image-plus': 'fa-file-image',
        'images': 'fa-images',
        'inbox': 'fa-inbox',
        'info': 'fa-circle-info',
        'layers': 'fa-layer-group',
        'layers-3': 'fa-layer-group',
        'loader': ['fa-spinner', 'fa-spin'],
        'loader-circle': ['fa-spinner', 'fa-spin'],
        'lock': 'fa-lock',
        'lock-keyhole': 'fa-lock',
        'log-in': 'fa-right-to-bracket',
        'log-out': 'fa-right-from-bracket',
        'mail': 'fa-envelope',
        'map-pin': 'fa-location-dot',
        'megaphone': 'fa-bullhorn',
        'menu': 'fa-bars',
        'message-square-more': 'fa-comments',
        'messages-square': 'fa-comments',
        'monitor-check': 'fa-desktop',
        'phone': 'fa-phone',
        'phone-forwarded': 'fa-phone-volume',
        'plus': 'fa-plus',
        'printer': 'fa-print',
        'refresh-cw': 'fa-arrows-rotate',
        'send': 'fa-paper-plane',
        'paperclip': 'fa-paperclip',
        'x': 'fa-xmark',
        'search': 'fa-magnifying-glass',
        'settings': 'fa-gear',
        'shield-check': 'fa-shield-check',
        'sparkles': 'fa-gem',
        'speaker': 'fa-bullhorn',
        'target': 'fa-bullseye',
        'timer': 'fa-stopwatch',
        'trending-up': 'fa-chart-line',
        'upload': 'fa-upload',
        'user': 'fa-user',
        'user-check': 'fa-user-check',
        'user-plus': 'fa-user-plus',
        'users': 'fa-users',
        'users-2': 'fa-users',
        'vote': 'fa-check-square',
        'wallet': 'fa-wallet',
        'eye-off': 'fa-eye-slash'
    };

    const getFaClassList = function (name) {
        const mapped = lucideToFaMap[name];
        if (!mapped) {
            return ['fa-solid', 'fa-circle-question'];
        }
        return Array.isArray(mapped) ? ['fa-solid', ...mapped] : ['fa-solid', mapped];
    };

    const createIcons = function (root = document) {
        const icons = root.querySelectorAll('i[data-lucide]');
        icons.forEach(function (icon) {
            const name = icon.dataset.lucide || '';
            const faClasses = getFaClassList(name);
            const removeClasses = Array.from(icon.classList).filter(function (cls) {
                return cls.startsWith('fa-');
            });
            if (removeClasses.length) {
                icon.classList.remove(...removeClasses);
            }
            icon.classList.add(...faClasses);
        });
    };

    window.faIconFactory = {
        render: createIcons
    };
})();

document.addEventListener('DOMContentLoaded', function () {
    if (window.AOS) {
        AOS.init({ duration: 700, once: true });
    }

    const idleTimeoutMs = Number(window.NACOS_IDLE_TIMEOUT_MS || 0);
    if (idleTimeoutMs > 0 && window.NACOS_TIMEOUT_URL) {
        const activityStorageKey = 'nacos_last_user_activity';
        let lastActivity = Number(window.NACOS_LAST_ACTIVITY_MS || Date.now());
        let lastPingAt = 0;
        let idleTimer = null;
        try {
            lastActivity = Math.max(lastActivity, Number(localStorage.getItem(activityStorageKey) || 0));
            localStorage.setItem(activityStorageKey, String(lastActivity));
        } catch (e) {
            // The server-side timeout remains authoritative if browser storage is unavailable.
        }

        const scheduleIdleLogout = function () {
            clearTimeout(idleTimer);
            const remaining = idleTimeoutMs - (Date.now() - lastActivity);
            if (remaining <= 0) {
                window.location.replace(window.NACOS_TIMEOUT_URL);
                return;
            }
            idleTimer = setTimeout(function () {
                window.location.replace(window.NACOS_TIMEOUT_URL);
            }, remaining);
        };

        const recordUserActivity = function () {
            const now = Date.now();
            if (now - lastActivity < 1000) {
                return;
            }
            lastActivity = now;
            try {
                localStorage.setItem(activityStorageKey, String(now));
            } catch (e) {
                // Keep the current-tab timer working without browser storage.
            }
            scheduleIdleLogout();
        };

        ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach(function (eventName) {
            window.addEventListener(eventName, recordUserActivity, { passive: true });
        });

        window.addEventListener('storage', function (event) {
            if (event.key === activityStorageKey) {
                const otherTabActivity = Number(event.newValue || 0);
                if (otherTabActivity > lastActivity) {
                    lastActivity = otherTabActivity;
                    scheduleIdleLogout();
                }
            }
        });

        setInterval(async function () {
            const now = Date.now();
            if (now - lastActivity >= 45000 || now - lastPingAt < 25000 || !window.NACOS_ACTIVITY_URL) {
                return;
            }
            lastPingAt = now;
            try {
                const response = await fetch(window.NACOS_ACTIVITY_URL, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' }
                });
                if (response.redirected) {
                    window.location.replace(response.url);
                } else if (response.status === 401 || response.status === 403) {
                    window.location.replace(window.NACOS_TIMEOUT_URL);
                }
            } catch (e) {
                // A temporary network error must not log out an otherwise active user.
            }
        }, 30000);

        scheduleIdleLogout();
    }

    const navbar = document.querySelector('.navbar-glass');
    if (navbar) {
        const toggleNavbar = () => {
            navbar.classList.toggle('scrolled', window.scrollY > 20);
        };
        toggleNavbar();
        window.addEventListener('scroll', toggleNavbar, { passive: true });
    }

    document.querySelectorAll('.mobile-sidebar-toggle').forEach(function (toggle) {
        toggle.addEventListener('click', function () {
            const sidebar = toggle.closest('.student-sidebar-card, .admin-sidebar-card');
            if (!sidebar) {
                return;
            }
            const isOpen = sidebar.classList.toggle('mobile-sidebar-open');
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        });
    });

    const gallerySlider = document.querySelector('.gallery-slider');
    const gallerySlides = document.querySelectorAll('.gallery-slide');
    const sliderDots = document.querySelectorAll('.slider-dot');
    const sliderControls = document.querySelectorAll('.slider-control');
    const galleryFilters = document.querySelectorAll('.gallery-filter-chip');
    const galleryCards = document.querySelectorAll('.gallery-card');
    const lightbox = document.getElementById('gallery-lightbox');
    const lightboxMedia = lightbox ? lightbox.querySelector('.gallery-lightbox-media') : null;
    const lightboxCaption = lightbox ? lightbox.querySelector('.gallery-lightbox-caption') : null;
    const lightboxPrev = lightbox ? lightbox.querySelector('.gallery-lightbox-nav.prev') : null;
    const lightboxNext = lightbox ? lightbox.querySelector('.gallery-lightbox-nav.next') : null;
    const lightboxClose = lightbox ? lightbox.querySelector('.gallery-lightbox-close') : null;
    let galleryIndex = 0;
    let galleryTimer = null;
    let visibleGalleryCards = [];
    let activeGalleryCard = null;

    if (lightbox) {
        lightbox.hidden = true;
    }

    const showGallerySlide = function (index) {
        if (!gallerySlides.length) {
            return;
        }
        galleryIndex = (index + gallerySlides.length) % gallerySlides.length;
        gallerySlides.forEach(function (slide, slideIndex) {
            slide.classList.toggle('active', slideIndex === galleryIndex);
        });
        sliderDots.forEach(function (dot, dotIndex) {
            dot.classList.toggle('active', dotIndex === galleryIndex);
        });
    };

    const startGalleryAutoplay = function () {
        if (!gallerySlider || !gallerySlides.length) {
            return;
        }
        clearInterval(galleryTimer);
        galleryTimer = setInterval(function () {
            showGallerySlide(galleryIndex + 1);
        }, 5000);
    };

    if (gallerySlider) {
        showGallerySlide(0);
        startGalleryAutoplay();
        gallerySlider.addEventListener('mouseenter', function () {
            clearInterval(galleryTimer);
        });
        gallerySlider.addEventListener('mouseleave', function () {
            startGalleryAutoplay();
        });
        sliderControls.forEach(function (control) {
            control.addEventListener('click', function (event) {
                event.stopPropagation();
                const direction = control.classList.contains('next') ? 1 : -1;
                showGallerySlide(galleryIndex + direction);
                startGalleryAutoplay();
            });
        });
        sliderDots.forEach(function (dot) {
            dot.addEventListener('click', function () {
                showGallerySlide(Number(dot.dataset.index || 0));
                startGalleryAutoplay();
            });
        });
    }

    const refreshVisibleGalleryCards = function () {
        visibleGalleryCards = Array.from(galleryCards).filter(function (card) {
            return !card.classList.contains('is-hidden');
        });
    };

    refreshVisibleGalleryCards();

    galleryFilters.forEach(function (filter) {
        filter.addEventListener('click', function () {
            const selectedCategory = filter.dataset.category || 'All';
            galleryFilters.forEach(function (chip) {
                chip.classList.toggle('active', chip === filter);
            });
            galleryCards.forEach(function (card) {
                const matches = selectedCategory === 'All' || card.dataset.category === selectedCategory;
                card.classList.toggle('is-hidden', !matches);
            });
            refreshVisibleGalleryCards();
            if (lightbox && !lightbox.hidden && activeGalleryCard && !activeGalleryCard.classList.contains('is-hidden')) {
                return;
            }
            if (lightbox && !lightbox.hidden) {
                closeGalleryLightbox();
            }
        });
    });

    const renderLightboxMedia = function (card) {
        if (!lightbox || !lightboxMedia || !lightboxCaption) {
            return;
        }
        const mediaType = card.dataset.mediaType || 'image';
        const mediaUrl = card.dataset.mediaUrl || '';
        const title = card.querySelector('h6')?.textContent || 'NACOS gallery';
        lightboxMedia.innerHTML = '';
        if (mediaType === 'video') {
            const video = document.createElement('video');
            video.src = mediaUrl;
            video.controls = true;
            video.autoplay = true;
            video.muted = true;
            video.playsInline = true;
            lightboxMedia.appendChild(video);
        } else {
            const image = document.createElement('img');
            image.src = mediaUrl;
            image.alt = title;
            lightboxMedia.appendChild(image);
        }
        lightboxCaption.textContent = title;
    };

    const openGalleryLightbox = function (card) {
        if (!lightbox || !lightboxMedia || !lightboxCaption) {
            return;
        }
        activeGalleryCard = card;
        renderLightboxMedia(card);
        lightbox.hidden = false;
        lightbox.style.display = 'flex';
        document.body.style.overflow = 'hidden';
    };

    const closeGalleryLightbox = function () {
        if (!lightbox) {
            return;
        }
        lightbox.hidden = true;
        lightbox.style.display = 'none';
        document.body.style.overflow = '';
        if (lightboxMedia) {
            lightboxMedia.innerHTML = '';
        }
    };

    galleryCards.forEach(function (card) {
        card.addEventListener('click', function (event) {
            if (event.target.closest('a')) {
                return;
            }
            openGalleryLightbox(card);
        });
    });

    const navigateGalleryLightbox = function (direction) {
        if (!visibleGalleryCards.length || !activeGalleryCard) {
            return;
        }
        const currentIndex = visibleGalleryCards.indexOf(activeGalleryCard);
        const nextIndex = (currentIndex + direction + visibleGalleryCards.length) % visibleGalleryCards.length;
        activeGalleryCard = visibleGalleryCards[nextIndex];
        renderLightboxMedia(activeGalleryCard);
    };

    if (lightboxPrev) {
        lightboxPrev.addEventListener('click', function () {
            navigateGalleryLightbox(-1);
        });
    }

    if (lightboxClose) {
        lightboxClose.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            closeGalleryLightbox();
        });
    }

    if (lightboxNext) {
        lightboxNext.addEventListener('click', function () {
            navigateGalleryLightbox(1);
        });
    }

    if (lightbox) {
        lightbox.addEventListener('click', function (event) {
            if (event.target.closest('.gallery-lightbox-close')) {
                event.preventDefault();
                closeGalleryLightbox();
                return;
            }
            if (event.target === lightbox) {
                closeGalleryLightbox();
            }
        });
    }

    document.addEventListener('keydown', function (event) {
        if (!lightbox || lightbox.hidden) {
            return;
        }
        if (event.key === 'Escape') {
            closeGalleryLightbox();
        } else if (event.key === 'ArrowRight') {
            navigateGalleryLightbox(1);
        } else if (event.key === 'ArrowLeft') {
            navigateGalleryLightbox(-1);
        }
    });

    const forms = document.querySelectorAll('form');
    forms.forEach(function (form) {
        form.addEventListener('submit', function () {
            form.querySelectorAll('button[type="submit"]').forEach(function (button) {
                button.disabled = true;
                button.innerHTML = '<span class="me-2">⏳</span> Processing...';
            });
        });
    });

    const counters = document.querySelectorAll('.counter');
    const countObserver = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = Number(el.dataset.target || 0);
                let current = 0;
                const step = Math.max(1, Math.ceil(target / 40));
                const timer = setInterval(function () {
                    current += step;
                    if (current >= target) {
                        current = target;
                        clearInterval(timer);
                    }
                    el.textContent = current.toString();
                }, 30);
                countObserver.unobserve(el);
            }
        });
    }, { threshold: 0.6 });
    counters.forEach(function (counter) {
        countObserver.observe(counter);
    });

    const faqItems = document.querySelectorAll('.faq-item');
    faqItems.forEach(function (item) {
        item.querySelector('.faq-question').addEventListener('click', function () {
            const openItem = item.classList.contains('active');
            faqItems.forEach(function (entry) {
                entry.classList.remove('active');
            });
            if (!openItem) {
                item.classList.add('active');
            }
        });
    });

    const eventCards = document.querySelectorAll('.event-card');
    eventCards.forEach(function (card) {
        const media = card.querySelector('.event-media');
        const images = card.dataset.images ? JSON.parse(card.dataset.images) : [];
        let index = 0;
        if (images.length > 1) {
            card.addEventListener('mouseenter', function () {
                const timer = setInterval(function () {
                    index = (index + 1) % images.length;
                    media.style.backgroundImage = 'url(' + images[index] + ')';
                }, 1200);
                card.dataset.hoverTimer = timer;
            });
            card.addEventListener('mouseleave', function () {
                if (card.dataset.hoverTimer) {
                    clearInterval(Number(card.dataset.hoverTimer));
                }
                media.style.backgroundImage = '';
            });
        }
    });

    const countdowns = document.querySelectorAll('[data-countdown]');
    countdowns.forEach(function (countdown) {
        const targetDate = new Date(countdown.dataset.countdown);
        if (Number.isNaN(targetDate.getTime())) {
            return;
        }
        const updateCountdown = function () {
            const now = new Date();
            const diff = targetDate - now;
            if (diff <= 0) {
                countdown.textContent = 'Starting now';
                return;
            }
            const days = Math.floor(diff / (1000 * 60 * 60 * 24));
            const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
            const minutes = Math.floor((diff / (1000 * 60)) % 60);
            const seconds = Math.floor((diff / 1000) % 60);
            countdown.innerHTML = '<i data-lucide="timer"></i> ' + days + 'd ' + hours + 'h ' + minutes + 'm ' + seconds + 's';
            if (window.faIconFactory) {
                window.faIconFactory.render();
            }
        };
        updateCountdown();
        setInterval(updateCountdown, 1000);
    });

    if (window.faIconFactory) {
        window.faIconFactory.render();
    }

    const backToTopButton = document.getElementById('backToTop');
    if (backToTopButton) {
        const toggleBackToTop = function () {
            if (window.scrollY > 300) {
                backToTopButton.classList.add('show');
            } else {
                backToTopButton.classList.remove('show');
            }
        };
        toggleBackToTop();
        window.addEventListener('scroll', toggleBackToTop, { passive: true });
        backToTopButton.addEventListener('click', function () {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    const newsletterForm = document.getElementById('newsletterForm');
    if (newsletterForm) {
        newsletterForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const emailInput = newsletterForm.querySelector('input[name="newsletter_email"]');
            if (!emailInput || !emailInput.value.trim()) {
                return;
            }
            Swal.fire({
                icon: 'success',
                title: 'Subscribed',
                text: 'You will receive announcements and event updates shortly.',
                timer: 2500,
                showConfirmButton: false,
                customClass: { popup: 'swal2-popup-glass' }
            });
            emailInput.value = '';
        });
    }

    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const assistantLauncher = document.getElementById('assistantLauncher');
    const assistantPanel = document.getElementById('assistantPanel');
    const assistantClose = document.getElementById('assistantClose');
    const assistantInput = document.getElementById('assistantInput');
    const assistantSend = document.getElementById('assistantSend');
    const assistantMessages = document.getElementById('assistantMessages');
    const assistantStatus = document.getElementById('assistantStatus');
    const assistantSuggestions = document.querySelectorAll('.assistant-suggestion');

    const updateThemeToggleLabel = function (isDark) {
        if (!themeToggleBtn) return;
        const icon = themeToggleBtn.querySelector('i');
        const text = themeToggleBtn.querySelector('span');
        if (icon) {
            icon.className = isDark ? 'fa-solid fa-sun' : 'fa-solid fa-moon';
        }
        if (text) {
            text.textContent = isDark ? 'Light Mode' : 'Dark Mode';
        }
    };

    const applyStudentTheme = function (mode) {
        const isDark = mode === 'dark';
        document.documentElement.classList.toggle('dark-mode', isDark);
        document.body.classList.toggle('dark-mode', isDark);
        updateThemeToggleLabel(isDark);
    };

    if (themeToggleBtn) {
        const storedTheme = localStorage.getItem('nacos_theme') || 'light';
        applyStudentTheme(storedTheme);
        themeToggleBtn.addEventListener('click', function () {
            const nextTheme = document.body.classList.contains('dark-mode') ? 'light' : 'dark';
            localStorage.setItem('nacos_theme', nextTheme);
            applyStudentTheme(nextTheme);
        });
    }

    const addAssistantMessage = function (text, type) {
        if (!assistantMessages) {
            return;
        }

        const message = document.createElement('div');
        message.className = 'assistant-message assistant-message-' + type;
        const content = document.createElement('div');
        content.className = 'assistant-message-content';
        const paragraph = document.createElement('p');
        const lines = String(text || '').split('\n');
        lines.forEach(function (line, index) {
            paragraph.appendChild(document.createTextNode(line));
            if (index < lines.length - 1) {
                paragraph.appendChild(document.createElement('br'));
            }
        });
        content.appendChild(paragraph);
        const timestamp = document.createElement('span');
        timestamp.className = 'assistant-timestamp';
        timestamp.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        message.appendChild(content);
        message.appendChild(timestamp);
        message.setAttribute('aria-label', type === 'user' ? 'Your message' : 'Assistant response');
        assistantMessages.appendChild(message);
        assistantMessages.scrollTop = assistantMessages.scrollHeight;
    };

    const setAssistantTyping = function (isTyping) {
        if (!assistantStatus) {
            return;
        }
        assistantStatus.hidden = !isTyping;
    };

    const sendAssistantQuery = async function (text) {
        const value = String(text || '').trim();
        if (!value || !assistantMessages) {
            return;
        }

        addAssistantMessage(value, 'user');
        if (assistantInput) {
            assistantInput.value = '';
        }

        setAssistantTyping(true);

        try {
            const response = await fetch((window.APP_URL || '') + '/api/assistant_message.php', {
                method: 'POST',
                headers: Object.assign(
                    { 'Content-Type': 'application/json' },
                    window.CSRF_TOKEN ? { 'X-CSRF-Token': window.CSRF_TOKEN } : {}
                ),
                body: JSON.stringify({ name: 'Guest', email: '', message: value })
            });

            const payload = await response.json();
            const reply = payload && payload.ok && payload.reply ? payload.reply : 'I am having trouble answering that right now. Please try again in a moment or open a support ticket.';
            addAssistantMessage(reply, 'bot');
        } catch (error) {
            addAssistantMessage('I am having trouble answering that right now. Please try again in a moment or open a support ticket.', 'bot');
        } finally {
            setAssistantTyping(false);
        }
    };

    if (assistantLauncher) {
        assistantLauncher.classList.add('bounce');
        setInterval(function () {
            assistantLauncher.classList.toggle('assistant-pulse');
        }, 5000);

        assistantLauncher.addEventListener('click', function () {
            if (!assistantPanel) {
                return;
            }
            const isOpen = assistantPanel.hidden === false;
            assistantPanel.hidden = isOpen;
            assistantLauncher.setAttribute('aria-expanded', String(!isOpen));
            if (!isOpen) {
                assistantPanel.classList.add('show');
                setTimeout(function () {
                    assistantInput?.focus();
                }, 200);
            }
        });
    }

    if (assistantClose) {
        assistantClose.addEventListener('click', function () {
            if (assistantPanel) {
                assistantPanel.hidden = true;
                assistantLauncher?.setAttribute('aria-expanded', 'false');
            }
        });
    }

    if (assistantSend && assistantInput) {
        assistantSend.addEventListener('click', function () {
            const value = assistantInput.value.trim();
            if (!value) {
                return;
            }
            sendAssistantQuery(value);
        });

        assistantInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                assistantSend.click();
            }
        });
    }

    assistantSuggestions.forEach(function (suggestion) {
        suggestion.addEventListener('click', function () {
            sendAssistantQuery(suggestion.textContent || suggestion.innerText);
        });
    });

    const communityRefreshBtn = document.getElementById('communityRefreshBtn');
    const communityRefreshIconBtn = document.getElementById('communityRefreshIconBtn');
    const communitySendBtn = document.getElementById('communitySendBtn');
    const communityAttachmentBtn = document.getElementById('communityAttachmentBtn');
    const communityAttachmentInput = document.getElementById('communityAttachmentInput');
    const communityAttachmentMenu = document.getElementById('communityAttachmentMenu');
    const communityAttachmentPreview = document.getElementById('communityAttachmentPreview');
    const communityConversation = document.getElementById('communityConversation');
    const communityPostInput = document.getElementById('communityPostInput');
    const communityEmpty = document.getElementById('communityEmpty');
    const communityUsernameDisplay = document.getElementById('communityUsernameDisplay');
    const communityReplyPreview = document.getElementById('communityReplyPreview');
    const communityUsernameModal = document.getElementById('communityUsernameModal');
    const communityUsernameField = document.getElementById('communityUsernameField');
    const communityUsernameSave = document.getElementById('communityUsernameSave');
    const communityUsernameClose = document.getElementById('communityUsernameClose');
    const communityChangeUsernameBtn = document.getElementById('communityChangeUsernameBtn');
    const reactionOptions = ['❤️', '😂', '👍', '😮', '👏', '🎉'];
    const reactionHoldDelay = 600;
    const attachmentTypes = {
        image: { icon: 'image', label: 'Image', accept: 'image/*' },
        file: { icon: 'paperclip', label: 'Attachment', accept: '*/*' },
        doc: { icon: 'file-text', label: 'Document', accept: '.doc,.docx' },
        pdf: { icon: 'file-pdf', label: 'PDF', accept: '.pdf' }
    };
    const reservedCommunityNames = ['admin', 'community', 'support', 'nacos', 'staff', 'system', 'root'];

    let communityChatMessages = [];
    const getCommunityMessageStorageKey = function () {
        return 'nacosCommunityMessages-' + getCurrentStudentId();
    };

    const getStoredCommunityMessages = function () {
        try {
            const raw = localStorage.getItem(getCommunityMessageStorageKey());
            return raw ? JSON.parse(raw) : [];
        } catch (e) {
            return [];
        }
    };

    const setStoredCommunityMessages = function (messages) {
        communityChatMessages = messages;
        try {
            localStorage.setItem(getCommunityMessageStorageKey(), JSON.stringify(messages));
        } catch (e) {
            // ignore storage failures
        }
    };

    const addMessageToStorage = function (message) {
        const updated = communityChatMessages.concat([message]);
        setStoredCommunityMessages(updated);
    };

    const updateStoredMessage = function (messageId, updater) {
        const updated = communityChatMessages.map(function (message) {
            if (message.id !== messageId) {
                return message;
            }
            return updater(message);
        });
        setStoredCommunityMessages(updated);
    };

    const removeMessageFromStorage = function (messageId) {
        const updated = communityChatMessages.filter(function (message) {
            return message.id !== messageId;
        });
        setStoredCommunityMessages(updated);
    };

    let activeReplyTarget = null;
    let longPressTimer = null;
    let pointerStartData = null;
    let currentContextCard = null;
    let lastContextCoords = { x: 0, y: 0 };

    const communityActionMenu = document.createElement('div');
    communityActionMenu.className = 'community-message-action-menu d-none';
    communityActionMenu.innerHTML = '<button type="button" class="action-item" data-action="react"><i data-lucide="smile-plus"></i> React</button><button type="button" class="action-item" data-action="reply"><i data-lucide="reply"></i> Reply</button><button type="button" class="action-item" data-action="copy"><i data-lucide="copy"></i> Copy</button><button type="button" class="action-item timed-message-action" data-action="edit"><i data-lucide="pencil"></i> Edit</button><button type="button" class="action-item timed-message-action text-danger" data-action="delete_for_everyone"><i data-lucide="trash-2"></i> Delete for everyone</button><button type="button" class="action-item" data-action="delete_for_me"><i data-lucide="eye-off"></i> Delete for me</button><button type="button" class="action-item action-close" data-action="close">Cancel</button>';
    document.body.appendChild(communityActionMenu);
    if (window.faIconFactory) {
        faIconFactory.render(communityActionMenu);
    }

    const communityReactionPicker = document.createElement('div');
    communityReactionPicker.className = 'community-reaction-picker d-none';
    communityReactionPicker.innerHTML = reactionOptions.map(function (emoji) {
        return '<button type="button" class="reaction-action-btn">' + emoji + '</button>';
    }).join('');
    document.body.appendChild(communityReactionPicker);

    const communityApiUrl = (window.APP_URL || '') + '/api/community_messages.php';
    const getCurrentStudentId = function () {
        return Number(communityConversation?.dataset.currentStudentId || 0);
    };
    const getCommunityCsrfToken = function () {
        return window.CSRF_TOKEN || communityConversation?.dataset.csrfToken || '';
    };

    let currentCommunityUsername = null;
    const getStoredCommunityUsername = function () {
        return currentCommunityUsername;
    };

    const sanitizeUsername = function (value) {
        return value.replace(/[^a-zA-Z0-9_]/g, '').replace(/_{2,}/g, '_');
    };

    const validateCommunityUsername = function (value) {
        const username = (value || '').trim();
        if (username.length < 3 || username.length > 20) {
            return 'Username must be 3 to 20 characters long.';
        }
        if (!/^[a-zA-Z0-9_]+$/.test(username)) {
            return 'Only letters, numbers and underscores are allowed.';
        }
        if (/^_|_$/.test(username)) {
            return 'Username cannot start or end with an underscore.';
        }
        if (reservedCommunityNames.includes(username.toLowerCase())) {
            return 'This username is reserved. Please choose another.';
        }
        return '';
    };

    const renderCommunityMessages = function (messages) {
        if (!communityConversation) {
            return;
        }
        communityConversation.querySelectorAll('.community-message').forEach(function (node) {
            node.remove();
        });
        if (!Array.isArray(messages) || messages.length === 0) {
            setEmptyState(true);
            communityChatMessages = [];
            return;
        }
        communityChatMessages = messages.slice();
        messages.forEach(function (message) {
            createMessageBubble(message);
        });
        setEmptyState(false);
        scrollConversationToBottom();
        setStoredCommunityMessages(messages);
    };

    const fetchCommunityMessages = async function () {
        try {
            const response = await fetch(communityApiUrl, { cache: 'no-store' });
            const payload = await response.json();
            if (!response.ok || !payload || !Array.isArray(payload.posts)) {
                throw new Error('Unable to load community posts');
            }
            if (payload.currentStudentId) {
                communityConversation.dataset.currentStudentId = String(payload.currentStudentId);
            }
            currentCommunityUsername = payload.username || null;
            renderUsername();
            if (!currentCommunityUsername) {
                openCommunityUsernameModal();
            }
            const messages = payload.posts.map(function (post) {
                return {
                    id: 'msg-' + post.id,
                    sender: post.sender || 'Student',
                    text: post.text || '',
                    timestamp: post.timestamp || '',
                    createdAt: Number(post.createdAt || 0),
                    outgoing: Number(post.studentId) === getCurrentStudentId(),
                    attachment: null,
                    replyTo: null,
                    reactions: Array.isArray(post.reactions) ? post.reactions : [],
                    status: ''
                };
            });
            renderCommunityMessages(messages);
        } catch (e) {
            renderStoredMessages();
        }
    };

    const persistCommunityMessage = async function (message, tempId) {
        if (!message || !message.text) {
            return;
        }
        try {
            const headers = { 'Content-Type': 'application/json' };
            if (getCommunityCsrfToken()) {
                headers['X-CSRF-Token'] = getCommunityCsrfToken();
            }
            const response = await fetch(communityApiUrl, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    content: message.text
                })
            });
            const payload = await response.json();
            if (!response.ok || !payload || !payload.ok || !payload.id) {
                throw new Error(payload?.error || 'Failed to save message');
            }
            const newId = 'msg-' + payload.id;
            updateStoredMessage(tempId, function (m) {
                return Object.assign({}, m, { id: newId, status: 'Sent' });
            });
            const card = getCardByMessageId(tempId);
            if (card) {
                card.dataset.messageId = newId;
                const statusNode = card.querySelector('.message-time.mt-2');
                if (statusNode) {
                    statusNode.textContent = 'Sent';
                }
            }
        } catch (e) {
            updateStoredMessage(tempId, function (m) {
                return Object.assign({}, m, { status: 'Saved locally' });
            });
            const card = getCardByMessageId(tempId);
            const statusNode = card?.querySelector('.message-time.mt-2');
            if (statusNode) {
                statusNode.textContent = 'Saved locally';
            }
        }
    };

    const renderUsername = function () {
        const username = getStoredCommunityUsername();
        if (communityUsernameDisplay) {
            communityUsernameDisplay.textContent = username ? '@' + username : '@YourName';
        }
    };

    const openCommunityUsernameModal = function () {
        if (!communityUsernameModal) {
            return;
        }
        communityUsernameModal.classList.remove('d-none');
        if (communityUsernameField) {
            communityUsernameField.value = getStoredCommunityUsername() || '';
            communityUsernameField.focus();
        }
    };

    const closeCommunityUsernameModal = function () {
        if (!communityUsernameModal) {
            return;
        }
        communityUsernameModal.classList.add('d-none');
    };

    const updateAttachmentPreview = function (file) {
        if (!communityAttachmentPreview) {
            return;
        }
        communityAttachmentPreview.innerHTML = '';
        if (!file) {
            communityAttachmentPreview.classList.add('d-none');
            return;
        }
        communityAttachmentPreview.classList.remove('d-none');
        const previewCard = document.createElement('div');
        previewCard.className = 'attachment-preview-card';
        const fileType = file.type;
        const isImage = fileType.startsWith('image/');
        const label = document.createElement('div');
        label.className = 'preview-label';
        const labelIcon = document.createElement('i');
        labelIcon.dataset.lucide = isImage ? 'image' : fileType === 'application/pdf' ? 'file-pdf' : 'file-text';
        const labelText = document.createElement('div');
        const labelTitle = document.createElement('strong');
        labelTitle.textContent = file.name;
        const labelSize = document.createElement('div');
        labelSize.className = 'text-secondary';
        labelSize.textContent = Math.round(file.size / 1024) + ' KB';
        labelText.appendChild(labelTitle);
        labelText.appendChild(labelSize);
        label.appendChild(labelIcon);
        label.appendChild(labelText);
        const controls = document.createElement('div');
        controls.className = 'preview-meta';
        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'preview-remove';
        removeBtn.textContent = 'Remove';
        removeBtn.addEventListener('click', function () {
            selectedAttachment = null;
            communityAttachmentInput.value = '';
            updateAttachmentPreview(null);
        });
        controls.appendChild(removeBtn);
        previewCard.appendChild(label);
        previewCard.appendChild(controls);
        communityAttachmentPreview.appendChild(previewCard);
        if (window.faIconFactory) {
            faIconFactory.render(previewCard);
        }
    };

    const getMessageTextSnippet = function (card) {
        const bubble = card?.querySelector('.message-bubble');
        if (!bubble) {
            return '';
        }
        const text = bubble.textContent.trim().replace(/\s+/g, ' ');
        return text.length > 90 ? text.slice(0, 90) + '…' : text;
    };

    const hideReplyPreview = function () {
        if (!communityReplyPreview) {
            return;
        }
        communityReplyPreview.classList.add('d-none');
        while (communityReplyPreview.firstChild) {
            communityReplyPreview.removeChild(communityReplyPreview.firstChild);
        }
        activeReplyTarget = null;
    };

    const renderStoredMessages = function () {
        if (!communityConversation) {
            return;
        }
        communityConversation.querySelectorAll('.community-message').forEach(function (node) {
            node.remove();
        });
        const stored = getStoredCommunityMessages();
        communityChatMessages = stored;
        if (stored.length === 0) {
            setEmptyState(true);
            return;
        }
        stored.forEach(function (message) {
            createMessageBubble(message);
        });
        setEmptyState(false);
        scrollConversationToBottom();
    };

    const showReplyPreview = function (card) {
        if (!communityReplyPreview || !card) {
            return;
        }
        const sender = card.querySelector('.message-sender')?.textContent || '@Unknown';
        const snippet = getMessageTextSnippet(card);
        activeReplyTarget = {
            id: card.dataset.messageId,
            sender: sender.replace(/^@/, ''),
            snippet: snippet
        };
        communityReplyPreview.classList.remove('d-none');
        const replyContent = document.createElement('div');
        replyContent.className = 'reply-preview-content';
        const replyLabel = document.createElement('span');
        replyLabel.className = 'reply-label';
        replyLabel.textContent = 'Replying to ' + sender;
        const replyText = document.createElement('p');
        replyText.textContent = snippet;
        replyContent.appendChild(replyLabel);
        replyContent.appendChild(replyText);
        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-sm btn-soft-surface reply-cancel-btn';
        cancelButton.textContent = 'Cancel';
        communityReplyPreview.appendChild(replyContent);
        communityReplyPreview.appendChild(cancelButton);
        const cancelBtn = cancelButton;
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function () {
                hideReplyPreview();
            });
        }
    };

    const closeActionMenu = function () {
        communityActionMenu.classList.add('d-none');
        currentContextCard = null;
    };

    const openActionMenu = function (card, x, y) {
        if (!card) {
            return;
        }
        currentContextCard = card;
        const message = communityChatMessages.find(function (item) {
            return item.id === card.dataset.messageId;
        });
        const messageAge = message?.createdAt ? Math.floor(Date.now() / 1000) - Number(message.createdAt) : Number.POSITIVE_INFINITY;
        const timedActionsAvailable = card.classList.contains('outgoing') && messageAge >= 0 && messageAge < 180;
        communityActionMenu.querySelectorAll('.timed-message-action').forEach(function (action) {
            action.hidden = !timedActionsAvailable;
        });
        lastContextCoords = { x: x, y: y };
        communityActionMenu.style.left = Math.min(x, window.innerWidth - 240) + 'px';
        communityActionMenu.style.top = Math.min(y, window.innerHeight - 120) + 'px';
        communityActionMenu.classList.remove('d-none');
    };

    const getRemoteMessageId = function (card) {
        return Number((card?.dataset.messageId || '').replace('msg-', '')) || 0;
    };

    const sendCommunityAction = async function (action, data) {
        const headers = { 'Content-Type': 'application/json' };
        if (getCommunityCsrfToken()) {
            headers['X-CSRF-Token'] = getCommunityCsrfToken();
        }
        const response = await fetch(communityApiUrl, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(Object.assign({ action: action }, data || {}))
        });
        const payload = await response.json();
        if (!response.ok || !payload.ok) {
            throw new Error(payload.error || 'Unable to complete that action.');
        }
        return payload;
    };

    const editCommunityMessage = async function (card) {
        const message = communityChatMessages.find(function (item) {
            return item.id === card.dataset.messageId;
        });
        if (!message) return;
        const bubble = card.querySelector('.message-bubble');
        if (!bubble || bubble.dataset.editing === 'true') return;
        bubble.dataset.editing = 'true';
        const editor = document.createElement('textarea');
        editor.className = 'form-control community-inline-editor';
        editor.rows = 3;
        editor.maxLength = 5000;
        editor.value = message.text;
        const controls = document.createElement('div');
        controls.className = 'community-edit-controls';
        const saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'btn btn-success btn-sm';
        saveButton.textContent = 'Save';
        const cancelButton = document.createElement('button');
        cancelButton.type = 'button';
        cancelButton.className = 'btn btn-outline-secondary btn-sm';
        cancelButton.textContent = 'Cancel';
        controls.appendChild(saveButton);
        controls.appendChild(cancelButton);
        bubble.textContent = '';
        bubble.appendChild(editor);
        bubble.appendChild(controls);
        editor.focus();
        editor.setSelectionRange(editor.value.length, editor.value.length);
        cancelButton.addEventListener('click', function () {
            bubble.textContent = message.text;
            delete bubble.dataset.editing;
        });
        saveButton.addEventListener('click', async function () {
            const content = editor.value.trim();
            if (!content) return;
            saveButton.disabled = true;
            try {
                const payload = await sendCommunityAction('edit', { id: getRemoteMessageId(card), content: content });
                updateStoredMessage(message.id, function (item) {
                    return Object.assign({}, item, { text: payload.content });
                });
                bubble.textContent = payload.content;
                delete bubble.dataset.editing;
            } catch (error) {
                saveButton.disabled = false;
                window.alert(error.message);
            }
        });
    };

    const deleteCommunityMessage = async function (card, scope) {
        const confirmation = scope === 'delete_for_everyone'
            ? 'Delete this message for everyone?'
            : 'Delete this message for you?';
        if (!window.confirm(confirmation)) return;
        try {
            await sendCommunityAction(scope, { id: getRemoteMessageId(card) });
            removeMessageFromStorage(card.dataset.messageId);
            card.remove();
            if (!communityConversation.querySelector('.community-message')) setEmptyState(true);
        } catch (error) {
            window.alert(error.message);
        }
    };

    const closeReactionPicker = function () {
        communityReactionPicker.classList.add('d-none');
        communityReactionPicker.dataset.targetId = '';
    };

    const openReactionPicker = function (card, x, y) {
        if (!card) {
            return;
        }
        closeActionMenu();
        communityReactionPicker.dataset.targetId = card.dataset.messageId || '';
        communityReactionPicker.style.left = Math.min(x, window.innerWidth - 300) + 'px';
        communityReactionPicker.style.top = Math.min(y, window.innerHeight - 80) + 'px';
        communityReactionPicker.classList.remove('d-none');
    };

    const addReactionToCard = function (card, emoji) {
        if (!card) {
            return;
        }
        const reactions = card.querySelector('.message-reactions');
        if (!reactions) {
            return;
        }
        const existing = reactions.querySelector('[data-reaction="' + emoji + '"]');
        if (existing) {
            const count = Number(existing.dataset.count || 0) + 1;
            existing.dataset.count = count;
            existing.textContent = emoji + ' ' + count;
        } else {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'reaction-pill';
            pill.dataset.reaction = emoji;
            pill.dataset.count = '1';
            pill.textContent = emoji + ' 1';
            reactions.appendChild(pill);
        }
        const messageId = card.dataset.messageId;
        updateStoredMessage(messageId, function (message) {
            const reactions = Array.isArray(message.reactions) ? message.reactions.slice() : [];
            const existingReaction = reactions.find(function (reaction) {
                return reaction.emoji === emoji;
            });
            if (existingReaction) {
                existingReaction.count += 1;
            } else {
                reactions.push({ emoji: emoji, count: 1 });
            }
            return Object.assign({}, message, { reactions: reactions });
        });
    };

    const renderReactionCounts = function (card, reactions) {
        const strip = card?.querySelector('.message-reactions');
        if (!strip) return;
        strip.innerHTML = '';
        (Array.isArray(reactions) ? reactions : []).forEach(function (reaction) {
            const pill = document.createElement('button');
            pill.type = 'button';
            pill.className = 'reaction-pill';
            pill.dataset.reaction = reaction.emoji;
            pill.dataset.count = String(reaction.count);
            pill.textContent = reaction.emoji + ' ' + reaction.count;
            strip.appendChild(pill);
        });
    };

    const getCardByMessageId = function (messageId) {
        return communityConversation?.querySelector('[data-message-id="' + messageId + '"]');
    };

    const setEmptyState = function (visible) {
        if (!communityEmpty) {
            return;
        }
        communityEmpty.style.display = visible ? 'block' : 'none';
    };

    const formatPostTime = function () {
        return new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    };

    const scrollConversationToBottom = function () {
        if (!communityConversation) {
            return;
        }
        communityConversation.scrollTop = communityConversation.scrollHeight;
    };

    const createMessageBubble = function (message) {
        if (!communityConversation) {
            return;
        }
        const wrapper = document.createElement('div');
        wrapper.className = 'community-message ' + (message.outgoing ? 'outgoing' : 'incoming');
        wrapper.dataset.messageId = message.id || ('msg-' + Date.now() + '-' + Math.random().toString(36).slice(2));

        const meta = document.createElement('div');
        meta.className = 'message-meta';
        const senderSpan = document.createElement('span');
        senderSpan.className = 'message-sender';
        senderSpan.textContent = '@' + message.sender;
        const timeSpan = document.createElement('span');
        timeSpan.className = 'message-time';
        timeSpan.textContent = message.timestamp;
        meta.appendChild(senderSpan);
        meta.appendChild(timeSpan);

        const bubble = document.createElement('div');
        bubble.className = 'message-bubble ' + (message.outgoing ? 'outgoing-bubble' : 'incoming-bubble');
        bubble.textContent = message.text || '';

        if (message.replyTo) {
            const replyPreview = document.createElement('div');
            replyPreview.className = 'message-reply-preview';
            const replyLabel = document.createElement('span');
            replyLabel.className = 'reply-label';
            replyLabel.textContent = 'Replying to @' + message.replyTo.sender;
            const replyParagraph = document.createElement('p');
            replyParagraph.textContent = message.replyTo.snippet;
            replyPreview.appendChild(replyLabel);
            replyPreview.appendChild(replyParagraph);
            wrapper.appendChild(replyPreview);
        }

        if (message.attachment) {
            const attachmentCard = document.createElement('div');
            attachmentCard.className = 'message-attachment';
            if (message.attachment.type === 'image') {
                const image = document.createElement('img');
                image.src = message.attachment.url;
                image.alt = message.attachment.fileName;
                image.style.maxWidth = '100%';
                image.style.borderRadius = '1rem';
                attachmentCard.appendChild(image);
            } else {
                const label = document.createElement('div');
                label.className = 'preview-label';
                const labelIcon = document.createElement('i');
                labelIcon.dataset.lucide = message.attachment.type === 'pdf' ? 'file-pdf' : 'file-text';
                const labelText = document.createElement('div');
                const labelTitle = document.createElement('strong');
                labelTitle.textContent = message.attachment.fileName;
                const labelSize = document.createElement('div');
                labelSize.className = 'text-secondary';
                labelSize.textContent = message.attachment.fileSize;
                labelText.appendChild(labelTitle);
                labelText.appendChild(labelSize);
                label.appendChild(labelIcon);
                label.appendChild(labelText);
                attachmentCard.appendChild(label);
            }
            bubble.appendChild(document.createElement('br'));
            bubble.appendChild(attachmentCard);
        }

        if (message.outgoing && message.status) {
            const status = document.createElement('div');
            status.className = 'message-time mt-2';
            status.style.fontSize = '0.78rem';
            status.style.color = 'rgba(15, 23, 42, 0.45)';
            status.textContent = message.status;
            bubble.appendChild(status);
        }

        wrapper.appendChild(meta);
        wrapper.appendChild(bubble);
        const reactionStrip = document.createElement('div');
        reactionStrip.className = 'message-reactions';
        wrapper.appendChild(reactionStrip);
        if (Array.isArray(message.reactions) && message.reactions.length > 0) {
            message.reactions.forEach(function (reaction) {
                const pill = document.createElement('button');
                pill.type = 'button';
                pill.className = 'reaction-pill';
                pill.dataset.reaction = reaction.emoji;
                pill.dataset.count = String(reaction.count);
                pill.textContent = reaction.emoji + ' ' + reaction.count;
                reactionStrip.appendChild(pill);
            });
        }
        communityConversation.appendChild(wrapper);
        if (window.faIconFactory) {
            faIconFactory.render(wrapper);
        }
        return wrapper;
    };

    let selectedAttachment = null;
    let attachmentMenuOpen = false;

    const openAttachmentMenu = function () {
        if (!communityAttachmentMenu) {
            return;
        }
        communityAttachmentMenu.classList.remove('d-none');
        attachmentMenuOpen = true;
    };

    const closeAttachmentMenu = function () {
        if (!communityAttachmentMenu) {
            return;
        }
        communityAttachmentMenu.classList.add('d-none');
        attachmentMenuOpen = false;
    };

    const submitCommunityMessage = function () {
        const username = getStoredCommunityUsername();
        if (!username) {
            openCommunityUsernameModal();
            return;
        }
        const text = communityPostInput?.value.trim() || '';
        if (!text && !selectedAttachment) {
            return;
        }

        const attachmentData = selectedAttachment ? {
            type: selectedAttachment.type,
            fileName: selectedAttachment.file.name,
            fileSize: Math.round(selectedAttachment.file.size / 1024) + ' KB',
            url: selectedAttachment.url
        } : null;

        const message = {
            id: 'msg-' + Date.now() + '-' + Math.random().toString(36).slice(2),
            sender: username,
            text: text,
            timestamp: formatPostTime(),
            createdAt: Math.floor(Date.now() / 1000),
            outgoing: true,
            attachment: attachmentData,
            replyTo: activeReplyTarget ? {
                sender: activeReplyTarget.sender,
                snippet: activeReplyTarget.snippet
            } : null,
            reactions: [],
            status: 'Sending...'
        };

        addMessageToStorage(message);
        setEmptyState(false);
        const bubble = createMessageBubble(message);
        scrollConversationToBottom();

        if (communityPostInput) {
            communityPostInput.value = '';
            communityPostInput.focus();
        }
        selectedAttachment = null;
        if (communityAttachmentInput) {
            communityAttachmentInput.value = '';
        }
        updateAttachmentPreview(null);
        hideReplyPreview();

        persistCommunityMessage(message, message.id);
    };

    if (communityUsernameDisplay) {
        renderUsername();
    }

    if (communityChangeUsernameBtn) {
        communityChangeUsernameBtn.addEventListener('click', openCommunityUsernameModal);
    }
    if (communityUsernameClose) {
        communityUsernameClose.addEventListener('click', closeCommunityUsernameModal);
    }
    if (communityUsernameSave) {
        communityUsernameSave.addEventListener('click', async function () {
            if (!communityUsernameField) {
                return;
            }
            const raw = communityUsernameField.value.trim();
            const sanitized = sanitizeUsername(raw);
            const error = validateCommunityUsername(sanitized);
            const errorNode = document.getElementById('communityUsernameError');
            if (errorNode) {
                if (error) {
                    errorNode.textContent = error;
                    errorNode.classList.remove('d-none');
                    return;
                }
                errorNode.classList.add('d-none');
            }
            communityUsernameSave.disabled = true;
            try {
                const headers = { 'Content-Type': 'application/json' };
                if (getCommunityCsrfToken()) {
                    headers['X-CSRF-Token'] = getCommunityCsrfToken();
                }
                const response = await fetch(communityApiUrl, {
                    method: 'POST',
                    headers: headers,
                    body: JSON.stringify({ action: 'set_username', username: sanitized })
                });
                const payload = await response.json();
                if (!response.ok || !payload.ok) {
                    throw new Error(payload.error || 'Unable to save username.');
                }
                currentCommunityUsername = payload.username;
                renderUsername();
                closeCommunityUsernameModal();
            } catch (error) {
                if (errorNode) {
                    errorNode.textContent = error.message;
                    errorNode.classList.remove('d-none');
                }
            } finally {
                communityUsernameSave.disabled = false;
            }
        });
    }
    if (communityUsernameField) {
        communityUsernameField.addEventListener('input', function () {
            this.value = sanitizeUsername(this.value);
            const errorNode = document.getElementById('communityUsernameError');
            if (errorNode) {
                errorNode.classList.add('d-none');
            }
        });
        communityUsernameField.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                event.preventDefault();
                communityUsernameSave?.click();
            }
        });
    }
    fetchCommunityMessages();

    if (communityAttachmentBtn) {
        communityAttachmentBtn.addEventListener('click', function () {
            if (attachmentMenuOpen) {
                closeAttachmentMenu();
            } else {
                openAttachmentMenu();
            }
        });
    }

    if (communityAttachmentMenu) {
        communityAttachmentMenu.querySelectorAll('.attachment-option').forEach(function (button) {
            button.addEventListener('click', function () {
                const type = button.dataset.type;
                const option = attachmentTypes[type];
                if (!option || !communityAttachmentInput) {
                    return;
                }
                communityAttachmentInput.accept = option.accept;
                communityAttachmentInput.dataset.type = type;
                communityAttachmentInput.click();
                closeAttachmentMenu();
            });
        });
    }

    if (communityAttachmentInput) {
        communityAttachmentInput.addEventListener('change', function () {
            const file = this.files && this.files[0];
            if (!file) {
                return;
            }
            const type = this.dataset.type || 'file';
            selectedAttachment = {
                type: type,
                file: file,
                url: type === 'image' ? URL.createObjectURL(file) : null
            };
            updateAttachmentPreview(file);
        });
    }

    if (communityConversation) {
        communityConversation.addEventListener('contextmenu', function (event) {
            const card = event.target.closest('.community-message');
            if (!card) {
                return;
            }
            event.preventDefault();
            openActionMenu(card, event.clientX, event.clientY);
        });

        communityConversation.addEventListener('pointerdown', function (event) {
            const card = event.target.closest('.community-message');
            if (!card) {
                return;
            }
            pointerStartData = { x: event.clientX, y: event.clientY, card: card, pointerType: event.pointerType };
            longPressTimer = window.setTimeout(function () {
                openActionMenu(card, event.clientX, event.clientY);
            }, reactionHoldDelay);
        });

        communityConversation.addEventListener('pointermove', function (event) {
            if (!pointerStartData || !pointerStartData.card) {
                return;
            }
            const deltaX = event.clientX - pointerStartData.x;
            const deltaY = event.clientY - pointerStartData.y;
            if (Math.abs(deltaX) > 10 || Math.abs(deltaY) > 10) {
                clearTimeout(longPressTimer);
                longPressTimer = null;
            }
            if (pointerStartData.pointerType === 'touch' && deltaX > 80 && Math.abs(deltaY) < 40) {
                showReplyPreview(pointerStartData.card);
                clearTimeout(longPressTimer);
                longPressTimer = null;
                pointerStartData = null;
            }
        });

        ['pointerup', 'pointerleave', 'pointercancel'].forEach(function (eventName) {
            communityConversation.addEventListener(eventName, function () {
                clearTimeout(longPressTimer);
                longPressTimer = null;
                pointerStartData = null;
            });
        });
    }

    if (communityActionMenu) {
        communityActionMenu.addEventListener('click', function (event) {
            const action = event.target.closest('[data-action]')?.dataset.action;
            if (!action) {
                return;
            }
            if (action === 'react' && currentContextCard) {
                openReactionPicker(currentContextCard, lastContextCoords.x, lastContextCoords.y);
            }
            if (action === 'reply' && currentContextCard) {
                showReplyPreview(currentContextCard);
            }
            if (action === 'copy' && currentContextCard) {
                const bubble = currentContextCard.querySelector('.message-bubble');
                if (bubble) {
                    navigator.clipboard?.writeText(bubble.textContent || '');
                }
            }
            if (action === 'edit' && currentContextCard) {
                editCommunityMessage(currentContextCard);
            }
            if (action === 'delete_for_me' && currentContextCard) {
                deleteCommunityMessage(currentContextCard, 'delete_for_me');
            }
            if (action === 'delete_for_everyone' && currentContextCard) {
                deleteCommunityMessage(currentContextCard, 'delete_for_everyone');
            }
            closeActionMenu();
        });
    }

    if (communityReactionPicker) {
        communityReactionPicker.addEventListener('click', function (event) {
            const button = event.target.closest('.reaction-action-btn');
            if (!button) {
                return;
            }
            const emoji = button.textContent.trim();
            const messageId = communityReactionPicker.dataset.targetId;
            const card = getCardByMessageId(messageId);
            if (card && emoji) {
                sendCommunityAction('react', { id: getRemoteMessageId(card), reaction: emoji }).then(function (payload) {
                    renderReactionCounts(card, payload.reactions);
                    updateStoredMessage(messageId, function (message) {
                        return Object.assign({}, message, { reactions: payload.reactions });
                    });
                }).catch(function (error) {
                    window.alert(error.message);
                });
            }
            closeReactionPicker();
        });
    }

    document.addEventListener('click', function (event) {
        if (attachmentMenuOpen && communityAttachmentMenu && !event.target.closest('.community-attachment-menu') && !event.target.closest('.community-attachment-btn')) {
            closeAttachmentMenu();
        }
        if (!event.target.closest('.community-message-action-menu') && !event.target.closest('.community-reaction-picker') && !event.target.closest('.community-message')) {
            closeActionMenu();
            closeReactionPicker();
        }
    });

    if (communitySendBtn) {
        communitySendBtn.addEventListener('click', submitCommunityMessage);
    }
    if (communityPostInput) {
        communityPostInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                submitCommunityMessage();
            }
        });
    }

    const showCommunityRefreshFeedback = function () {
        Swal.fire({
            icon: 'success',
            title: 'Community refreshed',
            text: 'New posts and notifications have been loaded.',
            timer: 1800,
            showConfirmButton: false,
            customClass: { popup: 'swal2-popup-glass' }
        });
    };

    const refreshCommunityMessages = function () {
        fetchCommunityMessages().then(function () {
            showCommunityRefreshFeedback();
        }).catch(function () {
            showCommunityRefreshFeedback();
        });
    };

    if (communityRefreshBtn) {
        communityRefreshBtn.addEventListener('click', refreshCommunityMessages);
    }
    if (communityRefreshIconBtn) {
        communityRefreshIconBtn.addEventListener('click', refreshCommunityMessages);
    }

    document.addEventListener('click', function (event) {
        if (attachmentMenuOpen && communityAttachmentMenu && !event.target.closest('.community-attachment-menu') && !event.target.closest('.community-attachment-btn')) {
            closeAttachmentMenu();
        }
    });
    
        // Debug helper: toggle highlight of washed-out white text with Shift+H
        (function () {
            const hint = 'Press Shift+H to toggle washed-text highlight';
            // show console hint once
            try { console.info(hint); } catch (e) {}
            window.addEventListener('keydown', function (e) {
                if (e.shiftKey && (e.key === 'H' || e.key === 'h')) {
                    document.body.classList.toggle('highlight-washed');
                }
            });
        })();
});
