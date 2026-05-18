Trendyol WooCommerce İçe Aktarıcı - Güncelleme Notları
======================================================

Tarih: 2026-05-18

Yapılan düzeltmeler
-------------------

1. wp-admin/admin.php?page=trendyol-importer&tab=categories ekranındaki
   kategori link çekme akışı güncellendi.

2. Kategori sayfalarını alırken artık daha dayanıklı istek yapısı kullanılıyor.
   Mümkün olduğunda mevcut Trendyol_Scraper fetch akışı kullanılıyor.

3. Sayfalama sırasında kategori URL'sindeki mevcut filtre/sorgu parametreleri
   korunuyor. Örn: wc, pr, prc, sst gibi parametreler kaybolmuyor.

4. Ürün linki çıkarma işlemi sadece klasik href yapısına değil, sayfa içinde
   gömülü/escape edilmiş Trendyol ürün URL'lerini de yakalayacak şekilde
   genişletildi.

5. İlk sayfada hiç ürün linki bulunamazsa artık sessizce 0 kayıt dönmek yerine
   hata mesajı üretilecek. Böylece admin panelde sorun daha görünür olacak.

Değişen dosyalar
----------------

- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/includes/services/class-category-service.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/readme.txt
