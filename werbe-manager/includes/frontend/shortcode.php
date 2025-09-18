<?php
namespace Werbe_Manager\Frontend;

use Werbe_Manager\Support\Context;

/**
 * Registers the [promo_slot] shortcode.
 */
class Shortcode {

	private Resolver $resolver;

	private Context $context;

	public function __construct( Resolver $resolver, ?Context $context = null ) {
		$this->resolver = $resolver;
		$this->context  = $context ?: new Context();
	}

	public function register(): void {
		add_shortcode( 'promo_slot', array( $this, 'handle_shortcode' ) );
	}

	public function handle_shortcode( $atts, $content = '' ): string {
		$atts = shortcode_atts(
			array(
				'slot'             => '',
				'format'           => '',
				'shuffle'          => 'day',
				'brand_include'    => '',
				'brand_exclude'    => '',
				'category_include' => '',
				'category_exclude' => '',
				'tag_include'      => '',
				'tag_exclude'      => '',
				'include_ids'      => '',
				'exclude_ids'      => '',
				'fallback'         => $content,
			),
			$atts,
			'promo_slot'
		);

		$slot = sanitize_key( $atts['slot'] );
		if ( ! $slot ) {
			return ''; // slot required
		}

		wp_enqueue_style( 'werbe-manager-unit' );
		$context = $this->context->from_request( $atts );

		$html = $this->resolver->resolve( $slot, $context, $atts );

		if ( '' === trim( $html ) ) {
			return do_shortcode( $atts['fallback'] );
		}

		return $html;
	}
}
