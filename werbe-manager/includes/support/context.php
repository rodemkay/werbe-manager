<?php
namespace Werbe_Manager\Support;

/**
 * Gathers request context for slot resolution.
 */
class Context {

	/**
	 * Build context array from the current request plus overrides.
	 */
	public function from_request( array $overrides = array() ): array {
		$post_id   = get_queried_object_id();
		$brand_meta = $post_id ? get_post_meta( $post_id, '_wm_brand', true ) : '';
		$post_type = $post_id ? get_post_type( $post_id ) : null;
		$taxonomy_terms = array(
			'categories' => $post_id ? wp_get_post_categories( $post_id, array( 'fields' => 'slugs' ) ) : array(),
			'tags'       => $post_id ? wp_get_post_terms( $post_id, 'post_tag', array( 'fields' => 'slugs' ) ) : array(),
		);

		$context = array(
			'post_id'          => $post_id,
			'post_type'        => $post_type,
			'is_front_page'    => is_front_page(),
			'is_home'          => is_home(),
			'url'              => home_url( add_query_arg( array(), $_SERVER['REQUEST_URI'] ?? '' ) ),
			'categories'       => $taxonomy_terms['categories'],
			'tags'             => $taxonomy_terms['tags'],
			'brand'            => sanitize_key( $brand_meta ),
			'overrides'        => $overrides,
		);

		return $context;
	}
}
