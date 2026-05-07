<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
}

if ( ! class_exists( 'Trendyol_Store_Scraper_Service' ) ) {
	require_once TRENDYOL_IMPORTER_PATH . 'includes/services/class-store-scraper-service.php';
}

if ( ! session_id() ) {
	session_start();
}

$service         = new Trendyol_Store_Scraper_Service();
$analysis        = null;
$error           = '';
$success_notice  = '';

if ( ! empty( $_SESSION['trendyol_store_import_notice'] ) ) {
	$success_notice = $_SESSION['trendyol_store_import_notice'];
	unset( $_SESSION['trendyol_store_import_notice'] );
}

if (
	isset( $_POST['trendyol_store_import_nonce'] ) &&
	wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_POST['trendyol_store_import_nonce'] ) ),
		'trendyol_store_import_action'
	) &&
	! isset( $_POST['trendyol_store_save_links'] )
) {
	$store_url        = isset( $_POST['store_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['store_url'] ) ) ) : '';
	$limit            = isset( $_POST['store_limit'] ) ? absint( wp_unslash( $_POST['store_limit'] ) ) : 50;
	$manual_links_raw = isset( $_POST['manual_product_links'] ) ? wp_unslash( $_POST['manual_product_links'] ) : '';

	$analysis = $service->analyze_store( $store_url, $limit );

	if ( is_wp_error( $analysis ) ) {
		$error    = $analysis->get_error_message();
		$analysis = null;
	}

	if ( is_array( $analysis ) && ! empty( $manual_links_raw ) ) {
		$manual_links = preg_split( '/\r\n|\r|\n/', $manual_links_raw );
		$cleaned      = array();

		foreach ( (array) $manual_links as $line ) {
			$line = trim( (string) $line );

			if ( '' === $line ) {
				continue;
			}

			$line = preg_replace( '#\?.*$#', '', $line );

			if ( preg_match( '#^https?://(www\.)?trendyol\.com/.+-p-\d+$#i', $line ) ) {
				$cleaned[] = esc_url_raw( $line );
			}
		}

		$cleaned = array_values( array_unique( $cleaned ) );

		if ( ! empty( $cleaned ) ) {
			$analysis['product_urls']  = $cleaned;
			$analysis['product_count'] = count( $cleaned );
			$analysis['notes'][]       = sprintf(
				__( 'Elle girilen %d ürün linki işlendi.', 'trendyol-woocommerce-importer' ),
				count( $cleaned )
			);
		}
	}
}

if (
	isset( $_POST['trendyol_store_save_links'] ) &&
	isset( $_POST['trendyol_store_import_nonce'] ) &&
	wp_verify_nonce(
		sanitize_text_field( wp_unslash( $_POST['trendyol_store_import_nonce'] ) ),
		'trendyol_store_import_action'
	)
) {
	$store_slug  = isset( $_POST['store_slug'] ) ? sanitize_title( wp_unslash( $_POST['store_slug'] ) ) : '';
	$merchant_id = isset( $_POST['merchant_id'] ) ? absint( wp_unslash( $_POST['merchant_id'] ) ) : 0;
	$links_raw   = isset( $_POST['prepared_product_links'] ) ? wp_unslash( $_POST['prepared_product_links'] ) : '';

	$links = preg_split( '/\r\n|\r|\n/', $links_raw );
	$links = array_filter( array_map( 'trim', (array) $links ) );
	$links = array_values( array_unique( $links ) );

	if ( empty( $links ) ) {
		$error = __( 'Kaydetmek için geçerli ürün linki yok.', 'trendyol-woocommerce-importer' );
	} else {
		$data_dir = trailingslashit( TRENDYOL_IMPORTER_PATH . 'data' );

		if ( ! file_exists( $data_dir ) ) {
			wp_mkdir_p( $data_dir );
		}

		$file_base = ! empty( $store_slug ) ? $store_slug : 'magaza-' . $merchant_id;
		$file_base = sanitize_file_name( $file_base );
		$filename  = $file_base . '-urunleri.txt';
		$file_path = $data_dir . $filename;

		$content = implode( PHP_EOL, $links );
		$result  = @file_put_contents( $file_path, $content );

		if ( false === $result ) {
			$error = __( 'Dosya kaydedilemedi.', 'trendyol-woocommerce-importer' );
		} else {
			$_SESSION['trendyol_last_bulkfile']       = $filename;
			$_SESSION['trendyol_store_import_notice'] = sprintf(
				__( '✅ %1$d ürün linki kaydedildi: %2$s', 'trendyol-woocommerce-importer' ),
				count( $links ),
				$filename
			);

			wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=store-import' ) );
			exit;
		}
	}
}
?>

<div class="trendyol-tab-content">

	<?php if ( ! empty( $success_notice ) ) : ?>
		<div class="notice notice-success" style="margin:0 0 20px 0;padding:12px 14px;">
			<p style="margin:0;"><?php echo esc_html( $success_notice ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $error ) ) : ?>
		<div class="notice notice-error" style="margin:0 0 20px 0;padding:12px 14px;">
			<p style="margin:0;"><?php echo esc_html( $error ); ?></p>
		</div>
	<?php endif; ?>

	<div class="trendyol-card" style="margin-bottom:24px;">
		<h2 style="margin-top:0;"><?php esc_html_e( 'Mağazadan Toplu Çekim', 'trendyol-woocommerce-importer' ); ?></h2>
		<p>
			<?php esc_html_e( 'Trendyol mağaza sayfaları bot korumasına takılabildiği için bu ekran iki modda çalışır: mağaza bilgisini çözer ve istersen ürün linklerini manuel yapıştırıp toplu import dosyasına dönüştürür.', 'trendyol-woocommerce-importer' ); ?>
		</p>

		<form method="post">
			<?php wp_nonce_field( 'trendyol_store_import_action', 'trendyol_store_import_nonce' ); ?>

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="store_url"><?php esc_html_e( 'Mağaza URL', 'trendyol-woocommerce-importer' ); ?></label>
					</th>
					<td>
						<input
							type="url"
							name="store_url"
							id="store_url"
							class="regular-text"
							style="width:100%;max-width:800px;"
							placeholder="https://www.trendyol.com/magaza/..."
							value="<?php echo isset( $_POST['store_url'] ) ? esc_attr( wp_unslash( $_POST['store_url'] ) ) : ''; ?>"
							required
						/>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="store_limit"><?php esc_html_e( 'Maksimum Ürün Sayısı', 'trendyol-woocommerce-importer' ); ?></label>
					</th>
					<td>
						<input
							type="number"
							name="store_limit"
							id="store_limit"
							min="1"
							max="500"
							value="<?php echo isset( $_POST['store_limit'] ) ? esc_attr( wp_unslash( $_POST['store_limit'] ) ) : '50'; ?>"
						/>
					</td>
				</tr>

				<tr>
					<th scope="row">
						<label for="manual_product_links"><?php esc_html_e( 'Manuel Ürün Linkleri', 'trendyol-woocommerce-importer' ); ?></label>
					</th>
					<td>
						<textarea
							name="manual_product_links"
							id="manual_product_links"
							rows="10"
							style="width:100%;max-width:800px;"
							placeholder="Her satıra bir Trendyol ürün linki yazın..."
						><?php echo isset( $_POST['manual_product_links'] ) ? esc_textarea( wp_unslash( $_POST['manual_product_links'] ) ) : ''; ?></textarea>
						<p class="description">
							<?php esc_html_e( 'Mağaza otomatik taraması 403 verirse, ürün linklerini buraya yapıştırıp TXT dosyasına dönüştürebilirsin.', 'trendyol-woocommerce-importer' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<p>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Analiz Et', 'trendyol-woocommerce-importer' ); ?>
				</button>
			</p>
		</form>
	</div>

	<?php if ( is_array( $analysis ) ) : ?>
		<div class="trendyol-card" style="margin-bottom:24px;">
			<h3 style="margin-top:0;"><?php esc_html_e( 'Mağaza Analizi', 'trendyol-woocommerce-importer' ); ?></h3>

			<table class="widefat striped">
				<tbody>
					<tr>
						<td><strong><?php esc_html_e( 'Mağaza URL', 'trendyol-woocommerce-importer' ); ?></strong></td>
						<td><?php echo esc_html( $analysis['store_url'] ?? '' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Merchant ID', 'trendyol-woocommerce-importer' ); ?></strong></td>
						<td><?php echo esc_html( (string) ( $analysis['merchant_id'] ?? 0 ) ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Mağaza Slug', 'trendyol-woocommerce-importer' ); ?></strong></td>
						<td><?php echo esc_html( $analysis['store_slug'] ?? '' ); ?></td>
					</tr>
					<tr>
						<td><strong><?php esc_html_e( 'Bulunan Ürün Sayısı', 'trendyol-woocommerce-importer' ); ?></strong></td>
						<td><?php echo esc_html( (string) ( $analysis['product_count'] ?? 0 ) ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php if ( ! empty( $analysis['notes'] ) && is_array( $analysis['notes'] ) ) : ?>
			<div class="trendyol-card" style="margin-bottom:24px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Notlar', 'trendyol-woocommerce-importer' ); ?></h3>
				<ul style="margin-left:18px;">
					<?php foreach ( $analysis['notes'] as $note ) : ?>
						<li><?php echo esc_html( $note ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $analysis['product_urls'] ) && is_array( $analysis['product_urls'] ) ) : ?>
			<div class="trendyol-card" style="margin-bottom:24px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Hazır Ürün Linkleri', 'trendyol-woocommerce-importer' ); ?></h3>

				<ol style="margin-left:20px;">
					<?php foreach ( $analysis['product_urls'] as $product_url ) : ?>
						<li style="margin-bottom:8px;">
							<a href="<?php echo esc_url( $product_url ); ?>" target="_blank" rel="noopener noreferrer">
								<?php echo esc_html( $product_url ); ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ol>

				<form method="post" style="margin-top:20px;">
					<?php wp_nonce_field( 'trendyol_store_import_action', 'trendyol_store_import_nonce' ); ?>
					<input type="hidden" name="store_slug" value="<?php echo esc_attr( $analysis['store_slug'] ?? '' ); ?>">
					<input type="hidden" name="merchant_id" value="<?php echo esc_attr( (string) ( $analysis['merchant_id'] ?? 0 ) ); ?>">
					<textarea name="prepared_product_links" style="display:none;"><?php echo esc_textarea( implode( PHP_EOL, $analysis['product_urls'] ) ); ?></textarea>

					<button type="submit" name="trendyol_store_save_links" value="1" class="button button-primary">
						<?php esc_html_e( 'Linkleri TXT Olarak Kaydet', 'trendyol-woocommerce-importer' ); ?>
					</button>

					<a
						href="<?php echo esc_url( admin_url( 'admin.php?page=trendyol-importer&tab=bulk-import' ) ); ?>"
						class="button button-secondary"
						style="margin-left:8px;"
					>
						<?php esc_html_e( 'Toplu İçe Aktar Sekmesine Git', 'trendyol-woocommerce-importer' ); ?>
					</a>
				</form>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $analysis['fetch_debug'] ) && is_array( $analysis['fetch_debug'] ) : ?>
			<div class="trendyol-card" style="margin-bottom:24px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'Fetch Debug', 'trendyol-woocommerce-importer' ); ?></h3>
				<pre style="white-space:pre-wrap;background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0;"><?php echo esc_html( print_r( $analysis['fetch_debug'], true ) ); ?></pre>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $analysis['html_preview'] ) ) : ?>
			<div class="trendyol-card" style="margin-bottom:24px;">
				<h3 style="margin-top:0;"><?php esc_html_e( 'HTML Preview', 'trendyol-woocommerce-importer' ); ?></h3>
				<pre style="white-space:pre-wrap;background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0;"><?php echo esc_html( $analysis['html_preview'] ); ?></pre>
			</div>
		<?php endif; ?>

	<?php endif; ?>

</div>