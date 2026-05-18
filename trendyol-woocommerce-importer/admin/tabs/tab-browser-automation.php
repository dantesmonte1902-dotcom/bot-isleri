<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! session_id() ) {
	session_start();
}

if ( ! current_user_can( 'manage_woocommerce' ) ) {
	wp_die( esc_html__( 'Yetkisiz erişim', 'trendyol-woocommerce-importer' ) );
}

$automation_service = new Trendyol_Browser_Automation_Service();
$maxpages           = $automation_service->get_maxpages();
$categories         = $automation_service->get_categories();
$source_url         = isset( $_POST['automation_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['automation_source_url'] ) ) : '';

if ( isset( $_POST['set_browser_maxpages'], $_POST['browser_maxpages'], $_POST['_wpnonce'] ) && wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_set_browser_maxpages' ) ) {
	$saved_maxpages = $automation_service->save_maxpages( wp_unslash( $_POST['browser_maxpages'] ) );
	$_SESSION['browser_automation_notice'] = array(
		'type'    => 'success',
		'message' => 'Tarayıcı otomasyonu için varsayılan sayfa sayısı ayarlandı: ' . $saved_maxpages,
	);
	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=browser-automation' ) );
	exit;
}

if (
	isset( $_POST['browser_category_add'], $_POST['browser_category_name'], $_POST['browser_category_url'], $_POST['_wpnonce'] ) &&
	wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_add_browser_category' )
) {
	$result = $automation_service->add_category(
		sanitize_text_field( wp_unslash( $_POST['browser_category_name'] ) ),
		esc_url_raw( wp_unslash( $_POST['browser_category_url'] ) )
	);

	if ( is_wp_error( $result ) ) {
		$_SESSION['browser_automation_notice'] = array(
			'type'    => 'error',
			'message' => $result->get_error_message(),
		);
	} else {
		$_SESSION['browser_automation_notice'] = array(
			'type'    => 'success',
			'message' => 'Browser automation kategorisi kaydedildi: ' . sanitize_text_field( wp_unslash( $_POST['browser_category_name'] ) ),
		);
	}

	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=browser-automation' ) );
	exit;
}

if ( isset( $_GET['delbrowsercat'] ) ) {
	check_admin_referer( 'trendyol_delete_browser_category_' . intval( $_GET['delbrowsercat'] ) );
	$automation_service->delete_category( wp_unslash( $_GET['delbrowsercat'] ) );
	$_SESSION['browser_automation_notice'] = array(
		'type'    => 'success',
		'message' => 'Browser automation kategorisi silindi.',
	);
	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=browser-automation' ) );
	exit;
}

if ( isset( $_GET['fetchbrowsercat'] ) ) {
	check_admin_referer( 'trendyol_fetch_browser_category_' . intval( $_GET['fetchbrowsercat'] ) );

	$result = $automation_service->fetch_category_links( wp_unslash( $_GET['fetchbrowsercat'] ) );

	if ( is_wp_error( $result ) ) {
		$_SESSION['browser_automation_notice'] = array(
			'type'    => 'error',
			'message' => $result->get_error_message(),
		);
	} else {
		$_SESSION['browser_automation_notice'] = array(
			'type'    => 'success',
			'message' => sprintf(
				'✅ %1$s kategorisinden %2$d ürün linki çekildi: %3$s (Sayfa sayısı: %4$d)',
				$result['name'],
				$result['count'],
				$result['file'],
				isset( $result['maxpages'] ) ? intval( $result['maxpages'] ) : 0
			),
		);
		$_SESSION['trendyol_last_bulkfile'] = $result['file'];
	}

	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=browser-automation' ) );
	exit;
}

if (
	isset( $_POST['trendyol_browser_automation_save'], $_POST['_wpnonce'] ) &&
	wp_verify_nonce( $_POST['_wpnonce'], 'trendyol_browser_automation_save' )
) {
	$category_name = isset( $_POST['automation_category_name'] ) ? sanitize_text_field( wp_unslash( $_POST['automation_category_name'] ) ) : '';
	$links_text    = isset( $_POST['automation_links_text'] ) ? wp_unslash( $_POST['automation_links_text'] ) : '';
	$html_text     = isset( $_POST['automation_html_text'] ) ? wp_unslash( $_POST['automation_html_text'] ) : '';

	$result = $automation_service->save_collected_links( $category_name, $links_text, $html_text, $source_url );

	if ( is_wp_error( $result ) ) {
		$_SESSION['browser_automation_notice'] = array(
			'type'    => 'error',
			'message' => $result->get_error_message(),
		);
	} else {
		$_SESSION['browser_automation_notice'] = array(
			'type'    => 'success',
			'message' => sprintf(
				'%1$s için %2$d ürün linki kaydedildi: %3$s',
				$result['name'],
				$result['count'],
				$result['file']
			),
		);
		$_SESSION['trendyol_last_bulkfile'] = $result['file'];
	}

	wp_redirect( admin_url( 'admin.php?page=trendyol-importer&tab=browser-automation' ) );
	exit;
}

if ( isset( $_SESSION['browser_automation_notice'] ) && is_array( $_SESSION['browser_automation_notice'] ) ) {
	$notice = $_SESSION['browser_automation_notice'];
	$type   = ( isset( $notice['type'] ) && 'error' === $notice['type'] ) ? 'notice notice-error' : 'notice notice-success';
	echo '<div class="' . esc_attr( $type ) . '" style="margin-bottom:20px;"><p>' . esc_html( $notice['message'] ) . '</p></div>';
	unset( $_SESSION['browser_automation_notice'] );
}

$playwright_script = $automation_service->get_playwright_script_template( $source_url );
?>

<div class="trendyol-card" style="margin-bottom:24px;">
	<h2 style="margin-bottom:10px;">🤖 Gerçek Tarayıcı Otomasyonu ile Link Toplama</h2>
	<p style="max-width:960px;">
		Bu sekme artık <strong>Kategoriler</strong> sekmesi gibi kategori adı + kategori linki alıp sunucuda <strong>Node.js + Playwright</strong> varsa
		linkleri doğrudan çekebilir. Aynı zamanda altta manuel JSON / link listesi / HTML yapıştırma yedeği de durur.
	</p>
	<ol style="max-width:960px; padding-left:18px;">
		<li>Önce aşağıdaki alandan kategori adı ve kategori linkini kaydedin.</li>
		<li>Sunucuda Playwright kuruluysa <strong>Ürün Linklerini Çek</strong> düğmesi gerçek tarayıcı ile sayfaları gezer.</li>
		<li>Çıkan dosya yine <code>data/[kategori]-urunleri.txt</code> olarak kaydedilir.</li>
		<li>Sunucuda Playwright yoksa alttaki manuel alanlar yedek olarak kullanılabilir.</li>
	</ol>
</div>

<div class="trendyol-card" style="margin-bottom:24px;">
	<form method="post" style="margin-bottom:18px;display:flex;gap:10px;align-items:flex-end;">
		<?php wp_nonce_field( 'trendyol_set_browser_maxpages' ); ?>
		<input type="hidden" name="set_browser_maxpages" value="1">
		<div>
			<label for="browser_maxpages"><b>Maksimum Çekilecek Sayfa:</b></label>
			<input type="number" name="browser_maxpages" id="browser_maxpages" min="1" max="100" value="<?php echo esc_attr( $maxpages ); ?>" style="width:62px;">
		</div>
		<button type="submit" class="button button-secondary">Kaydet</button>
		<span style="color:#64748b;font-size:12px;margin-left:10px;">
			Browser automation çekiminde kaç sayfa gezileceğini buradan değiştirebilirsiniz.
		</span>
	</form>

	<h3 style="margin-bottom:15px;">Browser Automation Kategorilerim</h3>
	<form method="post" style="margin-bottom:26px;display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
		<?php wp_nonce_field( 'trendyol_add_browser_category' ); ?>
		<input type="hidden" name="browser_category_add" value="1">
		<div>
			<label>Kategori İsmi:</label><br>
			<input type="text" name="browser_category_name" required style="width:160px;">
		</div>
		<div>
			<label>Kategori Linki:</label><br>
			<input type="url" name="browser_category_url" required style="width:320px;">
		</div>
		<button type="submit" class="button button-primary">Ekle</button>
	</form>

	<?php if ( empty( $categories ) ) : ?>
		<p>Browser automation kategorisi eklenmemiş.</p>
	<?php else : ?>
		<table class="widefat striped" style="width:100%;max-width:760px;margin-bottom:20px;">
			<thead>
				<tr>
					<th style="width:35%">İsim</th>
					<th>Link</th>
					<th width="150">Ürün Listesi</th>
					<th width="60"></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $categories as $category ) : ?>
					<tr>
						<td><?php echo esc_html( $category['name'] ); ?></td>
						<td>
							<a href="<?php echo esc_url( $category['url'] ); ?>" target="_blank" style="font-size:11px;"><?php echo esc_url( $category['url'] ); ?></a>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=trendyol-importer&tab=browser-automation&fetchbrowsercat=' . $category['id'] ), 'trendyol_fetch_browser_category_' . $category['id'] ) ); ?>" class="button button-small">
								Ürün Linklerini Çek
							</a>
						</td>
						<td>
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=trendyol-importer&tab=browser-automation&delbrowsercat=' . $category['id'] ), 'trendyol_delete_browser_category_' . $category['id'] ) ); ?>" onclick="return confirm('Silinsin mi?')" class="button">Sil</a>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<p style="margin-top:12px;font-size:12px;color:#64748b;">
		Bu akış, sunucuda <b>Node.js</b> ve <b>Playwright</b> kuruluysa kategori linkini gerçek tarayıcıyla gezer.<br>
		Çekilen ürün linkleri “<b>data</b>” klasörü altında <b>[kategoriadı]-urunleri.txt</b> olarak kaydedilir.<br>
		Toplu içe aktarma sekmesinden anında kullanabilirsiniz.
	</p>
</div>

<div class="trendyol-card" style="margin-bottom:24px;">
	<h3 style="margin-bottom:12px;">Hazır Playwright Örneği</h3>
	<p style="margin-bottom:12px;">
		Aşağıdaki örnek, kategori / arama URL'sinde <code>pi</code> parametresi ile sayfa sayfa ilerleyip ürün linklerini JSON olarak çıktı verir.
	</p>
	<textarea readonly style="width:100%;min-height:340px;font-family:Consolas,Monaco,monospace;"><?php echo esc_textarea( $playwright_script ); ?></textarea>
</div>

<div class="trendyol-card">
	<h3 style="margin-bottom:14px;">Tarayıcıdan Gelen Çıktıyı Kaydet</h3>
	<form method="post">
		<?php wp_nonce_field( 'trendyol_browser_automation_save' ); ?>
		<input type="hidden" name="trendyol_browser_automation_save" value="1">

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="automation_category_name">Kategori Adı</label></th>
					<td>
						<input type="text" name="automation_category_name" id="automation_category_name" class="regular-text" required placeholder="Örn: Elbise Browser" value="<?php echo isset( $_POST['automation_category_name'] ) ? esc_attr( wp_unslash( $_POST['automation_category_name'] ) ) : ''; ?>">
						<p class="description">Kaydedilecek dosya adı bu bilgiye göre oluşturulur.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="automation_source_url">Kaynak URL</label></th>
					<td>
						<input type="url" name="automation_source_url" id="automation_source_url" class="regular-text" placeholder="https://www.trendyol.com/sr?q=..." value="<?php echo esc_attr( $source_url ); ?>">
						<p class="description">İsterseniz Playwright örneğindeki başlangıç URL'sini burada doldurun.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="automation_links_text">Link Listesi / JSON</label></th>
					<td>
						<textarea name="automation_links_text" id="automation_links_text" style="width:100%;min-height:180px;" placeholder='["https://www.trendyol.com/...-p-123","https://www.trendyol.com/...-p-456"] veya alt alta link listesi'><?php echo isset( $_POST['automation_links_text'] ) ? esc_textarea( wp_unslash( $_POST['automation_links_text'] ) ) : ''; ?></textarea>
						<p class="description">Playwright çıktısı JSON olabilir veya alt alta ürün linkleri olabilir.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="automation_html_text">HTML Kaynağı</label></th>
					<td>
						<textarea name="automation_html_text" id="automation_html_text" style="width:100%;min-height:220px;" placeholder="Browser ile aldığınız sayfa HTML içeriğini buraya yapıştırabilirsiniz."><?php echo isset( $_POST['automation_html_text'] ) ? esc_textarea( wp_unslash( $_POST['automation_html_text'] ) ) : ''; ?></textarea>
						<p class="description">Link listesi yoksa, tarayıcıdan alınan HTML içindeki ürün linkleri de otomatik ayıklanır.</p>
					</td>
				</tr>
			</tbody>
		</table>

		<p>
			<button type="submit" class="button button-primary button-large">Kaydet ve Ürün Linki Dosyası Oluştur</button>
		</p>
	</form>
</div>
