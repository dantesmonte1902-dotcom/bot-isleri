<?php
if ( ! session_id() ) {
	session_start();
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
}

$category_service = new Trendyol_Category_Service();

if ( isset( $_POST['set_maxpages'], $_POST['maxpages'], $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_set_maxpages' ) ) {
	$n = $category_service->save_maxpages( $_POST['maxpages'] );
	$_SESSION['categories_notice'] = 'Varsayılan sayfa sayısı ayarlandı: ' . $n;
	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=categories' ) );
	exit;
}

$maxpages = $category_service->get_maxpages();

if (
	isset( $_POST['category_add'], $_POST['category_name'], $_POST['category_url'], $_POST['_wpnonce'] ) &&
	wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_add_category' )
) {
	$result = $category_service->add_category( sanitize_text_field( wp_unslash( $_POST['category_name'] ) ), esc_url_raw( wp_unslash( $_POST['category_url'] ) ) );

	if ( is_wp_error( $result ) ) {
		$_SESSION['categories_notice'] = '⚠️ ' . $result->get_error_message();
	} else {
		$_SESSION['categories_notice'] = 'Kategori kaydedildi: ' . sanitize_text_field( wp_unslash( $_POST['category_name'] ) );
	}

	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=categories' ) );
	exit;
}

if ( isset( $_GET['delcat'] ) ) {
	check_admin_referer( 'trendyol_delete_category_' . intval( $_GET['delcat'] ) );
	$category_service->delete_category( $_GET['delcat'] );
	$_SESSION['categories_notice'] = 'Kategori silindi!';
	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=categories' ) );
	exit;
}

if ( isset( $_GET['fetchcat'] ) ) {
	check_admin_referer( 'trendyol_fetch_category_' . intval( $_GET['fetchcat'] ) );

	$result = $category_service->fetch_category_links( $_GET['fetchcat'] );

	if ( is_wp_error( $result ) ) {
		$_SESSION['categories_notice'] = $result->get_error_message();
	} else {
		$_SESSION['categories_notice'] = '✅ ' . $result['name'] . ' kategorisinden ' . $result['count'] . ' ürün linki çekildi: ' . $result['file'] . ' (Sayfa sayısı: ' . $result['maxpages'] . ')';
		$_SESSION['trendyol_last_bulkfile'] = $result['file'];
	}

	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=categories' ) );
	exit;
}

if ( isset( $_SESSION['categories_notice'] ) ) {
	echo '<div class="alert alert-info" style="margin-bottom:20px;">' . esc_html( $_SESSION['categories_notice'] ) . '</div>';
	unset( $_SESSION['categories_notice'] );
}

$categories = $category_service->get_categories();
?>

<div class="trendyol-card">
	<form method="post" style="margin-bottom:18px;display:flex;gap:10px;align-items:flex-end;">
		<?php wp_nonce_field( 'trendyol_set_maxpages' ); ?>
		<input type="hidden" name="set_maxpages" value="1">
		<div>
			<label for="maxpages"><b>Maksimum Çekilecek Sayfa:</b></label>
			<input type="number" name="maxpages" id="maxpages" min="1" max="100" value="<?php echo esc_attr( $maxpages ); ?>" style="width:62px;">
		</div>
		<button type="submit" class="button button-secondary">Kaydet</button>
		<span style="color:#64748b;font-size:12px;margin-left:10px;">
			Tüm kategoriler için kaç sayfa gezileceğini buradan değiştirebilirsiniz.
		</span>
	</form>

	<h2 style="margin-bottom:15px;">Kategorilerim</h2>
	<form method="post" style="margin-bottom:26px;display:flex;gap:12px;flex-wrap:wrap;align-items: flex-end;">
		<?php wp_nonce_field( 'trendyol_add_category' ); ?>
		<input type="hidden" name="category_add" value="1">
		<div>
			<label>Kategori İsmi:</label><br>
			<input type="text" name="category_name" required style="width:160px;">
		</div>
		<div>
			<label>Kategori Linki:</label><br>
			<input type="url" name="category_url" required style="width:320px;">
		</div>
		<button type="submit" class="button button-primary">Ekle</button>
	</form>

	<?php if ( empty( $categories ) ) : ?>
		<p>Kategori eklenmemiş.</p>
	<?php else : ?>
		<table class="widefat striped" style="width:100%;max-width:700px;">
			<thead>
				<tr>
					<th style="width:35%">İsim</th>
					<th>Link</th>
					<th width="120">Ürün Listesi</th>
					<th width="60"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $categories as $cat ) : ?>
					<tr>
						<td><?php echo esc_html( $cat['name'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $cat['url'] ); ?>" target="_blank" style="font-size:11px;"><?php echo esc_url( $cat['url'] ); ?></a>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=trendyol-importer&tab=categories&fetchcat=' . $cat['id'] ), 'trendyol_fetch_category_' . $cat['id'] ) ); ?>" class="button button-small">
								Ürün Linklerini Çek
							</a>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=trendyol-importer&tab=categories&delcat=' . $cat['id'] ), 'trendyol_delete_category_' . $cat['id'] ) ); ?>" onclick="return confirm('Silinsin mi?')" class="button">Sil</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<p style="margin-top:12px;font-size:12px;color:#64748b;">
		Kategori ekleyip “Ürün Linklerini Çek”e tıklayın.<br>
		Ürün linkleri “<b>data</b>” klasörü altında <b>[kategoriadı]-urunleri.txt</b> olarak kaydedilir.<br>
		Toplu içe aktarma sekmesinden anında kullanabilirsiniz.
	</p>
</div>