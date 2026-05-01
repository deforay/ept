/**
 * In-app help drawer for ePT.
 *
 * Triggered by Cmd/Ctrl+/, an explicit "?" button in the navbar, or any
 * element with [data-help-open] (optionally [data-help-slug]). Falls back
 * to the topic index when no slug is mapped.
 *
 * Reads from the page (set by views/partials/help-drawer.phtml):
 *   window.HELP_AUDIENCE   'admin' | 'participant'
 *   window.HELP_BASE_URL   '/help' | '/admin/help'
 *   window.HELP_SLUG       slug for the current page (may be empty)
 *   window.HELP_I18N       runtime-only translated strings
 */
(function ($) {
    'use strict';

    if (typeof $ === 'undefined') return;

    var $root, $panel, $loading, $topic, $index, $error, $search, $toc, $noResults, $fullpage;
    var topicsCache = null;
    var isOpen = false;
    var currentReq = null;

    function init() {
        $root      = $('#help-drawer-root');
        if ($root.length === 0) return;
        $panel     = $root.find('.help-drawer-panel');
        $loading   = $('#help-drawer-loading');
        $topic     = $('#help-drawer-topic');
        $index     = $('#help-drawer-index');
        $error     = $('#help-drawer-error');
        $search    = $('#help-drawer-search');
        $toc       = $('#help-drawer-toc');
        $noResults = $('#help-drawer-no-results');
        $fullpage  = $('#help-drawer-fullpage');

        // Mac users see ⌘/, everyone else Ctrl+/
        try {
            if ((navigator.platform || '').toLowerCase().indexOf('mac') >= 0) {
                $('#help-drawer-shortcut').text('⌘/');
            }
        } catch (e) { /* ignore */ }

        // Wire up close handlers
        $root.on('click', '[data-help-close]', function (e) {
            e.preventDefault();
            close();
        });

        // Open via explicit triggers (e.g. navbar "?" button)
        $(document).on('click', '[data-help-open]', function (e) {
            e.preventDefault();
            var slug = $(this).attr('data-help-slug') || window.HELP_SLUG || '';
            open(slug);
        });

        // Listen for the 'help-drawer:open' window event from the keyboard shortcut
        window.addEventListener('help-drawer:open', function (ev) {
            var slug = (ev && ev.detail && ev.detail.slug) || '';
            open(slug);
        });

        // Search inside the index view
        $search.on('input', function () { renderToc($search.val()); });

        // Cmd/Ctrl+/ toggles
        $(document).on('keydown', function (e) {
            if (!(e.metaKey || e.ctrlKey)) return;
            // Code 191 / key '/' — also cope with localised layouts via e.code
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
    }

    function open(slug) {
        isOpen = true;
        $root.addClass('is-open').attr('aria-hidden', 'false');

        if (slug) {
            loadTopic(slug);
        } else {
            loadIndex();
        }
    }

    function close() {
        isOpen = false;
        $root.removeClass('is-open').attr('aria-hidden', 'true');
        if (currentReq && currentReq.abort) { try { currentReq.abort(); } catch (e) {} }
    }

    function showOnly(which) {
        $loading.toggle(which === 'loading');
        $topic.toggle(which === 'topic');
        $index.toggle(which === 'index');
        $error.toggle(which === 'error');
    }

    function loadTopic(slug) {
        showOnly('loading');
        if (currentReq && currentReq.abort) { try { currentReq.abort(); } catch (e) {} }

        currentReq = $.ajax({
            url: window.HELP_BASE_URL + '/topic',
            data: { slug: slug },
            dataType: 'json'
        }).done(function (data) {
            if (!data || !data.html) {
                // Slug exists but is broken/missing — fall back to the index.
                loadIndex();
                return;
            }
            $('#help-drawer-title').text(data.title || '');
            $topic.html(data.html);
            $fullpage
                .attr('href', window.HELP_BASE_URL + '#' + encodeURIComponent(data.slug))
                .show();
            showOnly('topic');
        }).fail(function (xhr, status) {
            if (status === 'abort') return;
            // 404 → show index instead of an error.
            if (xhr && xhr.status === 404) {
                loadIndex();
                return;
            }
            showOnly('error');
        });
    }

    function loadIndex() {
        showOnly('loading');
        $('#help-drawer-title').text($('#help-drawer-title').data('default-title') || 'Help');
        $fullpage.attr('href', window.HELP_BASE_URL).show();

        var render = function () {
            renderToc($search.val() || '');
            showOnly('index');
        };

        if (topicsCache) { render(); return; }

        if (currentReq && currentReq.abort) { try { currentReq.abort(); } catch (e) {} }
        currentReq = $.ajax({
            url: window.HELP_BASE_URL + '/list',
            dataType: 'json'
        }).done(function (data) {
            topicsCache = (data && data.topics) || [];
            render();
        }).fail(function (xhr, status) {
            if (status === 'abort') return;
            showOnly('error');
        });
    }

    function renderToc(query) {
        var q = (query || '').trim().toLowerCase();
        var filtered = (topicsCache || []).filter(function (t) {
            if (!q) return true;
            if ((t.title || '').toLowerCase().indexOf(q) >= 0) return true;
            if ((t.summary || '').toLowerCase().indexOf(q) >= 0) return true;
            return (t.tags || []).some(function (tag) {
                return (tag || '').toLowerCase().indexOf(q) >= 0;
            });
        });

        $toc.empty();
        filtered.forEach(function (t) {
            var $a = $('<a>')
                .attr('href', 'javascript:void(0)')
                .addClass('help-drawer-toc-item')
                .on('click', function (e) {
                    e.preventDefault();
                    loadTopic(t.slug);
                });
            $('<span>').addClass('help-drawer-toc-title').text(t.title || t.slug).appendTo($a);
            if (t.summary) {
                $('<span>').addClass('help-drawer-toc-summary').text(t.summary).appendTo($a);
            }
            $toc.append($a);
        });

        $noResults.toggle(filtered.length === 0 && !!q);
    }

    $(document).ready(init);
})(window.jQuery);
