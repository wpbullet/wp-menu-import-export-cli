wp-cli/wp-menu-import-export-cli
================================

Forked and updated from https://github.com/lgedeon/wp-menu-import-export-cli

Export and import menus for WordPress using WP-CLI.

Quick links: [Export](#wp-menu-export) | [Import](#wp-menu-import)

## Installation

~~~~
wp package install https://github.com/wpbullet/wp-menu-import-export-cli.git
~~~~

## Using

This package implements the following commands:

### wp menu export

Export a WordPress Menu.

~~~~
wp menu export <menu>... [--all] [--filename[=<value>]]
~~~~

**OPTIONS**

    <menu>...
		The name, slug or term ID for the menu(s).

	[--all]
		Export all WordPress menus.

	[--filename[=<value>]]
		Specify the file name.

**EXAMPLES**

     # Export all menus.
     $ wp menu export --all
     
     # Export menus by name.
     $ wp menu export "My Awesome Menu" "Mobile Menu"
     
     # Export menus by term id.
     $ wp menu export 80 81 82
     
     # Export menus by slug.
     $ wp menu export menu-slug-1 menu-slug-2
     
     # Export all menus with a custom file name.
     $ wp menu export --all --filename="custom-filename.json"

**WARNING**

`<menu>` option and `--all` flag cannot be used together.

### wp menu import

Import a WordPress Menu.

~~~~
wp menu import <file> [--overwrite]
~~~~

**OPTIONS**

    <file>
		The exported menu JSON file.
		
    [--overwrite]
            Overwrite the existent menus.

**EXAMPLES**

     # Import a menu.
     $ wp menu import my-menu.json
     
     # Import a menu with overriding the existent ones.
     $ wp menu import my-menu.json --overwrite

## Limitations

Custom links used in menu items will not be magically turned into the local domain's version since there is no way to determine if the custom links belong to the local site or a foreign one. You will have to do a search and replace on them instead.
