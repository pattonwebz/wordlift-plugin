<?php

/**
 * The `wl_navigator` implementation.
 *
 * @since 3.5.4
 */
class Wordlift_Navigator_Shortcode extends Wordlift_Shortcode {

	/**
	 * {@inheritdoc}
	 */
	const SHORTCODE = 'wl_navigator';

	/**
	 * {@inheritdoc}
	 */
	public function render( $atts ) {

		// avoid building the widget when there is a list of posts.
		if ( ! is_single() ) {
			return '';
		}

		$this->enqueue_scripts();

		return "<div data-wl-navigator='wl-navigator'></div>";
	}

	public function preview() {

		$this->enqueue_scripts();

	}

	public function enqueue_scripts() {

		// Enqueue the Navigator script.
		wp_enqueue_script( 'wordlift-navigator', dirname( plugin_dir_url( __FILE__ ) ) . '/public/js/wordlift-navigator.bundle.js', array(
			'jquery',
			'wp-util',
		), $this->plugin->get_version(), true );

		wp_localize_script( 'wordlift-navigator', 'wlNavigator', array(
				'l10n' => array( 'Read More' => _x( 'Read More', 'Navigator Widget', 'wordlift' ) ),
			)
		);

	}


}
