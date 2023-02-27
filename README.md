Menu (module for Omeka S)
=========================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Menu] is a module for [Omeka S] that allows to display multiple menus in a
site, for example a top menu, a sidebar menu and a footer menu, or any structure
anywhere.

Furthermore, it adds two new menu element types, that can be used in the main
navigation too:
- Resource, that allows to add any resource as a menu element and take care of
  its rights (it is not displayed if the user has no right to see it);
- Structure, that inserts an element without link, that is useful to create a
  separator or a sub-menu.

**Warning**: The theme should be modified to display new menus.


Installation
------------

See general end user documentation for [installing a module].

The optional module [Generic] may be installed first.

The module uses an external library, so use the release zip to install it, or
use and init the source.

* From the zip

Download the last release [Menu.zip] from the list of releases (the master does
not contain the dependency), and uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Menu`, go to the root module, and run:

```sh
composer install --no-dev
```


Quick start
-----------

To display a second menu, it should be included in the right place in templates
of the theme with `<?= $this->navMenu($menuName, $options) ?>`.

If no menu name is specified, it displays the default menu of the site.

Supported options:
- `template` (string): template to use (default: "common/menu").
- `site` (SiteRepresentation): use a menu from another site.
- `menu` (array): use any arbitrary menu or sub-menu instead of the name one.
- `render` (string): render as "menu" (default) or "breadcrumbs".
- `activeUrl` (null|array|string|bool) Set the active url.
  - null (default): use the Laminas mechanism (compare with route);
  - true: use current url;
  - false: no active page;
  - string: If a url is set (generally a relative one), it will be checked
    against the real url;
  - array: when an array with keys "type" and "data" is set, a quick check is
    done against the menu element.
- `noNav` (bool): don't prepare nav (for performance and manual build).

Other options are passed to the template, in particular the ones managed by the
Laminas view helper [Navigation]. The Laminas helper `menu()` can be used too.

Note that the active url should generally be set when using multiple linked
resources, because it is not managed directly by default.


TODO
----

- [x] Convert to a standard Laminas navigation, in particular to manage rights better.
- [x] Include breadcrumbs from module [Next].
- [ ] Normalize breadcrumbs.
- [ ] Replace the standard menu?
- [ ] Add the right sidebar to select resources more easily.
- [ ] Add a block layout.
- [ ] Create a dynamic menu from resources (like Thesaurus, but without separate selection).
- [ ] Create a link "Resource as page", so a dynamic page for a resource, so the page item/show will be a site page browsable like any page.
- [ ] Store resource ids as integer, like page ids.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2021-2023 (see [Daniel-KM])

These features are built for the future digital library [Le Ménestrel],
currently managed with [Spip].


[Menu]: https://github.com/Daniel-KM/Omeka-S-module-Menu
[Omeka S]: https://omeka.org/s
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Menu.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Menu/-/releases
[Navigation]: https://docs.laminas.dev/laminas-navigation/helpers/menu
[Next]: https://github.com/Daniel-KM/Omeka-S-module-Next
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Menu/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Le Ménestrel]: http://www.menestrel.fr
[Spip]: https://spip.net
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
