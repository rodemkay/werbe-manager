<?php
namespace Werbe_Manager\DB;

use wpdb;

/**
 * Thin repository around Werbe Manager tables.
 */
class Repository {

	private wpdb $db;

	public function __construct( ?wpdb $wpdb = null ) {
		$this->db = $wpdb ?: $GLOBALS['wpdb'];
	}

	public function slots_table(): string {
		return $this->db->prefix . 'wm_slots';
	}

	public function campaigns_table(): string {
		return $this->db->prefix . 'wm_campaigns';
	}

	public function link_table(): string {
		return $this->db->prefix . 'wm_campaign_slot';
	}

	public function creatives_table(): string {
		return $this->db->prefix . 'wm_creatives';
	}

	public function get_slot_by_slug( string $slug ): ?array {
		$table = $this->slots_table();
		$sql   = $this->db->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $slug );
		$row   = $this->db->get_row( $sql, ARRAY_A );

		return $row ?: null;
	}

	/**
	 * Fetch campaigns mapped to a slot with mapping weight.
	 */
	public function get_campaigns_for_slot( int $slot_id ): array {
		$campaigns_table = $this->campaigns_table();
		$link_table      = $this->link_table();

		$sql = $this->db->prepare(
			"SELECT c.*, l.weight AS link_weight FROM {$campaigns_table} AS c
			INNER JOIN {$link_table} AS l ON c.id = l.campaign_id
			WHERE l.slot_id = %d",
			$slot_id
		);

		$rows = $this->db->get_results( $sql, ARRAY_A );

		return $rows ?: array();
	}

	/**
	 * Fetch creatives for a campaign.
	 */
	public function get_campaign( int $id ): ?array {
		$table = $this->campaigns_table();
		$sql   = $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id );
		$row   = $this->db->get_row( $sql, ARRAY_A );
		return $row ?: null;
	}

	public function get_creative( int $id ): ?array {
		$table = $this->creatives_table();
		$sql   = $this->db->prepare( "SELECT * FROM {$table} WHERE id = %d LIMIT 1", $id );
		$row   = $this->db->get_row( $sql, ARRAY_A );
		return $row ?: null;
	}

	public function get_creatives_for_campaign( int $campaign_id ): array {
		$table = $this->creatives_table();
		$sql   = $this->db->prepare( "SELECT * FROM {$table} WHERE campaign_id = %d", $campaign_id );
		$rows  = $this->db->get_results( $sql, ARRAY_A );

		return $rows ?: array();
	}

	/**
	 * Fetch all slots.
	 */
	public function get_slots(): array {
		$table = $this->slots_table();
		$sql   = "SELECT * FROM {$table} ORDER BY title ASC";

		return $this->db->get_results( $sql, ARRAY_A ) ?: array();
	}

	/**
	 * Persist slot (insert or update).
	 */
	public function save_slot( array $data ): int {
		$table = $this->slots_table();

		if ( empty( $data['id'] ) ) {
			$this->db->insert( $table, $data );
			return (int) $this->db->insert_id;
		}

		$id = (int) $data['id'];
		unset( $data['id'] );
		$this->db->update( $table, $data, array( 'id' => $id ) );

		return $id;
	}

	public function delete_slot( int $id ): void {
		$table = $this->slots_table();
		$this->db->delete( $table, array( 'id' => $id ) );
	}

	public function save_campaign( array $data ): int {
		$table = $this->campaigns_table();

		if ( empty( $data['id'] ) ) {
			$this->db->insert( $table, $data );
			return (int) $this->db->insert_id;
		}

		$id = (int) $data['id'];
		unset( $data['id'] );
		$this->db->update( $table, $data, array( 'id' => $id ) );

		return $id;
	}

	public function delete_campaign( int $id ): void {
		$this->db->delete( $this->campaigns_table(), array( 'id' => $id ) );
		$this->db->delete( $this->link_table(), array( 'campaign_id' => $id ) );
		$this->db->delete( $this->creatives_table(), array( 'campaign_id' => $id ) );
	}

	public function save_creative( array $data ): int {
		$table = $this->creatives_table();

		if ( empty( $data['id'] ) ) {
			$this->db->insert( $table, $data );
			return (int) $this->db->insert_id;
		}

		$id = (int) $data['id'];
		unset( $data['id'] );
		$this->db->update( $table, $data, array( 'id' => $id ) );

		return $id;
	}

	public function delete_creative( int $id ): void {
		$this->db->delete( $this->creatives_table(), array( 'id' => $id ) );
	}

	public function replace_slot_links( int $slot_id, array $campaign_weight_map ): void {
		$this->db->delete( $this->link_table(), array( 'slot_id' => $slot_id ) );
		foreach ( $campaign_weight_map as $campaign_id => $weight ) {
			$this->db->insert(
				$this->link_table(),
				array(
					'slot_id'     => $slot_id,
					'campaign_id' => $campaign_id,
					'weight'      => $weight,
				)
			);
		}
	}

	public function options_table(): string {
		return $this->db->options;
	}
}
