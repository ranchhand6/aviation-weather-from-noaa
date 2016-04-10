<?php

/**
 * Class AwfnPirep
 *
 * This class retrieves PIREPS from a specified timeframe and builds the HTML output
 *
 * @package     Aviation Weather from NOAA
 * @subpackage  Pirep
 * @since       0.4.0
 */
class AwfnPirep extends Awfn {

	/**
	 * AwfnPirep constructor.
	 *
	 * Builds URL for Awfn::load_xml()
	 *
	 * @param  string $icao
	 * @param  float $lat
	 * @param  float $lng
	 * @param int $distance
	 * @param int $hours
	 * @param bool $show
	 *
	 * @since 0.4.0
	 */
	public function __construct( $icao = '', $lat, $lng, $distance = 100, $hours = 2, $show = true ) {


		self::$log_name = 'AircraftReport';

		parent::__construct();

		$this->icao  = $icao;
		$this->show  = (bool) $show;
		$this->hours = (int) $hours;
		$base        = 'https://aviationweather.gov/adds/dataserver_current/httpparam?dataSource=aircraftreports&requestType=retrieve';
		$base .= '&format=xml&radialDistance=%d;%f,%f&hoursBeforeNow=%d';
		$this->url = sprintf( $base, $distance, $lng, $lat, $hours );

		$this->maybelog( 'info', 'New for ' . $icao );

	}

	/**
	 * Iterates through SimpleXMLElement to retrieve pireps
	 *
	 * data should include at least one pirep by the time it arrives here.
	 *
	 * @since 0.4.0
	 */
	public function decode_data() {
		$this->maybelog( 'debug', 'decode_data()' );
		if ( $this->xmlData ) {
			$this->maybelog( 'debug', 'We have data with count ' . count( $this->xmlData ) . ' ' . __FUNCTION__ );
			foreach ( $this->xmlData as $report ) {
				$this->data[] = (string) $report->raw_text;
			}
		} else { // This should never be called
			$this->maybelog( 'warning', 'No data found for ' . $this->icao );
		}
	}

	/**
	 * Builds HTML output for display on front-end
	 *
	 * @since 0.4.0
	 */
	public function build_display() {
		$this->maybelog( 'debug', 'build_display()' );

		if ( $this->data ) {

			$count = count( $this->data );
			$this->maybelog( 'debug', 'We have data with count ' . $count . ' ' . __FUNCTION__ );
			$count_display = sprintf( '<span class="awfn-min">(%d)</span>', $count );
			$this->maybelog( 'debug', 'Pirep count for ' . $this->icao . ': ' . $count, __LINE__ );

			$this->display_data = '<header>';
			$this->display_data .= sprintf( _n( 'Pirep %s', 'Pireps %s', $count, Adds_Weather_Widget::get_widget_slug() ), $count_display );
			$this->display_data .= '<span class="fa fa-sort-desc"></span></header><section id="all-pireps">';

			foreach ( $this->data as $pirep ) {
				$this->display_data .= sprintf( '<article class="pirep">%s</article>', $pirep );
			}
			$this->display_data .= '</section>';
		} else {
			$this->display_data = '<article class="no-pirep">' . __( 'No PIREPS found', Adds_Weather_Widget::get_widget_slug() ) .
			                      '</article>';
		}
	}
}