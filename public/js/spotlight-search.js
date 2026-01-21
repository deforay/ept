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
        entityResults: [],
        searchTimeout: null,
        expandedEntityIndex: -1,
        selectedActionIndex: -1,
        isSearching: false,
        maxHistoryItems: 5,

        init: function() {
            this.bindEvents();
            this.createResultsCache();
        },

        getStorageKey: function(type) {
            // User-specific localStorage key (domain is already handled by localStorage)
            var userId = window.spotlightUserId || 'default';
            var suffix = type === 'shipments' ? '_shipments' : '';
            return 'spotlightHistory_' + userId + suffix;
        },

        getHistory: function() {
            try {
                var history = JSON.parse(localStorage.getItem(this.getStorageKey())) || [];
                // Filter to only items that still exist in current spotlightData
                var validIds = this.searchData.map(function(item) { return item.id; });
                return history.filter(function(h) {
                    return validIds.indexOf(h.id) !== -1;
                });
            } catch (e) {
                return [];
            }
        },

        getShipmentHistory: function() {
            try {
                return JSON.parse(localStorage.getItem(this.getStorageKey('shipments'))) || [];
            } catch (e) {
                return [];
            }
        },

        addToHistory: function(itemId) {
            try {
                var history = this.getHistory();
                // Remove if already exists (to move to top)
                history = history.filter(function(h) { return h.id !== itemId; });
                // Add to beginning
                history.unshift({ id: itemId, timestamp: Date.now() });
                // Keep only max items
                history = history.slice(0, this.maxHistoryItems);
                localStorage.setItem(this.getStorageKey(), JSON.stringify(history));
            } catch (e) {
                // localStorage not available or quota exceeded
            }
        },

        addShipmentToHistory: function(shipmentCode, shipmentId) {
            try {
                var history = this.getShipmentHistory();
                // Remove if already exists (to move to top)
                history = history.filter(function(h) { return h.code !== shipmentCode; });
                // Add to beginning
                history.unshift({ code: shipmentCode, id: shipmentId, timestamp: Date.now() });
                // Keep only max items
                history = history.slice(0, this.maxHistoryItems);
                localStorage.setItem(this.getStorageKey('shipments'), JSON.stringify(history));
            } catch (e) {
                // localStorage not available or quota exceeded
            }
        },

        getRecentItems: function() {
            var self = this;
            var history = this.getHistory();
            var recentItems = [];

            history.forEach(function(h) {
                var item = self.searchData.find(function(i) { return i.id === h.id; });
                if (item) {
                    recentItems.push(item);
                }
            });

            return recentItems;
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
                    if (self.expandedEntityIndex >= 0) {
                        // Collapse expanded entity first
                        self.collapseEntity();
                    } else {
                        self.close();
                    }
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

            // Click on menu result item
            $(document).on('click', '.spotlight-result-item:not(.spotlight-entity-item)', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                var itemId = $(this).data('item-id');
                if (itemId) {
                    self.addToHistory(itemId);
                }
                if (url) {
                    window.location.href = url;
                }
            });

            // Click on entity item to expand
            $(document).on('click', '.spotlight-entity-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var index = $(this).data('entity-index');
                if (self.expandedEntityIndex === index) {
                    self.collapseEntity();
                } else {
                    self.expandEntity(index);
                }
            });

            // Click on action item
            $(document).on('click', '.spotlight-action-item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var url = $(this).data('url');
                var shipmentCode = $(this).closest('.spotlight-entity-actions').prev('.spotlight-entity-item').find('.spotlight-item-title').text();
                var shipmentId = $(this).closest('.spotlight-entity-actions').prev('.spotlight-entity-item').data('entity-id');
                if (shipmentCode) {
                    self.addShipmentToHistory(shipmentCode, shipmentId);
                }
                if (url) {
                    self.navigateToUrl(url);
                }
            });

            // Click on recent shipment item to search for it
            $(document).on('click', '.spotlight-recent-shipment', function(e) {
                e.preventDefault();
                var code = $(this).data('shipment-code');
                if (code) {
                    $('#spotlightInput').val(code);
                    self.search(code);
                }
            });

            // Hover on menu result
            $(document).on('mouseenter', '.spotlight-result-item:not(.spotlight-entity-item)', function() {
                self.expandedEntityIndex = -1;
                self.selectedActionIndex = -1;
                self.selectedIndex = $(this).data('index');
                self.updateSelection();
            });

            // Hover on entity item
            $(document).on('mouseenter', '.spotlight-entity-item', function() {
                self.selectedIndex = -1;
                self.selectedActionIndex = -1;
                // Keep expanded state
            });

            // Hover on action item
            $(document).on('mouseenter', '.spotlight-action-item', function() {
                self.selectedActionIndex = $(this).data('action-index');
                self.updateActionSelection();
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
            this.entityResults = [];
            this.expandedEntityIndex = -1;
            this.selectedActionIndex = -1;
            $('#spotlightModal').fadeIn(150);
            $('#spotlightInput').val('').focus();
            this.showDefaultResults();
            $('body').addClass('spotlight-open');
        },

        close: function() {
            this.isOpen = false;
            this.expandedEntityIndex = -1;
            this.selectedActionIndex = -1;
            clearTimeout(this.searchTimeout);
            $('#spotlightModal').fadeOut(150);
            $('#spotlightInput').val('');
            $('#spotlightResults').empty();
            $('body').removeClass('spotlight-open');
        },

        showDefaultResults: function() {
            var self = this;

            // Get recent menu items
            var recentItems = this.getRecentItems();

            // Get recent shipments
            var recentShipments = this.getShipmentHistory();

            // Get quick actions - find items that are in the Quick Actions category
            var quickActionsCategory = (window.spotlightTranslations && window.spotlightTranslations.quickActions) || 'Quick Actions';
            var quickActions = this.searchData.filter(function(item) {
                return item.category === quickActionsCategory;
            });

            // If no quick actions found, show first 8 items as default
            if (quickActions.length === 0 && recentItems.length === 0 && recentShipments.length === 0) {
                this.filteredResults = this.searchData.slice(0, 8);
                this.entityResults = [];
                this.renderResults();
                return;
            }

            // Combine: recent first (marked with special category), then quick actions
            this.filteredResults = [];

            // Add recent menu items with "Recent" category
            var recentCategory = (window.spotlightTranslations && window.spotlightTranslations.recent) || 'Recent';
            if (recentItems.length > 0) {
                recentItems.forEach(function(item) {
                    self.filteredResults.push($.extend({}, item, { category: recentCategory }));
                });
            }

            // Add recent shipments as clickable items
            if (recentShipments.length > 0) {
                var recentShipmentsCategory = (window.spotlightTranslations && window.spotlightTranslations.recentShipments) || 'Recent Shipments';
                recentShipments.forEach(function(shipment) {
                    self.filteredResults.push({
                        id: 'recent-shipment-' + shipment.code,
                        title: shipment.code,
                        category: recentShipmentsCategory,
                        icon: 'icon-truck',
                        isRecentShipment: true,
                        shipmentCode: shipment.code
                    });
                });
            }

            // Add quick actions (excluding any that are already in recent)
            var recentIds = recentItems.map(function(i) { return i.id; });
            quickActions.forEach(function(item) {
                if (recentIds.indexOf(item.id) === -1) {
                    self.filteredResults.push(item);
                }
            });

            this.entityResults = [];
            this.renderResults();
        },

        search: function(query) {
            var self = this;
            query = query.toLowerCase().trim();

            // Reset entity expansion
            this.expandedEntityIndex = -1;
            this.selectedActionIndex = -1;

            if (!query) {
                clearTimeout(this.searchTimeout);
                this.entityResults = [];
                this.showDefaultResults();
                return;
            }

            // Filter static menu items
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
            this.renderResults();

            // Async search for entities (debounced)
            clearTimeout(this.searchTimeout);
            if (query.length >= 2) {
                this.searchTimeout = setTimeout(function() {
                    self.searchEntities(query);
                }, 300);
            } else {
                this.entityResults = [];
            }
        },

        searchEntities: function(query) {
            var self = this;
            var baseUrl = window.spotlightBaseUrl || '';

            this.isSearching = true;
            this.renderResults(); // Show loading state

            $.ajax({
                url: baseUrl + '/admin/spotlight/search',
                data: { q: query },
                dataType: 'json',
                success: function(data) {
                    self.entityResults = data.results || [];
                    self.isSearching = false;
                    self.renderResults();
                },
                error: function() {
                    self.entityResults = [];
                    self.isSearching = false;
                    self.renderResults();
                }
            });
        },

        expandEntity: function(index) {
            this.expandedEntityIndex = index;
            this.selectedActionIndex = 0;
            this.selectedIndex = -1;
            this.renderResults();
        },

        collapseEntity: function() {
            this.expandedEntityIndex = -1;
            this.selectedActionIndex = -1;
            this.renderResults();
        },

        renderResults: function() {
            var self = this;
            var $container = $('#spotlightResults');
            $container.empty();

            var hasMenuResults = this.filteredResults.length > 0;
            var hasEntityResults = this.entityResults.length > 0;

            if (!hasMenuResults && !hasEntityResults && !this.isSearching) {
                var noResultsText = (window.spotlightTranslations && window.spotlightTranslations.noResults) || 'No results found';
                $container.html(
                    '<div class="spotlight-no-results">' +
                    '<i class="icon-search"></i>' +
                    '<p>' + this.escapeHtml(noResultsText) + '</p>' +
                    '</div>'
                );
                return;
            }

            var html = '';
            var globalIndex = 0;

            // Render menu results grouped by category
            if (hasMenuResults) {
                var grouped = {};
                this.filteredResults.forEach(function(item) {
                    var cat = item.category || 'Other';
                    if (!grouped[cat]) grouped[cat] = [];
                    grouped[cat].push(item);
                });

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
                        if (item.isRecentShipment) {
                            // Render recent shipment as clickable item that triggers search
                            html += '<div class="spotlight-result-item spotlight-recent-shipment' + (isSelected ? ' selected' : '') + '" ' +
                                    'data-shipment-code="' + self.escapeHtml(item.shipmentCode) + '" data-index="' + globalIndex + '">';
                            html += '<div class="spotlight-item-icon"><i class="' + self.escapeHtml(item.icon || 'icon-truck') + '"></i></div>';
                            html += '<div class="spotlight-item-content">';
                            html += '<span class="spotlight-item-title">' + self.escapeHtml(item.title) + '</span>';
                            html += '</div>';
                            html += '<i class="icon-arrow-right spotlight-item-arrow"></i>';
                            html += '</div>';
                        } else {
                            html += '<div class="spotlight-result-item' + (isSelected ? ' selected' : '') + '" ' +
                                    'data-url="' + self.escapeHtml(item.url) + '" data-index="' + globalIndex + '" data-item-id="' + self.escapeHtml(item.id) + '">';
                            html += '<div class="spotlight-item-icon"><i class="' + self.escapeHtml(item.icon || 'icon-file') + '"></i></div>';
                            html += '<div class="spotlight-item-content">';
                            html += '<span class="spotlight-item-title">' + self.escapeHtml(item.title) + '</span>';
                            html += '</div>';
                            html += '<i class="icon-arrow-right spotlight-item-arrow"></i>';
                            html += '</div>';
                        }
                        globalIndex++;
                    });

                    html += '</div>';
                });
            }

            // Render entity results (shipments)
            if (hasEntityResults || this.isSearching) {
                var shipmentsLabel = (window.spotlightTranslations && window.spotlightTranslations.shipments) || 'Shipments';
                html += '<div class="spotlight-category spotlight-entity-category">';
                html += '<div class="spotlight-category-title">' + self.escapeHtml(shipmentsLabel) + '</div>';

                if (this.isSearching && !hasEntityResults) {
                    var searchingText = (window.spotlightTranslations && window.spotlightTranslations.searching) || 'Searching...';
                    html += '<div class="spotlight-searching"><i class="icon-spinner icon-spin"></i> ' + self.escapeHtml(searchingText) + '</div>';
                }

                this.entityResults.forEach(function(entity, entityIndex) {
                    var isExpanded = entityIndex === self.expandedEntityIndex;
                    html += '<div class="spotlight-entity-item' + (isExpanded ? ' expanded' : '') + '" data-entity-index="' + entityIndex + '" data-entity-id="' + self.escapeHtml(entity.id) + '">';
                    html += '<div class="spotlight-item-icon"><i class="' + self.escapeHtml(entity.icon || 'icon-truck') + '"></i></div>';
                    html += '<div class="spotlight-item-content">';
                    html += '<span class="spotlight-item-title">' + self.escapeHtml(entity.title) + '</span>';
                    html += '<span class="spotlight-item-subtitle">' + self.escapeHtml(entity.subtitle) + '</span>';
                    html += '</div>';
                    html += '<i class="icon-chevron-' + (isExpanded ? 'down' : 'right') + ' spotlight-entity-expand"></i>';
                    html += '</div>';

                    // Render actions if expanded
                    if (isExpanded && entity.actions) {
                        html += '<div class="spotlight-entity-actions">';
                        entity.actions.forEach(function(action, actionIndex) {
                            var isActionSelected = actionIndex === self.selectedActionIndex;
                            html += '<div class="spotlight-action-item' + (isActionSelected ? ' selected' : '') + '" ' +
                                    'data-url="' + self.escapeHtml(action.url) + '" data-action-index="' + actionIndex + '">';
                            html += '<i class="' + self.escapeHtml(action.icon || 'icon-arrow-right') + ' spotlight-action-icon"></i>';
                            html += '<span class="spotlight-action-label">' + self.escapeHtml(action.label) + '</span>';
                            html += '</div>';
                        });
                        html += '</div>';
                    }
                });

                html += '</div>';
            }

            $container.html(html);
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        navigateToUrl: function(url) {
            // Open download links in new tab, navigate others normally
            if (url && url.indexOf('/d/') === 0) {
                window.open(url, '_blank');
            } else {
                window.location.href = url;
            }
        },

        handleKeyNavigation: function(e) {
            var self = this;

            // If an entity is expanded, navigate actions
            if (this.expandedEntityIndex >= 0) {
                var entity = this.entityResults[this.expandedEntityIndex];
                var maxActionIndex = entity && entity.actions ? entity.actions.length - 1 : -1;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        if (this.selectedActionIndex < maxActionIndex) {
                            this.selectedActionIndex++;
                            this.updateActionSelection();
                        }
                        break;

                    case 'ArrowUp':
                        e.preventDefault();
                        if (this.selectedActionIndex > 0) {
                            this.selectedActionIndex--;
                            this.updateActionSelection();
                        }
                        break;

                    case 'ArrowLeft':
                    case 'Escape':
                        e.preventDefault();
                        this.collapseEntity();
                        break;

                    case 'Enter':
                        e.preventDefault();
                        if (this.selectedActionIndex >= 0 && entity && entity.actions[this.selectedActionIndex]) {
                            this.navigateToUrl(entity.actions[this.selectedActionIndex].url);
                        }
                        break;
                }
                return;
            }

            // Normal navigation for menu items and entities
            var totalMenuItems = this.filteredResults.length;
            var totalEntities = this.entityResults.length;
            var maxIndex = totalMenuItems - 1;

            switch(e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    if (this.selectedIndex < maxIndex) {
                        this.selectedIndex++;
                        this.updateSelection();
                    } else if (totalEntities > 0) {
                        // Move to first entity
                        this.selectedIndex = -1;
                        this.expandEntity(0);
                    }
                    break;

                case 'ArrowUp':
                    e.preventDefault();
                    if (this.selectedIndex > 0) {
                        this.selectedIndex--;
                        this.updateSelection();
                    } else if (this.selectedIndex === 0) {
                        this.selectedIndex = -1;
                        this.updateSelection();
                    }
                    break;

                case 'ArrowRight':
                    e.preventDefault();
                    // If we have entity results and nothing selected or last menu item
                    if (totalEntities > 0 && this.selectedIndex === -1) {
                        this.expandEntity(0);
                    }
                    break;

                case 'Enter':
                    e.preventDefault();
                    if (this.selectedIndex >= 0 && this.filteredResults[this.selectedIndex]) {
                        var selectedItem = this.filteredResults[this.selectedIndex];
                        if (selectedItem.id) {
                            this.addToHistory(selectedItem.id);
                        }
                        window.location.href = selectedItem.url;
                    } else if (totalEntities > 0 && this.selectedIndex === -1) {
                        // Expand first entity
                        this.expandEntity(0);
                    }
                    break;
            }
        },

        updateSelection: function() {
            var $items = $('.spotlight-result-item:not(.spotlight-entity-item)');
            $items.removeClass('selected');
            var $selected = $items.filter('[data-index="' + this.selectedIndex + '"]');
            $selected.addClass('selected');

            // Scroll into view if needed
            if ($selected.length) {
                this.scrollIntoView($selected);
            }
        },

        updateActionSelection: function() {
            var $actions = $('.spotlight-action-item');
            $actions.removeClass('selected');
            var $selected = $actions.filter('[data-action-index="' + this.selectedActionIndex + '"]');
            $selected.addClass('selected');

            if ($selected.length) {
                this.scrollIntoView($selected);
            }
        },

        scrollIntoView: function($element) {
            var $container = $('#spotlightResults');
            var containerTop = $container.scrollTop();
            var containerHeight = $container.height();
            var itemTop = $element.position().top + containerTop;
            var itemHeight = $element.outerHeight();

            if (itemTop < containerTop) {
                $container.scrollTop(itemTop);
            } else if (itemTop + itemHeight > containerTop + containerHeight) {
                $container.scrollTop(itemTop + itemHeight - containerHeight);
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
