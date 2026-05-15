/**
 * In-app help drawer for ePT.
 *
 * Triggered by Cmd/Ctrl+/, an explicit "?" button in the navbar, or any
 * element with [data-help-open] (optionally [data-help-slug]). Falls back
 * to the topic index when no slug is mapped.
 *
 * Two content modes:
 *   - Page help (default) — shows the help topic for the current screen.
 *   - Guide mode — a workflow guide is pinned across page navigation
 *     using sessionStorage. A chip in the drawer header shows the guide
 *     title + current step. Tabs let the user flip between the guide
 *     and the page help without leaving guide mode.
 *
 * Reads from the page (set by views/partials/help-drawer.phtml):
 *   window.HELP_AUDIENCE   'admin' | 'participant'
 *   window.HELP_BASE_URL   '/help' | '/admin/help'
 *   window.HELP_SLUG       slug for the current page (may be empty)
 *   window.HELP_PAGE_KEY   'controller/action' for target_pages matching
 *   window.HELP_I18N       runtime-only translated strings
 *
 * Session keys (cleared on browser close):
 *   ept.help.activeGuide    slug of the pinned guide
 *   ept.help.guideStep      last viewed step id (number)
 *   ept.help.viewMode       'guide' | 'page' (which tab is active)
 *   ept.help.guideStartedAt epoch ms — drives the 24-hour auto-expiry
 *
 * Storage is localStorage (not sessionStorage) so the guide stays pinned
 * across tabs — important because guide steps offer "Open this screen in
 * a new tab", which would otherwise lose the guide.
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') return;

    var $root, $panel, $loading, $topic, $topicWrap, $index, $error, $search, $toc, $noResults, $fullpage;
    var $guideRegion, $chipRegion, $tabsRegion;
    var topicsCache = null;
    var guidesCache = null;
    var currentGuide = null; // full guide object once loaded
    var isOpen = false;
    var currentReq = null;

    var SS = {
        activeGuide:    'ept.help.activeGuide',
        guideStep:      'ept.help.guideStep',
        viewMode:       'ept.help.viewMode',
        guideStartedAt: 'ept.help.guideStartedAt'
    };
    var GUIDE_TTL_MS = 24 * 60 * 60 * 1000; // 24h — drops haunted guides

    var t = function (key, fallback) {
        return (window.HELP_I18N && window.HELP_I18N[key]) || fallback;
    };

    function init() {
        $root = $('#help-drawer-root');
        if ($root.length === 0) return;
        $panel     = $root.find('.help-drawer-panel');
        $loading   = $('#help-drawer-loading');
        $topic     = $('#help-drawer-topic');
        $topicWrap = $('#help-drawer-topic-wrap');
        if ($topicWrap.length === 0) $topicWrap = $topic; // legacy fallback
        $index     = $('#help-drawer-index');
        $error     = $('#help-drawer-error');
        $search    = $('#help-drawer-search');
        $toc       = $('#help-drawer-toc');
        $noResults = $('#help-drawer-no-results');
        $fullpage  = $('#help-drawer-fullpage');

        // Dynamic regions injected once
        ensureGuideMarkup();

        // Mac users see ⌘/, everyone else Ctrl+/
        try {
            if ((navigator.platform || '').toLowerCase().indexOf('mac') >= 0) {
                $('#help-drawer-shortcut').text('⌘/');
            }
        } catch (e) { /* ignore */ }

        $root.on('click', '[data-help-close]', function (e) {
            e.preventDefault();
            close();
        });

        $(document).on('click', '[data-help-open]', function (e) {
            e.preventDefault();
            var guideSlug = $(this).attr('data-help-guide');
            if (guideSlug) {
                selectGuide(guideSlug);
                if (!isOpen) open('');
                return;
            }
            var slug = $(this).attr('data-help-slug') || window.HELP_SLUG || '';
            open(slug);
        });

        // "Browse all topics & guides" link inside the topic view
        $(document).on('click', '#help-drawer-back-to-index', function (e) {
            e.preventDefault();
            loadIndex();
        });

        window.addEventListener('help-drawer:open', function (ev) {
            var slug = (ev && ev.detail && ev.detail.slug) || '';
            open(slug);
        });

        $search.on('input', function () { renderToc($search.val()); });

        // Cmd/Ctrl+/ toggles
        $(document).on('keydown', function (e) {
            if (!(e.metaKey || e.ctrlKey)) return;
            if (e.key !== '/' && e.code !== 'Slash') return;
            var t = e.target;
            if (t && (t.isContentEditable ||
                      t.tagName === 'INPUT' || t.tagName === 'TEXTAREA' || t.tagName === 'SELECT')) {
                return;
            }
            e.preventDefault();
            if (isOpen) {
                close();
            } else {
                window.dispatchEvent(new CustomEvent('help-drawer:open', {
                    detail: { slug: window.HELP_SLUG || '' }
                }));
            }
        });

        // Esc closes
        $(document).on('keydown', function (e) {
            if (e.key === 'Escape' && isOpen) close();
        });

        // Show the chip immediately on page load if a guide is pinned, so
        // users see they're still mid-workflow even without opening the drawer.
        renderChipOnly();
    }

    /* -------- guide state (localStorage, 24h TTL) -------- */

    function isGuideExpired() {
        try {
            var t = parseInt(localStorage.getItem(SS.guideStartedAt), 10);
            if (!t) return false;
            return (Date.now() - t) > GUIDE_TTL_MS;
        } catch (e) { return false; }
    }
    function clearGuideStorage() {
        try {
            localStorage.removeItem(SS.activeGuide);
            localStorage.removeItem(SS.guideStep);
            localStorage.removeItem(SS.viewMode);
            localStorage.removeItem(SS.guideStartedAt);
        } catch (e) { /* ignore */ }
    }
    function getActiveGuideSlug() {
        if (isGuideExpired()) { clearGuideStorage(); return ''; }
        try { return localStorage.getItem(SS.activeGuide) || ''; } catch (e) { return ''; }
    }
    function getActiveStep() {
        try { return parseInt(localStorage.getItem(SS.guideStep), 10) || 0; } catch (e) { return 0; }
    }
    function getViewMode() {
        try { return localStorage.getItem(SS.viewMode) || 'guide'; } catch (e) { return 'guide'; }
    }
    function setSession(k, v) {
        try {
            if (v === null || v === undefined || v === '') {
                localStorage.removeItem(k);
            } else {
                localStorage.setItem(k, String(v));
            }
        } catch (e) { /* ignore */ }
    }

    /* -------- markup -------- */

    function ensureGuideMarkup() {
        if ($('#help-drawer-chip').length === 0) {
            $chipRegion = $(
                '<div id="help-drawer-chip" class="help-drawer-chip" style="display:none;">' +
                    '<div class="help-drawer-chip-inner">' +
                        '<i class="fa fa-bookmark"></i>' +
                        '<div class="help-drawer-chip-text">' +
                            '<div class="help-drawer-chip-title"></div>' +
                            '<div class="help-drawer-chip-step"></div>' +
                        '</div>' +
                        '<button type="button" class="help-drawer-chip-close" ' +
                            'aria-label="' + t('exit_guide', 'Exit guide') + '">' +
                            '<i class="fa fa-times"></i> ' +
                            '<span class="help-drawer-chip-close-label">' +
                                t('exit_guide', 'Exit guide') +
                            '</span>' +
                        '</button>' +
                    '</div>' +
                    '<div class="help-drawer-chip-progress">' +
                        '<div class="help-drawer-chip-progress-bar"></div>' +
                    '</div>' +
                '</div>'
            );
            $('.help-drawer-header').after($chipRegion);

            $chipRegion.on('click', '.help-drawer-chip-close', function (e) {
                e.preventDefault();
                exitGuide();
            });
        } else {
            $chipRegion = $('#help-drawer-chip');
        }

        if ($('#help-drawer-tabs').length === 0) {
            $tabsRegion = $(
                '<div id="help-drawer-tabs" class="help-drawer-tabs" style="display:none;">' +
                    '<button type="button" class="help-drawer-tab" data-tab="guide">' +
                        t('tab_guide', 'Guide') +
                    '</button>' +
                    '<button type="button" class="help-drawer-tab" data-tab="page">' +
                        t('tab_this_page', 'This page') +
                    '</button>' +
                '</div>'
            );
            $chipRegion.after($tabsRegion);

            $tabsRegion.on('click', '.help-drawer-tab', function () {
                var which = $(this).attr('data-tab');
                setSession(SS.viewMode, which);
                renderActiveView();
            });
        } else {
            $tabsRegion = $('#help-drawer-tabs');
        }

        if ($('#help-drawer-guide').length === 0) {
            $guideRegion = $(
                '<div id="help-drawer-guide" class="help-drawer-guide" style="display:none;">' +
                    '<div class="help-drawer-guide-step-title"></div>' +
                    '<div class="help-drawer-guide-pageref"></div>' +
                    '<div class="help-drawer-guide-body help-prose"></div>' +
                    '<div class="help-drawer-guide-nav">' +
                        '<button type="button" class="help-drawer-step-prev btn btn-default btn-sm">' +
                            '&larr; ' + t('prev_step', 'Previous') +
                        '</button>' +
                        '<span class="help-drawer-step-counter"></span>' +
                        '<button type="button" class="help-drawer-step-next btn btn-primary btn-sm">' +
                            t('next_step', 'Next') + ' &rarr;' +
                        '</button>' +
                    '</div>' +
                '</div>'
            );
            $('.help-drawer-body').append($guideRegion);

            $guideRegion.on('click', '.help-drawer-step-prev', function () { changeStep(-1); });
            $guideRegion.on('click', '.help-drawer-step-next', function () { changeStep(+1); });
        } else {
            $guideRegion = $('#help-drawer-guide');
        }
    }

    /* -------- chip rendering (visible regardless of drawer open state) -------- */

    function renderChipOnly() {
        var slug = getActiveGuideSlug();
        if (!slug) {
            $chipRegion.hide();
            $tabsRegion.hide();
            return;
        }
        // Use cached title if available, else just slug
        var title = slug;
        if (guidesCache) {
            var g = guidesCache.find(function (x) { return x.slug === slug; });
            if (g) title = g.title;
        }
        $chipRegion.find('.help-drawer-chip-title').text(title);
        $chipRegion.find('.help-drawer-chip-step').text(''); // step count filled when guide loads
        $chipRegion.find('.help-drawer-chip-progress-bar').css('width', '0%');
        $chipRegion.show();
    }

    function renderChipFromGuide(guide) {
        if (!guide) { $chipRegion.hide(); $tabsRegion.hide(); return; }
        var step = clampStep(guide, getActiveStep());
        var total = guide.steps.length;
        $chipRegion.find('.help-drawer-chip-title').text(guide.title);
        $chipRegion.find('.help-drawer-chip-step').text(
            t('step_of', 'Step') + ' ' + step + ' ' + t('of', 'of') + ' ' + total
        );
        var pct = total > 0 ? Math.round((step / total) * 100) : 0;
        $chipRegion.find('.help-drawer-chip-progress-bar').css('width', pct + '%');
        $chipRegion.show();

        $tabsRegion.show();
        var mode = getViewMode();
        $tabsRegion.find('.help-drawer-tab').removeClass('is-active');
        $tabsRegion.find('.help-drawer-tab[data-tab="' + mode + '"]').addClass('is-active');
    }

    /* -------- open / close -------- */

    function open(explicitSlug) {
        isOpen = true;
        $root.addClass('is-open').attr('aria-hidden', 'false');

        var activeSlug = getActiveGuideSlug();

        // If user explicitly clicked a slug-bearing trigger, show that topic
        // even when a guide is pinned — but keep the chip / tab so they can
        // get back. We switch the viewMode to 'page' so the chip's "This page"
        // tab is highlighted.
        if (explicitSlug && activeSlug) {
            setSession(SS.viewMode, 'page');
        }

        if (activeSlug) {
            // Make sure the chip + tabs are rendered (chip data fills once guide loads)
            loadGuide(activeSlug, function () {
                renderActiveView(explicitSlug);
            });
        } else {
            // No guide pinned — normal behavior
            $chipRegion.hide();
            $tabsRegion.hide();
            if (explicitSlug) {
                loadTopic(explicitSlug);
            } else {
                loadIndex();
            }
        }
    }

    function close() {
        isOpen = false;
        $root.removeClass('is-open').attr('aria-hidden', 'true');
        if (currentReq && currentReq.abort) { try { currentReq.abort(); } catch (e) {} }
    }

    /* -------- view switching when a guide is pinned -------- */

    function renderActiveView(explicitSlug) {
        var mode = getViewMode();
        if (mode === 'page') {
            // Show the page-help for the explicit slug or the current page
            var slug = explicitSlug || window.HELP_SLUG || '';
            if (slug) {
                loadTopic(slug);
            } else {
                loadIndex();
            }
        } else {
            renderGuideStep();
        }
        renderChipFromGuide(currentGuide);
    }

    /* -------- guide loading + rendering -------- */

    function loadGuide(slug, done) {
        if (currentGuide && currentGuide.slug === slug) {
            if (done) done();
            return;
        }
        showOnly('loading');
        currentReq = $.ajax({
            url: window.HELP_BASE_URL + '/guide',
            data: { slug: slug },
            dataType: 'json'
        }).done(function (data) {
            if (!data || !data.steps) {
                // Guide gone or broken — exit gracefully
                exitGuide();
                return;
            }
            currentGuide = data;
            if (done) done();
        }).fail(function (xhr, status) {
            if (status === 'abort') return;
            exitGuide();
        });
    }

    function clampStep(guide, n) {
        if (!guide || !guide.steps || guide.steps.length === 0) return 1;
        if (n < 1) return 1;
        if (n > guide.steps.length) return guide.steps.length;
        return n;
    }

    function renderGuideStep() {
        if (!currentGuide) return;
        var step = clampStep(currentGuide, getActiveStep());
        if (step !== getActiveStep()) setSession(SS.guideStep, step);
        var s = currentGuide.steps[step - 1];

        $('#help-drawer-title').text(currentGuide.title);
        $fullpage.attr('href', window.HELP_BASE_URL).show();

        $guideRegion.find('.help-drawer-guide-step-title')
            .text(t('step_of', 'Step') + ' ' + step + ': ' + s.title);
        $guideRegion.find('.help-drawer-guide-body').html(s.html);

        // "You're here" or "Open this screen"
        var $ref = $guideRegion.find('.help-drawer-guide-pageref').empty();
        var pageKey = (window.HELP_PAGE_KEY || '').toLowerCase();
        var matches = matchesTargetPage(s.target_pages || [], pageKey);
        if ((s.target_pages || []).length === 0) {
            // No screen for this step (e.g. a concept step) — say nothing
        } else if (matches) {
            $ref.append(
                $('<div class="help-drawer-guide-here">')
                    .append('<i class="fa fa-check-circle"></i> ')
                    .append(document.createTextNode(t('youre_here', "You're on this screen now.")))
            );
        } else {
            var firstTarget = s.target_pages[0];
            var url = guessUrlFromPageKey(firstTarget);
            $ref.append(
                $('<a target="_blank" rel="noopener" class="help-drawer-guide-goto btn btn-default btn-sm">')
                    .attr('href', url)
                    .append('<i class="fa fa-external-link"></i> ')
                    .append(document.createTextNode(t('open_screen', 'Open this screen in a new tab')))
            );
        }

        // Prev / Next state
        $guideRegion.find('.help-drawer-step-prev').prop('disabled', step <= 1);
        $guideRegion.find('.help-drawer-step-next')
            .prop('disabled', step >= currentGuide.steps.length)
            .text(step >= currentGuide.steps.length
                ? t('done', 'Done')
                : t('next_step', 'Next') + ' →');
        $guideRegion.find('.help-drawer-step-counter')
            .text(t('step_of', 'Step') + ' ' + step + ' ' + t('of', 'of') + ' ' + currentGuide.steps.length);

        showOnly('guide');
    }

    function changeStep(delta) {
        if (!currentGuide) return;
        var step = clampStep(currentGuide, getActiveStep());
        var next = step + delta;
        if (next < 1) return;
        if (next > currentGuide.steps.length) {
            // "Done" — exit the guide
            exitGuide();
            return;
        }
        setSession(SS.guideStep, next);
        setSession(SS.guideStartedAt, Date.now()); // bump TTL on activity
        renderGuideStep();
        renderChipFromGuide(currentGuide);
    }

    function matchesTargetPage(targets, currentKey) {
        if (!currentKey) return false;
        currentKey = currentKey.toLowerCase();
        for (var i = 0; i < targets.length; i++) {
            var t = (targets[i] || '').toLowerCase();
            if (!t) continue;
            if (t === currentKey) return true;
            // Treat 'foo/index' as a wildcard for any action under 'foo'
            if (t.indexOf('/index') === t.length - 6) {
                var prefix = t.slice(0, -6);
                if (currentKey.indexOf(prefix + '/') === 0) return true;
            }
        }
        return false;
    }

    function guessUrlFromPageKey(pageKey) {
        // pageKey is 'controller/action'. Build /<audience>/<controller>/<action>.
        // 'admin/help/topic' style — HELP_BASE_URL strips the trailing /help so
        // we can prefix the admin or participant root.
        var base = (window.HELP_BASE_URL || '').replace(/\/help$/, '');
        if (!pageKey) return base || '/';
        return base + '/' + pageKey;
    }

    /* -------- guide entry / exit -------- */

    function selectGuide(slug) {
        setSession(SS.activeGuide, slug);
        setSession(SS.guideStep, 1);
        setSession(SS.viewMode, 'guide');
        setSession(SS.guideStartedAt, Date.now());
        currentGuide = null;
        loadGuide(slug, function () {
            renderGuideStep();
            renderChipFromGuide(currentGuide);
        });
    }

    function exitGuide() {
        clearGuideStorage();
        currentGuide = null;
        $chipRegion.hide();
        $tabsRegion.hide();
        $guideRegion.hide();
        // Fall back to whatever's natural for this page
        if (isOpen) {
            var slug = window.HELP_SLUG || '';
            if (slug) loadTopic(slug); else loadIndex();
        }
    }

    /* -------- existing topic / index loaders -------- */

    function showOnly(which) {
        $loading.toggle(which === 'loading');
        $topicWrap.toggle(which === 'topic');
        if ($topicWrap !== $topic) $topic.toggle(which === 'topic');
        $index.toggle(which === 'index');
        $error.toggle(which === 'error');
        $guideRegion.toggle(which === 'guide');
    }

    function loadTopic(slug) {
        showOnly('loading');
        if (currentReq && currentReq.abort) { try { currentReq.abort(); } catch (e) {} }

        currentReq = $.ajax({
            url: window.HELP_BASE_URL + '/topic',
            data: { slug: slug },
            dataType: 'json'
        }).done(function (data) {
            if (!data || !data.html) { loadIndex(); return; }
            $('#help-drawer-title').text(data.title || '');
            $topic.html(data.html);
            $fullpage
                .attr('href', window.HELP_BASE_URL + '#' + encodeURIComponent(data.slug))
                .show();
            showOnly('topic');
        }).fail(function (xhr, status) {
            if (status === 'abort') return;
            if (xhr && xhr.status === 404) { loadIndex(); return; }
            showOnly('error');
        });
    }

    function loadIndex() {
        showOnly('loading');
        $('#help-drawer-title').text($('#help-drawer-title').data('default-title') || 'Help');
        $fullpage.attr('href', window.HELP_BASE_URL).show();

        // The "No dedicated help for this page yet" callout is only useful
        // when the drawer falls back to the index *because* the page has
        // no slug. If the user reached the index intentionally (by clicking
        // the back link), hide it.
        $index.find('.help-drawer-callout').toggle(!window.HELP_SLUG);

        var render = function () {
            renderToc($search.val() || '');
            showOnly('index');
        };

        if (topicsCache && guidesCache) { render(); return; }

        if (currentReq && currentReq.abort) { try { currentReq.abort(); } catch (e) {} }
        currentReq = $.ajax({
            url: window.HELP_BASE_URL + '/list',
            dataType: 'json'
        }).done(function (data) {
            topicsCache = (data && data.topics) || [];
            guidesCache = (data && data.guides) || [];
            render();
        }).fail(function (xhr, status) {
            if (status === 'abort') return;
            showOnly('error');
        });
    }

    function renderToc(query) {
        var q = (query || '').trim().toLowerCase();
        var matchFn = function (t) {
            if (!q) return true;
            if ((t.title || '').toLowerCase().indexOf(q) >= 0) return true;
            if ((t.summary || '').toLowerCase().indexOf(q) >= 0) return true;
            return (t.tags || []).some(function (tag) {
                return (tag || '').toLowerCase().indexOf(q) >= 0;
            });
        };
        var filteredGuides = (guidesCache || []).filter(matchFn);
        var filteredTopics = (topicsCache || []).filter(matchFn);

        $toc.empty();

        if (filteredGuides.length > 0) {
            $('<div class="help-drawer-toc-section">')
                .text(t('section_guides', 'Workflow guides'))
                .appendTo($toc);
            filteredGuides.forEach(function (g) {
                var $a = $('<a>')
                    .attr('href', 'javascript:void(0)')
                    .addClass('help-drawer-toc-item help-drawer-toc-guide')
                    .on('click', function (e) {
                        e.preventDefault();
                        selectGuide(g.slug);
                    });
                $('<span>').addClass('help-drawer-toc-icon').html('<i class="fa fa-bookmark"></i>').appendTo($a);
                var $body = $('<span>').addClass('help-drawer-toc-body').appendTo($a);
                $('<span>').addClass('help-drawer-toc-title').text(g.title || g.slug).appendTo($body);
                if (g.summary) {
                    $('<span>').addClass('help-drawer-toc-summary').text(g.summary).appendTo($body);
                }
                if (g.estimated_minutes > 0) {
                    $('<span>').addClass('help-drawer-toc-meta')
                        .text('~' + g.estimated_minutes + ' ' + t('minutes', 'min'))
                        .appendTo($body);
                }
                $toc.append($a);
            });
        }

        if (filteredTopics.length > 0) {
            if (filteredGuides.length > 0) {
                $('<div class="help-drawer-toc-section">')
                    .text(t('section_topics', 'Help topics'))
                    .appendTo($toc);
            }
            filteredTopics.forEach(function (topic) {
                var $a = $('<a>')
                    .attr('href', 'javascript:void(0)')
                    .addClass('help-drawer-toc-item')
                    .on('click', function (e) {
                        e.preventDefault();
                        loadTopic(topic.slug);
                    });
                $('<span>').addClass('help-drawer-toc-title').text(topic.title || topic.slug).appendTo($a);
                if (topic.summary) {
                    $('<span>').addClass('help-drawer-toc-summary').text(topic.summary).appendTo($a);
                }
                $toc.append($a);
            });
        }

        $noResults.toggle(filteredGuides.length === 0 && filteredTopics.length === 0 && !!q);
    }

    $(document).ready(init);
})(window.jQuery);
