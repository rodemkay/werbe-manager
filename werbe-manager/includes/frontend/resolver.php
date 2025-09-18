<?php
namespace Werbe_Manager\Frontend;

use Werbe_Manager\DB\Repository;

/**
 * Resolves slots to creatives based on context and campaign rules.
 */
class Resolver {

	private Repository $repository;

	public function __construct( \Werbe_Manager\Support\Context $context ) {
		$this->repository = new Repository();
	}

	/**
	 * Resolve a slot to HTML output.
	 */
	public function resolve( string $slot_slug, array $context, array $overrides ): string {
		$slot = $this->repository->get_slot_by_slug( $slot_slug );

		if ( ! $slot ) {
			return '';
		}

		global $werbe_manager_rendered_campaigns;
		if ( ! is_array( $werbe_manager_rendered_campaigns ) ) {
			$werbe_manager_rendered_campaigns = array();
		}

		$shuffle             = $overrides['shuffle'] ?? 'day';
		list( $cache_key, $ttl ) = $this->cache_signature( $slot_slug, $context, $overrides, $shuffle );
		$cache_group         = 'werbe_manager';

		$cached = wp_cache_get( $cache_key, $cache_group );
		if ( is_array( $cached ) && isset( $cached['campaign_id'], $cached['creative_id'] ) ) {
			$html = $this->render_cached_selection( $slot, (int) $cached['campaign_id'], (int) $cached['creative_id'], $context, $overrides );
			if ( '' !== $html ) {
				if ( ! in_array( (int) $cached['campaign_id'], $werbe_manager_rendered_campaigns, true ) ) {
		$werbe_manager_rendered_campaigns[] = (int) $cached['campaign_id'];
	}
				return $html;
			}
			wp_cache_delete( $cache_key, $cache_group );
		}

		$campaigns = $this->repository->get_campaigns_for_slot( (int) $slot['id'] );
		$eligible  = array();

		foreach ( $campaigns as $campaign ) {
			$campaign_id = (int) ( $campaign['id'] ?? 0 );
			if ( in_array( $campaign_id, $werbe_manager_rendered_campaigns, true ) ) {
				continue;
			}

			if ( $this->is_campaign_eligible( $campaign, $slot, $context, $overrides ) ) {
				$campaign['effective_weight'] = max( 1, (int) ( $campaign['link_weight'] ?? $campaign['weight'] ?? 1 ) );
				$eligible[]                   = $campaign;
			}
		}

		if ( empty( $eligible ) ) {
			return '';
		}

		$campaign_pick = $this->weighted_pick( $eligible, $cache_key . '|campaign' );
		if ( ! $campaign_pick ) {
			return '';
		}

		$creatives = $this->repository->get_creatives_for_campaign( (int) $campaign_pick['id'] );
		if ( empty( $creatives ) ) {
			return '';
		}

		$creative_pick = $this->deterministic_pick( $creatives, $cache_key . '|creative' );
		if ( ! $creative_pick ) {
			return '';
		}

		$html = $this->render_output( $slot, $campaign_pick, $creative_pick );

		if ( '' === $html ) {
			wp_cache_delete( $cache_key, $cache_group );
			return '';
		}

		$wp_cache_value = array(
			'campaign_id' => (int) $campaign_pick['id'],
			'creative_id' => (int) $creative_pick['id'],
		);
		wp_cache_set( $cache_key, $wp_cache_value, $cache_group, $ttl );

		if ( ! in_array( (int) $campaign_pick['id'], $werbe_manager_rendered_campaigns, true ) ) {
		$werbe_manager_rendered_campaigns[] = (int) $campaign_pick['id'];
	}

		return $html;
	}

	private function render_cached_selection( array $slot, int $campaign_id, int $creative_id, array $context, array $overrides ): string {
		$campaign = $this->repository->get_campaign( $campaign_id );
		$creative = $this->repository->get_creative( $creative_id );

		if ( ! $campaign || ! $creative ) {
			return '';
		}

		if ( ! $this->is_campaign_eligible( $campaign, $slot, $context, $overrides ) ) {
			return '';
		}

		return $this->render_output( $slot, $campaign, $creative );
	}

