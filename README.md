# QvaPay for WooCommerce

Pasarela de pago oficial de [QvaPay](https://www.qvapay.com) para tiendas WooCommerce. Permite a tus clientes pagar en USD usando su balance de QvaPay.

## Requisitos

- WordPress 6.0+
- WooCommerce 7.0+
- PHP 7.4+
- Moneda de la tienda en **USD** (la API de QvaPay opera únicamente en USD).
- Sitio servido por **HTTPS** (necesario para que QvaPay pueda entregar webhooks).

## Instalación

1. Descarga el ZIP desde *Releases*.
2. En WordPress: *Plugins → Añadir nuevo → Subir plugin*.
3. Sube el ZIP y actívalo.
4. Crea una app en `https://qvapay.com/dev/app`.
   - **Callback URL** de la app: `https://TU-DOMINIO/wp-json/qvapay/v1/webhook`
5. En *WooCommerce → Settings → Payments → QvaPay*, pega `app-id` y `app-secret`.

## Cómo funciona

1. El cliente elige **QvaPay** en el checkout de WooCommerce.
2. El plugin crea una factura vía `POST /api/v2/create_invoice` y redirige al cliente a `https://www.qvapay.com/pay/{transaction_uuid}`.
3. El cliente paga con su balance de QvaPay.
4. QvaPay envía un webhook firmado con HMAC-SHA256 al endpoint `/wp-json/qvapay/v1/webhook`.
5. El plugin verifica la firma y marca el pedido como pagado.

## Seguridad

- Verificación de firma `x-qvapay-signature` con `hash_equals` (comparación timing-safe).
- Validación de timestamp `x-qvapay-timestamp` con ventana de ±5 minutos (anti-replay).
- Idempotencia: pedidos ya marcados como `processing`/`completed` no se reprocesan.

## Alcance v1

Solo flujo de **invoice + redirect**. No incluye:

- Reembolsos (la API de QvaPay no expone refund endpoint).
- Suscripciones recurrentes (`authorize_payments` / `charge`).
- Entorno sandbox.

## Licencia

GPL-2.0-or-later. Ver [LICENSE](LICENSE).
