# ELC — order & email workflow backlog

Raised 2026-10-03 from a review of the order placement flow, the admin order
process, the order statuses and the mail layer.

**How this was produced.** Code review of `C:\xampp\htdocs\elc` plus queries against a
local copy of the `ausdev` database (dump of 2026-08-21). **No end-to-end order was
placed and no email was sent**, because that needs an admin login and a Mailtrap
login that this review could not perform. Every item is marked:

- **Confirmed** — reproduced directly against the code or the database.
- **Code review** — read in the source but not exercised at runtime; verify before fixing.

Order data in this database stops in January 2026 (apart from abandoned carts up to
July 2026), so runtime behaviour must be re-checked against production.

---

## P1 — blocks or corrupts the order process

### 1. Every PayHere outcome is recorded as "Pending"
**Confirmed** · `oc_setting`

```
payment_payhere_order_status_id      = 1  (Pending)   <- successful payment
payment_payhere_pending_status_id    = 1  (Pending)
payment_payhere_failed_status_id     = 1  (Pending)   <- failed
payment_payhere_canceled_status_id   = 1  (Pending)   <- cancelled
payment_payhere_chargeback_status_id = 1  (Pending)   <- chargeback
```

Paid, failed, cancelled and charged-back orders all land in the same bucket, so staff
cannot tell which orders were actually paid. Consistent with the data: Failed, Denied,
Chargeback, Refunded and Shipped have **zero** orders in the whole table.

Fix: map success → Processing, failed → Failed (10), cancelled → Canceled (7),
chargeback → Chargeback (13).

### 2. No transactional email can be delivered as configured
**Confirmed** · `oc_setting`

```
config_mail_engine        = mail              <- PHP mail(), not SMTP
config_mail_smtp_hostname = mail.hello@elc.lk <- an email address, not a host
config_email              = malindarajith@gmail.com
```

Three separate faults:

1. The engine is `mail`, so **every SMTP setting is ignored and Mailtrap receives
   nothing**. Switching the engine to `smtp` is the prerequisite for any mail test.
2. The SMTP hostname is an email address. Switching to `smtp` without fixing this
   makes things worse — see item 3.
3. The From address is a `gmail.com` address sent from the store's own server, so SPF
   and DMARC fail at the recipient. Order mail will be spam-foldered or rejected.
   Use an address on a domain the server is authorised to send for.

### 3. A mail failure aborts the order status change
**Code review** · `catalog/controller/mail/order.php:357,466,615` ·
`catalog/model/checkout/order.php:979-981`

`mail/order` is bound to `catalog/model/checkout/order.addHistory/**before**`
(confirmed in `oc_event`), and the send has no `try`/`catch`. The status write and the
`oc_order_history` insert happen *after* it. An SMTP exception therefore leaves a paid
order at `order_status_id = 0`, which the admin order list hides
(`admin/model/sale/order.php` filters `order_status_id > 0`).

This is why item 2 must be fixed carefully: pointing the engine at a bad SMTP host
would convert "no emails" into "no orders".

There are already **10,446 orders at status 0** in this database. Worth checking how
many are genuine abandoned carts and how many are this failure.

### 4. Back-in-stock notifications never fire
**Confirmed** · `oc_event` rows for `stock_notify`

```
registered: admin/model/catalog/product/editProduct/before
core style: admin/model/customer/customer_approval.approveAffiliate/after
                                                 ^ dot, not slash
```

The trigger uses a slash where the dispatcher emits a dot before the method, so the
prefix match never succeeds. Restocking a product notifies nobody — and that event is
the feature's only trigger.

Note `extension/stock_notify/admin/controller/event/product.php` instantiates a
`Opencart\Catalog\...` class from the admin application, which is not autoloadable
there — so fixing the trigger alone will raise "class not found". Both need fixing
together. (**Code review** for the second part.)

### 5. Subscribers are marked notified even when the mail fails
**Code review** · `extension/stock_notify/catalog/model/module/stock_notify.php:152-153,253-255`

`$mail->send()`'s return value is discarded, then `markNotified()` runs. With the
current `mail` engine returning `false` on failure, subscribers are permanently
consumed without ever receiving an email.

---

## P2 — wrong data, silent failures, security

### 6. PayHere callback accepts unsigned requests when no secret is set
**Confirmed (code)** · not currently exploitable ·
`extension/payhere/catalog/controller/payment/payhere.php:232-259`

`$verified = true;` is the default and the signature check is wrapped in
`if ($secret)`. The admin save routine never requires the secret.

`payment_payhere_secret` **is currently set** (40 chars), so this is latent rather than
live. It becomes exploitable the moment the field is cleared — anyone could then POST
`order_id` + `status_code=2` and have the shop mark the order paid. Default `$verified`
to `false` and reject when the secret is unconfigured.

### 7. Successful PayHere payments store no transaction reference
**Code review** · `payhere.php:285,327`

The history comment is only built for the failure branch, and
`editTransactionId()` is never called. Every successful order gets a blank history row
and an empty `oc_order.transaction_id`, leaving staff nothing to reconcile or refund
against. Failed payments, ironically, do carry detail.

