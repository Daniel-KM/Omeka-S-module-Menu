$(document).ready( function() {

const isEdit = $('#nav-tree').data('link-form-url').length > 0;

// Initialize the menu structure.
var tree = $('#nav-tree');
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
        // Open all nodes by default.
        tree.jstree(true).open_all();
        initialTreeData = JSON.stringify(tree.jstree(true).get_json());
    })
    .on('move_node.jstree', function(e, data) {
        // Open node after moving it.
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

});
