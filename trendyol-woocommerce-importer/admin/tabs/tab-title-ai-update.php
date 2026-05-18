<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
}

$service           = new Trendyol_Title_AI_Update_Service();
$counts            = $service->get_product_counts();
$settings          = $service->get_runtime_settings();
$provider_statuses = $service->get_provider_statuses();
$provider_labels   = $service->get_provider_labels();
$draft_count       = intval( $counts['draft'] ?? 0 );
$publish_count     = intval( $counts['publish'] ?? 0 );
$ai_result         = null;
$ai_error          = '';

if (
	isset( $_POST['trendyol_ai_title_update'] ) &&
	isset( $_POST['_wpnonce_trendyol_ai_title_update'] ) &&
	wp_verify_nonce( $_POST['_wpnonce_trendyol_ai_title_update'], 'trendyol_ai_title_update_action' )
) {
	$status_filter   = isset( $_POST['trendyol_ai_title_status'] ) ? sanitize_key( $_POST['trendyol_ai_title_status'] ) : 'draft';
	$limit           = isset( $_POST['trendyol_ai_title_limit'] ) ? intval( $_POST['trendyol_ai_title_limit'] ) : 10;
	$processing_mode = isset( $_POST['trendyol_ai_processing_mode'] ) ? sanitize_key( $_POST['trendyol_ai_processing_mode'] ) : ( $settings['ai_default_processing_mode'] ?? 'single' );
	$batch_size      = isset( $_POST['trendyol_ai_batch_size'] ) ? intval( $_POST['trendyol_ai_batch_size'] ) : intval( $settings['ai_batch_size'] ?? 10 );

	$ai_result = $service->run(
		$status_filter,
		$limit,
		array(
			'processing_mode' => $processing_mode,
			'batch_size'      => $batch_size,
		)
	);

	if ( is_wp_error( $ai_result ) ) {
		$ai_error  = $ai_result->get_error_message();
		$ai_result = null;
	}
}

$has_provider = $service->has_any_configured_provider();
?>

