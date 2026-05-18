<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
}

$service       = new Trendyol_Title_AI_Update_Service();
$counts        = $service->get_product_counts();
$draft_count   = intval( $counts['draft'] ?? 0 );
$publish_count = intval( $counts['publish'] ?? 0 );
$ai_result     = null;
$ai_error      = '';

if (
	isset( $_POST['trendyol_ai_title_update'] ) &&
	isset( $_POST['_wpnonce_trendyol_ai_title_update'] ) &&
	wp_verify_nonce( $_POST['_wpnonce_trendyol_ai_title_update'], 'trendyol_ai_title_update_action' )
) {
	$status_filter = isset( $_POST['trendyol_ai_title_status'] ) ? sanitize_key( $_POST['trendyol_ai_title_status'] ) : 'draft';
	$limit         = isset( $_POST['trendyol_ai_title_limit'] ) ? intval( $_POST['trendyol_ai_title_limit'] ) : 10;
	$ai_result     = $service->run( $status_filter, $limit );

	if ( is_wp_error( $ai_result ) ) {
		$ai_error = $ai_result->get_error_message();
		$ai_result = null;
	}
}

$has_api_key = '' !== trim( (string) Trendyol_Settings::get( 'gemini_api_key', '' ) );
?>

<div class="trendyol-flex-row" style="display:flex; gap:32px; flex-wrap:wrap;">
	<div class="trendyol-card" style="padding:36px 28px; min-width:340px; flex:1 1 360px;">
		<h2 style="margin-top:0">Başlık Güncelle AI (Gemini)</h2>
		<p style="color:#64748b;">
			Trendyol ürünlerinin mevcut başlık, marka, kategori ve açıklama bilgilerini Gemini API ile analiz edip yeni başlık üretir.
		</p>

		<?php if ( ! $has_api_key ) : ?>
			<div class="notice notice-warning" style="margin:15px 0 18px 0;">
				<p>
					<?php echo esc_html__( 'Gemini API Key henüz girilmemiş. Önce Ayarlar sekmesinden API anahtarını kaydedin.', 'trendyol-woocommerce-importer' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trendyol-importer&tab=settings' ) ); ?>"><?php echo esc_html__( 'Ayarları aç', 'trendyol-woocommerce-importer' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" onsubmit="return confirm('Seçilen ürünlerin başlıkları Gemini ile güncellenecek. Emin misiniz?');" style="display:flex; flex-direction:column; gap:18px;">
			<?php wp_nonce_field( 'trendyol_ai_title_update_action', '_wpnonce_trendyol_ai_title_update' ); ?>

			<div>
				<label for="trendyol_ai_title_status" style="display:block; margin-bottom:6px; font-weight:600;">Hedef ürün grubu</label>
				<select id="trendyol_ai_title_status" name="trendyol_ai_title_status" class="widefat" style="max-width:320px;">
					<option value="draft">Yalnızca taslak ürünler (<?php echo esc_html( $draft_count ); ?>)</option>
					<option value="publish">Yalnızca yayındaki ürünler (<?php echo esc_html( $publish_count ); ?>)</option>
					<option value="both">Taslak + yayındaki ürünler (<?php echo esc_html( $draft_count + $publish_count ); ?>)</option>
				</select>
			</div>

			<div>
				<label for="trendyol_ai_title_limit" style="display:block; margin-bottom:6px; font-weight:600;">Bu çalıştırmada işlenecek ürün sayısı</label>
				<input id="trendyol_ai_title_limit" type="number" name="trendyol_ai_title_limit" min="1" max="100" value="10" class="widefat" style="max-width:160px;">
				<div style="color:#64748b; font-size:13px; margin-top:8px;">Uzun işlemler için küçük batch sayılarıyla ilerlemeniz önerilir.</div>
			</div>

			<button type="submit" name="trendyol_ai_title_update" class="button button-primary" style="font-size:16px; width:100%;" <?php disabled( ! $has_api_key ); ?>>Gemini ile Başlıkları Güncelle</button>
		</form>
	</div>

	<div class="trendyol-card" style="padding:36px 28px; min-width:340px; flex:1 1 360px;">
		<h2 style="margin-top:0">Durum</h2>
		<div class="trendyol-variant-sync-stats">
			<div class="sync-stat-box">
				<div class="sync-stat-number"><?php echo esc_html( $draft_count ); ?></div>
				<div class="sync-stat-label">Taslak ürün</div>
			</div>
			<div class="sync-stat-box">
				<div class="sync-stat-number"><?php echo esc_html( $publish_count ); ?></div>
				<div class="sync-stat-label">Yayındaki ürün</div>
			</div>
		</div>

		<p style="color:#64748b; margin-bottom:0;">
			İşlem yalnızca <code>trendyol_product_url</code> metasına sahip ürünlerde çalışır.
		</p>
	</div>
</div>

<?php if ( '' !== $ai_error ) : ?>
	<div class="notice notice-error" style="margin:20px 0 0;">
		<p><strong><?php echo esc_html( $ai_error ); ?></strong></p>
	</div>
<?php endif; ?>

<?php if ( is_array( $ai_result ) ) : ?>
	<div class="trendyol-card" style="padding:28px; margin-top:24px;">
		<h2 style="margin-top:0">AI Başlık Güncelleme Özeti</h2>
		<p style="margin-bottom:18px;">
			<strong><?php echo esc_html( $ai_result['updated'] ); ?></strong> güncellendi,
			<strong><?php echo esc_html( $ai_result['skipped'] ); ?></strong> atlandı,
			<strong><?php echo esc_html( $ai_result['failed'] ); ?></strong> hata verdi.
		</p>

		<div style="overflow:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Durum</th>
						<th>Eski Başlık</th>
						<th>Yeni Başlık / Mesaj</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $ai_result['items'] as $item ) : ?>
						<tr>
							<td>
								<?php
								if ( 'updated' === $item['status'] ) {
									echo esc_html__( 'Güncellendi', 'trendyol-woocommerce-importer' );
								} elseif ( 'skipped' === $item['status'] ) {
									echo esc_html__( 'Atlandı', 'trendyol-woocommerce-importer' );
								} else {
									echo esc_html__( 'Hata', 'trendyol-woocommerce-importer' );
								}
								?>
							</td>
							<td>
								<?php if ( ! empty( $item['edit_url'] ) ) : ?>
									<a href="<?php echo esc_url( $item['edit_url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $item['title'] ?? '-' ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $item['title'] ?? '-' ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( 'updated' === $item['status'] ) : ?>
									<?php echo esc_html( $item['new_title'] ?? '' ); ?>
								<?php else : ?>
									<?php echo esc_html( $item['message'] ?? '' ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	</div>
<?php endif; ?>
