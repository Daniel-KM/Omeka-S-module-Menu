$(document).ready( function() {

    // Browse batch actions.
    // Kept as long as pull request #1260 is not passed.
    $('.select-all, .batch-edit td input[type=checkbox]').change(function() {
        var selectedOptions = $('[value="delete-selected"], #batch-form .batch-inputs .batch-selected');
        if ($('.batch-edit td input[type=checkbox]:checked').length > 0) {
            selectedOptions.removeAttr('disabled');
        } else {
            selectedOptions.attr('disabled', true);
            $('.batch-actions-select').val('default');
            $('.batch-actions .active').removeClass('active');
            $('.batch-actions .default').addClass('active');
        }
    });
    // Complete the batch delete form after confirmation.
    $('#confirm-delete-selected').on('submit', function(e) {
        var confirmForm = $(this);
        $('#batch-form').find('input[name="menus[]"]:checked:not(:disabled)').each(function() {
            confirmForm.append($(this).clone().prop('disabled', false).attr('type', 'hidden'));
        });
    });
    $('.delete-selected').on('click', function(e) {
        Omeka.closeSidebar($('#sidebar-delete-all'));
        var inputs = $('input[name="menus[]"]');
        $('#delete-selected-count').text(inputs.filter(':checked').length);
    });

    // Initialize the menu structure.
    const tree = $('#nav-tree');
    if (!tree.jstree) return;

    const isEdit = $('#nav-tree').data('link-form-url') && $('#nav-tree').data('link-form-url').length > 0;

    var initialTreeData;

    /**
     * Display element plugin for jsTree.
     * Adapted from jstree-plugins to add a link to admin page.
     */
    $.jstree.plugins.displayElements = function(options, parent) {
       // Use a <i> instead of a <a> because inside a <a>.
        // Link to public side.
        var displayIconPublic = $('<i>', {
            class: 'jstree-icon jstree-displaylink link-public',
            attr:{role: 'presentation'}
        });
       // Link to admin resource.
        var displayIconAdmin = $('<i>', {
            class: 'jstree-icon jstree-displaylink link-admin',
            attr:{role: 'presentation'}
        });
        var displayIconPrivate = $('<span>', {
            class: 'o-icon-private',
            attr:{'aria-label': Omeka.jsTranslate('Private')},
        });
        // TODO Manage CleanUrl.
        // TODO Manage site page admin link.
        const regexPublicToAdmin = /(.*)\/s\/[a-zA-Z0-9_-]+\/((?:item|item-set|media|resource|value-annotation|annotation)\/[a-zA-Z0-9_-]+)/gm;
        this.bind = function() {
            parent.bind.call(this);
            this.element
                .on(
                    'click.jstree',
                    '.jstree-displaylink',
                    $.proxy(function(e) {
                        var icon = $(e.currentTarget);
                        var node = icon.closest('.jstree-node');
                        var nodeObj = this.get_node(node);
                        var nodeUrl = nodeObj.data.url;
                        // The url is public by default, so replace the /s/site/" by "/admin/".
                        if (e.currentTarget.classList.contains('link-admin')) {
                            nodeUrl = nodeObj.data.url.replace(regexPublicToAdmin, `$1/admin/$2`);
                        }
                        window.open(nodeUrl, '_blank');
                    }, this)
                );
        };
        this.redraw_node = function(node, deep, is_callback, force_render) {
            node = parent.redraw_node.apply(this, arguments);
            if (node) {
                var nodeObj = this.get_node(node);
                if (nodeObj.data) {
                    var nodeJq = $(node);
                    var anchor = nodeJq.children('.jstree-anchor');
                    var anchorClone;
                    var nodeUrl;
                    if (nodeObj.data.data && nodeObj.data.data.is_public === false) {
                        anchorClone = displayIconPrivate.clone();
                        anchor.append(anchorClone);
                    }
                    if (nodeObj.data.url) {
                        nodeUrl = nodeObj.data.url;
                        anchorClone = displayIconPublic.clone();
                        anchorClone.attr('title', '[public] ' + nodeObj.data.type + ' #' + nodeObj.data.data.id);
                        anchor.append(anchorClone);
                        let nodeUrlAdmin = nodeUrl.replace(regexPublicToAdmin, `$1/admin/$2`);
                        if (nodeUrlAdmin !== nodeUrl) {
                            anchorClone = displayIconAdmin.clone();
                            anchorClone.attr('title', '[admin] ' + nodeObj.data.type + ' #' + nodeObj.data.data.id);
                            anchor.append(anchorClone);
                        }
                    }
                }
            }
            return node;
        };
    };

    tree
        .jstree({
            core: {
                check_callback: true,
                force_text: true,
                // Get jstree data from attributes when an error occurs (not yet saved).
                data: tree.data('jstree-data')
                    ? tree.data('jstree-data')
                    : {
                        // Only an url for the root node.
                        url: $('#nav-tree').data('jstree-url'),
                    },
            },
            // Plugins jstree, omeka (jstree-plugins) or above.
            plugins: isEdit
                ? ['privateStatus', 'dnd', 'removenode', 'editlink', 'displayElements']
                : ['privateStatus', 'displayElements']
        })
        .on('loaded.jstree', function() {
            // Close all nodes by default.
            tree.jstree(true).close_all();
            // Don't store node state open/closed, since it's not stored.
            initialTreeData = JSON.stringify(tree.jstree(true).get_json(null, {no_state: true, no_a_attr: true, no_li_attr: true}));
        })
        .on('move_node.jstree', function(e, data) {
            // Open parent node after moving it.
            var parent = tree.jstree(true).get_node(data.parent);
            tree.jstree(true).open_all(parent);
        });

    $('#site-form')
        .on('o:before-form-unload', function () {
            if (initialTreeData !== JSON.stringify(tree.jstree(true).get_json(null, {no_state: true, no_a_attr: true, no_li_attr: true}))) {
                Omeka.markDirty(this);
            }
        });

    // Manage drag and drop from nav selector for custom links or page links.
    // @see https://www.jstree.com/api/#/?q=dnd ; https://jsfiddle.net/fvnn4c2a/665 ; https://developer.mozilla.org/en-US/docs/Web/API/DragEvent ; https://www.editcode.net/thread-277923-1-1.html
    // Store the dragged element.
    document.getElementById('nav-selector').addEventListener('dragstart', function(e) {
        const type = e.target.getAttribute('data-type');
        var data = type === 'page'
            ? {
                label: e.target.getAttribute('data-label'),
                id: e.target.getAttribute('data-id'),
                slug: e.target.getAttribute('data-slug'),
                is_public: e.target.getAttribute('data-is_public'),
            }
            : {};
        var navLink = {
            text: e.target.textContent,
            data: {
                type: type,
                value: e.target.getAttribute('data-value'),
                data: data,
            }
        };
        e.dataTransfer.setData('navLink', JSON.stringify(navLink));
    }, false);

    // Required to enable dropping and to prevend issue.
    document.getElementById('nav-tree').addEventListener('dragover', function(e) {
        e.preventDefault();
    }, false);

    // Append the nav link to the target tree.
    document.getElementById('nav-tree').addEventListener('drop', function(e) {
        if (typeof e.dataTransfer === 'object') {
            var navLink = JSON.parse(e.dataTransfer.getData('navLink'));
            if (navLink) {
                e.preventDefault();
                e.stopPropagation();
                const jstree = tree.jstree(true);
                const nodeTargetId = $(e.target).closest('.jstree-node').attr('id');
                const nodeTarget = jstree.get_node(nodeTargetId);
                var targetParent = $('#' + nodeTargetId).parent();
                var position = targetParent.children().index($('#' + nodeTargetId)) + 1;
                if (nodeTarget.parent === '#') {
                    targetParent = '#';
                }
                const nodeId = jstree.create_node(targetParent, navLink, position);
                // There cannot be duplicate page links in navigation. Remove
                // page links from the available list after they are added.
                // TODO Factorize with jstree-plugins (editlink).
                if (navLink.data.type === 'page') {
                    $('.nav-page-link[data-id="' + navLink.data.id + '"]').hide();
                    var pageLinks = $('#nav-page-links');
                    if (!pageLinks.children('.nav-page-link').filter(':visible').length) {
                        pageLinks.siblings('.page-selector-filter').hide();
                        pageLinks.after('<p>' + Omeka.jsTranslate('There are no available pages.') + '</p>');
                    }
                }
                jstree.toggleLinkEdit($('#' + nodeId));
                return false;
            }
        }
    }, false);

    var filterPages = function() {
        var thisInput = $(this);
        var search = thisInput.val().toLowerCase();
        var allPages = $('#nav-page-links .nav-page-link');
        allPages.hide();
        var results = allPages.filter(function() {
            return $(this).attr('data-label').toLowerCase().indexOf(search) >= 0;
        });
        results.show();
    };

    $('.page-selector-filter').on('keyup', (function() {
        var timer = 0;
        return function() {
            clearTimeout(timer);
            timer = setTimeout(filterPages.bind(this), 400);
        }
    })());

    $('#menu-open-all').on('click', function () {
        tree.jstree(true).open_all();
    });

    $('#menu-close-all').on('click', function () {
        tree.jstree(true).close_all();
    });

});
