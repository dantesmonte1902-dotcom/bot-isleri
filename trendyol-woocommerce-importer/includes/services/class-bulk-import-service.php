<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Bulk_Import_Service {

	private $import_service;

	public function __construct() {
		$this->import_service = new Trendyol_Import_Service();
	}

	public function normalize_url_list( $textarea_input = '', $file_tmp_path = '' ) {
		$linklist = array();

		if ( ! empty( $file_tmp_path ) && file_exists( $file_tmp_path ) ) {
			$text  = file_get_contents( $file_tmp_path );
			$lines = preg_split( '/\r\n|\r|\n/', $text );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$linklist[] = $line;
				}
			}
		}

		if ( ! empty( $textarea_input ) ) {
			$lines = preg_split( '/\r\n|\r|\n/', $textarea_input );
			foreach ( $lines as $line ) {
				$line = trim( $line );
				if ( '' !== $line ) {
					$linklist[] = $line;
				}
			}
		}

		return array_values( array_unique( $linklist ) );
	}

	public function import_urls( $urls, $options = array() ) {
		$results = array(
			'total'   => 0,
			'success' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'html'    => '',
			'items'   => array(),
		);

		$urls = array_values( array_unique( array_filter( array_map( 'trim', (array) $urls ) ) ) );

		if ( empty( $urls ) ) {
			$results['html'] = '<b>Hiç ürün linki verilmedi!</b>';
			return $results;
		}

		$results['total'] = count( $urls );
		$overrides        = array();

		if ( ! empty( $options['category'] ) ) {
			$overrides['category'] = sanitize_text_field( $options['category'] );
		}

		foreach ( $urls as $url ) {
			$product_id = $this->import_service->import_from_url( $url, $overrides );

			if ( is_wp_error( $product_id ) ) {
				$status = 'error';

				if ( in_array( $product_id->get_error_code(), array( 'already_exists', 'blocked_brand' ), true ) ) {
					$results['skipped']++;
					$status = 'skip';
				} else {
					$results['failed']++;
				}

				if ( 'blocked_brand' === $product_id->get_error_code() ) {
					$html = "<span style='color:#dc2626'>🚫 " . esc_html( $product_id->get_error_message() ) . '</span>';
				} elseif ( 'already_exists' === $product_id->get_error_code() ) {
					$html = "<span style='color:orange'>Atlandı (Zaten ekli): " . esc_html( $url ) . '</span>';
				} else {
					$html = "<span style='color:red'>Hata: " . esc_html( $url ) . ' - ' . esc_html( $product_id->get_error_message() ) . '</span>';
				}

				$results['items'][] = array(
					'url'        => $url,
					'status'     => $status,
					'error_code' => $product_id->get_error_code(),
					'message'    => $product_id->get_error_message(),
					'html'       => $html,
				);

				$results['html'] .= $html . '<br>';
				continue;
			}

			$results['success']++;

			$html = "<span style='color:green'>Eklendi: <a href='" . esc_url( get_edit_post_link( $product_id ) ) . "' target='_blank'>" . esc_html( $url ) . '</a></span>';

			$results['items'][] = array(
				'url'        => $url,
				'status'     => 'success',
				'product_id' => $product_id,
				'message'    => 'Başarıyla eklendi',
				'html'       => $html,
			);

			$results['html'] .= $html . '<br>';
		}

		return $results;
	}
}