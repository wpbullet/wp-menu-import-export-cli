<?php

trait WPB_Menu_Import {
	/**
	 * Import menu JSON main functionality.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  string   $file   The current JSON file to import.
	 * @return array|WP_Error   Whether is imported or not.
	 */
	private function import( $file ) {
		WP_CLI::log( 'Starting import menu process...' );
		WP_CLI::warning( 'The import process might not work properly if you use --skip-plugins flag.' );

		$this->locations = get_nav_menu_locations();
		$decoded_json    = json_decode( file_get_contents( $file ), true );
		$menus           = ! is_array( $decoded_json ) ? array( $decoded_json ) : $decoded_json;

		if ( empty( $menus ) || null === $menus[0] ) {
			return new WP_Error( 'no-menus', 'The file is empty.' );
		}

		return array_map( array( $this, 'start_set_menu_item' ), $menus );
	}

	/**
	 * Start to set the menu items.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  array   $menu   The menu item container.
	 */
	private function start_set_menu_item( $menu ) {
		$this->delete_menu( $menu );

		$menu_id  = $this->get_menu_id( $menu );
		$new_menu = array();

		if ( null === $menu_id ) {
			WP_CLI::log( 'Something went wrong with "' . $menu['name'] . '" menu.' );
			return;
		}

		if ( ! isset( $menu['items'] ) || ! is_array( $menu['items'] ) ) {
			return;
		}

		foreach ( $menu['items'] as $menu_item ) {
			$menu_data_defaults  = array(
				'menu-item-title'  => isset( $menu_item['title'] ) ? $menu_item['title'] : false,
				'menu-item-status' => 'publish',
			);

			$get_method = 'get_menu_data_by_' . $menu_item['type'];

			if ( method_exists( $this, $get_method ) ) {
				$menu_data_raw = $this->$get_method( $menu_item, $menu_data_defaults );
			} else {
				WP_CLI::log( 'The submenu type "' . $menu_item['type'] . '" is not supported. The menu item "' . $menu_item['title'] . '" will be skipped.' );
				continue;
			}

			if ( empty( $menu_data_raw ) ) {
				WP_CLI::log( 'The submenu item "' . $menu_item['title'] . '" does not have any data.' );
				continue;
			}

			$menu_data         = array_merge( $menu_data_defaults, $menu_data_raw );
			$slug              = $menu_item['slug'];
			$new_menu[ $slug ] = array();

			if ( isset( $menu_item['parent'] ) ) {
				$new_menu[ $slug ]['parent']      = $menu_item['parent'];
				$menu_data['menu-item-parent-id'] = isset( $new_menu[ $menu_item['parent'] ]['id'] ) ? $new_menu[ $menu_item['parent'] ]['id'] : 0;
			}

			// Set the menu items and get the nav menu item ID.
			$new_menu[ $slug ]['id'] = wp_update_nav_menu_item( $menu_id, 0, $menu_data );

			/**
			 * If current user does not have caps to insert
			 * terms (because we are doing CLI) then we need to handle that here.
			 */
			wp_set_object_terms( $new_menu[ $slug ]['id'], array( $menu_id ), 'nav_menu' );
		}

		$this->set_menu_location( $menu_id, $menu['location'] );
	}

	/**
	 * Delete the current menu so the import won't duplicate
	 * the entries.
	 *
	 * This method functionality will work when the user
	 * pass "--overwrite" option into the WP CLI command.
	 *
	 * @since  0.1.1
	 * @access private
	 *
	 * @param  array   $menu   The current menu data.
	 */
	private function delete_menu( $menu ) {
		if ( ! $this->overwrite_menus ) {
			return;
		}

		$menu_deleted = WP_CLI::runcommand( 'menu delete ' . $menu['slug'], array(
			'return'     => true,
			'exit_error' => false,
		) );

		if ( ! strpos( $menu_deleted, 'Success' ) ) {
			return;
		}

		WP_CLI::warning('The menu "' . $menu['name'] . '" was overwritten.');
	}

	/**
	 * Set the menu locations.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  integer               $menu_id    The current menu id.
	 * @param  string|int|boolean    $location   The current menu location.
	 */
	private function set_menu_location( $menu_id, $location ) {
		if ( is_bool( $location ) ) {
			return;
		}

		$locations              = get_theme_mod( 'nav_menu_locations' );
		$locations[ $location ] = $menu_id;

		set_theme_mod( 'nav_menu_locations', $locations );
	}

