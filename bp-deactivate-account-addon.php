<?php
/**
 * Plugin Name: BuddyPress Deactivate account Addon
 * Description: This is an addon for deactivate account plugin. This enable site admin to autoactivate deactive account after certain period of time.
 * Version: 1.0.0
 * Author: BuddyDev Team
 *
 * @package bp-deactivate-account-addon
 */

/**
 * @contributor Ravi Sharma(raviousprime)
 * @contributor-uri https://github.com/raviousprime
 */

/**
 * Class BP_Deactivate_Account_Addon
 *
 */
class BP_Deactivate_Account_Addon {

	/**
	 * Singleton Instance
	 *
	 * @var BP_Deactivate_Account_Addon
	 */
	private static $instance = null;

	/**
	 * BP_Deactivate_Account_Addon constructor.
	 */
	private function __construct() {
		$this->setup();
	}

	/**
	 * Get class instance
	 *
	 * @return BP_Deactivate_Account_Addon
	 */
	public static function get_instance() {

		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setup callback on necessaries hooks
	 */
	private function setup() {

		register_activation_hook( __FILE__, array( $this, 'on_activation' ) );
		register_deactivation_hook( __FILE__, array( $this, 'on_deactivation' ) );

		add_action( 'bp_deactivate_account_admin_settings', array( $this, 'register_settings' ) );

		add_action( 'bp-account-deactivated', array( $this, 'save_date' ) );
		add_action( 'bp-account-activated', array( $this, 'remove_date' ) );

		add_action( 'bp_deactivate_addon_reactivate_accounts', array( $this, 'reactivate_accounts' ) );
	}

	/**
	 * Setup hourly cronjob to activate accounts
	 */
	public function on_activation() {

		if ( ! wp_next_scheduled( 'bp_deactivate_addon_reactivate_accounts' ) ) {
			wp_schedule_event( time(), 'hourly', 'bp_deactivate_addon_reactivate_accounts' );
		}
	}

	/**
	 * Remove cronjob
	 */
	public function on_deactivation() {
		wp_clear_scheduled_hook( 'bp_deactivate_addon_reactivate_accounts' );
	}

	/**
	 * Register new admin settings.
	 *
	 * @param $page
	 */
	public function register_settings( $page ) {
		$panel = $page->add_panel( 'addon_settings', _x( 'Addon Settings', 'Admin settings', 'bp-deactivate-account-addon' ) );

		$section = $panel->add_section( 'addon-general-settings', _x( 'Addon Settings', 'Admin settings', 'bp-deactivate-account-addon' ) );

		$section->add_field( array(
			'name'    => 'enable_temporary_deactivate_account',
			'label'   => __( 'Enable temporary account deactivation', 'bp-deactivate-account-addon' ),
			'type'    => 'radio',
			'default' => 1,
			'options' => array(
				1 => __( 'Yes', 'bp-deactivate-account-addon' ),
				0 => __( 'No', 'bp-deactivate-account-addon' ),
			),
		) )->add_field( array(
			'name'    => 'activate_after_days',
			'label'   => __( 'Activate after days', 'bp-deactivate-account-addon' ),
			'type'    => 'text',
			'default' => 5,
			'desc'    => __( "If temporary deactivate account enable, After will be auto activate after these number of days.", 'bp-deactivate-account-addon' ),
		) );
	}

	/**
	 * Save datetime when a user account is deactivated
	 *
	 * @param int $user_id User id.
	 */
	public function save_date( $user_id ) {
		update_user_meta( $user_id, '_bp_account_deactivation_time', time() );
	}

	/**
	 * remove datetime when a user account is activated
	 *
	 * @param int $user_id User id.
	 */
	public function remove_date( $user_id ) {
		delete_user_meta( $user_id, '_bp_account_deactivation_time' );
	}

	/**
	 * Reactivate accounts
	 */
	public function reactivate_accounts() {

		global $wpdb;

		if ( ! function_exists( 'bp_account_deactivator' ) || ! bp_account_deactivator()->get_option( 'enable_temporary_deactivate_account' ) ) {
			return;
		}

		$interval = bp_account_deactivator()->get_option( 'activate_after_days' ) * DAY_IN_SECONDS;

		$user_query = $wpdb->prepare( "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s  AND CAST( meta_value AS UNSIGNED ) < %d", '_bp_account_deactivation_time', ( time() - $interval ) );

		$user_ids = $wpdb->get_col( $user_query );

		if ( empty( $user_ids ) ) {
			return;
		}

		foreach ( $user_ids as $user_id ) {
			bp_account_deactivator()->set_active( $user_id );
		}
	}
}
BP_Deactivate_Account_Addon::get_instance();
