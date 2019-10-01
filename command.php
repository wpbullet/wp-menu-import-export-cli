<?php

// Menu import functionality trait.
require 'includes/trait-import.php';

// Menu export functionality trait.
require 'includes/trait-export.php';

/**
 * Import a WordPress menus from the exported JSON file
 * that generated the "wp menu export" command.
 *
 * @since 0.1.0
 */
class WPB_Import_Menu_Command extends WP_CLI_Command {
	// Menu import functionality trait.
	use WPB_Menu_Import;

	/**
	 * Contains all menu locations.
	 *
	 * @since  0.1.0
	 * @access protected
	 *
	 * @var    array   $locations   The menu locations.
	 */
	protected $locations;

	/**
	 * Whether to overwrite the menus.
	 *
	 * @since  0.1.0
	 * @access protected
	 *
	 * @var    boolean   $overwrite_menus   Whether to overwrite the menus.
	 */
	protected $overwrite_menus;

	/**
	 * Start menu import using WP-CLI.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The exported menu JSON file.
	 *
	 * [--overwrite]
	 * : Overwrite the existent menus.
	 *
	 * ## EXAMPLES
	 *
	 *     # Import a menu.
	 *     $ wp menu import my-menu.json
	 *
	 *     # Import a menu with overriding the existent ones.
	 *     $ wp menu import my-menu.json --overwrite
	 */
	public function __invoke( $args, $assoc_args ) {
		list( $file ) = $args;

		if ( ! file_exists( $file ) ) {
			WP_CLI::error( 'File to import doesn\'t exist.' );
		}

		$args = wp_parse_args( $assoc_args );

		$this->overwrite_menus = isset( $args['overwrite'] );

		$menu_imported = $this->import( $file );

		if ( is_wp_error( $menu_imported ) ) {
			WP_CLI::error( $menu_imported->get_error_message() );
		}

		WP_CLI::line();
		WP_CLI::success( 'The import was successful.' );
	}
}

/**
 * Export the WordPress menus into a JSON file that will
 * be used later to import.
 *
 * @since 0.1.0
 */
class WPB_Export_Menu_Command extends WP_CLI_Command {
	// Menu import functionality trait.
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
