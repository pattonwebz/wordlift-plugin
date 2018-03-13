<?php
/**
 * Fired during plugin deactivate
 *
 * @link       https://wordlift.io
 * @since      3.19.0
 *
 * @package    Wordlift
 * @subpackage Wordlift/includes
 */

/**
 * Fired during plugin deactivate.
 *
 * This class defines all code necessary to run during the plugin's deactivate.
 *
 * @since      3.19.0
 * @package    Wordlift
 * @subpackage Wordlift/includes
 * @author     WordLift <hello@wordlift.io>
 */
class Wordlift_Deactivator_Feedback {

	/**
	 * A {@link Wordlift_Log_Service} instance.
	 *
	 * @since  3.19.0
	 * @access private
	 * @var \Wordlift_Log_Service $log A {@link Wordlift_Log_Service} instance.
	 */
	private $log;

	/**
	 * The {@link Wordlift_Configuration_Service} instance.
	 *
	 * @since  3.19.0
	 * @access private
	 * @var \Wordlift_Configuration_Service $configuration_service The {@link Wordlift_Configuration_Service} instance.
	 */
	private $configuration_service;

	/**
	 * Wordlift_Deactivator_Feedback constructor.
	 *
	 * @since 3.19.0
	 *
	 * @param \Wordlift_Configuration_Service $configuration_service The {@link Wordlift_Configuration_Service} instance.
	 */
	public function __construct( $configuration_service ) {

		$this->log = Wordlift_Log_Service::get_logger( 'Wordlift_Deactivator_Feedback' );

		$this->configuration_service = $configuration_service;

	}
	/**
	 * Checks whether we have permissions to show the popup.
	 *
	 * @version 3.19.0
	 *
	 * @return  bool `true` if we have permissions, false otherwise.
	 */
	private function has_permission_to_show_popup() {
		// Get the current page.
		global $pagenow;

		// Bail if the user doesn't have permissions
		// or if it's not the plugins page.
		if ( $pagenow !== 'plugins.php' ) {
			return false;
		}

		// Get the user preferences. We shouldn't show the feedback popup
		// if we don't have permissions for that.
		$configuration_service = Wordlift_Configuration_Service::get_instance();
		$user_preferences      = $configuration_service->get_diagnostic_preferences();

		// Bail. We don't have preferences to show the popup.
		if ( $user_preferences !== 'yes' ) {
			return false;
		}

		return true;
	}

	/**
	 * Render the feedback popup in the footer.
	 *
	 * @version 3.19.0
	 *
	 * @return  void
	 */
	public function render_feedback_popup() {
		// Bail if we don't have permissions to show the popup.
		if ( ! $this->has_permission_to_show_popup() ) {
			return;
		}
		// Include the partial.
		include plugin_dir_path( __FILE__ ) . '../admin/partials/wordlift-admin-deactivation-feedback-popup.php';
	}

	/**
	 * Enqueue required popup scripts and styles.
	 *
	 * @version 3.19.0
	 *
	 * @return  void
	 */
	public function enqueue_popup_scripts() {
		// Bail if we don't have permissions to show the popup.
		if ( ! $this->has_permission_to_show_popup() ) {
			return;
		}
		wp_enqueue_style( 'wordlift-admin-feedback-popup', plugin_dir_url( dirname( __FILE__ ) ) . 'admin/css/wordlift-admin-feedback-popup.css', array() );
		wp_enqueue_script( 'wordlift-admin-feedback-popup', plugin_dir_url( dirname( __FILE__ ) ) . 'admin/js/wordlift-admin-feedback-popup.js', array( 'jquery' ) );

		wp_localize_script( 'wordlift-admin-feedback-popup', 'settings', array( 'ajaxUrl' => admin_url( 'admin-ajax.php' ) ) );
	}

	/**
	 * Handle the deactivation ajax call
	 * and perform a request to external server.
	 *
	 * @version 3.19.0
	 *
	 * @return  void
	 */
	public function wl_deactivation_feedback() {
		// Bail if the nonce is not valid.
		if (
			empty( $_POST['wl_deactivation_feedback_nonce'] ) || // The nonce doens't exists.
			! wp_verify_nonce( $_POST['wl_deactivation_feedback_nonce'], 'wl_deactivation_feedback_nonce' ) // The nonce is invalid.
		) {
			wp_send_json_error( __( 'Nonce Security Check Failed!', 'wordlift' ) );
		}

		// We allow user to deactivate without providing a reason
		// so bail and send success response.
		if ( empty( $_POST['reason_id'] ) ) {
			wp_send_json_success();
		}

		// Prepare the options.
		$options = array(
			// The deactivation reason.
			'reason_id'   => $_POST['reason_id'],
			// Additional information if provided.
			'reason_info' => ( ! empty( $_POST['additional_info'] ) ) ? $_POST['additional_info'] : '',
			// The website url.
			'url'         => get_bloginfo( 'url' ),
			// WP version.
			'wp_version'  => get_bloginfo( 'version' ),
			// WL version.
			'version'     => get_option( 'wl_db_version' ),
			// The admin email.
			'email'       => get_bloginfo( 'admin_email' ),
		);

		$response = wp_remote_post(
			$this->configuration_service->get_deactivation_feedback_url(),
			array(
				'method' => 'POST',
				'body'   => $options,
			)
		);

		$code    = wp_remote_retrieve_response_code( $response );
		$message = wp_remote_retrieve_response_message( $response );

		// Add message to the error log if the response code is not 200.
		if ( $code !== 200 ) {
			// Write the error in the logs.
			$this->log->error( 'An error occurred while requesting a feedback endpoint error_code: ' . $code . ' message: ' . $message );
		}

		// We should send success message even when the feedback is not
		// send, because otherwise the plugin cannot be deactivated.
		wp_send_json_success();
	}
}