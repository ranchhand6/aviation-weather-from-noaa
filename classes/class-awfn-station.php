<?php

/**
 * Class AwfnStation
 *
 * This class retrieves, caches and builds HTML output for individual airports based on ICAO code
 *
 * @package     Aviation Weather from NOAA
 * @subpackage  Station
 * @since       0.4.0
 */
class AwfnStation extends Awfn {

	/**
	 * AwfnStation constructor.
	 *
	 * Builds URL for Awfn::load_xml()
	 *
	 * @param      $icao
	 * @param bool $show
	 *
	 * @since 0.4.0
	 */
	public function __construct( $icao, $show = false ) {

		self::$log_name = 'Station';

		parent::__construct();

		$this->icao = strtoupper( sanitize_text_field( $icao ) );
		$this->show = (bool) $show;
		$this->maybelog( 'info', 'New for ' . $this->icao );

		$this->base_url = 'https://www.aviationweather.gov/adds/dataserver_current/httpparam?dataSource=stations';
		$this->base_url .= '&requestType=retrieve&format=xml&stationString=%s';
		$this->url = sprintf( $this->base_url, $this->icao );

		$this->clean_icao();

	}

	/**
	 * Does airport exist?
	 *
	 * @return bool
	 * @since 0.4.0
	 */
	public function station_exist() {
		return $this->xmlData ? true : false;
	}

	public function get_icao() {
		return $this->icao;
	}

	/**
	 * Return airport latitude if available
	 *
	 * @return bool|string
	 * @since 0.4.0
	 */
	public function lat() {
		return $this->xmlData ? (float) $this->xmlData['latitude'] : false;
	}

	/**
	 * Return airport longitude if available
	 *
	 * @return bool|string
	 * @since 0.4.0
	 */
	public function lng() {
		return $this->xmlData ? (float) $this->xmlData['longitude'] : false;
	}

	/**
	 * Static wrapper for clean_icao()
	 *
	 * @param $icao
	 *
	 * @return string
	 * @since 0.4.0
	 */
	public static function static_clean_icao( $icao ) {

		$airport = new self( $icao );

		if ( $airport->station_exist() ) {
			return (string) $airport->get_icao();
		} else {
			return false;
		}

	}

	/**
	 * Validates potential ICAO
	 * If given ICAO is only 3 chars it will cycle through to check for US, CA, AU and GB matches.
	 * filterable
	 *
	 * @since 0.4.0
	 */
	public function clean_icao() {

		$this->maybelog( 'debug', 'clean_icao()' . '/' . __FUNCTION__ . ':' . __LINE__ );

		if ( ! preg_match( '/^[A-Za-z]{3,4}$/', $this->icao, $matches ) ) {
			// $this->station has no chance of being legit
//			$this->station = '';
			$this->maybelog( 'debug', 'No pregmatch for ' . $this->icao . '/' . __FUNCTION__ . ':' . __LINE__ );

			return false;
		}

		// If ICAO is only 3 chars we'll check some possibilities; filterable
		if ( strlen( $matches[0] ) == 3 ) {
			$this->maybelog( 'debug', 'Trying to find match for ' . $this->icao );
			foreach ( apply_filters( 'awfn_icao_search_array', array( 'K', 'C', 'M' ) ) as $first_letter ) {
				$this->icao = $first_letter . $matches[0];
				$this->url = sprintf( $this->base_url, $this->icao );
				$this->maybelog( 'debug', 'Looking for match: ' . $this->icao . '/' . __FUNCTION__ . ':' . __LINE__ );
				if ( $this->get_apt_info() ) {
					$this->maybelog( 'debug', 'Found match for ' . $this->icao . '/' . __FUNCTION__ . ':' . __LINE__ );
					break;
				}
			}
		} else {

			// We have a 4 char ICAO so let's see if we can find a match
			$this->get_apt_info();
		}

		// No match found
		if ( false === $this->xmlData ) {
			$this->maybelog( 'debug', 'No xmlData' );

			return false;
		}

	}

