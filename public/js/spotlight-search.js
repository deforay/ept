/**
 * Spotlight Search for ePT Admin
 * A macOS Spotlight-like global search for quick navigation
 */
(function($) {
    'use strict';

    var SpotlightSearch = {
        isOpen: false,
        selectedIndex: -1,
        filteredResults: [],

        init: function() {
            this.bindEvents();
            this.createResultsCache();
        },

        createResultsCache: function() {
            // Pre-process data for faster searching
            this.searchData = (window.spotlightData || []).map(function(item) {
                return $.extend({}, item, {
                    searchText: [
                        item.title,
                        item.category,
                        (item.keywords || []).join(' ')
                    ].join(' ').toLowerCase()
                });
            });
        },

        bindEvents: function() {
            var self = this;

            // Keyboard shortcut: Ctrl+K or Cmd+K
            $(document).on('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                    e.preventDefault();
                    self.toggle();
                }

                // ESC to close
                if (e.key === 'Escape' && self.isOpen) {
                    self.close();
                }
            });

            // Click trigger button
            $(document).on('click', '#spotlightTrigger', function(e) {
                e.preventDefault();
                self.toggle();
            });

            // Click backdrop to close
            $(document).on('click', '.spotlight-backdrop', function() {
                self.close();
            });

            // Input handling
            $(document).on('input', '#spotlightInput', function() {
                self.search($(this).val());
            });

            // Keyboard navigation in results
            $(document).on('keydown', '#spotlightInput', function(e) {
                self.handleKeyNavigation(e);
            });

            // Click on result
            $(document).on('click', '.spotlight-result-item', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                if (url) {
                    window.location.href = url;
                }
            });

            // Hover on result
            $(document).on('mouseenter', '.spotlight-result-item', function() {
                self.selectedIndex = $(this).data('index');
                self.updateSelection();
            });
        },

        toggle: function() {
            if (this.isOpen) {
                this.close();
            } else {
                this.open();
            }
        },

        open: function() {
            this.isOpen = true;
            this.selectedIndex = -1;
            $('#spotlightModal').fadeIn(150);
            $('#spotlightInput').val('').focus();
            this.showDefaultResults();
            $('body').addClass('spotlight-open');
        },

        close: function() {
            this.isOpen = false;
            $('#spotlightModal').fadeOut(150);
            $('#spotlightInput').val('');
            $('#spotlightResults').empty();
            $('body').removeClass('spotlight-open');
        },

        showDefaultResults: function() {
            // Show quick actions by default when opened
            var quickActions = this.searchData.filter(function(item) {
                return item.category === 'Quick Actions';
            });

            if (quickActions.length > 0) {
                this.filteredResults = quickActions;
                this.renderResults(quickActions);
            } else {
                this.filteredResults = this.searchData.slice(0, 8);
                this.renderResults(this.filteredResults);
            }
        },

        search: function(query) {
            var self = this;
            query = query.toLowerCase().trim();

            if (!query) {
                this.showDefaultResults();
                return;
            }

            // Filter results
            this.filteredResults = this.searchData.filter(function(item) {
                return item.searchText.indexOf(query) !== -1;
            });

            // Sort: exact title match first, then starts with, then contains
            this.filteredResults.sort(function(a, b) {
                var aTitle = a.title.toLowerCase();
                var bTitle = b.title.toLowerCase();

                var aExact = aTitle === query;
                var bExact = bTitle === query;
                if (aExact !== bExact) return bExact - aExact;

                var aStarts = aTitle.indexOf(query) === 0;
                var bStarts = bTitle.indexOf(query) === 0;
                if (aStarts !== bStarts) return bStarts - aStarts;

                return 0;
            });

            this.selectedIndex = this.filteredResults.length > 0 ? 0 : -1;
            this.renderResults(this.filteredResults);
        },

        renderResults: function(results) {
            var self = this;
            var $container = $('#spotlightResults');
            $container.empty();

            if (results.length === 0) {
                var noResultsText = (window.spotlightTranslations && window.spotlightTranslations.noResults) || 'No results found';
                $container.html(
                    '<div class="spotlight-no-results">' +
                    '<i class="icon-search"></i>' +
                    '<p>' + this.escapeHtml(noResultsText) + '</p>' +
                    '</div>'
                );
                return;
            }

            // Group by category
            var grouped = {};
            results.forEach(function(item) {
                var cat = item.category || 'Other';
                if (!grouped[cat]) grouped[cat] = [];
                grouped[cat].push(item);
            });

            var html = '';
            var globalIndex = 0;

            // Define category order (uses translated names from PHP)
            var categoryOrder = window.spotlightCategoryOrder || ['Quick Actions', 'Navigation', 'Configure', 'Manage', 'Analyze', 'Reports'];
            var sortedCategories = Object.keys(grouped).sort(function(a, b) {
                var aIdx = categoryOrder.indexOf(a);
                var bIdx = categoryOrder.indexOf(b);
                if (aIdx === -1) aIdx = 999;
                if (bIdx === -1) bIdx = 999;
                return aIdx - bIdx;
            });

            sortedCategories.forEach(function(category) {
                html += '<div class="spotlight-category">';
                html += '<div class="spotlight-category-title">' + self.escapeHtml(category) + '</div>';

                grouped[category].forEach(function(item) {
                    var isSelected = globalIndex === self.selectedIndex;
                    html += '<div class="spotlight-result-item' + (isSelected ? ' selected' : '') + '" ' +
                            'data-url="' + self.escapeHtml(item.url) + '" data-index="' + globalIndex + '">';
                    html += '<i class="' + self.escapeHtml(item.icon || 'icon-file') + ' spotlight-item-icon"></i>';
                    html += '<div class="spotlight-item-content">';
                    html += '<span class="spotlight-item-title">' + self.escapeHtml(item.title) + '</span>';
                    html += '</div>';
                    html += '<i class="icon-arrow-right spotlight-item-arrow"></i>';
                    html += '</div>';
                    globalIndex++;
                });

                html += '</div>';
            });

            $container.html(html);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        handleKeyNavigation: function(e) {
            var maxIndex = this.filteredResults.length - 1;

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.selectedIndex = Math.min(this.selectedIndex + 1, maxIndex);
                    this.updateSelection();
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    this.selectedIndex = Math.max(this.selectedIndex - 1, 0);
                    this.updateSelection();
                    break;

                case 'Enter':
                    e.preventDefault();
                    if (this.selectedIndex >= 0 && this.filteredResults[this.selectedIndex]) {
                        window.location.href = this.filteredResults[this.selectedIndex].url;
                    }
                    break;
            }
        },

        updateSelection: function() {
            var $items = $('.spotlight-result-item');
            $items.removeClass('selected');
            var $selected = $items.filter('[data-index="' + this.selectedIndex + '"]');
            $selected.addClass('selected');

            // Scroll into view if needed
            if ($selected.length) {
                var $container = $('#spotlightResults');
                var containerTop = $container.scrollTop();
                var containerHeight = $container.height();
                var itemTop = $selected.position().top + containerTop;
                var itemHeight = $selected.outerHeight();

                if (itemTop < containerTop) {
                    $container.scrollTop(itemTop);
                } else if (itemTop + itemHeight > containerTop + containerHeight) {
                    $container.scrollTop(itemTop + itemHeight - containerHeight);
                }
            }
        }
    };

    // Initialize when DOM is ready
    $(document).ready(function() {
        SpotlightSearch.init();
    });

    // Expose for potential external use
    window.SpotlightSearch = SpotlightSearch;

})(jQuery);