<div class="trendyol-flex-row" style="display:flex; gap:32px; flex-wrap:wrap;">
	<div class="trendyol-card" style="padding:36px 28px; min-width:340px; flex:1 1 420px;">
		<h2 style="margin-top:0">Başlık Güncelle AI</h2>
		<p style="color:#64748b;">
			Tekli veya toplu modda AI sağlayıcısına ürün gönderir, rate limit durumunda otomatik bekler, retry-after süresini uygular ve fallback sağlayıcıya geçebilir.
		</p>

		<?php if ( ! $has_provider ) : ?>
			<div class="notice notice-warning" style="margin:15px 0 18px 0;">
				<p>
					<?php echo esc_html__( 'Henüz yapılandırılmış bir AI sağlayıcısı yok. Önce Ayarlar sekmesinden sağlayıcı bilgilerini kaydedin.', 'trendyol-woocommerce-importer' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=trendyol-importer&tab=settings' ) ); ?>"><?php echo esc_html__( 'Ayarları aç', 'trendyol-woocommerce-importer' ); ?></a>
				</p>
			</div>
		<?php endif; ?>

		<form method="post" onsubmit="return confirm('Seçilen ürünlerin başlıkları AI ile güncellenecek. Emin misiniz?');" style="display:flex; flex-direction:column; gap:18px;">
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
				<input id="trendyol_ai_title_limit" type="number" name="trendyol_ai_title_limit" min="1" max="500" value="10" class="widefat" style="max-width:160px;">
			</div>

			<div>
				<label for="trendyol_ai_processing_mode" style="display:block; margin-bottom:6px; font-weight:600;">İşleme modu</label>
				<select id="trendyol_ai_processing_mode" name="trendyol_ai_processing_mode" class="widefat" style="max-width:220px;">
					<option value="single" <?php selected( $settings['ai_default_processing_mode'] ?? 'single', 'single' ); ?>>Tekli gönderim</option>
					<option value="batch" <?php selected( $settings['ai_default_processing_mode'] ?? '', 'batch' ); ?> <?php disabled( intval( $settings['ai_batch_enabled'] ?? 0 ) !== 1 ); ?>>Toplu gönderim</option>
				</select>
			</div>

			<div>
				<label for="trendyol_ai_batch_size" style="display:block; margin-bottom:6px; font-weight:600;">Batch size</label>
				<input id="trendyol_ai_batch_size" type="number" name="trendyol_ai_batch_size" min="2" max="20" value="<?php echo esc_attr( $settings['ai_batch_size'] ?? 10 ); ?>" class="widefat" style="max-width:160px;">
				<div style="color:#64748b; font-size:13px; margin-top:8px;">Toplu modda ürünler 10-20 arası gruplar halinde tek prompt içinde işlenir.</div>
			</div>

			<button type="submit" name="trendyol_ai_title_update" class="button button-primary" style="font-size:16px; width:100%;" <?php disabled( ! $has_provider ); ?>>AI ile Başlıkları Güncelle</button>
		</form>
	</div>

	<div class="trendyol-card" style="padding:36px 28px; min-width:340px; flex:1 1 360px;">
		<h2 style="margin-top:0">AI İşleme Durumu</h2>
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

		<ul style="margin:18px 0 0 18px; color:#475569;">
			<li>Birincil sağlayıcı: <strong><?php echo esc_html( $provider_labels[ $settings['ai_provider'] ] ?? 'Gemini' ); ?></strong></li>
			<li>Fallback: <strong><?php echo esc_html( $provider_labels[ $settings['ai_fallback_provider'] ] ?? __( 'Kapalı', 'trendyol-woocommerce-importer' ) ); ?></strong></li>
			<li>Batch işleme: <strong><?php echo intval( $settings['ai_batch_enabled'] ?? 0 ) === 1 ? esc_html__( 'Açık', 'trendyol-woocommerce-importer' ) : esc_html__( 'Kapalı', 'trendyol-woocommerce-importer' ); ?></strong></li>
			<li>Dakika limiti: <strong><?php echo esc_html( intval( $settings['ai_requests_per_minute'] ?? 5 ) ); ?></strong></li>
			<li>Bekleme: <strong><?php echo esc_html( intval( $settings['ai_request_pause_seconds'] ?? 12 ) ); ?> sn</strong></li>
		</ul>

		<table class="widefat striped" style="margin-top:18px;">
			<thead><tr><th>Sağlayıcı</th><th>Durum</th></tr></thead>
			<tbody>
				<?php foreach ( $provider_statuses as $provider_key => $provider_ready ) : ?>
					<tr>
						<td><?php echo esc_html( $provider_labels[ $provider_key ] ?? $provider_key ); ?></td>
						<td><?php echo $provider_ready ? esc_html__( 'Hazır', 'trendyol-woocommerce-importer' ) : esc_html__( 'Eksik yapılandırma', 'trendyol-woocommerce-importer' ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
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

		<div class="trendyol-variant-sync-stats" style="margin-bottom:18px;">
			<div class="sync-stat-box"><div class="sync-stat-number"><?php echo esc_html( $ai_result['batches_total'] ?? 0 ); ?></div><div class="sync-stat-label">Toplam batch</div></div>
			<div class="sync-stat-box"><div class="sync-stat-number"><?php echo esc_html( $ai_result['api_requests'] ?? 0 ); ?></div><div class="sync-stat-label">API isteği</div></div>
			<div class="sync-stat-box"><div class="sync-stat-number"><?php echo esc_html( $ai_result['retries_used'] ?? 0 ); ?></div><div class="sync-stat-label">Retry</div></div>
			<div class="sync-stat-box"><div class="sync-stat-number"><?php echo esc_html( $ai_result['waited_seconds'] ?? 0 ); ?></div><div class="sync-stat-label">Bekleme (sn)</div></div>
		</div>

		<p style="color:#64748b; margin-bottom:18px;">
			Mod: <strong><?php echo esc_html( $ai_result['mode'] ?? 'single' ); ?></strong> |
			Batch size: <strong><?php echo esc_html( $ai_result['batch_size'] ?? 1 ); ?></strong> |
			Sağlayıcılar: <strong><?php echo esc_html( implode( ', ', $ai_result['providers_used'] ?? array() ) ); ?></strong>
		</p>

		<div style="overflow:auto;">
			<table class="widefat striped">
				<thead>
					<tr>
						<th>Durum</th>
						<th>Batch</th>
						<th>Sağlayıcı</th>
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
							<td>#<?php echo esc_html( $item['batch'] ?? '-' ); ?> / <?php echo esc_html( $item['batch_index'] ?? '-' ); ?></td>
							<td><?php echo esc_html( $item['provider'] ?? '-' ); ?></td>
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
