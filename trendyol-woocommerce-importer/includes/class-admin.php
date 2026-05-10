<?php
/**
 * Admin Class - Önizleme, import, toplu import, varyant stok senkronizasyonu
 * + WooCommerce ürün listesinde € Net Kâr sütunu
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Trendyol_Admin {

	private $log_service;
	private $price_service;
	private $import_service;
	private $bulk_import_service;
	private $product_query_service;

	public function __construct() {
		$this->log_service         = new Trendyol_Log_Service();
		$this->price_service       = new Trendyol_Price_Service();
		$this->import_service      = new Trendyol_Import_Service();
		$this->bulk_import_service = new Trendyol_Bulk_Import_Service();
		$this->product_query_service = new Trendyol_Product_Query_Service();

		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'wp_ajax_trendyol_test_telegram', array( $this, 'ajax_test_telegram' ) );
		add_action( 'wp_ajax_trendyol_variant_stock_sync', array( $this, 'ajax_variant_stock_sync' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		add_filter( 'manage_edit-product_columns', array( $this, 'add_profit_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( $this, 'render_profit_column' ), 10, 2 );

		add_action( 'admin_head-edit.php', array( $this, 'print_products_list_profit_column_css' ) );
	}

	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			esc_html__( 'Trendyol İçe Aktarıcı', 'trendyol-woocommerce-importer' ),
			esc_html__( 'Trendyol İçe Aktarıcı', 'trendyol-woocommerce-importer' ),
			'manage_woocommerce',
			'trendyol-importer',
			array( $this, 'render_admin_page' )
		);
	}

	public function render_admin_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
		}

		if ( ! session_id() ) {
			session_start();
		}

		if (
			isset( $_POST['trendyol_preview'] ) &&
			! empty( $_POST['trendyol_url'] )
		) {
			check_admin_referer( 'trendyol_preview_nonce' );

			$url    = esc_url_raw( $_POST['trendyol_url'] );
			$result = $this->import_service->preview_url( $url );

			if ( is_wp_error( $result ) ) {
				$_SESSION['trendyol_preview_error'] = $result->get_error_message();
				unset( $_SESSION['trendyol_preview_data'] );
			} else {
				$_SESSION['trendyol_preview_data'] = $result;
				unset( $_SESSION['trendyol_preview_error'] );
			}

			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=import' ) );
			exit;
		}

		if ( isset( $_GET['cancelpreview'] ) && $_GET['cancelpreview'] == 1 ) {
			unset( $_SESSION['trendyol_preview_data'] );
			unset( $_SESSION['trendyol_preview_error'] );
			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=import' ) );
			exit;
		}

		if (
			isset( $_POST['action'] ) &&
			$_POST['action'] === 'trendyol_import' &&
			isset( $_POST['_wpnonce'] ) &&
			wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_import_nonce' )
		) {
			if ( ! Trendyol_Settings::user_can_import() ) {
				wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
			}

			$product_data = array(
				'name'     => isset( $_POST['product_name'] ) ? sanitize_text_field( $_POST['product_name'] ) : '',
				'price'    => isset( $_POST['product_price'] ) ? floatval( $_POST['product_price'] ) : 0,
				'sizes'    => isset( $_POST['product_sizes'] ) ? json_decode( wp_unslash( $_POST['product_sizes'] ), true ) : array(),
				'images'   => isset( $_POST['product_images'] ) ? json_decode( wp_unslash( $_POST['product_images'] ), true ) : array(),
				'content'  => isset( $_POST['product_content'] ) ? wp_kses_post( wp_unslash( $_POST['product_content'] ) ) : '',
				'url'      => isset( $_POST['product_url'] ) ? esc_url_raw( $_POST['product_url'] ) : '',
				'category' => isset( $_POST['product_category'] ) ? sanitize_text_field( $_POST['product_category'] ) : '',
				'brand'    => isset( $_POST['product_brand'] ) ? sanitize_text_field( $_POST['product_brand'] ) : '',
			);

			$product_id = $this->import_service->import_from_form_data( $product_data );

			if ( is_wp_error( $product_id ) ) {
				if ( 'already_exists' === $product_id->get_error_code() ) {
					$msg = '⚠️ ' . esc_html__( 'Bu Trendyol linkine sahip bir ürün zaten eklenmiş!', 'trendyol-woocommerce-importer' );
				} elseif ( 'blocked_brand' === $product_id->get_error_code() ) {
					$msg = '🚫 ' . esc_html( $product_id->get_error_message() );
				} else {
					$msg = '❌ ' . esc_html( $product_id->get_error_message() );
				}

				$_SESSION['trendyol_import_error'] = $msg;
				wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=import' ) );
				exit;
			}

			$_SESSION['trendyol_import_success'] = array(
				'id' => $product_id,
			);

			unset( $_SESSION['trendyol_preview_data'] );
			unset( $_SESSION['trendyol_preview_error'] );

			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=import' ) );
			exit;
		}

		if (
			isset( $_POST['trendyol_bulk_price_update'] ) &&
			isset( $_POST['_wpnonce'] ) &&
			wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_bulk_price_update_nonce' )
		) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
			}

			$status_filter = isset( $_POST['bulk_price_status'] ) ? sanitize_key( $_POST['bulk_price_status'] ) : 'both';

			$updater = new Trendyol_Bulk_Price_Updater();
			$result  = $updater->run( $status_filter );

			$this->log_service->log_bulk_price_update( $result );

			$_SESSION['trendyol_bulk_price_update_result'] = $result;

			wp_redirect( admin_url( 'admin.php?page=trendyol-importer' ) );
			exit;
		}

		if (
			isset( $_POST['trendyol_bulk_import'] ) &&
			isset( $_POST['_wpnonce'] ) &&
			wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_bulk_import_nonce' )
		) {
			$linklist = $this->bulk_import_service->normalize_url_list(
				isset( $_POST['bulk_links'] ) ? wp_unslash( $_POST['bulk_links'] ) : '',
				! empty( $_FILES['bulk_file']['tmp_name'] ) ? $_FILES['bulk_file']['tmp_name'] : ''
			);

			$result = $this->bulk_import_service->import_urls( $linklist );

			$_SESSION['bulk_import_result'] = $result['html'];
			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=bulk-import' ) );
			exit;
		}

		if (
			isset( $_POST['trendyol_export_product_urls'] ) &&
			isset( $_POST['_wpnonce'] ) &&
			wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_export_product_urls_nonce' )
		) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
			}

			$status_filter = $this->normalize_export_status_filter(
				isset( $_POST['export_status'] ) ? wp_unslash( $_POST['export_status'] ) : 'both'
			);
			$statuses      = $this->map_export_status_filter_to_statuses( $status_filter );
			$urls          = $this->product_query_service->get_trendyol_product_urls(
				array(
					'statuses' => $statuses,
				)
			);

			if ( empty( $urls ) ) {
				$_SESSION['trendyol_link_export_notice'] = 'Dışa aktarılacak Trendyol linki bulunamadı.';
				wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=link-export' ) );
				exit;
			}

			$this->download_trendyol_product_urls_txt( $urls, $status_filter );
		}

		if (
			isset( $_POST['trendyol_export_featured_images'] ) &&
			isset( $_POST['_wpnonce'] ) &&
			wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_export_featured_images_nonce' )
		) {
			if ( ! current_user_can( 'manage_woocommerce' ) ) {
				wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
			}

			$status_filter = $this->normalize_export_status_filter(
				isset( $_POST['featured_image_export_status'] ) ? wp_unslash( $_POST['featured_image_export_status'] ) : 'both'
			);
			$statuses      = $this->map_export_status_filter_to_statuses( $status_filter );
			$products      = $this->product_query_service->get_products_with_featured_images(
				array(
					'statuses' => $statuses,
				)
			);

			if ( empty( $products ) ) {
				$_SESSION['trendyol_featured_image_export_notice'] = 'İndirilecek öne çıkan görsel bulunamadı.';
				wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=featured-image-export' ) );
				exit;
			}

			$this->download_product_featured_images_zip( $products, $status_filter );
		}

		include TRENDYOL_IMPORTER_PATH . 'admin/admin-page.php';
	}

	private function map_export_status_filter_to_statuses( $status_filter ) {
		$status_filter = $this->normalize_export_status_filter( $status_filter );

		switch ( $status_filter ) {
			case 'publish':
				return array( 'publish' );
			case 'draft':
				return array( 'draft' );
			case 'both':
			default:
				return array( 'draft', 'publish' );
		}
	}

	private function normalize_export_status_filter( $status_filter ) {
		$allowed_status_filters = array( 'both', 'draft', 'publish' );
		$status_filter          = sanitize_key( (string) $status_filter );

		return in_array( $status_filter, $allowed_status_filters, true ) ? $status_filter : 'both';
	}

	private function download_trendyol_product_urls_txt( $urls, $status_filter ) {
		$status_filter = $this->normalize_export_status_filter( $status_filter );

		$filename = sprintf(
			'trendyol-urun-linkleri-%s-%s.txt',
			$status_filter,
			gmdate( 'Y-m-d-His' )
		);
		$content  = implode( "\r\n", $urls );

		nocache_headers();
		header( 'Content-Type: text/plain; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );

		echo $content;
		exit;
	}

	private function download_product_featured_images_zip( $products, $status_filter ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			$_SESSION['trendyol_featured_image_export_notice'] = 'Sunucuda ZIP desteği olmadığı için görseller indirilemedi.';
			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=featured-image-export' ) );
			exit;
		}

		$status_filter          = $this->normalize_export_status_filter( $status_filter );
		$zip_path               = wp_tempnam( 'trendyol-one-cikan-gorseller-' . $status_filter . '.zip' );

		if ( empty( $zip_path ) ) {
			$_SESSION['trendyol_featured_image_export_notice'] = 'ZIP dosyası hazırlanamadı.';
			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=featured-image-export' ) );
			exit;
		}

		$zip = new ZipArchive();

		if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			@unlink( $zip_path );
			$_SESSION['trendyol_featured_image_export_notice'] = 'ZIP dosyası oluşturulamadı.';
			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=featured-image-export' ) );
			exit;
		}

		$added_files = 0;

		foreach ( $products as $product ) {
			$product_id   = isset( $product['product_id'] ) ? intval( $product['product_id'] ) : 0;
			$thumbnail_id = isset( $product['thumbnail_id'] ) ? intval( $product['thumbnail_id'] ) : 0;
			$product_name = isset( $product['product_name'] ) ? $product['product_name'] : '';
			$file_path    = $this->get_attachment_export_file_path( $thumbnail_id );

			if ( $product_id <= 0 || $thumbnail_id <= 0 || empty( $file_path ) || ! file_exists( $file_path ) ) {
				continue;
			}

			$filename = $this->build_featured_image_zip_entry_name( $product_id, $product_name, $file_path );

			if ( $zip->addFile( $file_path, $filename ) ) {
				$added_files++;
			}
		}

		$zip->close();

		if ( $added_files <= 0 || ! file_exists( $zip_path ) ) {
			@unlink( $zip_path );
			$_SESSION['trendyol_featured_image_export_notice'] = 'İndirilebilir öne çıkan görsel bulunamadı.';
			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=featured-image-export' ) );
			exit;
		}

		$filename = sprintf(
			'one-cikan-gorseller-%s-%s.zip',
			$status_filter,
			gmdate( 'Y-m-d-His' )
		);

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $zip_path ) );

		readfile( $zip_path );
		@unlink( $zip_path );
		exit;
	}

	private function build_featured_image_zip_entry_name( $product_id, $product_name, $file_path ) {
		$product_id = intval( $product_id );
		$basename   = sanitize_title( wp_strip_all_tags( (string) $product_name ) );
		$basename   = trim( $basename, '.-_' );
		$extension  = sanitize_key( pathinfo( $file_path, PATHINFO_EXTENSION ) );

		if ( '' === $basename ) {
			$basename = 'urun';
		}

		if ( '' === $extension ) {
			$extension = 'jpg';
		}

		return sprintf( '%d-%s.%s', $product_id, $basename, $extension );
	}

	private function get_attachment_export_file_path( $attachment_id ) {
		$attachment_id = intval( $attachment_id );

		if ( $attachment_id <= 0 ) {
			return '';
		}

		if ( function_exists( 'wp_get_original_image_path' ) ) {
			$original_path = wp_get_original_image_path( $attachment_id );

			if ( ! empty( $original_path ) && file_exists( $original_path ) ) {
				return $original_path;
			}
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! empty( $file_path ) && file_exists( $file_path ) ) {
			return $file_path;
		}

		return '';
	}

	public function ajax_variant_stock_sync() {
		check_ajax_referer( 'trendyol_variant_stock_sync' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => 'Yetkisiz' ) );
		}

		$params = isset( $_POST['params'] ) ? (array) $_POST['params'] : array();

		$page   = isset( $params['page'] ) ? max( 1, intval( $params['page'] ) ) : 1;
		$batch  = isset( $params['batch'] ) ? max( 1, intval( $params['batch'] ) ) : 10;
		$status = isset( $params['status'] ) ? sanitize_key( $params['status'] ) : 'draft';

		$stock_sync = new Trendyol_Variant_Stock_Sync();
		$result     = $stock_sync->sync_products(
			array(
				'status' => $status,
				'batch'  => $batch,
				'page'   => $page,
			)
		);

		wp_send_json_success(
			array(
				'changed'   => isset( $result['changed'] ) ? $result['changed'] : array(),
				'thisBatch' => isset( $result['messages'] ) ? $result['messages'] : array(),
				'message'   => isset( $result['message'] ) ? $result['message'] : 'İşlem tamamlandı.',
				'more'      => ! empty( $result['more'] ),
				'page'      => isset( $result['next_page'] ) ? intval( $result['next_page'] ) : ( $page + 1 ),
			)
		);
	}

	public function ajax_test_telegram() {
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'trendyol_test_telegram' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Güvenlik hatası', 'trendyol-woocommerce-importer' ) ) );
		}

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Yetkisiz', 'trendyol-woocommerce-importer' ) ) );
		}

		$bot_token = isset( $_POST['bot_token'] ) ? sanitize_text_field( $_POST['bot_token'] ) : '';
		$chat_id   = isset( $_POST['chat_id'] ) ? sanitize_text_field( $_POST['chat_id'] ) : '';

		if ( empty( $bot_token ) || empty( $chat_id ) ) {
			wp_send_json_error( array( 'message' => esc_html__( 'Token veya Chat ID boş', 'trendyol-woocommerce-importer' ) ) );
		}

		$bot    = new Trendyol_Telegram_Bot( $bot_token, $chat_id );
		$result = $bot->test_connection();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => esc_html__( 'Test mesajı gönderildi!', 'trendyol-woocommerce-importer' ) ) );
	}

	public function enqueue_styles( $hook ) {
		if ( 'woocommerce_page_trendyol-importer' === $hook ) {
			wp_enqueue_style(
				'trendyol-admin-style',
				TRENDYOL_IMPORTER_URL . 'admin/assets/admin-style.css',
				array(),
				time()
			);
		}
	}

	public function add_profit_column( $columns ) {
		$new_columns = array();

		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			if ( 'name' === $key ) {
				$new_columns['trendyol_net_profit_eur'] = '€ Net Kâr';
			}
		}

		if ( ! isset( $new_columns['trendyol_net_profit_eur'] ) ) {
			$new_columns['trendyol_net_profit_eur'] = '€ Net Kâr';
		}

		return $new_columns;
	}

	public function render_profit_column( $column, $post_id ) {
		if ( 'trendyol_net_profit_eur' !== $column ) {
			return;
		}

		$profit_data = $this->get_product_net_profit_data( $post_id );

		echo '<div class="trendyol-profit-cell">';

		if ( empty( $profit_data['ok'] ) ) {
			$reason = ! empty( $profit_data['reason'] ) ? $profit_data['reason'] : 'Veri yok';
			echo '<span class="trendyol-profit-badge trendyol-profit-badge--error">' . esc_html( $reason ) . '</span>';
			echo '</div>';
			return;
		}

		$profit            = (float) $profit_data['net_profit_eur'];
		$kargo_eur         = (float) $profit_data['kargo_eur'];
		$current_sale_rate = (float) $profit_data['sale_currency_rate'];
		$sale_rate_label   = ! empty( $profit_data['sale_currency_label'] ) ? (string) $profit_data['sale_currency_label'] : 'RSD/EUR';

		$state_class = 'trendyol-profit-box--warn';
		if ( $profit > 0 ) {
			$state_class = 'trendyol-profit-box--positive';
		} elseif ( $profit < 0 ) {
			$state_class = 'trendyol-profit-box--negative';
		}

		echo '<div class="trendyol-profit-box ' . esc_attr( $state_class ) . '">';
		echo '<div class="trendyol-profit-title">Net Kâr</div>';
		echo '<div class="trendyol-profit-value">€ ' . esc_html( number_format( $profit, 2, '.', '' ) ) . '</div>';
		echo '<div class="trendyol-profit-meta">Kargo: €' . esc_html( number_format( $kargo_eur, 2, '.', '' ) ) . '</div>';
		echo '<div class="trendyol-profit-meta">Kur: ' . esc_html( $sale_rate_label ) . ' ' . esc_html( number_format( $current_sale_rate, 4, '.', '' ) ) . '</div>';
		echo '</div>';
		echo '</div>';
	}

	private function get_product_net_profit_data( $post_id ) {
		$kategori          = get_post_meta( $post_id, 'trendyol_product_category', true );
		$alis_tl           = get_post_meta( $post_id, 'trendyol_original_price_tl', true );
		$alis_rsd_fallback = get_post_meta( $post_id, 'trendyol_original_price_rsd', true );

		$satis_fiyat = $this->price_service->get_product_sale_price_rsd( $post_id );

		if ( '' === $satis_fiyat || null === $satis_fiyat || ! is_numeric( $satis_fiyat ) ) {
			return array(
				'ok'     => false,
				'reason' => 'Satış fiyatı yok',
			);
		}

		$current_euro_kur = get_trendyol_euro_kuru();
		$current_rsd_kur  = get_trendyol_rsd_kuru();
		$sale_rate_info   = method_exists( $this->price_service, 'get_active_currency_rate_info' )
			? $this->price_service->get_active_currency_rate_info()
			: array(
				'currency' => 'rsd',
				'rate'     => $current_rsd_kur,
				'label'    => 'RSD/EUR',
			);

		$purchase_eur = null;

		if (
			'' !== $alis_tl &&
			null !== $alis_tl &&
			is_numeric( $alis_tl ) &&
			(float) $alis_tl > 0 &&
			$current_euro_kur > 0
		) {
			$purchase_eur = (float) $alis_tl / (float) $current_euro_kur;
		} elseif (
			'' !== $alis_rsd_fallback &&
			null !== $alis_rsd_fallback &&
			is_numeric( $alis_rsd_fallback ) &&
			$current_rsd_kur > 0
		) {
			$purchase_eur = (float) $alis_rsd_fallback / (float) $current_rsd_kur;
		} else {
			return array(
				'ok'     => false,
				'reason' => 'Alış fiyatı yok',
			);
		}

		$kategori = function_exists( 'trendyol_normalize_cat' ) ? trendyol_normalize_cat( $kategori ) : sanitize_text_field( $kategori );

		$kargo_eur = (float) get_option( 'trendyol_default_kargo', 0 );
		$kargo_arr = get_option( 'trendyol_kargo_maliyetleri', array() );

		if ( is_array( $kargo_arr ) && ! empty( $kategori ) && isset( $kargo_arr[ $kategori ] ) && is_array( $kargo_arr[ $kategori ] ) ) {
			if ( isset( $kargo_arr[ $kategori ]['kargo'] ) ) {
				$kargo_eur = (float) str_replace( ',', '.', $kargo_arr[ $kategori ]['kargo'] );
			}
		}

		$sales_eur = method_exists( $this->price_service, 'convert_sale_price_to_eur' )
			? $this->price_service->convert_sale_price_to_eur( (float) $satis_fiyat )
			: ( (float) $satis_fiyat / (float) $current_rsd_kur );

		if ( null === $sales_eur ) {
			return array(
				'ok'     => false,
				'reason' => 'Kur bilgisi yok',
			);
		}

		$total_cost_eur = (float) $purchase_eur + (float) $kargo_eur;
		$net_profit_eur = (float) $sales_eur - (float) $total_cost_eur;

		return array(
			'ok'               => true,
			'sales_eur'        => $sales_eur,
			'purchase_eur'     => $purchase_eur,
			'kargo_eur'        => $kargo_eur,
			'cost_eur'         => $total_cost_eur,
			'net_profit_eur'   => $net_profit_eur,
			'current_euro_kur' => $current_euro_kur,
			'current_rsd_kur'  => $current_rsd_kur,
			'sale_currency'    => $sale_rate_info['currency'],
			'sale_currency_rate'  => (float) $sale_rate_info['rate'],
			'sale_currency_label' => $sale_rate_info['label'],
		);
	}

	public function print_products_list_profit_column_css() {
		$screen = get_current_screen();

		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		?>
		<style>
			.wp-list-table .column-trendyol_net_profit_eur { width: 150px; min-width: 150px; white-space: normal !important; }
			.wp-list-table th.column-trendyol_net_profit_eur { white-space: nowrap !important; font-weight: 600; }
			.wp-list-table td.column-trendyol_net_profit_eur { vertical-align: middle; }
			.trendyol-profit-cell { display: block; width: 100%; }
			.trendyol-profit-box { display: block; padding: 8px 10px; border-radius: 10px; border: 1px solid #dcdcde; background: #fff; box-shadow: 0 1px 1px rgba(0,0,0,.02); }
			.trendyol-profit-box--positive { border-color: #86efac; background: #f0fdf4; }
			.trendyol-profit-box--negative { border-color: #fca5a5; background: #fef2f2; }
			.trendyol-profit-box--warn { border-color: #fcd34d; background: #fffbeb; }
			.trendyol-profit-title { font-size: 10px; line-height: 1.2; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; color: #646970; margin-bottom: 4px; }
			.trendyol-profit-value { font-size: 18px; line-height: 1.15; font-weight: 700; color: #111827; margin-bottom: 4px; }
			.trendyol-profit-meta { font-size: 11px; line-height: 1.35; color: #646970; }
			.trendyol-profit-badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 600; line-height: 1.2; }
			.trendyol-profit-badge--error { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
		</style>
		<?php
	}

	public static function activate_plugin() {
		if ( class_exists( 'Trendyol_Logger' ) ) {
			Trendyol_Logger::create_table();
		}
		if ( class_exists( 'Trendyol_Settings' ) ) {
			Trendyol_Settings::init_defaults();
		}
		if ( class_exists( 'Trendyol_Cron_Manager' ) ) {
			Trendyol_Cron_Manager::reschedule_all();
		}
	}

	public static function deactivate_plugin() {
		if ( class_exists( 'Trendyol_Cron_Manager' ) ) {
			Trendyol_Cron_Manager::unschedule();
		}
	}
}
