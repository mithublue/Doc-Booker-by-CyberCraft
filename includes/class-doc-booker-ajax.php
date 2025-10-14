<?php
/**
 * AJAX controller for Doc Booker directory filtering.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Doc_Booker_Ajax {
	public const ACTION = 'doc_booker_filter_directory';
	public const NONCE  = 'doc_booker_directory_nonce';

	/**
	 * @var Doc_Booker_Shortcode
	 */
	private $shortcode;

	public function __construct( Doc_Booker_Shortcode $shortcode ) {
		$this->shortcode = $shortcode;

		add_action( 'wp_ajax_' . self::ACTION, [ $this, 'handle' ] );
		add_action( 'wp_ajax_nopriv_' . self::ACTION, [ $this, 'handle' ] );
	}

	public function handle() {
		their_check:
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), self::NONCE ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request. Please refresh the page.', 'doc-booker' ) ], 403 );
		}

		$filters_raw = isset( $_POST['filters'] ) ? (array) wp_unslash( $_POST['filters'] ) : [];

		$letter = isset( $filters_raw['letter'] ) ? sanitize_text_field( $filters_raw['letter'] ) : Doc_Booker_Shortcode::DEFAULT_LETTER;
		$letter = strtolower( $letter );

		$valid_letters = array_map( 'strtolower', range( 'A', 'Z' ) );
		if ( 'all' !== $letter && ! in_array( $letter, $valid_letters, true ) ) {
			$letter = Doc_Booker_Shortcode::DEFAULT_LETTER;
		}

		$filters = [
			'department'   => isset( $filters_raw['department'] ) ? sanitize_text_field( $filters_raw['department'] ) : '',
			'name'         => isset( $filters_raw['name'] ) ? sanitize_text_field( $filters_raw['name'] ) : '',
			'letter'       => $letter,
			'date'         => isset( $filters_raw['date'] ) ? sanitize_text_field( $filters_raw['date'] ) : '',
			'availability' => isset( $filters_raw['availability'] ) ? sanitize_text_field( $filters_raw['availability'] ) : '',
		];

		$data = $this->shortcode->get_directory_data( $filters );

		$html = $this->shortcode->render_directory_groups( $data['groups'] );

		wp_send_json_success(
			[
				'html'  => $html,
				'total' => $data['total'],
			]
		);
	}
}
