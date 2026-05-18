<?php
/**
 * Plugin Name: Trendyol WooCommerce İçe Aktarıcı
 * Plugin URI: https://www.sercan.com.tr
 * Description: Trendyol'dan ürünleri çekerek WooCommerce'e aktaran plugin
 * Version: 1.1.0
 * Author: Your Name
 * Author URI: https://www.sercan.com.tr
 * License: GPL v2 or later
 * Text Domain: trendyol-woocommerce-importer
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TRENDYOL_IMPORTER_VERSION', '1.1.0' );
define( 'TRENDYOL_IMPORTER_PATH', plugin_dir_path( __FILE__ ) );
define( 'TRENDYOL_IMPORTER_URL', plugin_dir_url( __FILE__ ) );

require_once TRENDYOL_IMPORTER_PATH . 'includes/tools.php';

$required_files = array(
	'includes/class-logger.php',
	'includes/class-settings.php',
	'includes/class-scraper.php',
	'includes/class-product-importer.php',

	'includes/services/class-product-query-service.php',
	'includes/services/class-log-service.php',
	'includes/services/class-price-service.php',
	'includes/services/class-dashboard-service.php',
	'includes/services/class-stock-sync-service.php',
	'includes/services/class-import-service.php',
	'includes/services/class-bulk-import-service.php',
	'includes/services/class-settings-service.php',
	'includes/services/class-kargo-service.php',
	'includes/services/class-category-service.php',
	'includes/services/class-title-bulk-update-service.php',
	'includes/services/class-title-ai-update-service.php',
	'includes/services/class-history-service.php',
	'includes/services/class-scraper-diagnostic-service.php',

	'includes/class-bulk-price-updater.php',
	'includes/class-dashboard-stats.php',
	'includes/class-variant-stock-sync.php',

	'includes/class-sync-manager.php',
	'includes/class-cron-manager.php',
	'includes/class-diff-detector.php',
	'includes/class-notifier.php',
	'includes/class-telegram-bot.php',
	'includes/class-admin.php',
);

foreach ( $required_files as $file ) {
	$file_path = TRENDYOL_IMPORTER_PATH . $file;
	if ( ! file_exists( $file_path ) ) {
		wp_die( sprintf( 'Trendyol Plugin dosyası bulunamadı: %s', esc_html( $file ) ) );
	}
	require_once $file_path;
}

register_activation_hook( __FILE__, array( 'Trendyol_Admin', 'activate_plugin' ) );
register_deactivation_hook( __FILE__, array( 'Trendyol_Admin', 'deactivate_plugin' ) );

function trendyol_importer_init() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'trendyol_importer_woocommerce_missing' );
		return;
	}

	load_plugin_textdomain(
		'trendyol-woocommerce-importer',
		false,
		dirname( plugin_basename( __FILE__ ) ) . '/languages'
	);

	try {
		Trendyol_Settings::init_defaults();
	} catch ( Exception $e ) {}

	try {
		new Trendyol_Admin();
	} catch ( Exception $e ) {}

	try {
		new Trendyol_Cron_Manager();
	} catch ( Exception $e ) {}
}

add_action( 'plugins_loaded', 'trendyol_importer_init' );

function trendyol_importer_woocommerce_missing() {
	echo '<div class="error"><p>' . esc_html__( 'Trendyol WooCommerce İçe Aktarıcı, WooCommerce\'in kurulu ve etkin olmasını gerektirir.', 'trendyol-woocommerce-importer' ) . '</p></div>';
}

add_action( 'wp_ajax_trendyol_bulk_import_ajax', function() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_send_json_error( 'Yetki yok' );
	}

	if ( empty( $_POST['links'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_bulk_import_nonce' ) ) {
		wp_send_json_error( 'Geçersiz' );
	}

	$linkarr = json_decode( stripslashes( $_POST['links'] ), true );
	if ( ! is_array( $linkarr ) ) {
		wp_send_json_error( 'Geçersiz link dizisi' );
	}

	$kategori_ad  = isset( $_POST['kategori_ad'] ) ? sanitize_text_field( $_POST['kategori_ad'] ) : '';
	$bulk_service = new Trendyol_Bulk_Import_Service();
	$result       = $bulk_service->import_urls(
		$linkarr,
		array(
			'category' => $kategori_ad,
		)
	);

	$ajax_results = array();

	foreach ( $result['items'] as $item ) {
		$ok = false;

		if ( 'success' === $item['status'] ) {
			$ok = true;
		} elseif ( 'skip' === $item['status'] ) {
			$ok = 'skip';
		}

		$ajax_results[] = array(
			'ok'         => $ok,
			'status'     => $item['status'],
			'url'        => isset( $item['url'] ) ? $item['url'] : '',
			'message'    => isset( $item['message'] ) ? $item['message'] : '',
			'error_code' => isset( $item['error_code'] ) ? $item['error_code'] : '',
			'text'       => $item['html'],
		);
	}

	wp_send_json(
		array(
			'results' => $ajax_results,
			'summary' => array(
				'total'   => $result['total'],
				'success' => $result['success'],
				'skipped' => $result['skipped'],
				'failed'  => $result['failed'],
			),
		)
	);
} );
