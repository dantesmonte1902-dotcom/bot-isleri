<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Trendyol_Scraper_Diagnostic_Service' ) ) {
	require_once TRENDYOL_IMPORTER_PATH . 'includes/services/class-scraper-diagnostic-service.php';
}

$analysis = null;
$error    = '';

if ( isset( $_POST['trendyol_scraper_diagnostic_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['trendyol_scraper_diagnostic_nonce'] ) ), 'trendyol_scraper_diagnostic_action' ) ) {
	$url  = isset( $_POST['diagnostic_url'] ) ? esc_url_raw( trim( wp_unslash( $_POST['diagnostic_url'] ) ) ) : '';
	$mode = isset( $_POST['diagnostic_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['diagnostic_mode'] ) ) : 'auto';

	$service  = new Trendyol_Scraper_Diagnostic_Service();
	$analysis = $service->analyze_url( $url, $mode );

	if ( is_wp_error( $analysis ) ) {
		$error = $analysis->get_error_message();
	}
}
?>

<div class="wrap">
	<h1><?php esc_html_e( 'Scraper Tanı Ekranı', 'trendyol-woocommerce-importer' ); ?></h1>
	<p><?php esc_html_e( 'Ürün linkini analiz ederek hangi alanların bulunabildiğini ve hangi kaynaktan geldiğini görürsünüz.', 'trendyol-woocommerce-importer' ); ?></p>

	<form method="post" style="background:#fff;padding:20px;border:1px solid #ddd;border-radius:8px;max-width:1000px;">
		<?php wp_nonce_field( 'trendyol_scraper_diagnostic_action', 'trendyol_scraper_diagnostic_nonce' ); ?>

		<table class="form-table">
			<tr>
				<th scope="row">
					<label for="diagnostic_url"><?php esc_html_e( 'Ürün URL', 'trendyol-woocommerce-importer' ); ?></label>
				</th>
				<td>
					<input
						type="url"
						name="diagnostic_url"
						id="diagnostic_url"
						class="regular-text"
						style="width:100%;max-width:800px;"
						placeholder="https://www.trendyol.com/..."
						value="<?php echo isset( $_POST['diagnostic_url'] ) ? esc_attr( wp_unslash( $_POST['diagnostic_url'] ) ) : ''; ?>"
						required
					/>
				</td>
			</tr>
			<tr>
				<th scope="row">
					<label for="diagnostic_mode"><?php esc_html_e( 'Mod', 'trendyol-woocommerce-importer' ); ?></label>
				</th>
				<td>
					<select name="diagnostic_mode" id="diagnostic_mode">
						<?php
						$current_mode = isset( $_POST['diagnostic_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['diagnostic_mode'] ) ) : 'auto';
						$modes        = array(
							'auto'         => 'auto',
							'standard'     => 'standard',
							'jsonld_first' => 'jsonld_first',
							'fallback_only'=> 'fallback_only',
						);

						foreach ( $modes as $value => $label ) :
							?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_mode, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</td>
			</tr>
		</table>

		<p>
			<button type="submit" class="button button-primary">
				<?php esc_html_e( 'Analiz Et', 'trendyol-woocommerce-importer' ); ?>
			</button>
		</p>
	</form>

	<?php if ( ! empty( $error ) ) : ?>
		<div style="margin-top:20px;background:#fff1f1;border:1px solid #d63638;color:#8a1f11;padding:15px;border-radius:8px;max-width:1000px;">
			<strong><?php esc_html_e( 'Hata:', 'trendyol-woocommerce-importer' ); ?></strong>
			<?php echo esc_html( $error ); ?>
		</div>
	<?php endif; ?>

	<?php if ( is_array( $analysis ) ) : ?>
		<div style="margin-top:20px;max-width:1200px;">

			<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
				<h2 style="margin-top:0;"><?php esc_html_e( 'Genel Durum', 'trendyol-woocommerce-importer' ); ?></h2>
				<table class="widefat striped">
					<tbody>
						<tr>
							<td><strong><?php esc_html_e( 'URL', 'trendyol-woocommerce-importer' ); ?></strong></td>
							<td><?php echo esc_html( $analysis['url'] ?? '' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Mod', 'trendyol-woocommerce-importer' ); ?></strong></td>
							<td><?php echo esc_html( $analysis['mode'] ?? '' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'URL Geçerli mi?', 'trendyol-woocommerce-importer' ); ?></strong></td>
							<td><?php echo ! empty( $analysis['valid_url'] ) ? 'Evet' : 'Hayır'; ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'HTML Alındı mı?', 'trendyol-woocommerce-importer' ); ?></strong></td>
							<td><?php echo ! empty( $analysis['html_ok'] ) ? 'Evet' : 'Hayır'; ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Parse Başarılı mı?', 'trendyol-woocommerce-importer' ); ?></strong></td>
							<td><?php echo ! empty( $analysis['parse_ok'] ) ? 'Evet' : 'Hayır'; ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Hata Kodu', 'trendyol-woocommerce-importer' ); ?></strong></td>
							<td><?php echo esc_html( $analysis['error_code'] ?? '' ); ?></td>
						</tr>
						<tr>
							<td><strong><?php esc_html_e( 'Hata Mesajı', 'trendyol-woocommerce-importer' ); ?></strong></td>
							<td><?php echo esc_html( $analysis['error_message'] ?? '' ); ?></td>
						</tr>
					</tbody>
				</table>
			</div>

			<?php if ( ! empty( $analysis['fields'] ) && is_array( $analysis['fields'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Alan Kontrolü', 'trendyol-woocommerce-importer' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Alan', 'trendyol-woocommerce-importer' ); ?></th>
								<th><?php esc_html_e( 'Durum', 'trendyol-woocommerce-importer' ); ?></th>
								<th><?php esc_html_e( 'Mesaj', 'trendyol-woocommerce-importer' ); ?></th>
								<th><?php esc_html_e( 'Kaynak', 'trendyol-woocommerce-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $analysis['fields'] as $field_key => $field ) : ?>
								<tr>
									<td><?php echo esc_html( $field['label'] ?? $field_key ); ?></td>
									<td>
										<?php if ( ! empty( $field['ok'] ) ) : ?>
											<span style="color:#008a20;font-weight:600;">✓</span>
										<?php else : ?>
											<span style="color:#d63638;font-weight:600;">✗</span>
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $field['message'] ?? '' ); ?></td>
									<td><?php echo esc_html( $analysis['field_sources'][ $field_key ] ?? '' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['price_debug'] ) && is_array( $analysis['price_debug'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Fiyat Debug', 'trendyol-woocommerce-importer' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<tr>
								<td><strong><?php esc_html_e( 'Regular Price', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo isset( $analysis['price_debug']['regular_price'] ) ? esc_html( (string) $analysis['price_debug']['regular_price'] ) : ''; ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Discounted Price', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo isset( $analysis['price_debug']['discounted_price'] ) ? esc_html( (string) $analysis['price_debug']['discounted_price'] ) : ''; ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Basket Price', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo isset( $analysis['price_debug']['basket_price'] ) ? esc_html( (string) $analysis['price_debug']['basket_price'] ) : ''; ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Selected Price', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo isset( $analysis['price_debug']['selected_price'] ) ? esc_html( (string) $analysis['price_debug']['selected_price'] ) : ''; ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Selected Type', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo esc_html( $analysis['price_debug']['selected_price_type'] ?? '' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Regular Source', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo esc_html( $analysis['price_debug']['regular_source'] ?? '' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Discounted Source', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo esc_html( $analysis['price_debug']['discounted_source'] ?? '' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Basket Source', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo esc_html( $analysis['price_debug']['basket_source'] ?? '' ); ?></td>
							</tr>
							<tr>
								<td><strong><?php esc_html_e( 'Selected Source', 'trendyol-woocommerce-importer' ); ?></strong></td>
								<td><?php echo esc_html( $analysis['price_debug']['selected_source'] ?? '' ); ?></td>
							</tr>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['field_sources'] ) && is_array( $analysis['field_sources'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Field Sources', 'trendyol-woocommerce-importer' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Alan', 'trendyol-woocommerce-importer' ); ?></th>
								<th><?php esc_html_e( 'Kaynak', 'trendyol-woocommerce-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $analysis['field_sources'] as $source_key => $source_value ) : ?>
								<tr>
									<td><?php echo esc_html( $source_key ); ?></td>
									<td><?php echo esc_html( $source_value ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['debug_signals'] ) && is_array( $analysis['debug_signals'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Debug Signals', 'trendyol-woocommerce-importer' ); ?></h2>
					<table class="widefat striped">
						<tbody>
							<?php foreach ( $analysis['debug_signals'] as $key => $value ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $key ); ?></strong></td>
									<td>
										<?php
										if ( is_bool( $value ) ) {
											echo $value ? 'true' : 'false';
										} else {
											echo esc_html( (string) $value );
										}
										?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['regex_tests'] ) && is_array( $analysis['regex_tests'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Regex Testleri', 'trendyol-woocommerce-importer' ); ?></h2>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Test', 'trendyol-woocommerce-importer' ); ?></th>
								<th><?php esc_html_e( 'Eşleşti mi?', 'trendyol-woocommerce-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $analysis['regex_tests'] as $test ) : ?>
								<tr>
									<td><?php echo esc_html( $test['label'] ?? '' ); ?></td>
									<td><?php echo ! empty( $test['matched'] ) ? 'true' : 'false'; ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['fetch_debug'] ) && is_array( $analysis['fetch_debug'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Fetch Debug', 'trendyol-woocommerce-importer' ); ?></h2>
					<pre style="white-space:pre-wrap;background:#f6f7f7;padding:15px;border-radius:6px;overflow:auto;"><?php echo esc_html( print_r( $analysis['fetch_debug'], true ) ); ?></pre>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['raw_data'] ) && is_array( $analysis['raw_data'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Raw Parsed Data', 'trendyol-woocommerce-importer' ); ?></h2>
					<pre style="white-space:pre-wrap;background:#f6f7f7;padding:15px;border-radius:6px;overflow:auto;max-height:500px;"><?php echo esc_html( print_r( $analysis['raw_data'], true ) ); ?></pre>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['jsonld_blocks'] ) && is_array( $analysis['jsonld_blocks'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'JSON-LD Blokları', 'trendyol-woocommerce-importer' ); ?></h2>
					<?php foreach ( $analysis['jsonld_blocks'] as $block ) : ?>
						<div style="margin-bottom:20px;">
							<h3 style="margin-bottom:10px;"><?php echo esc_html( 'Block #' . ( $block['index'] ?? '' ) ); ?></h3>
							<pre style="white-space:pre-wrap;background:#f6f7f7;padding:15px;border-radius:6px;overflow:auto;"><?php echo esc_html( $block['preview'] ?? '' ); ?></pre>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['html_preview'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'HTML Preview', 'trendyol-woocommerce-importer' ); ?></h2>
					<pre style="white-space:pre-wrap;background:#f6f7f7;padding:15px;border-radius:6px;overflow:auto;"><?php echo esc_html( $analysis['html_preview'] ); ?></pre>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['notes'] ) && is_array( $analysis['notes'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;margin-bottom:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Notlar', 'trendyol-woocommerce-importer' ); ?></h2>
					<ul style="margin:0 0 0 18px;">
						<?php foreach ( $analysis['notes'] as $note ) : ?>
							<li><?php echo esc_html( $note ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $analysis['suggestions'] ) && is_array( $analysis['suggestions'] ) ) : ?>
				<div style="background:#fff;border:1px solid #ddd;border-radius:8px;padding:20px;">
					<h2 style="margin-top:0;"><?php esc_html_e( 'Öneriler', 'trendyol-woocommerce-importer' ); ?></h2>
					<ul style="margin:0 0 0 18px;">
						<?php foreach ( $analysis['suggestions'] as $suggestion ) : ?>
							<li><?php echo esc_html( $suggestion ); ?></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

		</div>
	<?php endif; ?>
</div>