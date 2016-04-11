<?php

use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

/**
 * Class Awfn
 *
 * This class defines subclasses and retrieves XML data for subclasses
 *
 * @package    Aviation Weather from NOAA
 * @subpackage AWFN
 * @since      0.4.0
 */
abstract class Awfn {

	protected static $log_name;
	protected $log = false;
	protected $hours;
	protected $station = false;
	protected $icao = false;
	protected $show;
	protected $base_url;
	protected $url;
	protected $data = false;
	protected $display_data = false;
	public $xmlData = false;
	protected $decoded = false;

	public function get_hours() {
		return $this->hours;
	}

	public function get_show() {
		return $this->show;
	}

	public function has_data() {
		return $this->display_data;
	}

	/**
	 * Awfn constructor.
	 *
	 * Set up logger for individual subclasses.
	 * Due to permissin issues we use AWFN_DEBUG instead of WP_DEBUG in case that is set true for other reasons.
	 *
	 * TODO: Research file permission issues
	 * Inside plugin dir: "sudo mkdir logs", "sudo chown `whoami` logs", "chmod 700 logs"
	 *
	 * @since 0.4.0
	 */
	public function __construct() {

		$this->prepare_logger();
	}

	private function prepare_logger() {
		$awfn_logs_options = get_option( 'awfn_logs_option_name' );
		$debug_0           = isset( $awfn_logs_options['debug_0'] ) ? true : false;

		// Prepare logger
		if ( $debug_0 ) {

			$dev_log_dir = PLUGIN_ROOT . 'logs';

			// Permissions for his one are up to you, for now. Sorry.
			if ( ! file_exists( $dev_log_dir ) ) {
				mkdir( $dev_log_dir, 0700, true );
			}
			$prod_log_dir = PLUGIN_ROOT . 'logs';
			if ( ! file_exists( $prod_log_dir ) ) {
				mkdir( $prod_log_dir, 0700, true );
			}
			$this->log       = new Logger( static::$log_name );
			$formatter       = new LineFormatter( "[%datetime%] > %channel%.%level_name%: %message%\n" );
			$info_handler    = new StreamHandler( PLUGIN_ROOT . 'logs/info.log', Logger::INFO, false );
			$debug_handler   = new StreamHandler( PLUGIN_ROOT . 'logs/debug.log', Logger::DEBUG );
			$warning_handler = new StreamHandler( PLUGIN_ROOT . 'logs/warning.log', Logger::WARNING, false );
			$info_handler->setFormatter( $formatter );
			$debug_handler->setFormatter( $formatter );
			$warning_handler->setFormatter( $formatter );
			$this->log->pushHandler( $debug_handler );
			$this->log->pushHandler( $info_handler );
			$this->log->pushHandler( $warning_handler );
		}
	}

	/**
	 * Log debug or warning messages if our logger is set up.
	 *
	 * @param $severity     string debug | warning
	 * @param $msg          string Message to log
	 */
	protected function maybelog( $severity, $msg ) {

		if ( false !== $this->log ) {
			if ( is_array( $msg ) ) {
				$this->log->$severity( print_r( $msg, true ) );
			} else {
				$this->log->$severity( $msg );
			}
		}

	}

	/**
	 * Wrapper for subclass functions
	 *
	 * @since 0.4.0
	 */
	public function go( $display = true ) {

		if ( $display ) {
			$this->maybelog( 'info', 'go(true)' );
		} else {
			$this->maybelog( 'info', 'go(false)' );
		}

		if ( $this->load_xml() ) {
			$this->maybelog( 'debug', 'load_xml() returned true' );

			$this->decode_data();
			$this->build_display();

			if ( $display ) {

				$this->display_data();
			}
		}

	}

	/**
	 * Abstract function for building HTML output
	 *
	 * @since 0.4.0
	 */
	abstract public function build_display();

	/**
	 * Abstract function for decoding XML data
	 *
	 * @since 0.4.0
	 */
	abstract public function decode_data();

