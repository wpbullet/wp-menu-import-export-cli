WP-CLI Export & Import Menu
=========================

You can export and import menus for WordPress using WP-CLI.

To export a menu you just need to execute this command:

`wp wpb-menu export <file>`
  
And to import a menu, this command:

`wp wpb-menu import <file>`

The `export` and `import` commands only supports JSON files.

Usage
========

Just execute to export the menu into a JSON file:

`wp wpb-menu export my_filename.json`

This will generate a JSON file that contains your exported menu.

To import the menu you just need to:

`wp wpb-menu import my_filename.json`
