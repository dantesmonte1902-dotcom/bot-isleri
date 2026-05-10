<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$query_service = new Trendyol_Product_Query_Service();

if ( ! empty( $_SESSION['trendyol_link_export_notice'] ) ) {
	echo '<div class="notice notice-info" style="margin-bottom:20px;"><p>' . esc_html( $_SESSION['trendyol_link_export_notice'] ) . '</p></div>';
	unset( $_SESSION['trendyol_link_export_notice'] );
}

$export_counts = array(
	'both'    => count( $query_service->get_trendyol_product_ids( array( 'statuses' => array( 'draft', 'publish' ) ) ) ),
	'draft'   => count( $query_service->get_trendyol_product_ids( array( 'statuses' => array( 'draft' ) ) ) ),
	'publish' => count( $query_service->get_trendyol_product_ids( array( 'statuses' => array( 'publish' ) ) ) ),
);
?>

<div class="trendyol-card">
	<div class="trendyol-section">
		<h2>🔗 Trendyol Linklerini Dışa Aktar</h2>
		<p>
			WooCommerce ürünlerinde kayıtlı <code>trendyol_product_url</code> alanlarını tek satırda bir link olacak şekilde <b>.txt</b> olarak indirebilirsiniz.<br>
			Bu dosyayı başka sitedeki <b>Toplu Ekle</b> alanına yükleyerek ürün linklerini tekrar toplu içeri alabilirsiniz.
		</p>
	</div>

	<form method="post" class="trendyol-form" style="max-width:560px;">
		<?php wp_nonce_field( 'trendyol_export_product_urls_nonce' ); ?>
		<input type="hidden" name="trendyol_export_product_urls" value="1" />

		<div class="form-group">
			<label for="export_status">Hangi ürünlerin linkleri indirilsin?</label>
			<select name="export_status" id="export_status" class="form-control" style="max-width:260px;">
				<option value="both">Taslak + Yayında (<?php echo esc_html( $export_counts['both'] ); ?>)</option>
				<option value="draft">Sadece Taslak (<?php echo esc_html( $export_counts['draft'] ); ?>)</option>
				<option value="publish">Sadece Yayında (<?php echo esc_html( $export_counts['publish'] ); ?>)</option>
			</select>
		</div>

		<div class="button-group">
			<button type="submit" class="button button-secondary button-large">⬇️ .txt Olarak İndir</button>
		</div>
	</form>
</div>
