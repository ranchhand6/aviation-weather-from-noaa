<?php

/**
 * Class AWFNLogs
 *
 * Displays the contents of any error logs in the the logs directory with the option to erase
 */
class AWFNLogs {
	private $awfn_logs_options;
	private $now;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'awfn_logs_add_plugin_page' ) );
		add_action( 'admin_init', array( $this, 'awfn_logs_page_init' ) );
		$time = getdate();
//		var_dump( $time );
		$this->now = date( 'Y-m-d H:i:s' );
	}

	public function awfn_logs_add_plugin_page() {
		add_management_page(
			'AWFN Logs', // page_title
			'AWFN Logs', // menu_title
			'manage_options', // capability
			'awfn-logs', // menu_slug
			array( $this, 'awfn_logs_create_admin_page' ), // function
			'dashicons-admin-generic', // icon_url
			76 // position
		);
	}

	public function awfn_logs_create_admin_page() {
		$awfn_logs_options = get_option( 'awfn_logs_option_name' );
		$debug_0 = isset( $awfn_logs_options['debug_0'] ) ? true : false;

		$this->awfn_logs_options = get_option( 'awfn_logs_option_name' ); ?>

		<div class="wrap">

			<h2><?php _e( 'AWFN Logs', Adds_Weather_Widget::get_widget_slug() ); ?></h2>
			<hr/>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'awfn_logs_option_group' );
				do_settings_sections( 'awfn-logs-admin' );
				submit_button('Save Setting');
				?>
			</form>
			<hr />
			<?php if( $debug_0 ) : ?>

			<h2><?php _e( 'Logs', Adds_Weather_Widget::get_widget_slug() ); ?></h2>
				<p><?php printf( __( 'Current Local Time %s', Adds_Weather_Widget::get_widget_slug() ), current_time( 'Y-m-d H:i:s' ) ); ?></p>
				<p><?php printf( __( 'Current Server Time %s', Adds_Weather_Widget::get_widget_slug() ), $this->now ); ?></p>
				<button id="awfn-clear-all"><?php _e( 'Clear All Logs', Adds_Weather_Widget::get_widget_slug() ); ?></button>
			<?php settings_errors(); ?>
			<?php $this->display_log(); ?>

			<?php endif; ?>

		</div>
	<?php }

	private function display_log() {

		$files = self::get_log_files();

		if ( ! empty( $files ) ) {

			echo '<div  id="awfn-error-logs">';

			foreach ( $files as $key => $filename ) {

				echo '<h2>' . strtoupper( basename( $filename ) ) . '</h2>';
				echo '<section id="' . basename( $filename ) . '" class="awfn-log-display">';

				if ( 0 < filesize( $filename ) ) {

					echo '<button class="awfn-clear-log" data-file="' . $filename . '">' . __( 'Clear Log', Adds_Weather_Widget::get_widget_slug() ) . '</button>';
					echo '<div class="errors">';
				} else {
					echo '<p>&lt;' . __( 'Log file is empty', Adds_Weather_Widget::get_widget_slug() ) . '&gt;</p>';
				}

				if ( 0 < filesize( $filename ) ) {
					$handle = fopen( $filename, 'r' );
					if ( $handle ) {
						echo '<ul>';
						while ( false !== $line = fgets( $handle ) ) {
							echo '<li>' . $line . '</li>';
						}
						echo '</ul>';
						fclose( $handle );
					}

					echo '</div>';
				}
				echo '</section>';

			}
			echo '</div>';
		} else {
			echo '<h2>' . __( 'No Logs Found', Adds_Weather_Widget::get_widget_slug() ) . '</h2>';
		}
	}

	public function awfn_logs_page_init() {
		register_setting(
			'awfn_logs_option_group', // option_group
			'awfn_logs_option_name', // option_name
			array( $this, 'awfn_logs_sanitize' ) // sanitize_callback
		);

		add_settings_section(
			'awfn_logs_setting_section', // id
			'Settings', // title
			array( $this, 'awfn_logs_section_info' ), // callback
			'awfn-logs-admin' // page
		);

		add_settings_field(
			'debug_0', // id
			'Debug', // title
			array( $this, 'debug_0_callback' ), // callback
			'awfn-logs-admin', // page
			'awfn_logs_setting_section' // section
		);

		add_settings_field(
			'debug_1',
			'SSLVerify',
			array( $this, 'debug_1_callback' ),
			'awfn-logs-admin',
			'awfn_logs_setting_section'
		);
	}

	public function awfn_logs_sanitize($input) {
		$sanitary_values = array();
		if ( isset( $input['debug_0'] ) ) {
			$sanitary_values['debug_0'] = $input['debug_0'];
		}
		if ( isset( $input['debug_1'] ) ) {
			$sanitary_values['debug_1'] = $input['debug_1'];
		}

		return $sanitary_values;
	}

	public function awfn_logs_section_info() {

	}

	public function debug_0_callback() {
		printf(
			'<input type="checkbox" name="awfn_logs_option_name[debug_0]" id="debug_0" value="debug_0" %s> <label for="debug_0">Check to enable logging</label>',
			( isset( $this->awfn_logs_options['debug_0'] ) && $this->awfn_logs_options['debug_0'] === 'debug_0' ) ? 'checked' : ''
		);
	}

	public function debug_1_callback() {
		printf(
			'<input type="checkbox" name=awfn_logs_option_name[debug_1]" id="debug_1" value="debug_1" %s><label for="debug_1">Check to <strong>disable</strong> sslverify in wp_remote_get()</label>
 <p>(only check this if you are seeing Bad Request errors in the logs)</p>',
			( isset( $this->awfn_logs_options['debug_1'] ) && $this->awfn_logs_options['debug_1'] === 'debug_1' ) ? 'checked' : ''
		);
	}

	public static function clear_all_logs() {
		check_ajax_referer( 'awfn_clear_logs', 'secure' );

		$files = self::get_log_files();
		foreach ( $files as $filename ) {
			$handle = fopen( $filename, 'w' );
			if ( $handle ) {
				fwrite( $handle, 'Log cleared ' . current_time( 'mysql' ) . "\n" );
				fclose( $handle );
			}
		}

		wp_send_json_success( 'ALL CLEARED' );
	}

	/**
	 * AJAX call to clear log files
	 */
	public static function clear_log() {
		check_ajax_referer( 'awfn_clear_logs', 'secure' );

		$filename = $_POST['file'];

		$handle = fopen( $filename, 'w' );
		if ( $handle ) {
//			ftruncate( $handle, 0 );
			$time = current_time( 'mysql' );
			fwrite( $handle, 'Log cleared ' . $time . "\n" );
			fclose( $handle );
		}

		wp_send_json_success( $filename . ' CLEARED' );
	}

	/**
	 * Retrieve any log files in the logs directory
	 * @return array log files
	 */
	private static function get_log_files() {
		$dir      = new RecursiveDirectoryIterator( plugin_dir_path( dirname( __FILE__ ) ) . 'logs', RecursiveDirectoryIterator::SKIP_DOTS );
		$iterator = new RecursiveIteratorIterator( $dir, RecursiveIteratorIterator::SELF_FIRST );

		# We create our list of files in the theme
		$files = array();
		foreach ( $iterator as $file ) {
			if ( substr( $file->getFilename(), - 4 ) == '.log' ) {
				array_push( $files, $file->getPathname() );
			}
		}

		return $files;
	}

}

/**
 * this only happens in admin and if AWFN_DEBUG is true
 */
//$debug = ( defined( 'AWFN_DEBUG' ) && AWFN_DEBUG ) ? true : false;
if ( is_admin() ) {
	$awfn_errors = new AWFNLogs();
}

/* 
 * $awfn_logs_options = get_option( 'awfn_logs_option_name' ); // Array of All Options
 * $debug_0 = $awfn_logs_options['debug_0']; // Debug
 */