	private function render_output( array $slot, array $campaign, array $creative ): string {
		$payload = json_decode( $creative['payload'] ?? '[]', true );
		if ( ! is_array( $payload ) ) {
			return '';
		}

		$content = $this->render_creative_content( $creative['type'] ?? 'html', $payload );
		if ( '' === trim( $content ) ) {
			return '';
		}

		$slot_class     = sanitize_html_class( 'promo-slot--' . ( $slot['slug'] ?? 'slot' ) );
		$campaign_class = sanitize_html_class( 'promo-campaign--' . ( $campaign['slug'] ?? 'campaign' ) );

		return sprintf(
			'<section class="promo-slot %1$s %2$s"><div class="promo-unit">%3$s</div></section>',
			esc_attr( $slot_class ),
			esc_attr( $campaign_class ),
			$content
		);
	}

	private function render_creative_content( string $type, array $payload ): string {
		switch ( $type ) {
			case 'shortcode':
				return $this->render_shortcode_creative( $payload );
			case 'image':
				return $this->render_image_creative( $payload );
			case 'html':
			default:
				return $this->render_html_creative( $payload );
		}
	}

	private function is_campaign_eligible( array $campaign, array $slot, array $context, array $overrides ): bool {
		if ( 'active' !== ( $campaign['status'] ?? 'active' ) ) {
			return false;
		}

		$now = current_datetime();

		if ( ! empty( $campaign['start_at'] ) && $now < new \DateTimeImmutable( $campaign['start_at'] ) ) {
			return false;
		}

		if ( ! empty( $campaign['end_at'] ) && $now > new \DateTimeImmutable( $campaign['end_at'] ) ) {
			return false;
		}

		$slot_format = $slot['format'] ?? 'third';
		$formats     = $this->csv_to_array( $campaign['formats'] ?? '' );
		if ( $formats && ! in_array( $slot_format, $formats, true ) ) {
			return false;
		}

		$post_type  = $context['post_type'] ?? null;
		$post_types = $this->csv_to_array( $campaign['post_types'] ?? '' );
		if ( $post_types && $post_type && ! in_array( $post_type, $post_types, true ) ) {
			return false;
		}

		if ( ! $this->match_tax_filters( $campaign, $context, $overrides ) ) {
			return false;
		}

		if ( ! $this->match_post_filters( $campaign, $context, $overrides ) ) {
			return false;
		}

		if ( ! $this->match_brands( $campaign, $context, $overrides ) ) {
			return false;
		}

		if ( ! empty( $campaign['url_like'] ) && ! empty( $context['url'] ) ) {
			$pattern = str_replace( '%', '.*', preg_quote( $campaign['url_like'], '/' ) );
			if ( ! preg_match( '/' . $pattern . '/i', $context['url'] ) ) {
				return false;
			}
		}

		return true;
	}

	private function match_tax_filters( array $campaign, array $context, array $overrides ): bool {
		$cats_include = $this->csv_to_array( $campaign['categories_include'] ?? '' );
		$cats_exclude = $this->csv_to_array( $campaign['categories_exclude'] ?? '' );
		$tags_include = $this->csv_to_array( $campaign['tags_include'] ?? '' );
		$tags_exclude = $this->csv_to_array( $campaign['tags_exclude'] ?? '' );

		$override_cats_include = $this->csv_to_array( $overrides['category_include'] ?? '' );
		$override_cats_exclude = $this->csv_to_array( $overrides['category_exclude'] ?? '' );
		$override_tags_include = $this->csv_to_array( $overrides['tag_include'] ?? '' );
		$override_tags_exclude = $this->csv_to_array( $overrides['tag_exclude'] ?? '' );

		$context_cats = $context['categories'] ?? array();
		$context_tags = $context['tags'] ?? array();

		if ( $override_cats_include ) {
			$cats_include = $cats_include ? array_intersect( $cats_include, $override_cats_include ) : $override_cats_include;
		}
		if ( $override_tags_include ) {
			$tags_include = $tags_include ? array_intersect( $tags_include, $override_tags_include ) : $override_tags_include;
		}

		if ( $override_cats_exclude ) {
			$cats_exclude = array_unique( array_merge( $cats_exclude, $override_cats_exclude ) );
		}
		if ( $override_tags_exclude ) {
			$tags_exclude = array_unique( array_merge( $tags_exclude, $override_tags_exclude ) );
		}

		if ( $cats_include && ! array_intersect( $cats_include, $context_cats ) ) {
			return false;
		}

		if ( $cats_exclude && array_intersect( $cats_exclude, $context_cats ) ) {
			return false;
		}

		if ( $tags_include && ! array_intersect( $tags_include, $context_tags ) ) {
			return false;
		}

		if ( $tags_exclude && array_intersect( $tags_exclude, $context_tags ) ) {
			return false;
		}

		return true;
	}

