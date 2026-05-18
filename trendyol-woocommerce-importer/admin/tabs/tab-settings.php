<?php
/**
 * Tab: Settings
 * Ayarlar sekmesi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( isset( $_POST['save_settings'] ) ) {
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_settings_nonce' ) ) {
		wp_die( esc_html__( 'Güvenlik kontrolü başarısız', 'trendyol-woocommerce-importer' ) );
	}

	$settings_service = new Trendyol_Settings_Service();
	$settings_service->save_from_post( $_POST );

	$saved_message = true;
}

$settings = Trendyol_Settings::get_all();

$woo_capabilities = array(
	'manage_woocommerce'      => esc_html__( 'Mağaza Müdürü / Yönetici', 'trendyol-woocommerce-importer' ),
	'edit_published_products' => esc_html__( 'Editör', 'trendyol-woocommerce-importer' ),
	'edit_products'           => esc_html__( 'Ürün Yöneticisi', 'trendyol-woocommerce-importer' ),
);

$blocked_brands = get_option( 'trendyol_blocked_brands', '' );

$cron_intervals = array(
	'off'            => esc_html__( 'Kapalı', 'trendyol-woocommerce-importer' ),
	'every_6_hours'  => esc_html__( 'Her 6 saatte bir', 'trendyol-woocommerce-importer' ),
	'every_12_hours' => esc_html__( 'Her 12 saatte bir', 'trendyol-woocommerce-importer' ),
	'daily'          => esc_html__( 'Günde 1 kez', 'trendyol-woocommerce-importer' ),
);

$product_status_options = array(
	'both'    => esc_html__( 'Taslak + Yayında', 'trendyol-woocommerce-importer' ),
	'draft'   => esc_html__( 'Sadece taslak', 'trendyol-woocommerce-importer' ),
	'publish' => esc_html__( 'Sadece yayında', 'trendyol-woocommerce-importer' ),
);

$auto_price_update_interval = $settings['auto_price_update_interval'] ?? 'off';
$auto_stock_sync_interval   = $settings['auto_stock_sync_interval'] ?? 'off';
$auto_sync_product_status   = $settings['auto_sync_product_status'] ?? 'both';
?>

<div class="trendyol-tab-content">
	<?php if ( isset( $saved_message ) ) : ?>
		<div class="alert alert-success" style="margin-bottom: 25px;">
			<span style="font-size: 20px;">✅</span>
			<div>
				<strong><?php echo esc_html__( 'Başarılı!', 'trendyol-woocommerce-importer' ); ?></strong>
				<p><?php echo esc_html__( 'Ayarlar başarıyla kaydedildi.', 'trendyol-woocommerce-importer' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="trendyol-card">
		<form method="post" action="" class="trendyol-form">
			<?php wp_nonce_field( 'trendyol_settings_nonce' ); ?>
			<input type="hidden" name="save_settings" value="1">

			<div class="trendyol-section">
				<h3><?php echo esc_html__( '👥 İzinler', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label for="import_capability">
						<?php echo esc_html__( 'Ürünleri kimler içe aktarabilir?', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<select id="import_capability" name="import_capability" class="form-control">
						<?php foreach ( $woo_capabilities as $capability => $label ) : ?>
							<option value="<?php echo esc_attr( $capability ); ?>" <?php selected( $settings['import_capability'], $capability ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="form-group">
					<label for="sync_capability">
						<?php echo esc_html__( 'Ürünleri kimler senkronize edebilir?', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<select id="sync_capability" name="sync_capability" class="form-control">
						<?php foreach ( $woo_capabilities as $capability => $label ) : ?>
							<option value="<?php echo esc_attr( $capability ); ?>" <?php selected( $settings['sync_capability'], $capability ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</div>

			<div class="divider"></div>

			<div class="trendyol-section">
				<h3><?php echo esc_html__( '⏱️ Otomatik Senkron Ayarları', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label for="auto_price_update_interval">
						<?php echo esc_html__( 'Otomatik fiyat güncelleme', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<select id="auto_price_update_interval" name="auto_price_update_interval" class="form-control">
						<?php foreach ( $cron_intervals as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $auto_price_update_interval, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<small class="form-text">
						<?php echo esc_html__( 'Kayıtlı Trendyol ürünlerinin satış fiyatlarını otomatik yeniden hesaplar.', 'trendyol-woocommerce-importer' ); ?>
					</small>
				</div>

				<div class="form-group">
					<label for="auto_stock_sync_interval">
						<?php echo esc_html__( 'Otomatik stok senkronu', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<select id="auto_stock_sync_interval" name="auto_stock_sync_interval" class="form-control">
						<?php foreach ( $cron_intervals as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $auto_stock_sync_interval, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<small class="form-text">
						<?php echo esc_html__( 'Trendyol ürünlerinin stok durumlarını zamanlanmış olarak senkronize eder.', 'trendyol-woocommerce-importer' ); ?>
					</small>
				</div>

				<div class="form-group">
					<label for="auto_sync_product_status">
						<?php echo esc_html__( 'Hedef ürün durumu', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<select id="auto_sync_product_status" name="auto_sync_product_status" class="form-control">
						<?php foreach ( $product_status_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $auto_sync_product_status, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<small class="form-text">
						<?php echo esc_html__( 'Otomatik fiyat/stok işlemlerinde hangi ürün durumlarının taranacağını belirler.', 'trendyol-woocommerce-importer' ); ?>
					</small>
				</div>

				<div class="form-group">
					<label>
						<input type="checkbox" name="enable_auto_sync" value="1" <?php checked( $settings['enable_auto_sync'], 1 ); ?>>
						<?php echo esc_html__( 'Eski otomatik senkron uyumluluk anahtarı (geri uyumluluk için)', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<small class="form-text">
						<?php echo esc_html__( 'Eski sürümden gelen tek cron yapısı için korunur. Yeni sistemde asıl kontrol yukarıdaki iki ayrı cron alanıdır.', 'trendyol-woocommerce-importer' ); ?>
					</small>
				</div>
			</div>

			<div class="divider"></div>

			<div class="trendyol-section">
				<h3><?php echo esc_html__( '🚫 İstenmeyen Marka Filtresi', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label for="trendyol_blocked_brands">
						<?php echo esc_html__( 'Engellenecek Markalar', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<textarea
						id="trendyol_blocked_brands"
						name="trendyol_blocked_brands"
						class="form-control"
						rows="8"
						placeholder="bershka&#10;zara&#10;pull&bear&#10;stradivarius"
					><?php echo esc_textarea( $blocked_brands ); ?></textarea>
					<small class="form-text">
						<?php echo esc_html__( 'Her satıra bir marka yazın. Trendyol sayfasındaki brand.name alanı ile büyük/küçük harf duyarsız eşleşirse ürün içe aktarılmaz.', 'trendyol-woocommerce-importer' ); ?>
					</small>
				</div>
			</div>

			<div class="divider"></div>

			<div class="trendyol-section">
				<h3><?php echo esc_html__( '🤖 AI Başlık Ayarları', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label for="ai_provider"><?php echo esc_html__( 'Birincil AI sağlayıcısı', 'trendyol-woocommerce-importer' ); ?></label>
					<select id="ai_provider" name="ai_provider" class="form-control">
						<option value="gemini" <?php selected( $settings['ai_provider'] ?? 'gemini', 'gemini' ); ?>>Gemini</option>
						<option value="openrouter" <?php selected( $settings['ai_provider'] ?? '', 'openrouter' ); ?>>OpenRouter</option>
						<option value="custom" <?php selected( $settings['ai_provider'] ?? '', 'custom' ); ?>>Custom AI</option>
					</select>
				</div>

				<div class="form-group">
					<label for="ai_fallback_provider"><?php echo esc_html__( 'Fallback sağlayıcısı', 'trendyol-woocommerce-importer' ); ?></label>
					<select id="ai_fallback_provider" name="ai_fallback_provider" class="form-control">
						<option value="none" <?php selected( $settings['ai_fallback_provider'] ?? 'none', 'none' ); ?>><?php echo esc_html__( 'Kapalı', 'trendyol-woocommerce-importer' ); ?></option>
						<option value="gemini" <?php selected( $settings['ai_fallback_provider'] ?? '', 'gemini' ); ?>>Gemini</option>
						<option value="openrouter" <?php selected( $settings['ai_fallback_provider'] ?? '', 'openrouter' ); ?>>OpenRouter</option>
						<option value="custom" <?php selected( $settings['ai_fallback_provider'] ?? '', 'custom' ); ?>>Custom AI</option>
					</select>
				</div>

				<div class="form-group">
					<label>
						<input type="checkbox" name="ai_batch_enabled" value="1" <?php checked( $settings['ai_batch_enabled'] ?? 0, 1 ); ?>>
						<?php echo esc_html__( 'Toplu AI İşleme seçeneğini aç', 'trendyol-woocommerce-importer' ); ?>
					</label>
				</div>

				<div class="form-group">
					<label for="ai_default_processing_mode"><?php echo esc_html__( 'Varsayılan işlem modu', 'trendyol-woocommerce-importer' ); ?></label>
					<select id="ai_default_processing_mode" name="ai_default_processing_mode" class="form-control">
						<option value="single" <?php selected( $settings['ai_default_processing_mode'] ?? 'single', 'single' ); ?>><?php echo esc_html__( 'Tekli', 'trendyol-woocommerce-importer' ); ?></option>
						<option value="batch" <?php selected( $settings['ai_default_processing_mode'] ?? '', 'batch' ); ?>><?php echo esc_html__( 'Toplu', 'trendyol-woocommerce-importer' ); ?></option>
					</select>
				</div>

				<div class="form-group">
					<label for="ai_batch_size"><?php echo esc_html__( 'Varsayılan batch size', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="number" id="ai_batch_size" name="ai_batch_size" class="form-control" min="2" max="20" value="<?php echo esc_attr( $settings['ai_batch_size'] ?? 10 ); ?>">
				</div>

				<div class="form-group">
					<label for="ai_retry_limit"><?php echo esc_html__( 'Batch retry sayısı', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="number" id="ai_retry_limit" name="ai_retry_limit" class="form-control" min="0" max="5" value="<?php echo esc_attr( $settings['ai_retry_limit'] ?? 2 ); ?>">
				</div>

				<div class="form-group">
					<label for="ai_request_pause_seconds"><?php echo esc_html__( 'Batch arası bekleme (saniye)', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="number" id="ai_request_pause_seconds" name="ai_request_pause_seconds" class="form-control" min="0" max="120" value="<?php echo esc_attr( $settings['ai_request_pause_seconds'] ?? 12 ); ?>">
				</div>

				<div class="form-group">
					<label for="ai_requests_per_minute"><?php echo esc_html__( 'Dakikadaki istek limiti', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="number" id="ai_requests_per_minute" name="ai_requests_per_minute" class="form-control" min="1" max="60" value="<?php echo esc_attr( $settings['ai_requests_per_minute'] ?? 5 ); ?>">
				</div>

				<div class="form-group">
					<label for="ai_output_language"><?php echo esc_html__( 'Çıktı dili', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="text" id="ai_output_language" name="ai_output_language" class="form-control" value="<?php echo esc_attr( $settings['ai_output_language'] ?? 'Boşnakça' ); ?>" placeholder="Boşnakça">
				</div>

				<div class="form-group">
					<label for="gemini_api_key"><?php echo esc_html__( 'Gemini API Key', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="password" id="gemini_api_key" name="gemini_api_key" class="form-control" value="<?php echo esc_attr( $settings['gemini_api_key'] ?? '' ); ?>" autocomplete="off">
				</div>

				<div class="form-group">
					<label for="gemini_model"><?php echo esc_html__( 'Gemini Model', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="text" id="gemini_model" name="gemini_model" class="form-control" value="<?php echo esc_attr( $settings['gemini_model'] ?? 'gemini-2.5-flash' ); ?>" placeholder="gemini-2.5-flash">
				</div>

				<div class="form-group">
					<label for="openrouter_api_key"><?php echo esc_html__( 'OpenRouter API Key', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="password" id="openrouter_api_key" name="openrouter_api_key" class="form-control" value="<?php echo esc_attr( $settings['openrouter_api_key'] ?? '' ); ?>" autocomplete="off">
				</div>

				<div class="form-group">
					<label for="openrouter_model"><?php echo esc_html__( 'OpenRouter Model', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="text" id="openrouter_model" name="openrouter_model" class="form-control" value="<?php echo esc_attr( $settings['openrouter_model'] ?? '' ); ?>" placeholder="openai/gpt-4.1-mini">
				</div>

				<div class="form-group">
					<label for="custom_ai_api_url"><?php echo esc_html__( 'Custom AI API URL', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="url" id="custom_ai_api_url" name="custom_ai_api_url" class="form-control" value="<?php echo esc_attr( $settings['custom_ai_api_url'] ?? '' ); ?>" placeholder="https://example.com/v1/chat/completions">
				</div>

				<div class="form-group">
					<label for="custom_ai_api_key"><?php echo esc_html__( 'Custom AI API Key', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="password" id="custom_ai_api_key" name="custom_ai_api_key" class="form-control" value="<?php echo esc_attr( $settings['custom_ai_api_key'] ?? '' ); ?>" autocomplete="off">
				</div>

				<div class="form-group">
					<label for="custom_ai_model"><?php echo esc_html__( 'Custom AI Model', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="text" id="custom_ai_model" name="custom_ai_model" class="form-control" value="<?php echo esc_attr( $settings['custom_ai_model'] ?? '' ); ?>" placeholder="gpt-4.1-mini">
				</div>

				<div class="form-group">
					<label for="gemini_title_max_length"><?php echo esc_html__( 'Maksimum başlık uzunluğu', 'trendyol-woocommerce-importer' ); ?></label>
					<input type="number" id="gemini_title_max_length" name="gemini_title_max_length" class="form-control" min="40" max="200" value="<?php echo esc_attr( $settings['gemini_title_max_length'] ?? 160 ); ?>">
				</div>

				<div class="form-group">
					<label for="gemini_title_prompt"><?php echo esc_html__( 'Ek AI başlık talimatı', 'trendyol-woocommerce-importer' ); ?></label>
					<textarea id="gemini_title_prompt" name="gemini_title_prompt" class="form-control" rows="6" placeholder="Sadece kısa e-ticaret başlığı üret."><?php echo esc_textarea( $settings['gemini_title_prompt'] ?? '' ); ?></textarea>
				</div>
			</div>

			<div class="divider"></div>

			<div class="trendyol-section">
				<h3><?php echo esc_html__( '⚙️ Diğer Ayarlar', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label for="max_images">
						<?php echo esc_html__( 'İçe aktarılacak maksimum resim sayısı', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="number" id="max_images" name="max_images" class="form-control" min="1" max="50" value="<?php echo esc_attr( $settings['max_images'] ); ?>">
				</div>

				<div class="form-group">
					<label for="logs_retention_days">
						<?php echo esc_html__( 'Günlükleri (gün) tutun', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="number" id="logs_retention_days" name="logs_retention_days" class="form-control" min="1" value="<?php echo esc_attr( $settings['logs_retention_days'] ); ?>">
				</div>

				<div class="form-group">
					<label>
						<input type="checkbox" name="debug_mode" value="1" <?php checked( $settings['debug_mode'], 1 ); ?>>
						<?php echo esc_html__( 'Hata ayıklama modunu etkinleştir', 'trendyol-woocommerce-importer' ); ?>
					</label>
				</div>
			</div>

			<div class="divider"></div>

			<div class="button-group">
				<button type="submit" class="btn btn-primary btn-lg">
					<?php echo esc_html__( '💾 Ayarları Kaydet', 'trendyol-woocommerce-importer' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>