### 8. The token API is unusable
**Confirmed** · database

`oc_api_session` **does not exist** in the database, though
`catalog/model/setting/api.php` queries it on every `api/*` request carrying a token.
`oc_api_ip` also has **0 rows** while the same model requires an IP match. Any external
integration or gateway callback using token auth fails with a MySQL "table doesn't
exist" error.

### 9. Admin order AJAX may be redirected by the store selector
**Code review — unverified, test first** ·
`extension/store_selector/catalog/controller/startup/store_selector.php`

The admin→catalog bridge builds a store instance whose request has no cookies and whose
route is not yet `api/order` when the startup actions run, so the store selector's
`api/` bypass may not apply and it may issue a redirect instead of returning JSON. The
"Add History" button's only error handler is `console.log`, so this would present as
"the button does nothing".

**The order history data neither confirms nor refutes this** — this database has no
order history after January 2026, while the store selector shipped in July 2026. Test
on a live order before acting on it.

### 10. Success page is not gated on payment
**Code review** · `catalog/controller/checkout/success.php:17-29` ·
`extension/payhere/catalog/view/template/payment/payhere_pay.twig:76-78`

The success page checks only that a session order id exists, never the order's status.
The onsite PayHere widget redirects there from `onCompleted`, which fires when the
payment window closes rather than only on approval. A declined card can therefore show
"Your order has been placed" and clear the cart.

### 11. Onsite PayHere sends sandbox as a truthy string
**Code review** · `payhere_pay.twig:42`

`sandbox: "{{ sandbox ? 'true' : 'false' }}"` emits the JavaScript string `"false"` in
live mode, which is truthy. With onsite checkout enabled and test mode off, the
embedded form would run against the sandbox with live merchant credentials.

### 12. Customer custom fields are lost in the one-page checkout
**Code review** · `extension/ajax_quick_checkout/.../order.php:492` vs
`catalog/model/checkout/order.php:355`

Written with `serialize()`, read back with `json_decode()`, which yields `null`. Every
custom field captured at checkout is empty in the admin order view and in the order
email.

### 13. The one-page checkout bypasses the core order model
**Code review** · `extension/ajax_quick_checkout/.../order.php:33-153,197-321`

Raw SQL against `oc_order` instead of `addOrder()`/`editOrder()`, so no
`addOrder`/`editOrder` events fire — any analytics, ERP or courier integration hooked
to them is dead on this path. It also rewrites `date_added` on every AJAX update, which
corrupts date-ordered admin lists.

### 14. PHP errors are displayed to visitors
**Confirmed** · `config_error_display = 1`

Warnings and traces are rendered into pages and into AJAX responses, which both leaks
server paths and corrupts JSON payloads. Turn off on any public host; keep
`config_error_log` on.

---

## P3 — correctness, hygiene

### 15. `$thid` typo disables affiliate validation
**Confirmed** · `catalog/controller/api/order.php:418`

`isset($thid->request->post['affiliate_id'])` — the branch is dead, so commission
silently resolves to 0.

### 16. Contact form reports success when the email failed
**Code review** · `extension/contact_stores/catalog/model/contact.php:150-159`

The send result is discarded and `true` is returned unconditionally, and there is no
`try`/`catch`. Customers are told "message sent" for enquiries that were never
delivered — and an SMTP exception loses the message with no log of its contents.

### 17. Unused order statuses
**Confirmed** · `oc_order`

Shipped, Denied, Canceled Reversal, Failed, Refunded, Reversed, Chargeback, Expired and
Voided have zero orders. Partly a consequence of item 1. Decide which statuses the
business actually uses and remove or start using the rest — an unused status list makes
the admin dropdown misleading.

### 18. SMTP adaptor robustness
**Code review** · `system/library/mail/smtp.php`

Relevant once the engine is switched to `smtp`: `fsockopen()` is not error-suppressed
(line 143) so a warning is echoed into responses; no `QUIT` is sent and the socket
leaks on any exception (line 254); `EHLO` uses `getenv('SERVER_NAME')`, which is empty
under cron, so cron mail can be rejected while web mail works.

### 19. `array +` instead of `array_merge` for status lists
**Confirmed — no current impact** ·
`catalog/model/checkout/order.php:800,833,926`

`config_processing_status + config_complete_status` uses PHP's array union, which drops
right-hand elements at duplicate integer keys rather than appending them.

With this store's current settings it changes nothing: processing is `["5","1","2","12","3"]`
and complete is `["5","3"]`, so both complete statuses are already present. **It becomes
a live bug the moment a complete status is set that is not also in the processing
list** — stock would not be decremented and coupons would stay reusable. Worth changing
to `array_merge` while it is cheap.

---

## Cannot be tested without access

These need a login this review could not perform:

1. **Place a real order end to end** and watch each status transition.
2. **Confirm what Mailtrap actually receives** — nothing can arrive until item 2 is fixed.
3. **Exercise the admin status workflow** (item 9 in particular).

To unblock: point the store at the Mailtrap sandbox (Settings → Mail → Mail Engine =
SMTP, with the host, port, username and password from the Mailtrap sandbox page), then
place a test order and walk it through each status.
