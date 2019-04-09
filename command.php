<?php

class WPB_Menu_Command extends WP_CLI_Command {
	/**
	 * Import the exported menu JSON file.
	 *
	 * @param $args
	 * @param $assoc_args
	 */
    public function import( $args, $assoc_args ) {
        list( $file ) = $args;

        if ( ! file_exists( $file ) ) {
	        WP_CLI::error( 'File to import doesn\'t exist.' );
        }

        $defaults    = array(
            'missing' => 'skip',
            'default' => null,
        );
        $assoc_args  = wp_parse_args( $assoc_args, $defaults );
		$is_imported = $this->import_json( $file, $assoc_args['missing'], $assoc_args['default'] );

		if ( is_wp_error( $is_imported ) ) {
			WP_CLI::error( $is_imported->get_error_message() );
			return;
		}

	    WP_CLI::line();
	    WP_CLI::success( 'The import was successful.' );
	}

	/**
	 * Import menu JSON functionality.
	 *
	 * @param $file
	 * @return bool
	 */
	private function import_json( $file ) {
		$encoded_json = file_get_contents( $file );
		$decoded_json = json_decode( $encoded_json, true );
		$locations    = get_nav_menu_locations();
		$menus        = ! is_array( $decoded_json ) ? array( $decoded_json ) : $decoded_json;

		foreach ( $menus as $menu ) {
			$menu_id  = $this->get_menu_id( $menu, $locations );
			$new_menu = array();

			if ( null === $menu_id ) {
				continue;
			}

			if ( isset( $menu['items'] ) && is_array( $menu['items'] ) ) {
				foreach ( $menu['items'] as $item ) {
					$menu_data = array(
						'menu-item-title'  => isset( $item['title'] ) ? $item['title'] : false,
						'menu-item-status' => 'publish',
					);

					if ( isset( $item['page'] ) && $page = get_page_by_path( $item['page'] ) ) {
						$menu_data['menu-item-type']      = 'post_type';
						$menu_data['menu-item-object']    = 'page';
						$menu_data['menu-item-object-id'] = $page->ID;
						$menu_data['menu-item-title']     = $menu_data['menu-item-title'] ?: $page->post_title;
					} elseif ( isset ( $item['taxonomy'] ) && isset( $item['term'] ) && $term = get_term_by( 'name', $item['term'], $item['taxonomy'] ) ) {
						$menu_data['menu-item-type']      = 'taxonomy';
						$menu_data['menu-item-object']    = $term->taxonomy;
						$menu_data['menu-item-object-id'] = $term->term_id;
						$menu_data['menu-item-title']     = $menu_data['menu-item-title'] ?: $term->name;
					} elseif ( isset( $item['url'] ) ) {
						$menu_data['menu-item-url']   = 'http' === substr( $item['url'], 0, 4 ) ? esc_url( $item['url'] ) : home_url( $item['url'] );
						$menu_data['menu-item-title'] = $menu_data['menu-item-title'] ?: $item['url'];
					} else {
						continue;
					}

					$slug              = isset( $item['slug'] ) ? $item['slug'] : sanitize_title_with_dashes( $menu_data['menu-item-title'] );
					$new_menu[ $slug ] = array();

					if ( isset( $item['parent'] ) ) {
						$new_menu[$slug]['parent']        = $item['parent'];
						$menu_data['menu-item-parent-id'] = isset( $new_menu[ $item['parent'] ]['id'] ) ? $new_menu[ $item['parent'] ]['id'] : 0;
					}

					$new_menu[ $slug ]['id'] = wp_update_nav_menu_item( $menu_id, 0, $menu_data );

					// if current user doesn't have caps to insert term (because we are doing cli) then we need to handle that here
					wp_set_object_terms( $new_menu[ $slug ]['id'], array( $menu_id ), 'nav_menu' );
				}
			}
		}

		return true;
	}

	/**
	 * Get the current menu id.
	 *
	 * @param $menu
	 * @param $locations
	 *
	 * @return int|WP_Error|null
	 */
	private function get_menu_id( $menu, $locations ) {
		$menu_id = null;

		if ( isset( $menu['location'] ) && isset( $locations[ $menu['location'] ] ) ) {
			return $locations[ $menu['location'] ];
		}

		if ( isset( $menu['name'] ) ) {
			$nav_menu_object = wp_get_nav_menu_object( $menu['name'] );
			$menu_id         = $nav_menu_object ? $nav_menu_object->term_id : wp_create_nav_menu( $menu['name'] );
		}

		return $menu_id;
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
