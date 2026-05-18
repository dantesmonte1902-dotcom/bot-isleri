<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<?php
$query_service = new Trendyol_Product_Query_Service();

if ( ! empty( $_SESSION['trendyol_featured_image_export_notice'] ) ) {
	echo '<div class="notice notice-info" style="margin-bottom:20px;"><p>' . esc_html( $_SESSION['trendyol_featured_image_export_notice'] ) . '</p></div>';
	unset( $_SESSION['trendyol_featured_image_export_notice'] );
}

$export_counts = array(
	'both'    => $query_service->count_products_with_featured_images( array( 'statuses' => array( 'draft', 'publish' ) ) ),
	'draft'   => $query_service->count_products_with_featured_images( array( 'statuses' => array( 'draft' ) ) ),
	'publish' => $query_service->count_products_with_featured_images( array( 'statuses' => array( 'publish' ) ) ),
);
?>

<div class="trendyol-card">
	<div class="trendyol-section">
		<h2>🖼️ Öne Çıkan Görselleri İndir</h2>
		<p>
			Ürünlerinizin öne çıkan görsellerini tek bir <b>.zip</b> dosyası halinde indirebilirsiniz.<br>
			Yalnızca öne çıkan görseli olan ürünler arşive eklenir; büyük arşivler ise indirme isteği sırasında sunucuda partiler halinde hazırlanır.
		</p>
	</div>

	<form method="post" class="trendyol-form" style="max-width:560px;">
		<?php wp_nonce_field( 'trendyol_export_featured_images_nonce' ); ?>
		<input type="hidden" name="trendyol_export_featured_images" value="1" />

		<div class="form-group">
			<label for="featured_image_export_status">Hangi ürünlerin görselleri indirilsin?</label>
			<select name="featured_image_export_status" id="featured_image_export_status" class="form-control" style="max-width:260px;">
				<option value="both">Taslak + Yayında (<?php echo esc_html( $export_counts['both'] ); ?>)</option>
				<option value="draft">Sadece Taslak (<?php echo esc_html( $export_counts['draft'] ); ?>)</option>
				<option value="publish">Sadece Yayında (<?php echo esc_html( $export_counts['publish'] ); ?>)</option>
			</select>
		</div>

		<div class="form-group">
			<label for="featured_image_export_folder_mode">ZIP içinde nasıl sıralansın?</label>
			<select name="featured_image_export_folder_mode" id="featured_image_export_folder_mode" class="form-control" style="max-width:260px;">
				<option value="flat">Düz liste</option>
				<option value="category">Kategori klasörlerine göre</option>
			</select>
		</div>

		<div class="button-group">
			<button type="submit" class="button button-secondary button-large">⬇️ .zip Olarak İndir</button>
		</div>
	</form>
</div>
