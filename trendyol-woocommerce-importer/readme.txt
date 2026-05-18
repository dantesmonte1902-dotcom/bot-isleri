Trendyol WooCommerce İçe Aktarıcı - Güncelleme Notları
======================================================

Tarih: 2026-05-18

AI Başlık Güncelle özelliğinde yapılan son geliştirmeler
--------------------------------------------------------

1. Başlık Güncelle AI özelliğine yeni bir optimizasyon katmanı eklendi.
   Kullanıcı artık tekli gönderim ile toplu gönderim arasında seçim yapabiliyor.

2. Ayarlara "Toplu AI İşleme" seçeneği eklendi.
   Bu seçenek açıldığında ürünler küçük gruplar halinde AI API'ye gönderiliyor.

3. Batch size ayarı eklendi.
   Varsayılan değer 10 olacak şekilde ayarlandı ve 2-20 arası kullanılabiliyor.

4. Her başarılı batch sonrasında otomatik bekleme desteği eklendi.
   Varsayılan bekleme süresi 12 saniye olarak ayarlandı.

5. Dakikadaki istek sayısını sınırlayan request throttling sistemi eklendi.
   Böylece rate limit alma riski azaltıldı.

6. Retry-after desteği eklendi.
   AI sağlayıcısı bekleme süresi döndürürse sistem bunu okuyup otomatik uyguluyor.

7. Başarısız batch işlemleri için retry desteği eklendi.
   Geçici hata ve rate limit durumlarında batch tekrar denenebiliyor.

8. AI provider yapısı abstract hale getirildi.
   Gemini, OpenRouter ve OpenAI uyumlu özel endpoint desteği aynı yapı üzerinden çalışıyor.

9. Fallback provider desteği eklendi.
   Birincil sağlayıcı hata verirse ikinci sağlayıcıya otomatik geçiş yapılabiliyor.

10. Tek prompt içinde birden fazla ürün işlenebiliyor.
    AI çıktısı satır satır parse edilip ürün sırası korunarak ilgili ürünle eşleştiriliyor.

11. JSON parse hatalarına karşı koruma eklendi.
    Geçersiz AI yanıtlarında anlamlı hata bilgisi üretiliyor.

12. Büyük ürün listeleri için chunk mantığı eklendi.
    Bellek kullanımını kontrol altında tutmak için ürünler kuyruk halinde işleniyor.

13. Her batch loglanıyor.
    Kullanılan provider, deneme sayısı, başarı/hata durumu ve batch bilgileri kayıt altına alınıyor.

14. İşlem istatistikleri admin panelde gösteriliyor.
    Toplam batch, API isteği, retry sayısı, beklenen süre ve kullanılan provider bilgileri özetleniyor.

15. Tekli mod da korunarak geliştirildi.
    Tek ürün işlendiğinde daha detaylı tekil prompt yapısı kullanılmaya devam ediyor.

Değişen dosyalar
----------------

- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/includes/services/class-ai-provider.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/includes/services/class-title-ai-update-service.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/includes/class-settings.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/includes/services/class-settings-service.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/admin/tabs/tab-settings.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/admin/tabs/tab-title-ai-update.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/trendyol-woocommerce-importer.php
- /home/runner/work/bot-isleri/bot-isleri/trendyol-woocommerce-importer/readme.txt
