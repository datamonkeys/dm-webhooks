<?php
/**
 * Settings page (WooCommerce > DM Webhooks).
 *
 * @package DM_Webhooks
 */

defined( 'ABSPATH' ) || exit;

class DMWH_Settings {

	const OPTION = 'dmwh_settings';

	/** Events the plugin can emit. */
	public static function events() {
		return array(
			'order.created'        => __( 'Order created', 'dm-webhooks' ),
			'order.paid'           => __( 'Order paid', 'dm-webhooks' ),
			'order.status_changed' => __( 'Order status changed', 'dm-webhooks' ),
			'order.refunded'       => __( 'Order refunded', 'dm-webhooks' ),
		);
	}

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	public static function get() {
		return wp_parse_args(
			get_option( self::OPTION, array() ),
			array(
				'endpoints'   => array(),
				'secret'      => '',
				'max_retries' => 3,
			)
		);
	}

	public static function menu() {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'options-general.php';
		add_submenu_page( $parent, 'DM Webhooks', 'DM Webhooks', 'manage_woocommerce', 'dm-webhooks', array( __CLASS__, 'render' ) );
	}

	public static function register() {
		register_setting(
			'dmwh',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
			)
		);
	}

	/**
	 * Sanitizes the settings form.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$out = array(
			'endpoints'   => array(),
			'secret'      => sanitize_text_field( $input['secret'] ?? '' ),
			'max_retries' => min( 10, max( 0, absint( $input['max_retries'] ?? 3 ) ) ),
		);
		foreach ( (array) ( $input['endpoints'] ?? array() ) as $row ) {
			$url = esc_url_raw( trim( $row['url'] ?? '' ) );
			if ( ! $url || ! wp_http_validate_url( $url ) ) {
				continue;
			}
			$events            = array_values( array_intersect( (array) ( $row['events'] ?? array() ), array_keys( self::events() ) ) );
			$out['endpoints'][] = array(
				'url'    => $url,
				'events' => $events,
				'active' => ! empty( $row['active'] ),
			);
		}
		return $out;
	}

	public static function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$s         = self::get();
		$endpoints = $s['endpoints'];
		$endpoints[] = array( 'url' => '', 'events' => array_keys( self::events() ), 'active' => true ); // Empty row to add a new one.
		?>
		<div class="wrap">
			<h1>DM Webhooks</h1>
			<p><?php esc_html_e( 'Each request is a JSON POST signed with HMAC-SHA256 in the X-DMWH-Signature header. Failed deliveries are retried with exponential backoff.', 'dm-webhooks' ); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields( 'dmwh' ); ?>
				<table class="widefat striped">
					<thead><tr><th><?php esc_html_e( 'Active', 'dm-webhooks' ); ?></th><th>URL</th><th><?php esc_html_e( 'Events', 'dm-webhooks' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $endpoints as $i => $ep ) : ?>
						<tr>
							<td><input type="checkbox" name="<?php echo esc_attr( self::OPTION . "[endpoints][$i][active]" ); ?>" value="1" <?php checked( $ep['active'] ); ?>></td>
							<td><input type="url" class="regular-text" placeholder="https://hooks.zapier.com/..." name="<?php echo esc_attr( self::OPTION . "[endpoints][$i][url]" ); ?>" value="<?php echo esc_attr( $ep['url'] ); ?>"></td>
							<td>
								<?php foreach ( self::events() as $key => $label ) : ?>
									<label style="margin-right:12px"><input type="checkbox" name="<?php echo esc_attr( self::OPTION . "[endpoints][$i][events][]" ); ?>" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, $ep['events'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<h2><?php esc_html_e( 'Security & delivery', 'dm-webhooks' ); ?></h2>
				<table class="form-table">
					<tr><th><?php esc_html_e( 'Signing secret', 'dm-webhooks' ); ?></th><td><input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION . '[secret]' ); ?>" value="<?php echo esc_attr( $s['secret'] ); ?>"></td></tr>
					<tr><th><?php esc_html_e( 'Max retries', 'dm-webhooks' ); ?></th><td><input type="number" min="0" max="10" name="<?php echo esc_attr( self::OPTION . '[max_retries]' ); ?>" value="<?php echo esc_attr( $s['max_retries'] ); ?>"></td></tr>
				</table>
				<?php submit_button(); ?>
			</form>
			<h2><?php esc_html_e( 'Recent deliveries', 'dm-webhooks' ); ?></h2>
			<?php DMWH_Log::render_table(); ?>
		</div>
		<?php
	}
}
