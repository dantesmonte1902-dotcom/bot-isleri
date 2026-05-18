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
				<h3><?php echo esc_html__( '🤖 Gemini AI Başlık Ayarları', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label for="gemini_api_key">
						<?php echo esc_html__( 'Gemini API Key', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="password" id="gemini_api_key" name="gemini_api_key" class="form-control" value="<?php echo esc_attr( $settings['gemini_api_key'] ?? '' ); ?>" autocomplete="off">
					<small class="form-text">
						<?php echo esc_html__( 'Başlık Güncelle AI sekmesi bu anahtar ile Gemini API çağrısı yapar.', 'trendyol-woocommerce-importer' ); ?>
					</small>
				</div>

				<div class="form-group">
					<label for="gemini_model">
						<?php echo esc_html__( 'Gemini Model', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="text" id="gemini_model" name="gemini_model" class="form-control" value="<?php echo esc_attr( $settings['gemini_model'] ?? 'gemini-2.5-flash' ); ?>" placeholder="gemini-2.5-flash">
				</div>

				<div class="form-group">
					<label for="gemini_title_max_length">
						<?php echo esc_html__( 'Maksimum başlık uzunluğu', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="number" id="gemini_title_max_length" name="gemini_title_max_length" class="form-control" min="40" max="200" value="<?php echo esc_attr( $settings['gemini_title_max_length'] ?? 110 ); ?>">
				</div>

				<div class="form-group">
					<label for="gemini_title_prompt">
						<?php echo esc_html__( 'Ek AI başlık talimatı', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<textarea id="gemini_title_prompt" name="gemini_title_prompt" class="form-control" rows="6" placeholder="Markayı başta kullan, sade ve satış odaklı yaz."><?php echo esc_textarea( $settings['gemini_title_prompt'] ?? '' ); ?></textarea>
					<small class="form-text">
						<?php echo esc_html__( 'Boş bırakırsanız varsayılan Türkçe SEO uyumlu başlık kuralları kullanılır.', 'trendyol-woocommerce-importer' ); ?>
					</small>
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
