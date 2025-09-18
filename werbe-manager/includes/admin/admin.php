<?php
namespace Werbe_Manager\Admin;

use Werbe_Manager\DB\Repository;

/**
 * Basic admin pages for managing slots, campaigns and creatives.
 */
class Admin {

	private const MENU_SLUG = 'werbe-manager';

	private Repository $repository;

	public function __construct() {
		$this->repository = new Repository();
	}

	public function register_menu(): void {
		add_menu_page(
			__( 'Werbe Manager', 'werbe-manager' ),
			__( 'Werbe Manager', 'werbe-manager' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_page' ),
			'dashicons-megaphone',
			57
		);
	}

	public function register_settings(): void {
		// Placeholder – using manual form handling inside render_page.
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung.', 'werbe-manager' ) );
		}

		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'slots';

		$this->handle_actions( $tab );

		echo '<div class="wrap werbe-manager-admin">';
		echo '<h1>' . esc_html__( 'Werbe Manager', 'werbe-manager' ) . '</h1>';
		echo '<nav class="nav-tab-wrapper">';

		$tabs = array(
			'slots'      => __( 'Slots', 'werbe-manager' ),
			'campaigns'  => __( 'Kampagnen', 'werbe-manager' ),
			'creatives'  => __( 'Creatives', 'werbe-manager' ),
			'mapping'    => __( 'Slot-Zuordnung', 'werbe-manager' ),
		);

		foreach ( $tabs as $slug => $label ) {
			$url   = add_query_arg( array( 'page' => self::MENU_SLUG, 'tab' => $slug ), admin_url( 'admin.php' ) );
			$class = 'nav-tab' . ( $tab === $slug ? ' nav-tab-active' : '' );
			echo '<a href="' . esc_url( $url ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</a>';
		}

		echo '</nav>';

		switch ( $tab ) {
			case 'campaigns':
				$this->render_campaigns();
				break;
			case 'creatives':
				$this->render_creatives();
				break;
			case 'mapping':
				$this->render_mapping();
				break;
			case 'slots':
			default:
				$this->render_slots();
				break;
		}

		echo '</div>';
	}

	private function render_slots(): void {
		$slots = $this->repository->get_slots();
		$formats = array( 'third', 'half', 'full', 'banner', 'square' );
		?>
		<h2><?php esc_html_e( 'Slots', 'werbe-manager' ); ?></h2>
		<form method="post" class="form-table">
			<?php wp_nonce_field( 'wm_save_slot' ); ?>
			<input type="hidden" name="wm_action" value="save_slot" />
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wm-slot-slug"><?php esc_html_e( 'Slug', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-slot-slug" name="slug" class="regular-text" required pattern="[a-z0-9_-]+" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-slot-title"><?php esc_html_e( 'Titel', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-slot-title" name="title" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-slot-format"><?php esc_html_e( 'Format', 'werbe-manager' ); ?></label></th>
					<td>
						<select id="wm-slot-format" name="format">
							<?php foreach ( $formats as $format ) : ?>
								<option value="<?php echo esc_attr( $format ); ?>"><?php echo esc_html( ucfirst( $format ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-slot-post-types"><?php esc_html_e( 'Erlaubte Post Types (CSV)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-slot-post-types" name="allowed_post_types" class="regular-text" value="post,page" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-slot-css"><?php esc_html_e( 'Zusätzliche CSS-Klasse', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-slot-css" name="css_class" class="regular-text" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Slot speichern', 'werbe-manager' ) ); ?>
		</form>

		<h3><?php esc_html_e( 'Bestehende Slots', 'werbe-manager' ); ?></h3>
		<table class="widefat fixed striped">
			<thead>
			<tr>
				<th><?php esc_html_e( 'Slug', 'werbe-manager' ); ?></th>
				<th><?php esc_html_e( 'Titel', 'werbe-manager' ); ?></th>
				<th><?php esc_html_e( 'Format', 'werbe-manager' ); ?></th>
				<th><?php esc_html_e( 'Post Types', 'werbe-manager' ); ?></th>
				<th><?php esc_html_e( 'Aktionen', 'werbe-manager' ); ?></th>
			</tr>
			</thead>
			<tbody>
			<?php foreach ( $slots as $slot ) : ?>
				<tr>
					<td><code><?php echo esc_html( $slot['slug'] ); ?></code></td>
					<td><?php echo esc_html( $slot['title'] ); ?></td>
					<td><?php echo esc_html( $slot['format'] ); ?></td>
					<td><?php echo esc_html( $slot['allowed_post_types'] ); ?></td>
					<td>
						<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Slot wirklich löschen?', 'werbe-manager' ) ); ?>');">
							<?php wp_nonce_field( 'wm_delete_slot' ); ?>
							<input type="hidden" name="wm_action" value="delete_slot" />
							<input type="hidden" name="slot_id" value="<?php echo esc_attr( $slot['id'] ); ?>" />
							<?php submit_button( __( 'Löschen', 'werbe-manager' ), 'delete small', '', false ); ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_campaigns(): void {
		$campaigns = $this->repository->get_campaigns_for_slot( 0 ); // placeholder, fetch all via helper

		$table = $this->repository->campaigns_table();
		$campaigns = $GLOBALS['wpdb']->get_results( "SELECT * FROM {$table} ORDER BY title", ARRAY_A ) ?: array();
		?>
		<h2><?php esc_html_e( 'Kampagnen', 'werbe-manager' ); ?></h2>
		<form method="post" class="form-table">
			<?php wp_nonce_field( 'wm_save_campaign' ); ?>
			<input type="hidden" name="wm_action" value="save_campaign" />
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wm-campaign-slug"><?php esc_html_e( 'Slug', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-slug" name="slug" class="regular-text" required pattern="[a-z0-9_-]+" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-title"><?php esc_html_e( 'Titel', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-title" name="title" class="regular-text" required /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'werbe-manager' ); ?></th>
					<td>
						<select name="status">
							<option value="active"><?php esc_html_e( 'Aktiv', 'werbe-manager' ); ?></option>
							<option value="paused"><?php esc_html_e( 'Pausiert', 'werbe-manager' ); ?></option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-weight"><?php esc_html_e( 'Gewicht', 'werbe-manager' ); ?></label></th>
					<td><input type="number" id="wm-campaign-weight" name="weight" value="10" min="1" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-formats"><?php esc_html_e( 'Formate (CSV)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-formats" name="formats" value="third" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-brands-include"><?php esc_html_e( 'Brands einschließen (CSV)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-brands-include" name="brands_include" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-brands-exclude"><?php esc_html_e( 'Brands ausschließen (CSV)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-brands-exclude" name="brands_exclude" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-post-types"><?php esc_html_e( 'Post Types (CSV)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-post-types" name="post_types" value="post,page" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-cats-include"><?php esc_html_e( 'Kategorien einschließen (Slugs)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-cats-include" name="categories_include" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-cats-exclude"><?php esc_html_e( 'Kategorien ausschließen (Slugs)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-cats-exclude" name="categories_exclude" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-tags-include"><?php esc_html_e( 'Tags einschließen (Slugs)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-tags-include" name="tags_include" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-tags-exclude"><?php esc_html_e( 'Tags ausschließen (Slugs)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-tags-exclude" name="tags_exclude" class="regular-text" /></td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-campaign-url-like"><?php esc_html_e( 'URL enthält (SQL LIKE, % wildcards)', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-campaign-url-like" name="url_like" class="regular-text" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Kampagne speichern', 'werbe-manager' ) ); ?>
		</form>

		<h3><?php esc_html_e( 'Bestehende Kampagnen', 'werbe-manager' ); ?></h3>
		<table class="widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'Slug', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Titel', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Status', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Gewicht', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Formate', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Aktionen', 'werbe-manager' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $campaigns as $campaign ) : ?>
				<tr>
					<td><code><?php echo esc_html( $campaign['slug'] ); ?></code></td>
					<td><?php echo esc_html( $campaign['title'] ); ?></td>
					<td><?php echo esc_html( $campaign['status'] ); ?></td>
					<td><?php echo esc_html( $campaign['weight'] ); ?></td>
					<td><?php echo esc_html( $campaign['formats'] ); ?></td>
					<td>
						<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Kampagne wirklich löschen?', 'werbe-manager' ) ); ?>');">
							<?php wp_nonce_field( 'wm_delete_campaign' ); ?>
							<input type="hidden" name="wm_action" value="delete_campaign" />
							<input type="hidden" name="campaign_id" value="<?php echo esc_attr( $campaign['id'] ); ?>" />
							<?php submit_button( __( 'Löschen', 'werbe-manager' ), 'delete small', '', false ); ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_creatives(): void {
		global $wpdb;
		$creatives = $wpdb->get_results( 'SELECT c.*, cp.slug AS campaign_slug FROM ' . $this->repository->creatives_table() . ' c LEFT JOIN ' . $this->repository->campaigns_table() . ' cp ON c.campaign_id = cp.id ORDER BY c.campaign_id', ARRAY_A ) ?: array();
		?>
		<h2><?php esc_html_e( 'Creatives', 'werbe-manager' ); ?></h2>
		<form method="post" class="form-table">
			<?php wp_nonce_field( 'wm_save_creative' ); ?>
			<input type="hidden" name="wm_action" value="save_creative" />
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wm-creative-campaign"><?php esc_html_e( 'Kampagnen-ID', 'werbe-manager' ); ?></label></th>
					<td><input type="number" min="1" id="wm-creative-campaign" name="campaign_id" required /></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Typ', 'werbe-manager' ); ?></th>
					<td>
						<select name="type">
							<option value="shortcode"><?php esc_html_e( 'Shortcode', 'werbe-manager' ); ?></option>
							<option value="html"><?php esc_html_e( 'HTML', 'werbe-manager' ); ?></option>
							<option value="image"><?php esc_html_e( 'Bild', 'werbe-manager' ); ?></option>
						</select>
						<p class="description"><?php esc_html_e( 'Payload als JSON eingeben. Beispiele siehe Dokumentation.', 'werbe-manager' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-creative-payload"><?php esc_html_e( 'Payload (JSON)', 'werbe-manager' ); ?></label></th>
					<td><textarea id="wm-creative-payload" name="payload" class="large-text code" rows="6" placeholder='{"slug":"roboforex_card","atts":{"foo":"bar"}}'></textarea></td>
				</tr>
			</table>
			<?php submit_button( __( 'Creative speichern', 'werbe-manager' ) ); ?>
		</form>

		<h3><?php esc_html_e( 'Bestehende Creatives', 'werbe-manager' ); ?></h3>
		<table class="widefat fixed striped">
			<thead><tr><th><?php esc_html_e( 'ID', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Kampagne', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Typ', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Payload', 'werbe-manager' ); ?></th><th><?php esc_html_e( 'Aktionen', 'werbe-manager' ); ?></th></tr></thead>
			<tbody>
			<?php foreach ( $creatives as $creative ) : ?>
				<tr>
					<td><?php echo esc_html( $creative['id'] ); ?></td>
					<td><?php echo esc_html( $creative['campaign_slug'] ?? $creative['campaign_id'] ); ?></td>
					<td><?php echo esc_html( $creative['type'] ); ?></td>
					<td><code><?php echo esc_html( $creative['payload'] ); ?></code></td>
					<td>
						<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Creative wirklich löschen?', 'werbe-manager' ) ); ?>');">
							<?php wp_nonce_field( 'wm_delete_creative' ); ?>
							<input type="hidden" name="wm_action" value="delete_creative" />
							<input type="hidden" name="creative_id" value="<?php echo esc_attr( $creative['id'] ); ?>" />
							<?php submit_button( __( 'Löschen', 'werbe-manager' ), 'delete small', '', false ); ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	private function render_mapping(): void {
		$slots      = $this->repository->get_slots();
		$campaigns  = $GLOBALS['wpdb']->get_results( 'SELECT id, title FROM ' . $this->repository->campaigns_table() . ' ORDER BY title', ARRAY_A ) ?: array();
		?>
		<h2><?php esc_html_e( 'Slot-Zuordnung', 'werbe-manager' ); ?></h2>
		<p><?php esc_html_e( 'Ordne Kampagnen mit Gewichten einem Slot zu. Mehrere Paare per Zeile im Format "campaign_id:weight" eingeben, z.B. "1:10,2:5".', 'werbe-manager' ); ?></p>
		<form method="post" class="form-table">
			<?php wp_nonce_field( 'wm_save_mapping' ); ?>
			<input type="hidden" name="wm_action" value="save_mapping" />
			<table class="form-table">
				<tr>
					<th scope="row"><label for="wm-mapping-slot"><?php esc_html_e( 'Slot', 'werbe-manager' ); ?></label></th>
					<td>
						<select id="wm-mapping-slot" name="slot_id">
							<?php foreach ( $slots as $slot ) : ?>
								<option value="<?php echo esc_attr( $slot['id'] ); ?>"><?php echo esc_html( $slot['title'] . ' (' . $slot['slug'] . ')' ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wm-mapping-pairs"><?php esc_html_e( 'Kampagnen:Gewicht', 'werbe-manager' ); ?></label></th>
					<td><input type="text" id="wm-mapping-pairs" name="pairs" class="regular-text" placeholder="1:10,2:5" /></td>
				</tr>
			</table>
			<?php submit_button( __( 'Zuordnung speichern', 'werbe-manager' ) ); ?>
		</form>

		<h3><?php esc_html_e( 'Kampagnenliste (Referenz)', 'werbe-manager' ); ?></h3>
		<ul>
		<?php foreach ( $campaigns as $campaign ) : ?>
			<li><?php echo esc_html( $campaign['id'] . ' – ' . $campaign['title'] ); ?></li>
		<?php endforeach; ?>
		</ul>
		<?php
	}

	private function handle_actions( string $tab ): void {
		if ( empty( $_POST['wm_action'] ) ) {
			return;
		}

		switch ( sanitize_key( wp_unslash( $_POST['wm_action'] ) ) ) {
			case 'save_slot':
				check_admin_referer( 'wm_save_slot' );
				$this->save_slot();
				break;
			case 'delete_slot':
				check_admin_referer( 'wm_delete_slot' );
				$this->delete_slot();
				break;
			case 'save_campaign':
				check_admin_referer( 'wm_save_campaign' );
				$this->save_campaign();
				break;
			case 'delete_campaign':
				check_admin_referer( 'wm_delete_campaign' );
				$this->delete_campaign();
				break;
			case 'save_creative':
				check_admin_referer( 'wm_save_creative' );
				$this->save_creative();
				break;
			case 'delete_creative':
				check_admin_referer( 'wm_delete_creative' );
				$this->delete_creative();
				break;
			case 'save_mapping':
				check_admin_referer( 'wm_save_mapping' );
				$this->save_mapping();
				break;
		}
	}

	private function save_slot(): void {
		$data = array(
			'slug'               => sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) ),
			'title'              => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'format'             => sanitize_key( wp_unslash( $_POST['format'] ?? 'third' ) ),
			'allowed_post_types' => sanitize_text_field( wp_unslash( $_POST['allowed_post_types'] ?? 'post,page' ) ),
			'css_class'          => sanitize_html_class( wp_unslash( $_POST['css_class'] ?? '' ) ),
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		if ( ! $data['slug'] || ! $data['title'] ) {
			return;
		}

		$this->repository->save_slot( $data );
	}

	private function delete_slot(): void {
		$slot_id = isset( $_POST['slot_id'] ) ? (int) $_POST['slot_id'] : 0;
		if ( $slot_id ) {
			$this->repository->delete_slot( $slot_id );
		}
	}

	private function save_campaign(): void {
		$data = array(
			'slug'               => sanitize_key( wp_unslash( $_POST['slug'] ?? '' ) ),
			'title'              => sanitize_text_field( wp_unslash( $_POST['title'] ?? '' ) ),
			'status'             => sanitize_key( wp_unslash( $_POST['status'] ?? 'active' ) ),
			'weight'             => (int) ( $_POST['weight'] ?? 10 ),
			'formats'            => sanitize_text_field( wp_unslash( $_POST['formats'] ?? 'third' ) ),
			'brands_include'     => sanitize_text_field( wp_unslash( $_POST['brands_include'] ?? '' ) ),
			'brands_exclude'     => sanitize_text_field( wp_unslash( $_POST['brands_exclude'] ?? '' ) ),
			'post_types'         => sanitize_text_field( wp_unslash( $_POST['post_types'] ?? 'post,page' ) ),
			'categories_include' => sanitize_text_field( wp_unslash( $_POST['categories_include'] ?? '' ) ),
			'categories_exclude' => sanitize_text_field( wp_unslash( $_POST['categories_exclude'] ?? '' ) ),
			'tags_include'       => sanitize_text_field( wp_unslash( $_POST['tags_include'] ?? '' ) ),
			'tags_exclude'       => sanitize_text_field( wp_unslash( $_POST['tags_exclude'] ?? '' ) ),
			'url_like'           => sanitize_text_field( wp_unslash( $_POST['url_like'] ?? '' ) ),
			'created_at'         => current_time( 'mysql' ),
			'updated_at'         => current_time( 'mysql' ),
		);

		if ( ! $data['slug'] || ! $data['title'] ) {
			return;
		}

		$this->repository->save_campaign( $data );
	}

	private function delete_campaign(): void {
		$id = isset( $_POST['campaign_id'] ) ? (int) $_POST['campaign_id'] : 0;
		if ( $id ) {
			$this->repository->delete_campaign( $id );
		}
	}

	private function save_creative(): void {
		$campaign_id = isset( $_POST['campaign_id'] ) ? (int) $_POST['campaign_id'] : 0;
		$type        = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : 'html';
		$payload_raw = wp_unslash( $_POST['payload'] ?? '' );

		if ( ! $campaign_id || '' === trim( $payload_raw ) ) {
			return;
		}

		$payload = json_decode( $payload_raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) ) {
			return;
		}

		$data = array(
			'campaign_id' => $campaign_id,
			'type'        => $type,
			'payload'     => wp_json_encode( $payload ),
			'created_at'  => current_time( 'mysql' ),
			'updated_at'  => current_time( 'mysql' ),
		);

		$this->repository->save_creative( $data );
	}

	private function delete_creative(): void {
		$id = isset( $_POST['creative_id'] ) ? (int) $_POST['creative_id'] : 0;
		if ( $id ) {
			$this->repository->delete_creative( $id );
		}
	}

	private function save_mapping(): void {
		$slot_id = isset( $_POST['slot_id'] ) ? (int) $_POST['slot_id'] : 0;
		$pairs   = sanitize_text_field( wp_unslash( $_POST['pairs'] ?? '' ) );

		if ( ! $slot_id ) {
			return;
		}

		$map = array();
		foreach ( array_filter( array_map( 'trim', explode( ',', $pairs ) ) ) as $pair ) {
			list( $campaign, $weight ) = array_pad( array_map( 'trim', explode( ':', $pair, 2 ) ), 2, '' );
			$campaign_id = (int) $campaign;
			$weight      = max( 1, (int) $weight );
			if ( $campaign_id > 0 ) {
				$map[ $campaign_id ] = $weight;
			}
		}

		$this->repository->replace_slot_links( $slot_id, $map );
	}
}
