<?php
/**
 * Shortcodes: Navigator Support Functions.
 *
 * @since      3.0.0
 * @package    Wordlift
 * @subpackage Wordlift/shortcodes
 */

/**
 * The Navigator data function.
 *
 * @since 3.0.0
 */
function wl_shortcode_navigator_data() {

	// Post ID must be defined
	if ( ! isset( $_GET['post_id'] ) ) {
		wp_die( 'No post_id given' );

		return;
	}

	$current_post_id = $_GET['post_id'];
	$current_post    = get_post( $current_post_id );

	// Post ID has to match an existing item
	if ( null === $current_post ) {
		wp_die( 'No valid post_id given' );

		return;
	}

	// prepare structures to memorize other related posts
	$results          = array();
	$blacklist_ids    = array( $current_post_id );
	$related_entities = array();


	$relation_service = Wordlift_Relation_Service::get_instance();

	// Get the related entities, ordering them by WHO, WHAT, WHERE, WHEN 
	// TODO Replace with a single query if it is possible
	// We select in inverse order to give priority to less used entities 
	foreach (
		array(
			WL_WHEN_RELATION,
			WL_WHERE_RELATION,
			WL_WHAT_RELATION,
			WL_WHO_RELATION,
		) as $predicate
	) {

		$related_entities = array_merge( $related_entities,
			$relation_service->get_objects( $current_post_id, '*', $predicate, 'publish' )
//			wl_core_get_related_entities( $current_post_id, array(
//					'predicate' => $predicate,
//					'status'    => 'publish',
//				)
		);

	}

	foreach ( $related_entities as $related_entity ) {

		// take the id of posts referencing the entity
		$referencing_posts = Wordlift_Relation_Service::get_instance()->get_article_subjects( $related_entity->ID, '*', null, 'publish' );

		// loop over them and take the first one which is not already in the $related_posts
		foreach ( $referencing_posts as $referencing_post ) {

			if ( ! in_array( $referencing_post->ID, $blacklist_ids ) ) {

				$blacklist_ids[]   = $referencing_post->ID;
				$serialized_entity = wl_serialize_entity( $related_entity );

				/**
				 * Use the thumbnail.
				 *
				 * @see https://github.com/insideout10/wordlift-plugin/issues/825 related issue.
				 * @see https://github.com/insideout10/wordlift-plugin/issues/837
				 *
				 * @since 3.19.3 We're using the medium size image.
				 */
				$thumbnail = get_the_post_thumbnail_url( $referencing_post, 'medium' );

				if ( $thumbnail ) {

					$results[] = array(
						'post'   => array(
							'permalink' => get_post_permalink( $referencing_post->ID ),
							'title'     => $referencing_post->post_title,
							'thumbnail' => $thumbnail,
						),
						'entity' => array(
							'label'     => $serialized_entity['label'],
							'mainType'  => $serialized_entity['mainType'],
							'permalink' => get_post_permalink( $related_entity->ID ),
						),
					);

					// Be sure no more than 1 post for entity is returned
					break;
				}
			}
		}
	}

	// Return first 4 results in json accordingly to 4 columns layout
	return array_slice( array_reverse( $results ), 0, 4 );

}

/**
 * The Navigator Ajax function.
 *
 * @since 3.20.0
 */
function wl_shortcode_navigator_ajax() {

	$results = wl_shortcode_navigator_data();
	wl_core_send_json($results);

}

add_action( 'wp_ajax_wl_navigator', 'wl_shortcode_navigator_ajax' );
add_action( 'wp_ajax_nopriv_wl_navigator', 'wl_shortcode_navigator_ajax' );

/**
 * wp-json call for the navigator widget
 */
function wl_shortcode_navigator_wp_json() {

	$results = wl_shortcode_navigator_data();
	if ( ob_get_contents() ) {
		ob_clean();
	}
	return array(
		'items' => array( 
			array('values' => $results)
		)
	);

}

/**
 * Adding `rest_api_init` action for amp backend of navigator
 */
add_action( 'rest_api_init', function () {
	register_rest_route( WL_REST_ROUTE_DEFAULT_NAMESPACE, '/navigator', array(
	  'methods' => 'GET',
	  'callback' => 'wl_shortcode_navigator_wp_json',
	) );
} );