	/**
	 * Get menu data by custom url.
	 *
	 * @since  0.1.0
	 * @since  0.1.1   Get the menu advanced properties.
	 *
	 * @access private
	 *
	 * @param  array   $menu_item   The current menu item data.
	 * @param  array   $defaults    The default values.
	 * @return array                The menu data.
	 */
	private function get_menu_data_by_custom( $menu_item, $defaults ) {
		$url              = $menu_item['url'];
		$basic_properties = array(
			'menu-item-url'   => 'http' === substr( $url, 0, 4 ) ? esc_url( $url ) : home_url( $url ),
			'menu-item-title' => $defaults['menu-item-title'] ?: $menu_item['url'],
		);

		return array_merge( $basic_properties, $this->get_advanced_menu_properties( $menu_item ) );
	}

	/**
	 * Get menu data by taxonomy.
	 *
	 * @since  0.1.0
	 * @since  0.1.1   Get the menu advanced properties.
	 * @since  0.1.2   Find the term object by id instead of name.
	 *
	 * @access private
	 *
	 * @param  array   $menu_item   The current menu item data.
	 * @param  array   $defaults    The default values.
	 * @return array                The menu data.
	 */
	private function get_menu_data_by_taxonomy( $menu_item, $defaults ) {
		$term = get_term_by( 'id', $menu_item['term'], $menu_item['taxonomy'] );

		if ( ! $term ) {
			return array();
		}

		$basic_properties = array(
			'menu-item-type'      => 'taxonomy',
			'menu-item-object'    => $term->taxonomy,
			'menu-item-object-id' => $term->term_id,
			'menu-item-title'     => $defaults['menu-item-title'] ?: $term->name,
		);

		return array_merge( $basic_properties, $this->get_advanced_menu_properties( $menu_item ) );
	}

	/**
	 * Get menu data by post type.
	 *
	 * @since  0.1.0
	 * @since  0.1.1   Get the menu advanced properties.
	 *
	 * @access private
	 *
	 * @param  array   $menu_item   The current menu item data.
	 * @param  array   $defaults    The default values.
	 * @return array                The menu data.
	 */
	private function get_menu_data_by_post_type( $menu_item, $defaults ) {
		$pages = get_posts( array(
			'name'        => $menu_item['page'],
			'post_type'   => $menu_item['post_type'],
			'post_status' => 'publish',
			'numberposts' => 1,
		) );

		if ( empty( $pages ) ) {
			return array();
		}

		$basic_properties = array(
			'menu-item-type'      => 'post_type',
			'menu-item-object'    => 'page',
			'menu-item-object-id' => $pages[0]->ID,
			'menu-item-title'     => $defaults['menu-item-title'] ?: $pages[0]->post_title,
		);

		return array_merge( $basic_properties, $this->get_advanced_menu_properties( $menu_item ) );
	}

	/**
	 * Get the menu advanced properties.
	 *
	 * Those properties are: Link Target, Title Attribute, CSS Classes,
	 * Link Relationship (XFN), and Description.
	 *
	 * These properties are inside of the Screen Options box.
	 *
	 * @since  0.1.1
	 * @access private
	 *
	 * @param  array   $menu_item   The current menu item data.
	 * @return array                The current menu advanced properties.
	 */
	private function get_advanced_menu_properties( $menu_item ) {
		return array(
			'menu-item-target'      => $menu_item['target'],
			'menu-item-attr-title'  => $menu_item['attr_title'],
			'menu-item-description' => $menu_item['description'],
			'menu-item-classes'     => implode( ' ', $menu_item['classes'] ),
			'menu-item-xfn'         => $menu_item['xfn'],
		);
	}

	/**
	 * Get the current menu id.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  array   $menu           The current menu to get the id.
	 * @return integer|WP_Error|null   The current menu id.
	 */
	private function get_menu_id( $menu ) {
		$menu_id = null;

		if ( ! is_bool( $menu['location'] ) && isset( $menu['location'], $this->locations[ $menu['location'] ] ) ) {
			$location_id     = $this->locations[ $menu['location'] ];
			$nav_menu_object = wp_get_nav_menu_object( $location_id );

			if ( $nav_menu_object ) {
				return $this->locations[ $menu['location'] ];
			}
		}

		if ( isset( $menu['name'] ) ) {
			$nav_menu_object = wp_get_nav_menu_object( $menu['name'] );
			$menu_id         = $nav_menu_object ? $nav_menu_object->term_id : wp_create_nav_menu( $menu['name'] );
		}

		return $menu_id;
	}
}
