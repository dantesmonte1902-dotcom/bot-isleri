<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$bulk_price_result = isset( $bulk_price_result ) ? $bulk_price_result : null;
?>

<div class="trendyol-tool-screen trendyol-bulk-price-screen">

	<div class="trendyol-tool-screen__header">
		<div class="trendyol-tool-screen__icon">💶</div>
		<div class="trendyol-tool-screen__intro">
			<h2><?php echo esc_html__( 'Toplu Fiyat Güncelleme', 'trendyol-woocommerce-importer' ); ?></h2>
			<p>
				<?php echo esc_html__( 'WooCommerce ürün fiyatlarını toplu olarak artırın veya azaltın. Yüzde bazlı ya da sabit tutarlı güncelleme yapabilirsiniz.', 'trendyol-woocommerce-importer' ); ?>
			</p>
		</div>
	</div>

	<?php if ( ! empty( $bulk_price_result ) && is_array( $bulk_price_result ) ) : ?>
		<?php
		$updated_count = isset( $bulk_price_result['updated_count'] ) ? (int) $bulk_price_result['updated_count'] : 0;
		$message       = isset( $bulk_price_result['message'] ) ? (string) $bulk_price_result['message'] : '';
		$is_success    = ! empty( $bulk_price_result['success'] );
		?>
		<div class="trendyol-tool-alert <?php echo $is_success ? 'is-success' : 'is-error'; ?>">
			<div class="trendyol-tool-alert__title">
				<?php echo $is_success ? '✅ ' . esc_html__( 'İşlem tamamlandı', 'trendyol-woocommerce-importer' ) : '❌ ' . esc_html__( 'İşlem başarısız', 'trendyol-woocommerce-importer' ); ?>
			</div>

			<?php if ( $message ) : ?>
				<div class="trendyol-tool-alert__message"><?php echo esc_html( $message ); ?></div>
			<?php endif; ?>

			<?php if ( $updated_count > 0 ) : ?>
				<div class="trendyol-tool-alert__meta">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %d: updated products count */
							__( 'Güncellenen ürün sayısı: %d', 'trendyol-woocommerce-importer' ),
							$updated_count
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="trendyol-tool-panel">
		<form method="post" action="">
			<?php wp_nonce_field( 'trendyol_bulk_price_update_action', 'trendyol_bulk_price_update_nonce' ); ?>
			<input type="hidden" name="trendyol_bulk_price_update_submit" value="1">

			<div class="trendyol-form-grid">
				<div class="trendyol-form-field">
					<label for="price_update_type"><?php echo esc_html__( 'Güncelleme Tipi', 'trendyol-woocommerce-importer' ); ?></label>
					<select name="price_update_type" id="price_update_type" required>
						<option value="percentage"><?php echo esc_html__( 'Yüzde Bazlı', 'trendyol-woocommerce-importer' ); ?></option>
						<option value="fixed"><?php echo esc_html__( 'Sabit Tutar', 'trendyol-woocommerce-importer' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'Fiyatları yüzde oranıyla ya da sabit bir tutarla güncelleyin.', 'trendyol-woocommerce-importer' ); ?></p>
				</div>

				<div class="trendyol-form-field">
					<label for="price_update_direction"><?php echo esc_html__( 'İşlem Yönü', 'trendyol-woocommerce-importer' ); ?></label>
					<select name="price_update_direction" id="price_update_direction" required>
						<option value="increase"><?php echo esc_html__( 'Artır', 'trendyol-woocommerce-importer' ); ?></option>
						<option value="decrease"><?php echo esc_html__( 'Azalt', 'trendyol-woocommerce-importer' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'Fiyatların artacağını mı azalacağını mı seçin.', 'trendyol-woocommerce-importer' ); ?></p>
				</div>

				<div class="trendyol-form-field">
					<label for="price_update_amount"><?php echo esc_html__( 'Değer', 'trendyol-woocommerce-importer' ); ?></label>
					<input
						type="number"
						step="0.01"
						min="0"
						name="price_update_amount"
						id="price_update_amount"
						placeholder="<?php echo esc_attr__( 'Örn: 10 veya 25.50', 'trendyol-woocommerce-importer' ); ?>"
						required
					>
					<p class="description"><?php echo esc_html__( 'Yüzde ya da sabit tutar değerini girin.', 'trendyol-woocommerce-importer' ); ?></p>
				</div>

				<div class="trendyol-form-field">
					<label for="price_update_scope"><?php echo esc_html__( 'Uygulama Alanı', 'trendyol-woocommerce-importer' ); ?></label>
					<select name="price_update_scope" id="price_update_scope" required>
						<option value="all"><?php echo esc_html__( 'Tüm Ürünler', 'trendyol-woocommerce-importer' ); ?></option>
						<option value="trendyol"><?php echo esc_html__( 'Sadece Trendyol Ürünleri', 'trendyol-woocommerce-importer' ); ?></option>
					</select>
					<p class="description"><?php echo esc_html__( 'İşlemin hangi ürünlerde uygulanacağını seçin.', 'trendyol-woocommerce-importer' ); ?></p>
				</div>
			</div>

			<div class="trendyol-tool-actions">
				<button type="submit" class="button button-primary button-large">
					<?php echo esc_html__( 'Toplu Fiyat Güncelle', 'trendyol-woocommerce-importer' ); ?>
				</button>
			</div>
		</form>
	</div>

	<div class="trendyol-tool-note">
		<strong><?php echo esc_html__( 'Not:', 'trendyol-woocommerce-importer' ); ?></strong>
		<?php echo esc_html__( 'Bu işlem çok sayıda ürünü etkileyebilir. Uygulamadan önce değerleri dikkatlice kontrol etmeniz önerilir.', 'trendyol-woocommerce-importer' ); ?>
	</div>
