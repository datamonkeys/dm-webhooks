<?php
/**
 * Delivery log stored in a custom table.
 *
 * @package DM_Webhooks
 */

defined( 'ABSPATH' ) || exit;

class DMWH_Log {

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'dmwh_log';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			'CREATE TABLE ' . self::table() . " (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				created_at DATETIME NOT NULL,
				url VARCHAR(500) NOT NULL,
				event VARCHAR(60) NOT NULL,
				delivery_id CHAR(36) NOT NULL,
				attempt TINYINT UNSIGNED NOT NULL,
				status_code SMALLINT UNSIGNED NOT NULL,
				error TEXT NULL,
				PRIMARY KEY  (id),
				KEY delivery_id (delivery_id)
			) $charset;"
		);
	}

	public static function add( $url, $event, $delivery_id, $attempt, $code, $error = '' ) {
		global $wpdb;
		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::table(),
			array(
				'created_at'  => current_time( 'mysql', true ),
				'url'         => $url,
				'event'       => $event,
				'delivery_id' => $delivery_id,
				'attempt'     => $attempt,
				'status_code' => $code,
				'error'       => $error,
			)
		);
	}

	public static function recent( $limit = 20 ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' ORDER BY id DESC LIMIT %d', $limit ) ); // phpcs:ignore WordPress.DB
	}

	public static function render_table() {
		$rows = self::recent();
		if ( ! $rows ) {
			echo '<p>' . esc_html__( 'No deliveries yet.', 'dm-webhooks' ) . '</p>';
			return;
		}
		echo '<table class="widefat striped"><thead><tr><th>UTC</th><th>Event</th><th>URL</th><th>Attempt</th><th>Status</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$ok = $r->status_code >= 200 && $r->status_code < 300;
			printf(
				'<tr><td>%s</td><td>%s</td><td>%s</td><td>%d</td><td style="color:%s">%s</td></tr>',
				esc_html( $r->created_at ),
				esc_html( $r->event ),
				esc_html( $r->url ),
				(int) $r->attempt,
				$ok ? '#008a20' : '#d63638',
				esc_html( $r->status_code ? $r->status_code : $r->error )
			);
		}
		echo '</tbody></table>';
	}
}
