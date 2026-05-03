<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
}

$diagnostic_result = null;
$diagnostic_url    = '';
$diagnostic_mode   = 'auto';

if (
	isset( $_POST['trendyol_scraper_diagnostic_run'] ) &&
	isset( $_POST['_wpnonce'] ) &&
	wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_scraper_diagnostic_nonce' )
) {
	$diagnostic_url  = isset( $_POST['diagnostic_url'] ) ? esc_url_raw( wp_unslash( $_POST['diagnostic_url'] ) ) : '';
	$diagnostic_mode = isset( $_POST['diagnostic_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['diagnostic_mode'] ) ) : 'auto';

	$service           = new Trendyol_Scraper_Diagnostic_Service();
	$diagnostic_result = $service->analyze_url( $diagnostic_url, $diagnostic_mode );
}
?>

<div class="trendyol-tool-screen trendyol-diagnostic-screen">

	<div class="trendyol-diagnostic-hero">
		<div class="trendyol-diagnostic-hero__badge">🩺</div>
		<div class="trendyol-diagnostic-hero__content">
			<h2><?php echo esc_html__( 'Scraper Tanı', 'trendyol-woocommerce-importer' ); ?></h2>
			<p>
				<?php echo esc_html__( 'Trendyol ürün linklerinin çekilme ve parse edilme durumunu test edin. Bu ekran canlı import akışını değiştirmez; sadece tanı ve analiz amacıyla çalışır.', 'trendyol-woocommerce-importer' ); ?>
			</p>
		</div>
	</div>

	<div class="trendyol-diagnostic-form-card">
		<form method="post" action="">
			<?php wp_nonce_field( 'trendyol_scraper_diagnostic_nonce' ); ?>
			<input type="hidden" name="trendyol_scraper_diagnostic_run" value="1">

			<div class="trendyol-diagnostic-form-grid">
				<div class="trendyol-diagnostic-field trendyol-diagnostic-field--wide">
					<label for="diagnostic_url"><?php echo esc_html__( 'Trendyol Ürün Linki', 'trendyol-woocommerce-importer' ); ?></label>
					<input
						type="url"
						id="diagnostic_url"
						name="diagnostic_url"
						class="trendyol-modern-input"
						placeholder="https://www.trendyol.com/..."
						value="<?php echo esc_attr( $diagnostic_url ); ?>"
						required
					>
					<p class="description"><?php echo esc_html__( 'Analiz etmek istediğiniz Trendyol ürün linkini girin.', 'trendyol-woocommerce-importer' ); ?></p>
				</div>

				<div class="trendyol-diagnostic-field">
					<label for="diagnostic_mode"><?php echo esc_html__( 'Parser Modu', 'trendyol-woocommerce-importer' ); ?></label>
					<select id="diagnostic_mode" name="diagnostic_mode" class="trendyol-modern-select">
						<option value="auto" <?php selected( $diagnostic_mode, 'auto' ); ?>>Auto</option>
						<option value="standard" <?php selected( $diagnostic_mode, 'standard' ); ?>>Standard</option>
						<option value="jsonld_first" <?php selected( $diagnostic_mode, 'jsonld_first' ); ?>>JSON-LD First</option>
						<option value="fallback_only" <?php selected( $diagnostic_mode, 'fallback_only' ); ?>>Fallback Only</option>
					</select>
					<p class="description"><?php echo esc_html__( 'Bu seçim yalnızca tanı ekranındaki test akışında kullanılır.', 'trendyol-woocommerce-importer' ); ?></p>
				</div>
			</div>

			<div class="trendyol-diagnostic-form-actions">
				<button type="submit" class="button button-primary button-large">
					<?php echo esc_html__( 'Analiz Et', 'trendyol-woocommerce-importer' ); ?>
				</button>
			</div>
		</form>
	</div>

	<?php if ( is_array( $diagnostic_result ) ) : ?>
		<div class="trendyol-diagnostic-results">

			<div class="trendyol-tool-panel">
				<h3><?php echo esc_html__( 'Analiz Özeti', 'trendyol-woocommerce-importer' ); ?></h3>

				<p><strong><?php echo esc_html__( 'URL:', 'trendyol-woocommerce-importer' ); ?></strong> <?php echo esc_html( $diagnostic_result['url'] ); ?></p>
				<p><strong><?php echo esc_html__( 'Parser Modu:', 'trendyol-woocommerce-importer' ); ?></strong> <?php echo esc_html( $diagnostic_result['mode'] ); ?></p>

				<div class="trendyol-diagnostic-status-grid">
					<div class="trendyol-diagnostic-status-card <?php echo ! empty( $diagnostic_result['valid_url'] ) ? 'is-success' : 'is-error'; ?>">
						<div class="label"><?php echo esc_html__( 'URL Doğrulama', 'trendyol-woocommerce-importer' ); ?></div>
						<div class="value"><?php echo ! empty( $diagnostic_result['valid_url'] ) ? '✅ Başarılı' : '❌ Başarısız'; ?></div>
					</div>

					<div class="trendyol-diagnostic-status-card <?php echo ! empty( $diagnostic_result['html_ok'] ) ? 'is-success' : 'is-error'; ?>">
						<div class="label"><?php echo esc_html__( 'HTML Çekme', 'trendyol-woocommerce-importer' ); ?></div>
						<div class="value"><?php echo ! empty( $diagnostic_result['html_ok'] ) ? '✅ Başarılı' : '❌ Başarısız'; ?></div>
					</div>

					<div class="trendyol-diagnostic-status-card <?php echo ! empty( $diagnostic_result['parse_ok'] ) ? 'is-success' : 'is-error'; ?>">
						<div class="label"><?php echo esc_html__( 'Parse', 'trendyol-woocommerce-importer' ); ?></div>
						<div class="value"><?php echo ! empty( $diagnostic_result['parse_ok'] ) ? '✅ Başarılı' : '❌ Başarısız'; ?></div>
					</div>
				</div>

				<?php if ( ! empty( $diagnostic_result['error_message'] ) ) : ?>
					<div class="notice notice-error inline" style="margin-top:16px;">
						<p>
							<strong><?php echo esc_html__( 'Hata:', 'trendyol-woocommerce-importer' ); ?></strong>
							<?php echo esc_html( $diagnostic_result['error_code'] ); ?> —
							<?php echo esc_html( $diagnostic_result['error_message'] ); ?>
						</p>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $diagnostic_result['fields'] ) ) : ?>
				<div class="trendyol-tool-panel">
					<h3><?php echo esc_html__( 'Alan Sonuçları', 'trendyol-woocommerce-importer' ); ?></h3>

					<div class="trendyol-diagnostic-field-grid">
						<?php foreach ( $diagnostic_result['fields'] as $field ) : ?>
							<div class="trendyol-diagnostic-field-card <?php echo ! empty( $field['ok'] ) ? 'is-success' : 'is-error'; ?>">
								<div class="field-title"><?php echo esc_html( $field['label'] ); ?></div>
								<div class="field-status"><?php echo ! empty( $field['ok'] ) ? '✅ OK' : '❌ Eksik'; ?></div>
								<div class="field-message"><?php echo esc_html( $field['message'] ); ?></div>
								<div class="field-value">
									<?php
									if ( is_array( $field['value'] ) ) {
										echo esc_html( implode( ', ', array_map( 'strval', $field['value'] ) ) );
									} else {
										echo esc_html( (string) $field['value'] );
									}
									?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $diagnostic_result['field_sources'] ) ) : ?>
				<div class="trendyol-tool-panel">
					<h3><?php echo esc_html__( 'Alan Kaynakları', 'trendyol-woocommerce-importer' ); ?></h3>

					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Alan', 'trendyol-woocommerce-importer' ); ?></th>
								<th><?php echo esc_html__( 'Kaynak', 'trendyol-woocommerce-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $diagnostic_result['field_sources'] as $field_key => $source ) : ?>
								<tr>
									<td><strong><?php echo esc_html( $field_key ); ?></strong></td>
									<td><?php echo esc_html( $source ? $source : 'bulunamadı' ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="trendyol-diagnostic-columns">
				<?php if ( ! empty( $diagnostic_result['notes'] ) ) : ?>
					<div class="trendyol-tool-panel">
						<h3><?php echo esc_html__( 'Tanı Notları', 'trendyol-woocommerce-importer' ); ?></h3>
						<ul>
							<?php foreach ( $diagnostic_result['notes'] as $note ) : ?>
								<li><?php echo esc_html( $note ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $diagnostic_result['suggestions'] ) ) : ?>
					<div class="trendyol-tool-panel">
						<h3><?php echo esc_html__( 'Öneriler', 'trendyol-woocommerce-importer' ); ?></h3>
						<ul>
							<?php foreach ( $diagnostic_result['suggestions'] as $suggestion ) : ?>
								<li><?php echo esc_html( $suggestion ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( ! empty( $diagnostic_result['regex_tests'] ) ) : ?>
				<div class="trendyol-tool-panel">
					<h3><?php echo esc_html__( 'Regex Test Sonuçları', 'trendyol-woocommerce-importer' ); ?></h3>

					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php echo esc_html__( 'Test', 'trendyol-woocommerce-importer' ); ?></th>
								<th><?php echo esc_html__( 'Durum', 'trendyol-woocommerce-importer' ); ?></th>
								<th><?php echo esc_html__( 'Değer', 'trendyol-woocommerce-importer' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $diagnostic_result['regex_tests'] as $test ) : ?>
								<tr>
									<td><?php echo esc_html( $test['label'] ); ?></td>
									<td><?php echo ! empty( $test['matched'] ) ? '✅ Matched' : '❌ No Match'; ?></td>
									<td><?php echo esc_html( (string) $test['value'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			<?php endif; ?>

			<div class="trendyol-tool-panel">
				<h3><?php echo esc_html__( 'Gelişmiş Teknik Detaylar', 'trendyol-woocommerce-importer' ); ?></h3>

				<?php if ( ! empty( $diagnostic_result['fetch_debug'] ) ) : ?>
					<details class="trendyol-simple-details">
						<summary><?php echo esc_html__( 'Fetch Debug', 'trendyol-woocommerce-importer' ); ?></summary>
						<pre><?php echo esc_html( wp_json_encode( $diagnostic_result['fetch_debug'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
					</details>
				<?php endif; ?>

				<?php if ( ! empty( $diagnostic_result['debug_signals'] ) ) : ?>
					<details class="trendyol-simple-details">
						<summary><?php echo esc_html__( 'Debug Sinyalleri', 'trendyol-woocommerce-importer' ); ?></summary>
						<pre><?php echo esc_html( wp_json_encode( $diagnostic_result['debug_signals'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
					</details>
				<?php endif; ?>

				<?php if ( ! empty( $diagnostic_result['jsonld_blocks'] ) ) : ?>
					<details class="trendyol-simple-details">
						<summary><?php echo esc_html( sprintf( __( 'JSON-LD Blokları (%d)', 'trendyol-woocommerce-importer' ), count( $diagnostic_result['jsonld_blocks'] ) ) ); ?></summary>
						<div class="trendyol-jsonld-blocks">
							<?php foreach ( $diagnostic_result['jsonld_blocks'] as $block ) : ?>
								<div class="trendyol-jsonld-block">
									<p>
										<strong>
											<?php
											echo esc_html(
												sprintf(
													__( 'Blok #%d', 'trendyol-woocommerce-importer' ),
													isset( $block['index'] ) ? (int) $block['index'] : 0
												)
											);
											?>
										</strong>
										<?php if ( ! empty( $block['type_summary'] ) ) : ?>
											— <?php echo esc_html( $block['type_summary'] ); ?>
										<?php endif; ?>
									</p>
									<pre><?php echo esc_html( $block['preview'] ); ?></pre>
								</div>
							<?php endforeach; ?>
						</div>
					</details>
				<?php endif; ?>

				<details class="trendyol-simple-details">
					<summary><?php echo esc_html__( 'Ham Parse Verisi', 'trendyol-woocommerce-importer' ); ?></summary>
					<pre><?php echo esc_html( wp_json_encode( $diagnostic_result['raw_data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) ); ?></pre>
				</details>

				<details class="trendyol-simple-details">
					<summary><?php echo esc_html__( 'HTML Önizleme', 'trendyol-woocommerce-importer' ); ?></summary>
					<pre><?php echo esc_html( $diagnostic_result['html_preview'] ); ?></pre>
				</details>
			</div>

		</div>
	<?php endif; ?>

</div>

<style>
.trendyol-diagnostic-hero {
	display: flex;
	align-items: center;
	gap: 16px;
	padding: 18px 20px;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	background: linear-gradient(180deg, #ffffff 0%, #f8fbff 100%);
	margin-bottom: 16px;
}

.trendyol-diagnostic-hero__badge {
	width: 52px;
	height: 52px;
	border-radius: 14px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 24px;
	background: linear-gradient(135deg, #eff6ff, #dbeafe);
	border: 1px solid #bfdbfe;
	flex-shrink: 0;
}

.trendyol-diagnostic-hero__content h2 {
	margin: 0 0 6px;
	font-size: 23px;
	line-height: 1.2;
	color: #1e293b;
}

.trendyol-diagnostic-hero__content p {
	margin: 0;
	color: #64748b;
	font-size: 14px;
	line-height: 1.6;
	max-width: 760px;
}

.trendyol-diagnostic-form-card {
	background: #fff;
	border: 1px solid #e2e8f0;
	border-radius: 14px;
	padding: 18px 20px;
	box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
}

.trendyol-diagnostic-form-grid {
	display: grid;
	grid-template-columns: minmax(0, 1.8fr) minmax(220px, .8fr);
	gap: 16px;
	align-items: start;
}

.trendyol-diagnostic-field--wide {
	grid-column: auto;
}

.trendyol-diagnostic-field label {
	display: block;
	font-size: 13px;
	font-weight: 700;
	color: #334155;
	margin-bottom: 8px;
}

.trendyol-modern-input,
.trendyol-modern-select {
	width: 100%;
	max-width: 100%;
	min-height: 44px;
	border: 1px solid #cbd5e1;
	border-radius: 10px;
	padding: 0 14px;
	background: #fff;
	color: #0f172a;
	box-shadow: inset 0 1px 2px rgba(15, 23, 42, 0.02);
}

.trendyol-modern-input:focus,
.trendyol-modern-select:focus {
	border-color: #60a5fa;
	box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.16);
	outline: none;
}

.trendyol-diagnostic-field .description {
	margin: 8px 0 0;
	font-size: 12px;
	color: #64748b;
	line-height: 1.5;
}

.trendyol-diagnostic-form-actions {
	margin-top: 16px;
	display: flex;
	align-items: center;
	gap: 12px;
}

.trendyol-diagnostic-screen .trendyol-tool-panel + .trendyol-tool-panel,
.trendyol-diagnostic-screen .trendyol-tool-panel + .trendyol-diagnostic-columns,
.trendyol-diagnostic-screen .trendyol-diagnostic-columns + .trendyol-tool-panel {
	margin-top: 18px;
}

.trendyol-diagnostic-results {
	margin-top: 18px;
}

.trendyol-diagnostic-status-grid {
	display: grid;
	grid-template-columns: repeat(3, minmax(0, 1fr));
	gap: 14px;
	margin-top: 14px;
}

.trendyol-diagnostic-status-card {
	border: 1px solid #dcdcde;
	border-radius: 8px;
	padding: 14px;
	background: #fff;
}

.trendyol-diagnostic-status-card.is-success {
	border-color: #86efac;
	background: #f0fdf4;
}

.trendyol-diagnostic-status-card.is-error {
	border-color: #fecaca;
	background: #fef2f2;
}

.trendyol-diagnostic-status-card .label {
	font-size: 12px;
	font-weight: 600;
	color: #50575e;
	margin-bottom: 6px;
}

.trendyol-diagnostic-status-card .value {
	font-size: 14px;
	font-weight: 700;
	color: #1d2327;
}

.trendyol-diagnostic-field-grid {
	display: grid;
	grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
	gap: 14px;
	margin-top: 14px;
}

.trendyol-diagnostic-field-card {
	border: 1px solid #dcdcde;
	border-radius: 8px;
	padding: 14px;
	background: #fff;
}

.trendyol-diagnostic-field-card.is-success {
	border-color: #86efac;
	background: #f0fdf4;
}

.trendyol-diagnostic-field-card.is-error {
	border-color: #fecaca;
	background: #fef2f2;
}

.trendyol-diagnostic-field-card .field-title {
	font-size: 12px;
	font-weight: 700;
	color: #50575e;
	text-transform: uppercase;
	margin-bottom: 6px;
}

.trendyol-diagnostic-field-card .field-status {
	font-size: 14px;
	font-weight: 700;
	color: #1d2327;
	margin-bottom: 6px;
}

.trendyol-diagnostic-field-card .field-message {
	font-size: 13px;
	color: #50575e;
	margin-bottom: 8px;
}

.trendyol-diagnostic-field-card .field-value {
	font-size: 12px;
	color: #1d2327;
	word-break: break-word;
	line-height: 1.5;
}

.trendyol-diagnostic-columns {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 18px;
	margin-top: 18px;
}

.trendyol-simple-details {
	margin-top: 12px;
	border: 1px solid #dcdcde;
	border-radius: 8px;
	background: #fff;
}

.trendyol-simple-details summary {
	cursor: pointer;
	padding: 10px 12px;
	font-weight: 600;
}

.trendyol-simple-details pre {
	margin: 0;
	padding: 12px;
	border-top: 1px solid #dcdcde;
	background: #f6f7f7;
	overflow: auto;
	white-space: pre-wrap;
	word-break: break-word;
	font-size: 12px;
	line-height: 1.6;
}

.trendyol-jsonld-blocks {
	padding: 12px;
	border-top: 1px solid #dcdcde;
	background: #f6f7f7;
}

.trendyol-jsonld-block + .trendyol-jsonld-block {
	margin-top: 12px;
}

.trendyol-jsonld-block p {
	margin: 0 0 8px;
}

.trendyol-jsonld-block pre {
	margin: 0;
	background: #fff;
	border: 1px solid #dcdcde;
	padding: 12px;
	overflow: auto;
	white-space: pre-wrap;
	word-break: break-word;
	font-size: 12px;
	line-height: 1.6;
}

@media (max-width: 960px) {
	.trendyol-diagnostic-form-grid,
	.trendyol-diagnostic-status-grid,
	.trendyol-diagnostic-columns {
		grid-template-columns: 1fr;
	}
}

@media (max-width: 782px) {
	.trendyol-diagnostic-hero {
		align-items: flex-start;
	}

	.trendyol-diagnostic-field-grid {
		grid-template-columns: 1fr;
	}
}
</style>