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
    var tree = $('#nav-tree');
    if (!tree.jstree) return;

    const isEdit = $('#nav-tree').data('link-form-url') && $('#nav-tree').data('link-form-url').length > 0;

    var initialTreeData;

    tree
        .jstree({
            'core': {
                'check_callback': true,
                'force_text': true,
                'data': tree.data('jstree-data'),
            },
            // Plugins jstree and omeka (jstree-plugins).
            'plugins': isEdit
                ? ['dnd', 'removenode', 'editlink', 'display']
                : ['display']
        })
        .on('loaded.jstree', function() {
            // Close all nodes by default.
            tree.jstree(true).close_all();
            initialTreeData = JSON.stringify(tree.jstree(true).get_json());
        })
        .on('move_node.jstree', function(e, data) {
            // Open parent node after moving it.
            var parent = tree.jstree(true).get_node(data.parent);
            tree.jstree(true).open_all(parent);
        });

    $('#site-form')
        .on('o:before-form-unload', function () {
            if (initialTreeData !== JSON.stringify(tree.jstree(true).get_json())) {
                Omeka.markDirty(this);
            }
        });

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
