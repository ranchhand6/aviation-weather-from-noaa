<?php

/**
 * Class AwfnTaf
 *
 * This class retrieves, caches and builds HTML output for the most recent Terminal Area Forecast
 */
class AwfnTaf extends Awfn {

	/**
	 * AwfnTaf constructor.
	 *
	 * Builds URL for Awfn::load_xml()
	 *
	 * @param string $icao
	 * @param int $hours
	 * @param bool $show
	 *
	 * @since 0.4.0
	 */
	public function __construct( $icao = 'KSMF', $hours = 2, $show = true ) {

		self::$log_name = 'TAF';

		parent::__construct();

		$url = 'https://www.aviationweather.gov/adds/dataserver_current/httpparam?dataSource=tafs&requestType=retrieve&format=xml';
		$url .= '&mostRecent=true&stationString=%s&hoursBeforeNow=%d';

		$this->url   = sprintf( $url, $icao, $hours );
		$this->icao  = $icao;
		$this->hours = $hours;
		$this->show  = $show;

		$this->maybelog( 'info', 'New for ' . $this->icao );
	}

	/**
	 * Copies raw taf, or no data found message, for display later
	 *
	 * @since 0.4.0
	 */
	public function decode_data() {
		$this->maybelog( 'debug', 'decod_data()' );
		if ( $this->xmlData ) {
			$this->maybelog( 'debug', 'We have xmlData' );
			$this->data = (string) $this->xmlData->raw_text;
		} else { // Should never get to this point
			$this->maybelog( 'debug', 'No data found for ' . $this->icao );
		}
	}

	/**
	 * Build HTML output for display on front-end
	 *
	 * @since 0.4.0
	 */
	public function build_display() {
		$this->maybelog( 'debug', 'build_display()' );
		if ( $this->data ) {
			$this->maybelog( 'debug', 'We have data: ' . $this->data );
			$this->display_data = '<header>TAF</header><article class="taf">' . esc_html( $this->data )
			                      . '</article>';
		} else {
			$this->display_data = '<article class="taf">No TAF returned</article>';
			$this->maybelog( 'debug', 'No data returned for ' . $this->icao . '/' . $this->hours . ' hours', null );
		}
	}


}