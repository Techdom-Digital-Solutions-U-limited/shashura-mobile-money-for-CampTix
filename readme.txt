=== Shashura Mobile Money for CampTix ===
Contributors: cipherwavesoftware, ssebuwufumoses
Tags: camptix, mobile money, mtn, airtel, uganda
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept MTN and Airtel Mobile Money for CampTix ticket sales in Uganda, through the Shashura payment gateway.

== Description ==

Shashura Mobile Money for CampTix adds MTN and Airtel Mobile Money as a payment option for CampTix, the WordPress event ticketing plugin used by WordCamp and community events. It's built for organisers selling tickets in Uganda who need a payment method their attendees already use day to day.

A Shashura merchant account is required to use this plugin. Sign up or log in at [shashura.com](https://shashura.com).

The plugin connects CampTix to the Shashura Mobile Money API and handles the full collection lifecycle: number verification, the PIN push to the buyer's phone, settlement confirmation, and reconciliation. It runs against either Shashura's Sandbox or Production environment, and the API endpoint is configurable.

Not affiliated with, or endorsed by, the CampTix project.

= How a payment works =

1. The buyer selects Mobile Money at checkout and enters their MTN or Airtel number.
2. The number is verified against the network before anything is charged, and the registered account name is shown to the buyer for confirmation.
3. A PIN prompt is pushed to the buyer's phone for the exact order total.
4. The plugin confirms the payment with Shashura directly rather than trusting the browser or an incoming webhook on their own. Both only trigger a check; only a status enquiry whose reference and amount match the order can mark it paid.
5. Once confirmed, the buyer is taken to their CampTix ticket page and receives CampTix's standard confirmation email.

= Reliability and safety =

* Every payment prompt on an order is tracked and reconciled, including ones approved after a buyer tried more than once.
* A duplicate payment on the same order is detected and flagged to the site administrator for a manual refund.
* Incoming webhooks are verified against an HMAC-SHA256 signature. Unsigned, forged, oversized or mismatched-environment events are rejected.
* A per-order lock stops the webhook, the buyer's browser, an order cancellation and CampTix's timeout sweep from recording conflicting outcomes for the same order.
* A payment approved after an order was cancelled or timed out is still recognised and completes the order.
* Works with CampTix 2.x and CampTix 1.4.x.

= Requirements =

* CampTix, with the ticket currency set to UGX.
* A Shashura merchant account with an app configured for Mobile Money collections, and its Client ID, Client Secret and Webhook Secret.
* A publicly reachable site, with the server's outbound IP whitelisted on the Shashura app.

== External services ==

This plugin connects to the **Shashura Mobile Money API** to collect ticket payments. It is required: without it the plugin cannot verify mobile numbers, push PIN prompts or confirm payment.

What is sent, and when:

* **Number verification:** when a buyer enters a mobile number at checkout, that number is sent to Shashura to look up the registered account name on the MTN/Airtel network, shown to the buyer for confirmation before anything is charged.
* **Payment request:** once the buyer confirms, the mobile number, the order total and an order reference are sent to Shashura to push a PIN prompt to the buyer's phone.
* **Status confirmation:** after a prompt is sent, the plugin asks Shashura whether the referenced payment succeeded. Shashura also notifies the plugin through a signed webhook.
* **Saving the settings:** the Client ID, Client Secret and environment are sent to Shashura to test the connection.

No card numbers, mobile money PINs or other site data are sent. Buyer data is only sent when a buyer actually pays with Mobile Money.

This service is provided by Shashura (operated by Techdom Digital Solutions): [Terms of Service](https://shashura.com/terms), [Privacy Policy](https://shashura.com/privacy).

== Installation ==

1. Install and activate CampTix. Under Tickets → Setup → General, set the ticket currency to UGX.
2. Install and activate this plugin.
3. Go to Tickets → Setup → Payment and open the Mobile Money (MTN / Airtel) section:
    * Set Enabled to Yes.
    * Enter the Client ID and Client Secret from your Shashura app.
    * Choose Sandbox while testing. Switch to Production only when ready to take real payments.
    * Enter the Webhook Secret shown on your Shashura app. Mobile Money is not offered at checkout until it is set.
    * Leave API Base URL blank to use Shashura's default endpoint.
4. Copy the webhook URL shown under Webhook Secret and set it as the Callback URL on the matching app in your Shashura dashboard.
5. Ask Shashura to whitelist your server's outbound IP address for that app.
6. Save. The Connection line confirms whether the credentials work.

= Getting your Shashura API credentials =

1. Log in to your Shashura merchant dashboard at [shashura.com](https://shashura.com) and open the Apps page.
2. Click Create App. Shashura generates a Sandbox credential pair, a Production credential pair and a Webhook Signing Secret for the app.
3. Sandbox Client IDs begin with `TID_`, Production Client IDs with `PID_`. Use the pair that matches the Environment setting in this plugin.
4. Reveal the Webhook Signing Secret (begins with `whsec_`) and copy it into the Webhook Secret field.
5. Paste the webhook URL this plugin shows into the Callback URL field for the same app.

Regenerating a Client Secret invalidates the previous one immediately, so update the plugin settings straight after.

== Frequently Asked Questions ==

= Which currencies are supported? =

Uganda Shillings (UGX) only.

= Does this plugin process refunds? =

Not automatically. The Shashura API has no refund endpoint yet. Issue the refund from the Shashura dashboard, then mark the attendee as refunded in CampTix.

= What happens if a buyer closes the browser tab after approving the PIN prompt? =

The payment still settles. Shashura's webhook, and CampTix's timeout check on CampTix 2.x, both ask Shashura directly whether the order was paid.

= Can I switch between Sandbox and Production while orders are still open? =

Avoid it. A prompt sent under one environment cannot be checked while the plugin is set to the other. It stays waiting, never shown as failed, until the environment is switched back.

= Where do I see what happened on a specific order? =

Open the attendee under Tickets → Attendees. The CampTix log on that screen records every check, error, amount mismatch or duplicate-payment warning.

== Privacy ==

Against each paid order, this plugin stores the payer's registered name, a masked mobile number, the mobile network and the Shashura transaction reference, for reconciliation and support. These records are included in WordPress's Tools → Export Personal Data and Erase Personal Data. The plugin never handles or stores card numbers, mobile money PINs or Shashura account credentials.

== Screenshots ==

1. Payment settings under Tickets → Setup → Payment: credentials, environment and connection status.
2. Buyer checkout: entering a number, confirming the account, approving on the phone and the payment receipt.
3. The PIN-approval step with its countdown.

== Changelog ==

= 1.0.2 =
* Changes for the WordPress.org plugin guidelines.

= 1.0.0 =
* Initial release.
