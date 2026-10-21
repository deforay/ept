/*
 * Two-list mapper: "not on" on the left, "on" on the right, click a row to move it.
 * Shared by Map Participants and the enrollment list editor. Markup comes from
 * partials/dual-mapper.phtml, styles from the .mapper-* section of admin.css.
 *
 * Rows are { id, code, name, label, selected }. `selected` is the saved state, so the
 * mapper can mark each row as saved, to add or to remove and count the changes.
 * Only the visible rows are in the DOM, so lists of thousands stay fast.
 */
var DualMapper = (function () {
    var ROW_HEIGHT = 34;
    var OVERSCAN = 6;

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }

    function create(root, options) {
        var opts = $.extend({ nothingHere: '', noMatch: '', onChange: function () {} }, options);
        var state = { left: [], right: [] };
        var panes = {};

        function initPane(sideKey, paneEl) {
            var $pane = $(paneEl);
            var pane = {
                sideKey: sideKey,
                $scroll: $pane.find('.mapper-scroll'),
                $inner: $pane.find('.mapper-inner'),
                $search: $pane.find('.mapper-search'),
                $count: $pane.find('.mapper-count'),
                $chip: $pane.find('.mapper-chip'),
                filter: '',
                filtered: []
            };
            pane.$scroll.on('scroll', function () { render(pane); });
            pane.$search.on('input', function () {
                pane.filter = pane.$search.val().toLowerCase();
                recomputeFiltered(pane);
                pane.$scroll.scrollTop(0);
                render(pane);
            });
            pane.$inner.on('click', '.mapper-row', function () {
                moveItem(pane.sideKey, $(this).attr('data-id'));
            });
            $pane.find('.mapper-move-all').on('click', function () { moveAllShown(pane.sideKey); });
            panes[sideKey] = pane;
        }

        // Unsaved moves are listed first, so a row just moved never lands out of
        // sight at the bottom of a long list.
        function recomputeFiltered(pane) {
            var src = state[pane.sideKey];
            var pending = [], rest = [];
            for (var i = 0; i < src.length; i++) {
                if (pane.filter && src[i]._lc.indexOf(pane.filter) === -1) continue;
                var isPending = pane.sideKey === 'right' ? !src[i].savedRight : src[i].savedRight;
                (isPending ? pending : rest).push(src[i]);
            }
            pane.filtered = pending.concat(rest);
            pane.$count.text(src.length);
            pane.$inner.css('height', (pane.filtered.length * ROW_HEIGHT) + 'px');
        }

        function rowClass(it, sideKey) {
            if (sideKey === 'right') return it.savedRight ? 'is-saved' : 'is-add';
            return it.savedRight ? 'is-remove' : '';
        }

        function render(pane) {
            if (pane.filtered.length === 0) {
                pane.$inner.html('<div class="mapper-none">' + escapeHtml(pane.filter ? opts.noMatch : opts.nothingHere) + '</div>');
                return;
            }
            var scrollTop = pane.$scroll.scrollTop();
            var h = pane.$scroll.height();
            var first = Math.max(0, Math.floor(scrollTop / ROW_HEIGHT) - OVERSCAN);
            var last = Math.min(pane.filtered.length, Math.ceil((scrollTop + h) / ROW_HEIGHT) + OVERSCAN);
            var arrow = pane.sideKey === 'left' ? '&rarr;' : '&larr;';
            var parts = [];
            for (var i = first; i < last; i++) {
                var it = pane.filtered[i];
                var cls = rowClass(it, pane.sideKey);
                parts.push(
                    '<div class="mapper-row' + (cls ? ' ' + cls : '') + '" style="top:' + (i * ROW_HEIGHT) + 'px" data-id="' + escapeHtml(it.id) + '" title="' + escapeHtml(it.label) + '">' +
                    '<span class="mapper-mark"></span>' +
                    '<span class="mapper-code">' + escapeHtml(it.code) + '</span>' +
                    '<span class="mapper-name">' + escapeHtml(it.name) + '</span>' +
                    '<span class="mapper-go">' + arrow + '</span></div>'
                );
            }
            pane.$inner.html(parts.join(''));
        }

        function moveItem(fromSide, id) {
            var toSide = fromSide === 'left' ? 'right' : 'left';
            var arr = state[fromSide];
            for (var i = 0; i < arr.length; i++) {
                if (String(arr[i].id) === String(id)) {
                    state[toSide].push(arr.splice(i, 1)[0]);
                    break;
                }
            }
            refresh();
        }

        // Moves only what the search box shows, so "Add all" after a search does
        // what the admin can see rather than sweeping the whole list.
        function moveAllShown(fromSide) {
            var toSide = fromSide === 'left' ? 'right' : 'left';
            var shown = {};
            for (var i = 0; i < panes[fromSide].filtered.length; i++) shown[panes[fromSide].filtered[i].id] = true;
            var keep = [];
            for (var j = 0; j < state[fromSide].length; j++) {
                var it = state[fromSide][j];
                if (shown[it.id]) state[toSide].push(it);
                else keep.push(it);
            }
            state[fromSide] = keep;
            refresh();
        }

        function changes() {
            var add = 0, remove = 0;
            for (var i = 0; i < state.right.length; i++) if (!state.right[i].savedRight) add++;
            for (var j = 0; j < state.left.length; j++) if (state.left[j].savedRight) remove++;
            return { add: add, remove: remove };
        }

        function refresh() {
            recomputeFiltered(panes.left);
            recomputeFiltered(panes.right);
            render(panes.left);
            render(panes.right);
            var c = changes();
            panes.right.$chip.text('+' + c.add).toggle(c.add > 0);
            panes.left.$chip.text('−' + c.remove).toggle(c.remove > 0);
            opts.onChange(c);
        }

        // keepPending carries unsaved moves across a filter change. Saved rows are
        // always loaded, so a pending remove is always found. A pending add can fall
        // outside the new filter, so its row is kept as it was.
        function load(items, keepPending) {
            var pendingAdd = {}, pendingRemove = {};
            if (keepPending) {
                for (var a = 0; a < state.right.length; a++) if (!state.right[a].savedRight) pendingAdd[state.right[a].id] = state.right[a];
                for (var r = 0; r < state.left.length; r++) if (state.left[r].savedRight) pendingRemove[state.left[r].id] = true;
            }
            state.left = [];
            state.right = [];
            for (var i = 0; i < items.length; i++) {
                var it = items[i];
                it.code = it.code || '';
                it.name = it.name || it.label;
                it._lc = (it.code + ' ' + it.name + ' ' + it.label).toLowerCase();
                it.savedRight = !!it.selected;
                var onRight = it.savedRight ? !pendingRemove[it.id] : !!pendingAdd[it.id];
                delete pendingAdd[it.id];
                (onRight ? state.right : state.left).push(it);
            }
            for (var id in pendingAdd) {
                if (Object.prototype.hasOwnProperty.call(pendingAdd, id)) state.right.push(pendingAdd[id]);
            }
            $.each(panes, function (_, p) {
                if (!keepPending) { p.$search.val(''); p.filter = ''; }
                p.$scroll.scrollTop(0);
            });
            refresh();
        }

        $(root).find('.mapper-pane').each(function () {
            initPane($(this).attr('data-side'), this);
        });

        return {
            load: load,
            changes: changes,
            isDirty: function () {
                var c = changes();
                return c.add + c.remove > 0;
            },
            getSelectedIds: function () {
                return state.right.map(function (it) { return it.id; });
            }
        };
    }

    return { create: create };
})();
