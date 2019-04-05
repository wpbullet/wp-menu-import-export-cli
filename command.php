<?php

class WPB_Menu_Command extends WP_CLI_Command {

    /**
     * Handle menu import cli command and call import_json() to import menu content from a json file.
	 *
	 * Still soo much to do:
	 * - (maybe) support pasting a json object on the commandline instead of file name
	 * - (maybe) incorporate into main cli import and export commands
	 * - add wp-admin ui to call functions without setting up wp-cli
	 * - support mode and missing parameters
     *
     * ## OPTIONS
     *
     * <file>
     * : Path to a valid json file for importing.
	 *
	 * json object should be in the form:
	 * [
	 *   {
	 *     "location" : "theme location menu should be assigned to (optional)",
	 *     "name" : "Menu Name Required",
	 *     "items" :
	 *     [
	 *       {
	 *         "slug" : "only-required-for-nested-menu--used-to-link-to-parent",
	 *         "parent" : "parent-menu-item-slug--parent-must-be-defined-before-children",
	 *         "title" : "Not always required but highly recommended",
	 *         "page" : "slug/path--only-if-menu-points-to-page",
	 *         "taxonomy" : "only_if_pointing_to_term",
	 *         "term" : "the Term not the slug",
	 *         "url" : "http://domain.com/fully/qualified/" OR "/relative/"
	 *       },
	 *       { ... additional menu items ... }
	 *     ]
	 *   },
	 *   { ... additional menus ... }
	 * ]
	 *
	 * <mode>
	 * : update = matching menus and menu items overwritten. skip = matching items skipped, missing items skipped. append = matching skipped, new items added
	 *
     * <missing>
     * : Method for handling missing objects pointed to by menu. Can be 'create', 'skip', 'default'.
	 *
	 * <default>
	 * : page to point to if matching slug isn't found. If default slug doesn't exist either menu items will be skipped.
     *
     * @synopsis <file> [--mode=<mode>] [--missing=<missing>] [--default=<default>]
     */

    public function import ( $args, $assoc_args ) {
        list( $file ) = $args;

        if ( ! file_exists( $file ) )
            WP_CLI::error( "File to import doesn't exist." );

        $defaults = array(
            'missing' => 'skip',
            'default' => null,
        );
        $assoc_args = wp_parse_args( $assoc_args, $defaults );

		$ret = $this->import_json( $file, $assoc_args['missing'], $assoc_args['default'] );

		if ( is_wp_error( $ret ) ) {
			WP_CLI::error( $ret->get_error_message() );
		} else {
			WP_CLI::line();
			WP_CLI::success( "Import complete." );
		}
	}

	/**
	 * Import menu content from a json file.
	 *
	 * @param string $file Name of json file to import. (might allow just passing the json string here later)
	 * @param string $mode - not yet implemented
	 * @param string $missing - not yet implemented
	 * @param string $default - not yet implemented
	 */
	public function import_json( $file, $mode = 'append', $missing = 'skip', $default = null ) {
		$string      = file_get_contents( $file );

		$json_menus = json_decode( $string );

		// $json object may contain a single menu definition object or array of menu objects
		if ( ! is_array( $json_menus ) ) {
			$json_menus = array( $json_menus );
		}

		$locations = get_nav_menu_locations();

		foreach ( $json_menus as $menu ) :
			if ( isset( $menu->location ) && isset( $locations[ $menu->location ] ) ) :
				$menu_id = $locations[ $menu->location ];
			elseif ( isset( $menu->name ) ) :
				// If we can't find a menu by this name, create one.
				if ( $menu_object = wp_get_nav_menu_object( $menu->name ) ) :
					$menu_id = $menu_object->term_id;
				else :
					$menu_object = wp_create_nav_menu( $menu->name );
					if ( isset( $menu_object->term_id ) ) {
						$menu_id = $menu_object->term_id;
					} else {
						continue;
					}
				endif;
			else : // if no location or name is supplied, we have nowhere to put any additional info in this object.
				continue;
			endif;

			$new_menu = array();

			if ( isset ( $menu->items ) && is_array( $menu->items ) ) : foreach ( $menu->items as $item ) :

				// merge in existing items here

				// Build $item_array from supplied data
				$item_array = array(
					'menu-item-title' => ( isset( $item->title ) ? $item->title : false ),
					'menu-item-status' => 'publish'
				);

				if ( isset( $item->page ) && $page = get_page_by_path( $item->page ) ) { // @todo support lookup by title
					$item_array['menu-item-type']      = 'post_type';
					$item_array['menu-item-object']    = 'page';
					$item_array['menu-item-object-id'] = $page->ID;
					$item_array['menu-item-title']     = ( $item_array['menu-item-title'] ) ?: $page->post_title;
				} elseif ( isset ( $item->taxonomy ) && isset( $item->term ) && $term = get_term_by( 'name', $item->term, $item->taxonomy ) ) {
					$item_array['menu-item-type']      = 'taxonomy';
					$item_array['menu-item-object']    = $term->taxonomy;
					$item_array['menu-item-object-id'] = $term->term_id;
					$item_array['menu-item-title'] = ( $item_array['menu-item-title'] ) ?: $term->name;
				} elseif ( isset( $item->url ) ) {
					$item_array['menu-item-url']   = ( 'http' == substr( $item->url, 0, 4 ) ) ? esc_url( $item->url ) : home_url( $item->url );
					$item_array['menu-item-title'] = ( $item_array['menu-item-title'] ) ?: $item->url;
				} else {
					continue;
				}

				$slug  = isset( $item->slug ) ? $item->slug : sanitize_title_with_dashes( $item_array['menu-item-title'] );
				$new_menu[$slug] = array();

				if ( isset( $item->parent ) ) {
					$new_menu[$slug]['parent']         = $item->parent;
					$item_array['menu-item-parent-id'] = isset( $new_menu[ $item->parent ]['id'] ) ? $new_menu[ $item->parent ]['id'] : 0 ;
				}

				$new_menu[$slug]['id'] = wp_update_nav_menu_item($menu_id, 0, $item_array );

				// if current user doesn't have caps to insert term (because we are doing cli) then we need to handle that here
				wp_set_object_terms( $new_menu[$slug]['id'], array( (int) $menu_id ), 'nav_menu' );

			endforeach; endif;


		endforeach;
	}

    /**
     * Handle menu export cli command and call export_json() to export menu content to a json file.
	 *
     * ## OPTIONS
     *
     * <file>
     * : Path to export to.
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

    public function export ( $args, $assoc_args ) {
        list( $file ) = $args;

        $defaults = array(
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
						$export_item['url'] = $item->url;
						break;
					case 'post_type':
						if ( 'page' == $item->object ) {
							$page = get_post( $item->object_id );
							$export_item['page'] = $page->post_name;
						}
						break;
					case 'taxonomy':
						$term = get_term( $item->object_id, $item->object );
						$export_item['taxonomy'] = $term->taxonomy;
						$export_item['term']     = $term->name;
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

WP_CLI::add_command( 'wpb-menu', new WPB_Menu_Command );
