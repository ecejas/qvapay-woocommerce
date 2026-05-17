=== QvaPay for WooCommerce ===
Contributors: qvapay
Tags: woocommerce, payment gateway, qvapay, usd, payments
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Acepta pagos en USD a través de QvaPay como pasarela oficial de WooCommerce.

== Description ==

Plugin oficial de [QvaPay](https://qvapay.com) que añade QvaPay como método de pago en el checkout de WooCommerce.

Flujo:

1. El cliente elige **QvaPay** en el checkout.
2. El plugin crea una factura vía la API de merchants y redirige al cliente a `https://www.qvapay.com/pay/{transaction_uuid}`.
3. El cliente paga con su balance de QvaPay.
4. QvaPay notifica el pago mediante un webhook firmado con HMAC-SHA256.
5. El pedido pasa automáticamente a estado *Processing*.

= Características =

* Integración directa con la API v2 de merchants de QvaPay.
* Verificación criptográfica del webhook (HMAC-SHA256) con ventana anti-replay.
* Idempotencia: pedidos ya procesados no se duplican aunque QvaPay reintente.
* Compatibilidad declarada con WooCommerce HPOS (Custom Order Tables).
* Modo debug que registra peticiones y webhooks en *WooCommerce → Status → Logs*.

= Requisitos =

* WooCommerce 7.0+
* WordPress 6.0+
* PHP 7.4+
* Moneda de la tienda en USD
* Sitio servido por HTTPS (para recibir webhooks)

== Installation ==

1. Sube el plugin a `/wp-content/plugins/qvapay-for-woocommerce/` o instálalo desde el panel de WordPress.
2. Activa el plugin desde *Plugins*.
3. Crea una app en `https://qvapay.com/dev/app` y copia `app-id` y `app-secret`.
4. Ve a *WooCommerce → Settings → Payments → QvaPay* y pega las credenciales.
5. Copia el campo *URL de webhook* del plugin al campo *Callback URL* de tu app en QvaPay.

== Frequently Asked Questions ==

= ¿En qué monedas funciona? =

Solo USD. La API de QvaPay opera exclusivamente en dólares estadounidenses.

= ¿Soporta reembolsos? =

No en esta versión. QvaPay aún no expone un endpoint público de reembolsos.

= ¿Soporta suscripciones recurrentes? =

No en esta versión. Está en el roadmap usando `authorize_payments` + `charge`.

= ¿Necesito HTTPS? =

Sí. QvaPay solo entrega webhooks a endpoints HTTPS.

== Changelog ==

= 1.0.0 =
* Lanzamiento inicial: gateway WooCommerce con flujo invoice + redirect, webhook firmado y verificación HMAC.