	private function match_post_filters( array $campaign, array $context, array $overrides ): bool {
		$post_id = (int) ( $context['post_id'] ?? 0 );

		$include = array_filter( array_map( 'intval', $this->csv_to_array( $campaign['include_post_ids'] ?? '' ) ) );
		$exclude = array_filter( array_map( 'intval', $this->csv_to_array( $campaign['exclude_post_ids'] ?? '' ) ) );

		$override_include = array_filter( array_map( 'intval', $this->csv_to_array( $overrides['include_ids'] ?? '' ) ) );
		$override_exclude = array_filter( array_map( 'intval', $this->csv_to_array( $overrides['exclude_ids'] ?? '' ) ) );

		if ( $override_include ) {
			$include = $include ? array_intersect( $include, $override_include ) : $override_include;
		}

		if ( $override_exclude ) {
			$exclude = array_unique( array_merge( $exclude, $override_exclude ) );
		}

		if ( $include && ! in_array( $post_id, $include, true ) ) {
			return false;
		}

		if ( $exclude && in_array( $post_id, $exclude, true ) ) {
			return false;
		}

		return true;
	}

	private function match_brands( array $campaign, array $context, array $overrides ): bool {
		$include_campaign = $this->csv_to_array( $campaign['brands_include'] ?? '' );
		$exclude_campaign = $this->csv_to_array( $campaign['brands_exclude'] ?? '' );

		$include_override = $this->csv_to_array( $overrides['brand_include'] ?? '' );
		$exclude_override = $this->csv_to_array( $overrides['brand_exclude'] ?? '' );

		$context_brand = $context['brand'] ?? '';
		$context_brands = $context_brand ? array( $context_brand ) : array();

		if ( $exclude_override && $include_campaign && array_intersect( $exclude_override, $include_campaign ) ) {
			return false;
		}

		if ( $exclude_campaign && $include_override && array_intersect( $exclude_campaign, $include_override ) ) {
			return false;
		}

		if ( $exclude_campaign && $context_brands && array_intersect( $exclude_campaign, $context_brands ) ) {
			return false;
		}

		if ( $exclude_override && $context_brands && array_intersect( $exclude_override, $context_brands ) ) {
			return false;
		}

		$effective_include = $include_campaign;
		if ( $include_override ) {
			$effective_include = $effective_include ? array_intersect( $effective_include, $include_override ) : $include_override;
		}

		if ( $effective_include ) {
			if ( $context_brands ) {
				return (bool) array_intersect( $effective_include, $context_brands );
			}

			// Ohne Kontextbrand muss mindestens ein Override vorhanden sein.
			return (bool) $include_override;
		}

		return true;
	}

	private function weighted_pick( array $campaigns, string $seed ): ?array {
		$total = array_sum( array_map( static fn( $c ) => max( 1, (int) $c['effective_weight'] ), $campaigns ) );
		if ( $total <= 0 ) {
			return null;
		}

		$random = $this->deterministic_float( $seed );
		$acc    = 0.0;

		foreach ( $campaigns as $campaign ) {
			$portion = max( 1, (int) $campaign['effective_weight'] ) / $total;
			$acc    += $portion;
			if ( $random <= $acc ) {
				return $campaign;
			}
		}

		return end( $campaigns ) ?: null;
	}

	private function deterministic_pick( array $items, string $seed ): ?array {
		if ( empty( $items ) ) {
			return null;
		}

		$index = (int) floor( $this->deterministic_float( $seed ) * count( $items ) );
		$index = max( 0, min( $index, count( $items ) - 1 ) );

		return $items[ $index ] ?? null;
	}

	private function deterministic_float( string $seed ): float {
		$hash = substr( sha1( $seed ), 0, 12 );
		$int  = hexdec( $hash );

		return ( $int % 1000000 ) / 1000000; // 6-stellige Auflösung
	}

