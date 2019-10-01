<?php

trait WPB_Menu_Export {
	/**
	 * Main export functionality.
	 *
	 * Before start it will run some conditions to validate the
	 * passed options in the command.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  array   $args         The required arguments.
	 * @param  array   $assoc_args   The flags arguments.
	 * @return bool|int|WP_Error     Errors or file size if success.
	 */
	private function export( $args, $assoc_args ) {
		if ( empty( $args ) && ! isset( $assoc_args['all'] ) ) {
			return new WP_Error( 'menu-not-specified', 'You must specify a menu or use --all flag.' );
		}

		if ( ! empty( $args ) && isset( $assoc_args['all'] ) ) {
			return new WP_Error( 'wrong-params-usage', 'You can\'t export all menus when specifying single menus.' );
		}

		if ( isset( $assoc_args['filename'] ) && ( empty( $assoc_args['filename'] ) || is_bool( $assoc_args['filename'] ) ) ) {
			return new WP_Error( 'filename-empty', 'The filename flag is empty.' );
		}

		WP_CLI::log( 'Starting menu export process...' );

		$locations = get_nav_menu_locations();
		$exporter  = array();
		$menus     = $this->get_menus( $args, $assoc_args );

		if ( empty( $menus ) ) {
			return new WP_Error( 'no-menus', 'There are no menus to export.' );
		}

		foreach ( $menus as $menu ) {
			$items       = wp_get_nav_menu_items( $menu );
			$export_menu = array(
				'location' => array_search( $menu->term_id, $locations, true ),
				'name'     => $menu->name,
				'slug'     => $menu->slug,
			);

			foreach ( $items as $item ) {
				$export_item = array(
					'slug'   => $item->ID,
					'parent' => $item->menu_item_parent,
					'title'  => $item->title,
					'type'   => $item->type,
				);

				switch ( $item->type ) {
					case 'custom':
						$export_item['url'] = $item->url;
						break;
					case 'post_type':
						$post                     = get_post( $item->object_id );
						$export_item['page']      = $post->post_name;
						$export_item['post_type'] = $post->post_type;
						break;
					case 'taxonomy':
						$term                    = get_term( $item->object_id, $item->object );
						$export_item['taxonomy'] = $term->taxonomy;
						$export_item['term']     = $term->term_id;
						break;
				}

				$export_menu['items'][] = $export_item;
			}

			$exporter[] = $export_menu;
		}

		$filename = isset( $assoc_args['filename'] ) ? $assoc_args['filename'] : $this->get_default_filename();

		WP_CLI::log( 'Writing to file ' . getcwd() . '/' . $filename );

		return file_put_contents( $filename, json_encode( $exporter ) );
	}

	/**
	 * Get the menus to be exported.
	 *
	 * If user specify "--all" flag, it will export all menus but
	 * if any menu is specified will only return that object.
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @param  array   $raw_menus    The raw menus to be exported.
	 * @param  array   $assoc_args   The flags arguments.
	 * @return array                 The menus to be exported.
	 */
	private function get_menus( $raw_menus, $assoc_args ) {
		if ( empty( $raw_menus ) && isset( $assoc_args['all'] ) ) {
			return wp_get_nav_menus();
		}

		$menus = array();

		foreach ( $raw_menus as $raw_menu ) {
			// $raw_menu could be a name, slug or term id for the menu(s).
			$menu = wp_get_nav_menu_object( $raw_menu );

			if ( ! $menu ) {
				WP_CLI::log( 'Menu Export: The menu "' . $raw_menu . '" does not exist.' );
				continue;
			}

			$menus[] = $menu;
		}

		return $menus;
	}

	/**
	 * Get the default filename.
	 * The filename structure is {hostname}-exported-menu-{todayDate}.json
	 *
	 * @since  0.1.0
	 * @access private
	 *
	 * @return string   Default filename.
	 */
	private function get_default_filename() {
		return wp_parse_url( home_url() )['host'] . '-exported-menu-' . date( 'Y-m-d' ) . '.json';
	}
}
