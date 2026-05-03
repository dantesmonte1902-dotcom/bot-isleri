<?php
/**
 * Notifications Settings Section
 * Bildirim ayarları bölümü
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>

<!-- ===== BİLDİRİM SEÇENEKLERI BÖLÜMÜ ===== -->
<div class="trendyol-section">
	<h3><?php echo esc_html__( '🔔 Bildirim Ayarları', 'trendyol-woocommerce-importer' ); ?></h3>

	<div class="form-group">
		<label>
			<input type="checkbox" name="notification_enabled" value="1" <?php checked( $settings['notification_enabled'], 1 ); ?>>
			<?php echo esc_html__( 'Ürün değişim bildirimlerini etkinleştir', 'trendyol-woocommerce-importer' ); ?>
		</label>
		<small class="form-text"><?php echo esc_html__( 'Trendyol\'da ürün bilgisinde değişim tespit edildiğinde bildir.', 'trendyol-woocommerce-importer' ); ?></small>
	</div>

	<div class="divider"></div>

	<!-- Email Bildirim -->
	<div style="margin-bottom: 25px;">
		<h4 style="color: #1e293b; margin-bottom: 15px;">📧 Email Bildirimleri</h4>

		<div class="form-group">
			<label>
				<input type="checkbox" name="notification_email" value="1" <?php checked( $settings['notification_email'], 1 ); ?> id="notification_email">
				<?php echo esc_html__( 'Email ile bildir', 'trendyol-woocommerce-importer' ); ?>
			</label>
		</div>

		<div class="form-group" id="email_address_group" style="display: <?php echo $settings['notification_email'] ? 'block' : 'none'; ?>;">
			<label for="notification_email_address">
				<?php echo esc_html__( 'Email Adresi', 'trendyol-woocommerce-importer' ); ?>
			</label>
			<input 
				type="email" 
				id="notification_email_address" 
				name="notification_email_address" 
				class="form-control" 
				placeholder="ornek@example.com"
				value="<?php echo esc_attr( $settings['notification_email_address'] ); ?>"
			>
			<small class="form-text">
				<?php echo esc_html__( 'Bildirimleri alacak email adresi. Boş bırakırsan site admin emaili kullanılır.', 'trendyol-woocommerce-importer' ); ?>
			</small>
		</div>

		<div class="form-group" id="email_preview" style="display: <?php echo $settings['notification_email'] ? 'block' : 'none'; ?>; margin-top: 15px;">
			<button type="button" class="button button-secondary" id="preview_email_btn">
				<?php echo esc_html__( '👁️ Email Önizlemesi Gör', 'trendyol-woocommerce-importer' ); ?>
			</button>
		</div>
	</div>

	<div class="divider"></div>

	<!-- Telegram Bildirim -->
	<div style="margin-bottom: 25px;">
		<h4 style="color: #1e293b; margin-bottom: 15px;">💬 Telegram Bildirimleri</h4>

		<div class="alert alert-info" style="margin-bottom: 20px;">
			<span style="font-size: 20px;">ℹ️</span>
			<div>
				<strong><?php echo esc_html__( 'Telegram Nasıl Kurulur?', 'trendyol-woocommerce-importer' ); ?></strong>
				<p style="margin: 10px 0 0 0;">
					<?php echo esc_html__( '1. BotFather ile yeni bir bot oluştur (@BotFather)' , 'trendyol-woocommerce-importer' ); ?><br>
					<?php echo esc_html__( '2. Bot Token\'ı kopyala' , 'trendyol-woocommerce-importer' ); ?><br>
					<?php echo esc_html__( '3. Bota /start komutunu gönder' , 'trendyol-woocommerce-importer' ); ?><br>
					<?php echo esc_html__( '4. Chat ID\'yi https://api.telegram.org/bot[TOKEN]/getUpdates sayfasından al' , 'trendyol-woocommerce-importer' ); ?>
				</p>
			</div>
		</div>

		<div class="form-group">
			<label>
				<input type="checkbox" name="notification_telegram" value="1" <?php checked( $settings['notification_telegram'], 1 ); ?> id="notification_telegram">
				<?php echo esc_html__( 'Telegram ile bildir', 'trendyol-woocommerce-importer' ); ?>
			</label>
		</div>

		<div class="form-group" id="telegram_token_group" style="display: <?php echo $settings['notification_telegram'] ? 'block' : 'none'; ?>;">
			<label for="telegram_bot_token">
				<?php echo esc_html__( 'Telegram Bot Token', 'trendyol-woocommerce-importer' ); ?>
			</label>
			<input 
				type="password" 
				id="telegram_bot_token" 
				name="telegram_bot_token" 
				class="form-control" 
				placeholder="123456:ABCdefGHIjklmnoPQRstuvWXYZ"
				value="<?php echo esc_attr( $settings['telegram_bot_token'] ); ?>"
			>
			<small class="form-text">
				<?php echo esc_html__( 'BotFather\'dan aldığın token. Gizli tut!', 'trendyol-woocommerce-importer' ); ?>
			</small>
		</div>

		<div class="form-group" id="telegram_chat_group" style="display: <?php echo $settings['notification_telegram'] ? 'block' : 'none'; ?>;">
			<label for="telegram_chat_id">
				<?php echo esc_html__( 'Telegram Chat ID', 'trendyol-woocommerce-importer' ); ?>
			</label>
			<input 
				type="text" 
				id="telegram_chat_id" 
				name="telegram_chat_id" 
				class="form-control" 
				placeholder="-123456789"
				value="<?php echo esc_attr( $settings['telegram_chat_id'] ); ?>"
			>
			<small class="form-text">
				<?php echo esc_html__( 'Telegram Chat ID\'n (- işaretinden başlayabilir)', 'trendyol-woocommerce-importer' ); ?>
			</small>
		</div>

		<div class="form-group" id="telegram_test" style="display: <?php echo $settings['notification_telegram'] ? 'block' : 'none'; ?>; margin-top: 15px;">
			<button type="button" class="button button-secondary" id="test_telegram_btn">
				<?php echo esc_html__( '✉️ Test Mesajı Gönder', 'trendyol-woocommerce-importer' ); ?>
			</button>
			<span id="telegram_test_result" style="margin-left: 10px;"></span>
		</div>
	</div>

	<div class="divider"></div>

	<!-- Değişim Eşiği -->
	<div style="margin-bottom: 25px;">
		<h4 style="color: #1e293b; margin-bottom: 15px;">📈 Değişim Eşiği</h4>

		<div class="form-group">
			<label>
				<input type="checkbox" name="enable_change_detection" value="1" <?php checked( $settings['enable_change_detection'], 1 ); ?>>
				<?php echo esc_html__( 'Otomatik değişim algılamayı etkinleştir', 'trendyol-woocommerce-importer' ); ?>
			</label>
		</div>

		<div class="form-group">
			<label for="price_change_threshold">
				<?php echo esc_html__( 'Fiyat Değişim Eşiği (%)', 'trendyol-woocommerce-importer' ); ?>
			</label>
			<input 
				type="number" 
				id="price_change_threshold" 
				name="price_change_threshold" 
				class="form-control" 
				min="0" 
				max="100" 
				step="0.1"
				value="<?php echo esc_attr( $settings['price_change_threshold'] ); ?>"
			>
			<small class="form-text">
				<?php echo esc_html__( 'Fiyat bu oranda değişirse uyarı gönder. Örneğin 5 = %5 değişimde uyarı.', 'trendyol-woocommerce-importer' ); ?>
			</small>
		</div>
	</div>
</div>

<script>
jQuery(document).ready(function($) {
	// Email checkbox işlemi
	$('#notification_email').on('change', function() {
		if ($(this).is(':checked')) {
			$('#email_address_group, #email_preview').slideDown();
		} else {
			$('#email_address_group, #email_preview').slideUp();
		}
	});

	// Telegram checkbox işlemi
	$('#notification_telegram').on('change', function() {
		if ($(this).is(':checked')) {
			$('#telegram_token_group, #telegram_chat_group, #telegram_test').slideDown();
		} else {
			$('#telegram_token_group, #telegram_chat_group, #telegram_test').slideUp();
		}
	});

	// Test Telegram butonu
	$('#test_telegram_btn').on('click', function(e) {
		e.preventDefault();
		var btn = $(this);
		var result = $('#telegram_test_result');

		btn.prop('disabled', true).text('<?php echo esc_js( __( '⏳ Gönderiliyor...', 'trendyol-woocommerce-importer' ) ); ?>');
		result.html('').removeClass('alert alert-success alert-danger');

		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'trendyol_test_telegram',
				nonce: '<?php echo wp_create_nonce( 'trendyol_test_telegram' ); ?>',
				bot_token: $('#telegram_bot_token').val(),
				chat_id: $('#telegram_chat_id').val()
			},
			success: function(response) {
				if (response.success) {
					result.html('<span style="color: #10b981;">✅ ' + response.data.message + '</span>');
				} else {
					result.html('<span style="color: #ef4444;">❌ ' + response.data.message + '</span>');
				}
			},
			complete: function() {
				btn.prop('disabled', false).text('<?php echo esc_js( __( '✉️ Test Mesajı Gönder', 'trendyol-woocommerce-importer' ) ); ?>');
			}
		});
	});

	// Email önizlemesi
	$('#preview_email_btn').on('click', function(e) {
		e.preventDefault();
		alert('<?php echo esc_js( __( 'Email önizlemesi yakında gelecek!', 'trendyol-woocommerce-importer' ) ); ?>');
	});
});
</script>

<style>
#email_address_group,
#email_preview,
#telegram_token_group,
#telegram_chat_group,
#telegram_test {
	display: none;
}

.alert-info {
	background-color: #dbeafe;
	border-color: #93c5fd;
	color: #1e40af;
	padding: 15px;
	border-radius: 4px;
	border-left: 4px solid #2563eb;
}

.alert-info strong {
	display: block;
	margin-bottom: 10px;
}
</style>