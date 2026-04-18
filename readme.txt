=== Bulk Product Delete via Excel ===
Contributors:      yourname
Tags:              woocommerce, products, bulk delete, excel
Requires at least: 5.8
Tested up to:      6.5
Requires PHP:      7.4
Stable tag:        1.0.0
License:           GPLv2 or later

حذف منتجات WooCommerce بشكل جماعي عبر رفع ملف Excel.

== Description ==

يضيف هذا البرنامج المساعد زراً في صفحة المنتجات بلوحة تحكم WooCommerce
يتيح لك رفع ملف Excel (.xlsx / .xls) يحتوي على:
- أسماء المنتجات، أو
- رموز SKU

ثم يعرض قائمة بالمنتجات المطابقة قبل الحذف للمراجعة والتأكيد.

**المميزات:**
* لا يتطلب أي مكتبات خارجية — يعمل بـ PHP النقية
* دعم .xlsx و .xls
* يتيح اختيار رقم العمود في ملف Excel
* معاينة كاملة قبل الحذف (أسماء + SKU)
* يعرض القيم غير الموجودة
* حذف نهائي مع تنظيف cache WooCommerce
* واجهة عربية بالكامل

== Installation ==

1. ارفع مجلد `bulk-product-delete` إلى `/wp-content/plugins/`
2. فعّل الإضافة من لوحة التحكم › إضافات
3. انتقل إلى WooCommerce › المنتجات
4. ستجد زر "حذف منتجات عبر Excel" في أعلى الصفحة

== Frequently Asked Questions ==

= هل الحذف نهائي؟ =
نعم، يتم الحذف النهائي (force delete) متجاوزاً سلة المهملات.

= ما الحد الأقصى لحجم الملف؟ =
يعتمد على إعداد `upload_max_filesize` في PHP. الافتراضي 10 ميغابايت.

= هل يدعم المتغيرات (variations)؟ =
عند الحذف بالاسم: يبحث في المنتجات الرئيسية فقط.
عند الحذف بـ SKU: يدعم المتغيرات لأن كل variation لها SKU مستقل.

== Changelog ==

= 1.0.0 =
* الإصدار الأول
