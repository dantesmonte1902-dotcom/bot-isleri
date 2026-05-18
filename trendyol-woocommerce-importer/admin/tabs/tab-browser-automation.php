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
$source_url         = isset( $_POST['automation_source_url'] ) ? esc_url_raw( wp_unslash( $_POST['automation_source_url'] ) ) : '';

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
		Bu sekme, Trendyol listeleme sayfasını <strong>Playwright / Puppeteer / Selenium</strong> gibi gerçek tarayıcı araçlarıyla aldıktan sonra
		çıktıyı WordPress içine kaydetmek için hazırlandı. Burada doğrudan Node.js çalıştırılmaz; tarayıcı katmanında topladığınız linkleri veya HTML çıktısını
		buraya yapıştırıp <code>data/[kategori]-urunleri.txt</code> dosyası olarak kaydedebilirsiniz.
	</p>
	<ol style="max-width:960px; padding-left:18px;">
		<li>Kategori / arama URL'sini gerçek tarayıcı ile açın.</li>
		<li>Aşağıdaki Playwright örneğini kendi ortamınızda çalıştırın veya tarayıcıdan HTML / link listesini alın.</li>
		<li>JSON link listesi, düz link listesi veya HTML kaynağını bu sekmeye yapıştırın.</li>
		<li>Kaydettikten sonra oluşan dosyayı <strong>Toplu Ekle</strong> sekmesinde kullanın.</li>
	</ol>
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
