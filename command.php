<?php

/**
 * Import functionality trait.
 */
require 'includes/trait-import.php';

/**
 * Export functionality trait.
 */
require 'includes/trait-export.php';

/**
 * Import Menu Class.
 */
class WPB_Import_Menu_Command extends WP_CLI_Command {
	/**
	 * Import menu functionality.
	 */
	use WPB_Menu_Import;

	/**
	 * Start menu import using WP-CLI.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The exported menu JSON file.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import a menu.
	 *     $ wp menu import my-menu.json
	 */
	public function __invoke( $args ) {
		list( $file ) = $args;

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'File to import doesn\'t exist.' );
		}

		$menu_imported = $this->import( $file );

		if ( is_wp_error( $menu_imported ) ) {
			WP_CLI::error( $menu_imported->get_error_message() );
		}

		WP_CLI::line();
		WP_CLI::success( 'The import was successful.' );
	}
}

/**
 * Export Menu Class.
 */
class WPB_Export_Menu_Command extends WP_CLI_Command {
	/**
	 * Export menu functionality.
	 */
	use WPB_Menu_Export;

    /**
     * Export a WordPress Menu.
	 *
     * ## OPTIONS
     *
     * <menu>...
     * : The name, slug or term ID for the menu(s).
     *
     * [--all]
     * : Export all WordPress menus.
     *
     * [--filename[=<value>]]
     * : Specify the file name.
     *
     * ## EXAMPLES
     *
     *     # Export all menus.
     *     $ wp menu export --all
     *
     *     # Export menus by name.
     *     $ wp menu export "My Awesome Menu" "Mobile Menu"
     *
     *     # Export menus by term id.
     *     $ wp menu export 80 81 82
     *
     *     # Export menus by slug.
     *     $ wp menu export menu-slug-1 menu-slug-2
     *
     *     # Export all menus with a custom file name.
     *     $ wp menu export --all --filename="custom-filename.json"
     */
    public function __invoke( $args, $assoc_args ) {
	    $menu_exported = $this->export( $args, wp_parse_args( $assoc_args ) );

		if ( is_wp_error( $menu_exported ) ) {
			WP_CLI::error( $menu_exported->get_error_message() );
		}

	    WP_CLI::line();
	    WP_CLI::success( 'The menu export was successful.' );
	}
}

$export_synopsis = array(
	'synopsis' => array(
		'name'     => 'menu',
		'optional' => true,
	),
);

WP_CLI::add_command( 'menu import', 'WPB_Import_Menu_Command' );
WP_CLI::add_command( 'menu export', 'WPB_Export_Menu_Command', $export_synopsis );
