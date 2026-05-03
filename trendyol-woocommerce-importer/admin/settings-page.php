<?php
/**
 * Settings Page Template - Turkish Version with Fixed Roles
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Ayar kaydet işlemi
if ( isset( $_POST['save_settings'] ) ) {
	// Nonce doğrula
	if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_settings_nonce' ) ) {
		wp_die( esc_html__( 'Güvenlik kontrolü başarısız', 'trendyol-woocommerce-importer' ) );
	}

	// Yetki kontrolü
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( esc_html__( 'Yetkisiz', 'trendyol-woocommerce-importer' ) );
	}

	// Ayarları kaydet
	Trendyol_Settings::set( 'import_capability', sanitize_text_field( $_POST['import_capability'] ?? 'manage_woocommerce' ) );
	Trendyol_Settings::set( 'sync_capability', sanitize_text_field( $_POST['sync_capability'] ?? 'manage_woocommerce' ) );
	Trendyol_Settings::set( 'enable_auto_sync', isset( $_POST['enable_auto_sync'] ) ? 1 : 0 );
	Trendyol_Settings::set( 'sync_interval', sanitize_text_field( $_POST['sync_interval'] ?? 'daily' ) );
	Trendyol_Settings::set( 'max_images', intval( $_POST['max_images'] ?? 10 ) );
	Trendyol_Settings::set( 'price_markup', floatval( $_POST['price_markup'] ?? 0 ) );
	Trendyol_Settings::set( 'sync_price', isset( $_POST['sync_price'] ) ? 1 : 0 );
	Trendyol_Settings::set( 'sync_stock', isset( $_POST['sync_stock'] ) ? 1 : 0 );
	Trendyol_Settings::set( 'sync_description', isset( $_POST['sync_description'] ) ? 1 : 0 );
	Trendyol_Settings::set( 'logs_retention_days', intval( $_POST['logs_retention_days'] ?? 30 ) );
	Trendyol_Settings::set( 'enable_webhook', isset( $_POST['enable_webhook'] ) ? 1 : 0 );
	Trendyol_Settings::set( 'webhook_url', esc_url_raw( $_POST['webhook_url'] ?? '' ) );
	Trendyol_Settings::set( 'debug_mode', isset( $_POST['debug_mode'] ) ? 1 : 0 );

	// Cron job'u yeniden zamanla
	if ( isset( $_POST['enable_auto_sync'] ) && $_POST['enable_auto_sync'] ) {
		Trendyol_Cron_Manager::schedule();
	} else {
		Trendyol_Cron_Manager::unschedule();
	}

	$saved_message = true;
}

$settings = Trendyol_Settings::get_all();

// Mevcut roller listesi (WooCommerce-uyumlu)
$woo_capabilities = array(
	'manage_woocommerce'     => esc_html__( 'Mağaza Müdürü / Yönetici', 'trendyol-woocommerce-importer' ),
	'edit_published_products' => esc_html__( 'Editör', 'trendyol-woocommerce-importer' ),
	'edit_products'          => esc_html__( 'Ürün Yöneticisi', 'trendyol-woocommerce-importer' ),
);
?>

<div class="trendyol-container">
	<?php if ( isset( $saved_message ) ) : ?>
		<div class="alert alert-success">
			<span style="font-size: 20px;">✅</span>
			<div>
				<strong><?php echo esc_html__( 'Başarılı!', 'trendyol-woocommerce-importer' ); ?></strong>
				<p><?php echo esc_html__( 'Ayarlar başarıyla kaydedildi.', 'trendyol-woocommerce-importer' ); ?></p>
			</div>
		</div>
	<?php endif; ?>

	<div class="trendyol-card">
		<h2><?php echo esc_html__( '⚙️ Plugin Ayarları', 'trendyol-woocommerce-importer' ); ?></h2>

		<form method="post" action="" class="trendyol-form">
			<?php wp_nonce_field( 'trendyol_settings_nonce' ); ?>
			<input type="hidden" name="save_settings" value="1">

			<!-- ===== İZİNLER BÖLÜMÜ ===== -->
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
					<small class="form-text"><?php echo esc_html__( 'Ürünleri içe aktarmak için gereken minimum kullanıcı yetkisini seçin.', 'trendyol-woocommerce-importer' ); ?></small>
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
					<small class="form-text"><?php echo esc_html__( 'Ürünleri Trendyol ile senkronize etmek için gereken minimum kullanıcı yetkisini seçin.', 'trendyol-woocommerce-importer' ); ?></small>
				</div>
			</div>

			<div class="divider"></div>

			<!-- ===== OTOMATİK SENKRONİZASYON BÖLÜMÜ ===== -->
			<div class="trendyol-section">
				<h3><?php echo esc_html__( '🔄 Otomatik Senkronizasyon', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label>
						<input type="checkbox" name="enable_auto_sync" value="1" <?php checked( $settings['enable_auto_sync'], 1 ); ?>>
						<?php echo esc_html__( 'Otomatik senkronizasyonu etkinleştir', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<small class="form-text"><?php echo esc_html__( 'Etkinleştirilirse, ürünler aşağıda belirtilen aralıkta otomatik olarak senkronize edilecektir.', 'trendyol-woocommerce-importer' ); ?></small>
				</div>

				<div class="form-group">
					<label for="sync_interval">
						<?php echo esc_html__( 'Senkronizasyon Aralığı', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<select id="sync_interval" name="sync_interval" class="form-control">
						<option value="hourly" <?php selected( $settings['sync_interval'], 'hourly' ); ?>>
							<?php echo esc_html__( 'Saatlik', 'trendyol-woocommerce-importer' ); ?>
						</option>
						<option value="twicedaily" <?php selected( $settings['sync_interval'], 'twicedaily' ); ?>>
							<?php echo esc_html__( 'Günde İki Kez', 'trendyol-woocommerce-importer' ); ?>
						</option>
						<option value="daily" <?php selected( $settings['sync_interval'], 'daily' ); ?>>
							<?php echo esc_html__( 'Günlük', 'trendyol-woocommerce-importer' ); ?>
						</option>
						<option value="weekly" <?php selected( $settings['sync_interval'], 'weekly' ); ?>>
							<?php echo esc_html__( 'Haftalık', 'trendyol-woocommerce-importer' ); ?>
						</option>
					</select>
				</div>
			</div>

			<div class="divider"></div>

			<!-- ===== SENKRONİZASYON SEÇENEKLERİ BÖLÜMÜ ===== -->
			<div class="trendyol-section">
				<h3><?php echo esc_html__( '📊 Senkronizasyon Seçenekleri', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label>
						<input type="checkbox" name="sync_price" value="1" <?php checked( $settings['sync_price'], 1 ); ?>>
						<?php echo esc_html__( 'Trendyol\'dan fiyatları senkronize et', 'trendyol-woocommerce-importer' ); ?>
					</label>
				</div>

				<div class="form-group">
					<label for="price_markup">
						<?php echo esc_html__( 'Fiyat Işaretlemesi (%)', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="number" id="price_markup" name="price_markup" class="form-control" step="0.01" value="<?php echo esc_attr( $settings['price_markup'] ); ?>">
					<small class="form-text"><?php echo esc_html__( 'Trendyol fiyatlarına yüzdelik bir işaretleme ekleyin. İşaretleme yok için 0 bırakın.', 'trendyol-woocommerce-importer' ); ?></small>
				</div>

				<div class="form-group">
					<label>
						<input type="checkbox" name="sync_stock" value="1" <?php checked( $settings['sync_stock'], 1 ); ?>>
						<?php echo esc_html__( 'Trendyol\'dan stok durumunu senkronize et', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<small class="form-text"><?php echo esc_html__( 'Etkinleştirilirse, stok durumu Trendyol kullanılabilirliğine göre güncellenecektir.', 'trendyol-woocommerce-importer' ); ?></small>
				</div>

				<div class="form-group">
					<label>
						<input type="checkbox" name="sync_description" value="1" <?php checked( $settings['sync_description'], 1 ); ?>>
						<?php echo esc_html__( 'Trendyol\'dan açıklamaları senkronize et', 'trendyol-woocommerce-importer' ); ?>
					</label>
				</div>
			</div>

			<div class="divider"></div>

			<!-- ===== İÇE AKTARMA SEÇENEKLERİ BÖLÜMÜ ===== -->
			<div class="trendyol-section">
				<h3><?php echo esc_html__( '📥 İçe Aktarma Seçenekleri', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label for="max_images">
						<?php echo esc_html__( 'İçe aktarılacak maksimum resim sayısı', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="number" id="max_images" name="max_images" class="form-control" min="1" max="50" value="<?php echo esc_attr( $settings['max_images'] ); ?>">
					<small class="form-text"><?php echo esc_html__( 'Trendyol\'dan indirilen ürün resmilerinin sayısını sınırlayın.', 'trendyol-woocommerce-importer' ); ?></small>
				</div>
			</div>

			<div class="divider"></div>

			<!-- ===== BAKIM BÖLÜMÜ ===== -->
			<div class="trendyol-section">
				<h3><?php echo esc_html__( '🧹 Bakım', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label for="logs_retention_days">
						<?php echo esc_html__( 'Günlükleri (gün) tutun', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="number" id="logs_retention_days" name="logs_retention_days" class="form-control" min="1" value="<?php echo esc_attr( $settings['logs_retention_days'] ); ?>">
					<small class="form-text"><?php echo esc_html__( 'Eski günlükler bu dönemden sonra otomatik olarak silinecektir.', 'trendyol-woocommerce-importer' ); ?></small>
				</div>
			</div>

			<div class="divider"></div>

			<!-- ===== WEB KANCASI BÖLÜMÜ (GELIŞMIŞ) ===== -->
			<div class="trendyol-section">
				<h3><?php echo esc_html__( '🔗 Gelişmiş - Web Kancaları', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label>
						<input type="checkbox" name="enable_webhook" value="1" <?php checked( $settings['enable_webhook'], 1 ); ?>>
						<?php echo esc_html__( 'Web kancası bildirimlerini etkinleştir', 'trendyol-woocommerce-importer' ); ?>
					</label>
				</div>

				<div class="form-group">
					<label for="webhook_url">
						<?php echo esc_html__( 'Web Kancası URL\'si', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<input type="url" id="webhook_url" name="webhook_url" class="form-control" placeholder="https://example.com/webhook" value="<?php echo esc_attr( $settings['webhook_url'] ); ?>">
					<small class="form-text"><?php echo esc_html__( 'Senkronizasyon sonuçları bu URL\'ye POST istekleri olarak gönderilecektir.', 'trendyol-woocommerce-importer' ); ?></small>
				</div>
			</div>

			<div class="divider"></div>

			<!-- ===== HATA AYIKLAMA BÖLÜMÜ ===== -->
			<div class="trendyol-section">
				<h3><?php echo esc_html__( '🐛 Hata Ayıklama', 'trendyol-woocommerce-importer' ); ?></h3>

				<div class="form-group">
					<label>
						<input type="checkbox" name="debug_mode" value="1" <?php checked( $settings['debug_mode'], 1 ); ?>>
						<?php echo esc_html__( 'Hata ayıklama modunu etkinleştir', 'trendyol-woocommerce-importer' ); ?>
					</label>
					<small class="form-text"><?php echo esc_html__( 'Sorun giderme için ayrıntılı günlüğü etkinleştirin. Ayrıntılar için hata günlüklerini kontrol edin.', 'trendyol-woocommerce-importer' ); ?></small>
				</div>
			</div>

			<div class="divider"></div>

			<!-- ===== GÖNDERİ BUTONU ===== -->
			<div class="button-group">
				<button type="submit" class="btn btn-primary btn-lg">
					<?php echo esc_html__( '💾 Ayarları Kaydet', 'trendyol-woocommerce-importer' ); ?>
				</button>
			</div>
		</form>
	</div>
</div>