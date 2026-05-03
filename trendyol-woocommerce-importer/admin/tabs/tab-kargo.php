<?php
if ( ! session_id() ) {
	session_start();
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
}

$kargo_service = new Trendyol_Kargo_Service();

$kategoriler   = $kargo_service->get_categories_from_file();
$kargo_maliyet = get_option( 'trendyol_kargo_maliyetleri', array() );
$default_kargo = get_option( 'trendyol_default_kargo', 0 );
$default_marj  = get_option( 'trendyol_default_marj', 1.3 );

if ( ! is_array( $kargo_maliyet ) ) {
	$kargo_maliyet = array();
}

if ( isset( $_POST['save_kargo'], $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'kargo_kaydet' ) ) {
	$kargo_service->save_kargo_settings( wp_unslash( $_POST ) );
	$_SESSION['kargo_notice'] = 'Kargo ve marj değerleri güncellendi!';
	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=kargo' ) );
	exit;
}

require_once dirname( dirname( __DIR__ ) ) . '/includes/tools.php';
$otomatik_kur = get_tcmb_euro_kuru();

if ( isset( $_POST['eurokur_save'], $_POST['eurokur'] ) ) {
	check_admin_referer( 'trendyol_euro_manual_save' );
	$kargo_service->save_manual_euro_rate( sanitize_text_field( wp_unslash( $_POST['eurokur'] ) ) );
	$_SESSION['kargo_notice'] = 'Euro kuru manuel olarak güncellendi!';
	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=kargo' ) );
	exit;
}

if ( isset( $_POST['eurokur_otomatik'] ) && $otomatik_kur ) {
	check_admin_referer( 'trendyol_euro_auto_refresh' );
	$yeni_kur = $kargo_service->refresh_auto_euro_rate();
	$_SESSION['kargo_notice'] = 'Euro kuru otomatik olarak güncellendi: ' . $yeni_kur;
	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=kargo' ) );
	exit;
}

$euro_kur = get_trendyol_euro_kuru();

if ( isset( $_SESSION['kargo_notice'] ) ) {
	echo '<div class="updated notice" style="margin-bottom:20px;">' . esc_html( $_SESSION['kargo_notice'] ) . '</div>';
	unset( $_SESSION['kargo_notice'] );
}
?>

<div class="trendyol-row" style="display:flex; gap:38px; flex-wrap:wrap; align-items: flex-start; margin-top:15px;">

	<div class="trendyol-card" style="flex:1; min-width:320px; max-width:400px;">
		<h2 style="margin-top:0;">💶 Euro Kuru (Döviz) Ayarı</h2>
		<form method="post" style="margin-bottom:18px; display:flex; gap:6px; align-items:center;">
			<?php wp_nonce_field( 'trendyol_euro_manual_save' ); ?>
			<label for="eurokur" style="font-weight:600;">Manuel Euro Kuru:</label>
			<input type="number" name="eurokur" id="eurokur" step="0.0001" min="0" value="<?php echo esc_attr( $euro_kur ); ?>" required style="width:120px; margin-left:7px;">
			<button type="submit" name="eurokur_save" class="button button-primary" style="margin-left:5px;">Kaydet</button>
		</form>

		<form method="post" style="margin-bottom: 8px;">
			<?php wp_nonce_field( 'trendyol_euro_auto_refresh' ); ?>
			<input type="hidden" name="eurokur_otomatik" value="1">
			<button type="submit" class="button button-secondary" style="width:100%">
				<?php if ( $otomatik_kur ) : ?>
					TCMB’den Güncel Kuru Çek <b>(<?php echo esc_html( $otomatik_kur ); ?>)</b>
				<?php else : ?>
					TCMB’den Güncel Kuru Çek (Otomatik alınamıyor!)
				<?php endif; ?>
			</button>
		</form>

		<div style="margin-top:12px;color:#64748b;">
			<b>Geçerli Kur:</b> <?php echo esc_html( $euro_kur ); ?><br>
			<span style="font-size:12px;">Bu değer fiyat çevirisinde kullanılır.</span>
		</div>
	</div>

	<div class="trendyol-card" style="flex:2; min-width:330px;">
		<h2 style="margin-top:0;">🚚 Kategori Bazında Kargo & Kar Marjı + <span style="color:#E47;">Default Değerler</span></h2>
		<form method="post">
			<?php wp_nonce_field( 'kargo_kaydet' ); ?>

			<div style="margin-bottom:18px;">
				<b>Varsayılan Kargo (EUR):</b>
				<input type="number" step="0.01" min="0" name="default_kargo" value="<?php echo esc_attr( $default_kargo ); ?>" style="width:70px; margin-right:34px;">
				<b>Varsayılan Kar Marjı:</b>
				<input type="number" step="0.01" min="1" name="default_marj" value="<?php echo esc_attr( $default_marj ); ?>" style="width:60px;">
				<span style="font-size:12px; color:#888;">Kategori bulunamazsa veya tekil eklemede otomatik kullanılır.</span>
			</div>

			<table class="widefat striped" style="width:100%; max-width:600px;">
				<thead>
					<tr>
						<th>Kategori</th>
						<th>Kargo (EUR)</th>
						<th>Kâr Marjı<br><small>Örn: <b>1.30</b> = %30 ek fiyat, <b>2</b> = 2 katı fiyat</small></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $kategoriler as $isim ) : ?>
						<?php
						$key_norm = $kargo_service->normalize_category_name( $isim );
						$val      = ( isset( $kargo_maliyet[ $key_norm ] ) && is_array( $kargo_maliyet[ $key_norm ] ) ) ? $kargo_maliyet[ $key_norm ] : array( 'kargo' => 0, 'marj' => 1 );
						?>
						<tr>
							<td><?php echo esc_html( $isim ); ?></td>
							<td>
								<input type="number" min="0" step="0.01" name="kargo[<?php echo esc_attr( $isim ); ?>]" value="<?php echo isset( $val['kargo'] ) ? esc_attr( $val['kargo'] ) : ''; ?>" style="width:80px;">
							</td>
							<td>
								<input type="number" min="1" step="0.01" name="marj[<?php echo esc_attr( $isim ); ?>]" value="<?php echo isset( $val['marj'] ) ? esc_attr( $val['marj'] ) : '1.0'; ?>" style="width:70px;">
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<br>
			<button type="submit" name="save_kargo" class="button button-primary">Kaydet</button>
		</form>

		<small>Her kategori için <b>Kargo (Euro)</b> ve <b>Kâr Marjı</b> çarpanını girin.<br>
		"Varsayılan" değerler, bilinmeyen kategorilere veya özel tekil eklemeye uygulanır.</small>
	</div>
</div>

<style>
.trendyol-row .trendyol-card { box-shadow: 0 2px 12px rgba(30,64,175,0.05); }
@media (max-width: 900px) {
	.trendyol-row { flex-direction:column; gap:22px; }
	.trendyol-card { max-width:100%!important; }
}
</style>