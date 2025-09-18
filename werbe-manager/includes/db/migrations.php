<?php
namespace Werbe_Manager\DB;

use wpdb;

/**
 * Handles database schema migrations.
 */
class Migrations {

	private wpdb $db;

	public function __construct( ?wpdb $wpdb = null ) {
		$this->db = $wpdb ?: $GLOBALS['wpdb'];
	}

	/**
	 * Run the latest migration.
	 */
	public function up(): void {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->db->get_charset_collate();

		$slots_table      = $this->table( 'slots' );
		$campaigns_table  = $this->table( 'campaigns' );
		$link_table       = $this->table( 'campaign_slot' );
		$creatives_table  = $this->table( 'creatives' );

		$schema = array(
			sprintf(
				'CREATE TABLE %1$s (
					id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					slug VARCHAR(64) NOT NULL UNIQUE,
					title VARCHAR(128) NOT NULL,
					format VARCHAR(32) NOT NULL DEFAULT "third",
					allowed_post_types VARCHAR(255) DEFAULT "post,page",
					css_class VARCHAR(128) DEFAULT NULL,
					created_at DATETIME NOT NULL,
					updated_at DATETIME NOT NULL,
					PRIMARY KEY  (id)
				) %2$s;',
				$slots_table,
				$charset_collate
			),
			sprintf(
				'CREATE TABLE %1$s (
					id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					slug VARCHAR(64) NOT NULL UNIQUE,
					title VARCHAR(128) NOT NULL,
					status VARCHAR(16) NOT NULL DEFAULT "active",
					weight INT NOT NULL DEFAULT 10,
					start_at DATETIME NULL,
					end_at DATETIME NULL,
					formats VARCHAR(128) NOT NULL DEFAULT "third",
					groups_csv VARCHAR(128) DEFAULT NULL,
					brands_include VARCHAR(255) DEFAULT NULL,
					brands_exclude VARCHAR(255) DEFAULT NULL,
					post_types VARCHAR(255) DEFAULT "post,page",
					categories_include VARCHAR(255) DEFAULT NULL,
					categories_exclude VARCHAR(255) DEFAULT NULL,
					tags_include VARCHAR(255) DEFAULT NULL,
					tags_exclude VARCHAR(255) DEFAULT NULL,
					include_post_ids VARCHAR(255) DEFAULT NULL,
					exclude_post_ids VARCHAR(255) DEFAULT NULL,
					url_like VARCHAR(255) DEFAULT NULL,
					frequency_cap JSON NULL,
					meta JSON NULL,
					created_at DATETIME NOT NULL,
					updated_at DATETIME NOT NULL,
					PRIMARY KEY  (id)
				) %2$s;',
				$campaigns_table,
				$charset_collate
			),
			sprintf(
				'CREATE TABLE %1$s (
					slot_id BIGINT UNSIGNED NOT NULL,
					campaign_id BIGINT UNSIGNED NOT NULL,
					weight INT NOT NULL DEFAULT 10,
					PRIMARY KEY (slot_id, campaign_id),
					KEY campaign_id (campaign_id)
				) %2$s;',
				$link_table,
				$charset_collate
			),
			sprintf(
				'CREATE TABLE %1$s (
					id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
					campaign_id BIGINT UNSIGNED NOT NULL,
					type VARCHAR(32) NOT NULL,
					payload LONGTEXT NOT NULL,
					created_at DATETIME NOT NULL,
					updated_at DATETIME NOT NULL,
					PRIMARY KEY  (id),
					KEY campaign_id (campaign_id)
				) %2$s;',
				$creatives_table,
				$charset_collate
			),
		);

		foreach ( $schema as $sql ) {
			dbDelta( $sql );
		}
	}

	/**
	 * Helper to get table name with prefix.
	 */
	private function table( string $suffix ): string {
		return $this->db->prefix . 'wm_' . $suffix;
	}
}