	/**
	 * Retrieves airport information and caches in option using ICAO as key
	 *
	 * @return bool
	 * @since 0.4.0
	 */
	public function get_apt_info() {

		$this->maybelog( 'debug', 'get_apt_info() ' . $this->icao . '/' . __FUNCTION__ . ':' . __LINE__ );

		// If we don't have a possible match, bail
		if ( ! preg_match( '~^[A-Za-z0-9]{4,4}$~', $this->icao, $matches ) ) {
			$this->maybelog( 'debug', 'No pregmatch for ' . $this->icao . '/' . __FUNCTION__ . ':' . __LINE__ );

			return false;
		}

//		$station_name = strtoupper( $this->icao );

		// Check our stored option for matching ICAO data
		$stations = get_option( STORED_STATIONS_KEY, array() );
		$this->maybelog( 'debug', 'Stored stations found ' . __FUNCTION__ . ':' . __LINE__ );
		$this->maybelog( 'debug', $stations );

		if ( isset( $stations[ $this->icao ] ) ) {
			$this->maybelog( 'debug', 'We have stored data for ' . $this->icao . '/' . __FUNCTION__ . ':' . __LINE__ );
			// Use cached station data
			$this->xmlData = $stations[ $this->icao ];
		} else {
			$this->maybelog( 'debug', 'No stored data found for ' . $this->icao . '/' . __FUNCTION__ . ':' . __LINE__ );
			// No match found in option so we need to go external
			$this->load_xml();

			if ( $this->xmlData ) {
				$this->maybelog( 'debug', 'We have xmlData ' . __FUNCTION__ . ':' . __LINE__ );
				// Update option with new station data
				$stations[ $this->icao ] = json_decode( json_encode( $this->xmlData ), 1 );
				if ( update_option( STORED_STATIONS_KEY, $stations ) ) {
					$this->maybelog( 'info', 'Station option updated ' . __FUNCTION__ . ':' . __LINE__ );
				} else {
					$this->maybelog( 'info', 'Station option not updated ' . __FUNCTION__ . ':' . __LINE__ );
				}
			}
		}

		if ( $this->xmlData ) {
			$this->maybelog( 'debug', '$this->xmlData ' . __FUNCTION__ . ':' . __LINE__ );
			$this->maybelog( 'debug', $this->xmlData );

			return true;
		} else {
			$this->maybelog( 'debug', 'No xmlData: ' . $this->icao . '/' . __FUNCTION__ . ':' . __LINE__ );

			return false;
		}
//		return $this->xmlData ? true : false;
	}

	/**
	 * Static wrapper for get_apt_info()
	 *
	 * @param $icao
	 *
	 * @return bool
	 *
	 * @since 0.4.0
	 */
	public static function static_apt_info( $icao ) {
		$airport = new self( $icao );
		$airport->clean_icao();
		$airport->get_apt_info();

		return $airport->xmlData;
	}

	/**
	 * Copy xmlData to data in order to match functionality among subclasses
	 *
	 * @since 0.4.0
	 */
	public function decode_data() {
		// doing this to match other sub-classes functionality when building display
		$this->data = $this->xmlData;
		$this->maybelog( 'debug', __FUNCTION__ . ':' . __LINE__ );
		$this->maybelog( 'debug', $this->data );
	}

	/**
	 * Build HTML output for display on front-end
	 * currently uses city and state
	 *
	 * @since 0.4.0
	 */
	public function build_display() {

		$this->maybelog('debug', __FUNCTION__ . ':' . __LINE__ );

		// TODO: improve
		if ( $this->data && $this->show ) {
			$keys = array( 'site', 'state' );
			foreach ( $keys as $key ) {
				if ( isset( $this->data[ $key ] ) ) {
					$this->maybelog( 'debug', $key . ' found in $this->data' . '/' . __FUNCTION__ . ':' . __LINE__ );
					$location_array[] = $this->data[ $key ];
				} else {
					$this->maybelog( 'debug', $key . ' not found in $this->data' . '/' . __FUNCTION__ . ':' . __LINE__ );
					$this->maybelog( 'debug', $this->data );
				}
			}

			if ( ! empty( $location_array ) ) {
				$location           = implode( ', ', array_filter( $location_array ) );
				$this->display_data = '<header>' . esc_html( $location ) . '</header>';
			} else {
				$this->display_data = '';
			}
			$this->maybelog('debug', 'Display: ' . $this->display_data );
		} else {
			$this->maybelog('debug', 'No data or No show' . '/' . __FUNCTION__ . ':' . __LINE__ );
			$this->maybelog('debug', $this->data );
			if( $this->show ) {
				$this->maybelog('debug', 'No data' . '/' . __FUNCTION__ . ':' . __LINE__ );
			} else {
				$this->maybelog('debug', 'No Shaw' . '/' . __FUNCTION__ . ':' . __LINE__ );
			}
			return $this->data;
		}

	}
}