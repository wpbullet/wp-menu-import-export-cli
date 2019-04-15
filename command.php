<?php

/**
 * Import functionality trait.
 */
require 'includes/import/trait-import.php';

/**
 * Export and import main class.
 */
class WPB_Menu_Command extends WP_CLI_Command {
	/**
	 * Import menu functionality.
	 */
	use WPB_Menu_Import;

	/**
	 * Import the exported menu JSON file.
	 *
	 * @param $args
	 */
    public function import( $args ) {
	    list( $file ) = $args;

        if ( ! file_exists( $file ) ) {
	        WP_CLI::error( 'File to import doesn\'t exist.' );
        }

		$is_imported = $this->start_import( $file );

		if ( is_wp_error( $is_imported ) ) {
			WP_CLI::error( $is_imported->get_error_message() );
			return;
		}

	    WP_CLI::line();
	    WP_CLI::success( 'The import was successful.' );
	}

    /**
     * Start menu export using WP-CLI. The result will be a JSON file.
	 *
     * ## OPTIONS
     *
     * <file>
     * : File name to export the menu data as JSON.
     *
     * (wp menu export <menu-name>)
     * (wp menu export --all)
     *
     * [--all]
     * : Export all WordPress menus.
     *
     * [--menus[=<value>]]
     * : Specify the menu to be exported into the JSON file.
     *
     * ## EXAMPLES
     * wp menu export exported-menu.json
	 *
	 * json object will be in the form:
	 * [
	 *   {
	 *     "location" : "theme location if menu has been assigned to one",
	 *     "name" : "Menu Name",
	 *     "slug" : "menu-slug",
	 *     "items" :
	 *     [
	 *       {
	 *         "slug" : "tracks-nesting-of-menu-items",
	 *         "parent" : "parent-menu-item-slug",
	 *         "title" : "The Title Says It All",
	 *         "page" : "only-if-menu-points-to-page",
	 *         "taxonomy" : "only_if_pointing_to_term",
	 *         "term" : "the Term",
	 *         "url" : "http://domain.com/"
	 *       },
	 *       { ... additional menu items ... }
	 *     ]
	 *   },
	 *   { ... additional menus ... }
	 * ]
	 *
	 * <mode>
	 * : absolute or relative
     *
     * @synopsis <file> [--mode=<mode>]
     */
    public function export( $args, $assoc_args ) {
        list( $file ) = $args;

        $defaults   = array(
            'mode' => 'absolute',
        );
        $assoc_args = wp_parse_args( $assoc_args, $defaults );
		$ret = $this->export_json( $file, $assoc_args['mode'] );

		if ( is_wp_error( $ret ) ) {
			WP_CLI::error( $ret->get_error_message() );
		} else {
			WP_CLI::line();
			WP_CLI::success( "Export complete." );
		}
	}

	/**
	 * Export menu content to a json file.
	 *
	 * @param string $file Name of file to export to.
	 * @param string $mode - not yet implemented
	 */
	public function export_json( $file, $mode = 'relative' ) {

		$locations = get_nav_menu_locations();
		$menus     = wp_get_nav_menus();
		$exporter  = array();

		foreach ( $menus as $menu ) :
			$export_menu = array(
				'location' => array_search( $menu->term_id, $locations ),
				'name'     => $menu->name,
				'slug'     => $menu->slug
			);

			$items = wp_get_nav_menu_items( $menu );
			foreach ( $items as $item ) :
				$export_item = array(
					'slug'   => $item->ID,
					'parent' => $item->menu_item_parent,
					'title'  => $item->title,
				);

				switch ( $item->type ) :
					case 'custom':
						$export_item['type'] = 'custom';
						$export_item['url'] = $item->url;
						break;
					case 'post_type':
						if ( 'page' == $item->object ) {
							$page = get_post( $item->object_id );
							$export_item['type']     = 'post_type';
							$export_item['page'] = $page->post_name;
						}
						break;
					case 'taxonomy':
						$term = get_term( $item->object_id, $item->object );
						$export_item['taxonomy'] = $term->taxonomy;
						$export_item['type']     = 'taxonomy';
						$export_item['term']     = $term->term_id;
						break;
				endswitch;

				$export_menu['items'][] = $export_item;
			endforeach;

			$exporter[] = $export_menu;
		endforeach;

		$json_menus = json_encode( $exporter );

		$size = file_put_contents( $file, $json_menus );

		return $size;
	}
}

WP_CLI::add_command( 'wpb-menu', 'WPB_Menu_Command');