	private function render_shortcode_creative( array $payload ): string {
		$slug = isset( $payload['slug'] ) ? sanitize_key( $payload['slug'] ) : '';
		if ( ! $slug || ! shortcode_exists( $slug ) ) {
			return '';
		}

		$atts = $payload['atts'] ?? array();
		$parts = array();
		foreach ( $atts as $key => $value ) {
			$parts[] = sprintf( '%s="%s"', esc_attr( $key ), esc_attr( $value ) );
		}

		$shortcode = sprintf( '[%1$s%2$s]', $slug, $parts ? ' ' . implode( ' ', $parts ) : '' );

		return do_shortcode( $shortcode );
	}

	private function render_image_creative( array $payload ): string {
		$src = esc_url( $payload['src'] ?? '' );
		if ( ! $src ) {
			return '';
		}

		$alt   = esc_attr( $payload['alt'] ?? '' );
		$class = sanitize_html_class( $payload['class'] ?? 'promo-image' );
		$img   = sprintf( '<img src="%s" alt="%s" class="%s" />', $src, $alt, $class );

		if ( ! empty( $payload['href'] ) ) {
			$href   = esc_url( $payload['href'] );
			$target = ! empty( $payload['target'] ) ? esc_attr( $payload['target'] ) : '_blank';
			$rel    = esc_attr( $payload['rel'] ?? 'nofollow sponsored noopener' );
			$img    = sprintf( '<a href="%s" target="%s" rel="%s">%s</a>', $href, $target, $rel, $img );
		}

		return $img;
	}

	private function render_html_creative( array $payload ): string {
		$html = $payload['html'] ?? '';

		return wp_kses_post( $html );
	}

	private function csv_to_array( ?string $csv ): array {
		if ( ! $csv ) {
			return array();
		}

		$parts = array_filter( array_map( 'trim', explode( ',', $csv ) ) );

		return array_values( array_unique( $parts ) );
	}

	private function cache_signature( string $slot_slug, array $context, array $overrides, string $shuffle ): array {
		$window = $this->shuffle_window( $shuffle );
		$hash_input = array(
			'slot'       => $slot_slug,
			'window'     => $window,
			'post_id'    => $context['post_id'] ?? 0,
			'post_type'  => $context['post_type'] ?? '',
			'categories' => $context['categories'] ?? array(),
			'tags'       => $context['tags'] ?? array(),
			'brand'      => $context['brand'] ?? '',
			'overrides'  => array(
				'brand_include'    => $overrides['brand_include'] ?? '',
				'brand_exclude'    => $overrides['brand_exclude'] ?? '',
				'category_include' => $overrides['category_include'] ?? '',
				'category_exclude' => $overrides['category_exclude'] ?? '',
				'tag_include'      => $overrides['tag_include'] ?? '',
				'tag_exclude'      => $overrides['tag_exclude'] ?? '',
				'include_ids'      => $overrides['include_ids'] ?? '',
				'exclude_ids'      => $overrides['exclude_ids'] ?? '',
			),
		);

		$key = 'wm_' . md5( wp_json_encode( $hash_input ) );
		$ttl = $this->cache_ttl( $shuffle );

		return array( $key, $ttl );
	}

	private function shuffle_window( string $shuffle ): string {
		switch ( $shuffle ) {
			case 'hour':
				return gmdate( 'Y-m-d-H' );
			case 'session':
				return $this->session_id();
			case 'none':
				return wp_generate_uuid4();
			case 'day':
			default:
				return gmdate( 'Y-m-d' );
		}
	}

	private function cache_ttl( string $shuffle ): int {
		switch ( $shuffle ) {
			case 'hour':
				return HOUR_IN_SECONDS;
			case 'session':
				return DAY_IN_SECONDS;
			case 'none':
				return MINUTE_IN_SECONDS;
			case 'day':
			default:
				return DAY_IN_SECONDS;
		}
	}

	private function session_id(): string {
		$cookie = 'wm_session';
		if ( isset( $_COOKIE[ $cookie ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) );
		}

		$session = wp_generate_uuid4();
		setcookie( $cookie, $session, time() + DAY_IN_SECONDS * 30, COOKIEPATH ?: '/', COOKIE_DOMAIN, is_ssl(), true );

		return $session;
	}
}