	/**
	 * Outputs HTML built by subclasses
	 *
	 * @since 0.4.0
	 */
	public function display_data() {

		if ( $this->display_data && $this->show ) {

			echo '<section id="' . strtolower( static::$log_name ) . '">';
			echo $this->display_data;
			echo '</section>';

		} else {
			return $this->display_data;
		}

	}

	public function will_show() {
		return $this->show;
	}

	/**
	 * Retrieves XML data using URL provided by subclass and returns array converted from simplexmlelement
	 *
	 *
	 *
	 * @since 0.4.0
	 */
	public function load_xml() {

		$xml_raw = wp_remote_get( esc_url_raw( $this->url ) );

		$this->maybelog( 'debug', '$this->url ' . __FUNCTION__ . ':' . __LINE__ );
		$this->maybelog( 'debug', 'URL: ' . $this->url );
		$this->maybelog( 'debug', '$xml_raw:' );
		$this->maybelog( 'debug', $xml_raw );

		if ( is_wp_error( $xml_raw ) ) {
			$this->maybelog( 'debug', $xml_raw->get_error_message() . ':' . __FUNCTION__ . ':' . __LINE__ );
			$this->xmlData = false;

			return false;
		}

		if ( 200 == wp_remote_retrieve_response_code( $xml_raw ) ) {
			$body = wp_remote_retrieve_body( $xml_raw );

			$this->maybelog( 'debug', '$body:' );
			$this->maybelog( 'debug', $body );

			if ( '' == $body || strpos( $body, '<!DOCTYPE' ) ) {
				$this->maybelog( 'debug', '$body contains empty string or found "<!DOCTYPE" : ' . __FUNCTION__ . ':' . __LINE__ );
				return false;
			}

			libxml_use_internal_errors(true);

			$loaded = simplexml_load_string( $body );
			$xml = explode( "\n", $body );
			if( false === $loaded ) {
				$this->maybelog( 'debug', 'Could not load body into simplexml :' . __FUNCTION__ . ':' . __LINE__ );
				$errors = libxml_get_errors();
				$this->maybelog( 'warning', 'Errors ' . __FUNCTION__ . ':' . __LINE__ );
				$this->maybelog('warning', $errors );

				foreach ($errors as $error) {
					$this->maybelog('debug', $this->display_xml_error( $error, $xml ) );
				}

				libxml_clear_errors();

				return false;
			}


			$atts = $loaded->data->attributes();
			$results = isset( $atts['num_results'] ) ? $atts['num_results'] : 0;
			$this->maybelog( 'debug', 'Results: ' . $results . ' ' . __FUNCTION__ . ':' . __LINE__ );


			if ( 0 < $results ) {
				$this->xmlData = $loaded->data->{static::$log_name};
				$this->maybelog( 'info', static::$log_name . ' XML loaded for ' . $this->icao . ': ' . __FUNCTION__ . ':' . __LINE__ );

				return true;
			}
		} else {
			$this->maybelog('debug', $xml_raw->get_error_message() );
			$this->maybelog( 'debug', 'No xml loaded for ' . $this->icao );

			return false;
		}



	}

	protected function display_xml_error($error, $xml)
	{
		$return  = $xml[$error->line - 1] . "<br />\n";
		$return .= str_repeat('-', $error->column) . "^<br />\n";

		switch ($error->level) {
			case LIBXML_ERR_WARNING:
				$return .= "Warning $error->code: ";
				break;
			case LIBXML_ERR_ERROR:
				$return .= "Error $error->code: ";
				break;
			case LIBXML_ERR_FATAL:
				$return .= "Fatal Error $error->code: ";
				break;
		}

		$return .= trim($error->message) .
		           "<br />\n  Line: $error->line" .
		           "<br />\n  Column: $error->column";

		if ($error->file) {
			$return .= "<br />\n  File: $error->file";
		}

		return "$return<br />\n\n--------------------------------------------<br />\n\n";
	}

}