</div>

<style>
.trendyol-tool-screen {
	background: #fff;
	border: 1px solid #e2e8f0;
	border-radius: 18px;
	padding: 24px;
	box-shadow: 0 6px 20px rgba(15, 23, 42, 0.04);
}

.trendyol-tool-screen__header {
	display: flex;
	align-items: flex-start;
	gap: 16px;
	margin-bottom: 22px;
}

.trendyol-tool-screen__icon {
	width: 52px;
	height: 52px;
	border-radius: 14px;
	display: flex;
	align-items: center;
	justify-content: center;
	font-size: 24px;
	background: linear-gradient(135deg, #eff6ff, #dbeafe);
	border: 1px solid #bfdbfe;
	flex-shrink: 0;
}

.trendyol-tool-screen__intro h2 {
	margin: 0 0 6px;
	font-size: 24px;
	line-height: 1.25;
	color: #0f172a;
}

.trendyol-tool-screen__intro p {
	margin: 0;
	color: #64748b;
	font-size: 14px;
	line-height: 1.6;
	max-width: 760px;
}

.trendyol-tool-alert {
	border-radius: 14px;
	padding: 14px 16px;
	margin-bottom: 20px;
	border: 1px solid transparent;
}

.trendyol-tool-alert.is-success {
	background: #f0fdf4;
	border-color: #86efac;
	color: #166534;
}

.trendyol-tool-alert.is-error {
	background: #fef2f2;
	border-color: #fecaca;
	color: #991b1b;
}

.trendyol-tool-alert__title {
	font-weight: 700;
	margin-bottom: 6px;
}

.trendyol-tool-alert__message {
	font-size: 14px;
	line-height: 1.5;
}

.trendyol-tool-alert__meta {
	margin-top: 8px;
	font-size: 13px;
	font-weight: 600;
}

.trendyol-tool-panel {
	background: #f8fafc;
	border: 1px solid #e2e8f0;
	border-radius: 16px;
	padding: 18px;
}

.trendyol-form-grid {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: 18px;
}

.trendyol-form-field label {
	display: block;
	font-size: 13px;
	font-weight: 700;
	color: #334155;
	margin-bottom: 8px;
}

.trendyol-form-field input,
.trendyol-form-field select {
	width: 100%;
	max-width: 100%;
	min-height: 42px;
	border: 1px solid #cbd5e1;
	border-radius: 10px;
	padding: 0 12px;
	background: #fff;
	color: #0f172a;
}

.trendyol-form-field input:focus,
.trendyol-form-field select:focus {
	border-color: #60a5fa;
	box-shadow: 0 0 0 3px rgba(96, 165, 250, 0.16);
	outline: none;
}

.trendyol-form-field .description {
	margin: 8px 0 0;
	font-size: 12px;
	color: #64748b;
	line-height: 1.5;
}

.trendyol-tool-actions {
	margin-top: 18px;
	display: flex;
	align-items: center;
	gap: 12px;
}

.trendyol-tool-note {
	margin-top: 18px;
	padding: 14px 16px;
	border-radius: 14px;
	background: #fff7ed;
	border: 1px solid #fed7aa;
	color: #9a3412;
	font-size: 13px;
	line-height: 1.6;
}

@media (max-width: 782px) {
	.trendyol-tool-screen {
		padding: 18px;
	}

	.trendyol-tool-screen__header {
		flex-direction: column;
	}

	.trendyol-form-grid {
		grid-template-columns: 1fr;
	}
}
</style>