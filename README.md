# TrustFlowPay Payment Gateway for WooCommerce

## Overview

This plugin integrates TrustFlowPay's PGH Checkout API with WooCommerce, supporting both **redirect** and **inline iframe** payment modes with proper hash validation and callback handling.

## Key Features

- ✅ **Two Integration Modes**:
  - **Redirect Mode**: Full-page redirect to TrustFlowPay hosted checkout
  - **Iframe Mode**: Embedded payment form on your site

- ✅ **Complete Payment Flow**:
  - Return URL handling
  - Webhook/callback support
  - Status Enquiry API integration
  - Proper order status updates

- ✅ **Security**:
  - SHA-512 hash validation
  - Exact parameter matching for hash generation
  - Secure credential storage

- ✅ **Pre-configured Sandbox**:
  - Ready-to-use test credentials
  - Support for TrustFlowPay test cards

## Installation

1. Copy the `trustflowpay-gateway` folder to `wp-content/plugins/`
2. Activate the plugin in WordPress admin
3. Navigate to **WooCommerce > Settings > Payments**
4. Click **Manage** next to "TrustFlowPay"
5. Configure your settings

## Configuration

### Basic Settings

- **Enable/Disable**: Turn the gateway on or off
- **Title**: What customers see at checkout (e.g., "Credit Card / Debit Card")
- **Description**: Payment method description shown to customers
- **Test Mode**: Enable for sandbox testing, disable for production
- **Checkout Display Mode**:
  - **Redirect**: Customer is redirected to TrustFlowPay's hosted page
  - **Iframe**: Payment form appears embedded on your site
- **Order Success Status**: Set to "Processing" or "Completed" for successful payments

### Sandbox Settings (Pre-configured)

Default sandbox credentials are already filled in:

- **Base URL**: `https://sandbox.trustflowpay.com/`
- **APP_ID**: `1107251111162944`
- **Secret Key**: `2be3c4b527d14c12`
- **Currency Code**: `840` (USD)

You can modify these if TrustFlowPay provides different sandbox credentials.

### Production Settings

When ready to go live:

1. Set **Test Mode** to unchecked
2. Enter your production credentials provided by TrustFlowPay:
   - Production Base URL
   - Production APP_ID
   - Production Secret Key
   - Production Currency Code

### Webhook & Return URLs

The plugin displays your Return URL and Callback URL in the settings. Copy these URLs and provide them to TrustFlowPay support for configuration:

- **Return URL**: Where customers are redirected after payment
- **Callback URL**: Where TrustFlowPay sends webhook notifications

Example URLs:
```
Return URL: https://yoursite.com/?wc-api=wc_gateway_trustflowpay_return
Callback URL: https://yoursite.com/?wc-api=wc_gateway_trustflowpay_callback
```

## Testing with Sandbox

### Test Cards (from TrustFlowPay sandbox sheet)

Use these test card numbers in sandbox mode:

- **Card Number**: `4111110000000211`
- **CVV**: `123`
- **Expiry**: `12/2030`
- **OTP/PIN**: `111111` (if prompted)

### Test Flow

1. Create a test order on your site
2. Select TrustFlowPay as payment method
3. Complete checkout
4. Enter test card details
5. Complete any 3DS/OTP challenge if prompted
6. You should be redirected back to your site
7. Order should update from "Pending payment" to "Processing" or "Completed"

### Manual Status Check

If an order is stuck in "Pending payment":

1. Go to **WooCommerce > Orders**
2. Find the order
3. Hover over the order row
4. Click **Check TrustFlowPay Status** action
5. The plugin will query TrustFlowPay and update the order status

## What Was Fixed

This version addresses critical issues that prevented successful payment processing:

### 1. **Hash Validation Issue** ✅ FIXED

**Problem**: The original code generated a hash for checkout parameters, but then added `BROWSER_*` fields (user-agent, screen size, etc.) to the form via JavaScript AFTER hash generation. TrustFlowPay received these extra fields and recomputed the hash including them, causing a hash mismatch.

**Solution**: Removed all `BROWSER_*` fields from the checkout form. The hash is now generated for exactly the parameters that are sent to TrustFlowPay.

### 2. **Return URL Handler** ✅ FIXED

**Problem**: The return URL handler wasn't properly parsing TrustFlowPay's response or validating the hash correctly.

**Solution**:
- Now correctly reads both POST and GET parameters
- Removes WordPress routing parameter (`wc-api`) before hash validation
- Validates hash using exact same logic as generation
- Properly handles cases where HASH may or may not be present
- Comprehensive logging for debugging

### 3. **Webhook Handler** ✅ FIXED

**Problem**: TrustFlowPay can send webhook data in two formats (array or single object), and the original code didn't handle both.

**Solution**:
- Now handles both `[{...txn data...}, {...API_RESPONSE...}]` array format
- And single object `{...txn data...}` format
- Validates hash on the transaction object
- Comprehensive error handling and logging

### 4. **Status Enquiry** ✅ FIXED

**Problem**: Similar format handling issues as webhook.

**Solution**:
- Handles both response formats
- Generates correct hash for status enquiry request (JSON format)
- Validates hash on response
- Can be triggered manually from order actions

### 5. **Success Criteria** ✅ FIXED

**Problem**: Original code may have accepted partial success statuses.

**Solution**: Now strictly enforces TrustFlowPay's rule:
- **ONLY** `RESPONSE_CODE = "000"` **AND** `STATUS = "Captured"` = Success
- `STATUS = "Enrolled"`, `"Pending"`, `"Timeout"` = On-Hold
- Anything else = Failed

### 6. **Iframe Integration** ✅ NEW FEATURE

**Added**: New checkout display mode for inline payment form:
- Loads TrustFlowPay's `checkout.min.css` and `checkout.min.js`
- Renders payment form in an iframe on your site
- Better user experience (no full-page redirect)
- Same security as redirect mode

### 7. **Logging & Debugging** ✅ ENHANCED

**Added comprehensive logging**:
- Hash generation details
- Request/response parameters (keys only, no secrets)
- Validation success/failure
- Order status updates
- Error conditions

View logs at: **WooCommerce > Status > Logs** (select "trustflowpay" source)

## How It Works

### Payment Flow

1. **Customer initiates payment**:
   - Selects TrustFlowPay at checkout
   - Clicks "Place Order"

2. **Plugin prepares request**:
   - Generates unique `ORDER_ID` (e.g., `TFP-123-1234567890`)
   - Converts amount to minor units (e.g., $10.00 → 1000)
   - Builds parameter array with customer and order data
   - Generates SHA-512 hash of ALL parameters
   - Stores parameters in order meta

3. **Customer is sent to payment page**:
   - **Redirect mode**: Full-page redirect to TrustFlowPay
   - **Iframe mode**: Payment form loads in iframe on your site

4. **Customer completes payment**:
   - Enters card details on TrustFlowPay's secure form
   - Completes 3DS authentication if required
   - TrustFlowPay processes the payment

5. **TrustFlowPay sends response**:
   - **Return URL**: Customer is redirected back with payment result
   - **Webhook**: TrustFlowPay posts payment result to callback URL

6. **Plugin processes response**:
   - Validates hash signature
   - Checks `RESPONSE_CODE` and `STATUS`
   - Updates order status:
     - Success (`000` + `Captured`) → Processing/Completed
     - Pending → On-Hold
     - Failed → Failed
   - Stores transaction details in order meta

7. **Customer sees result**:
   - Success: Redirected to "Thank You" page
   - Failure: Returned to checkout with error message

### Hash Generation Algorithm

```php
// 1. Take all parameters EXCEPT 'HASH'
$params = [
    'APP_ID' => '1107251111162944',
    'ORDER_ID' => 'TFP-123-1234567890',
    'AMOUNT' => '1000',
    // ... all other params
];

// 2. Sort by key name (ascending)
ksort($params);

// 3. Build string: KEY1=VALUE1~KEY2=VALUE2~...
$string = 'AMOUNT=1000~APP_ID=1107251111162944~...';

// 4. Append secret key in LOWERCASE (no separator)
$string .= strtolower($secret_key); // e.g., '2be3c4b527d14c12'

// 5. SHA-512 hash and UPPERCASE
$hash = strtoupper(hash('sha512', $string));

// 6. Send HASH with request
$params['HASH'] = $hash;
```

**Critical**: The parameters used to generate the hash MUST exactly match the parameters sent in the request. This is why BROWSER_* fields were removed.

## Troubleshooting

### Orders Stay "Pending Payment"

**Check**:
1. Enable **Debug Log** in settings
2. View logs at **WooCommerce > Status > Logs**
3. Look for hash validation errors or response issues

**Common causes**:
- Return URL or Callback URL not configured with TrustFlowPay
- Hash validation failing (check credentials match)
- TrustFlowPay not sending response

**Solution**:
- Use **Check TrustFlowPay Status** button to manually query status
- Verify URLs are correctly provided to TrustFlowPay support
- Check sandbox credentials are correct

### Hash Validation Fails

**Check**:
- APP_ID matches exactly (no extra spaces or characters)
- Secret Key matches exactly (case-sensitive)
- Currency Code is correct (840 for USD)

**Note**: The Excel sheet shows APP_ID with a leading backtick (`1107251111162944`). This is an Excel formatting character - use only the numeric value: `1107251111162944`

### Webhook Not Received

**Possible causes**:
- TrustFlowPay hasn't configured your Callback URL
- Your server blocks incoming POST requests
- SSL/HTTPS issues

**Solution**:
- Contact TrustFlowPay support to verify webhook configuration
- Test with Return URL (which always works)
- Check server logs for blocked requests

### Amount Mismatch

**Issue**: TrustFlowPay expects amounts in **minor units** (cents, not dollars).

**Example**:
- Order total: $10.50
- Sent to TrustFlowPay: `1050` (not `10.50`)

The plugin handles this automatically. If you see amount errors, check the currency configuration.

## Developer Notes

### File Structure

```
trustflowpay-gateway/
├── trustflowpay-gateway.php          # Main plugin file
├── includes/
│   └── class-wc-gateway-trustflowpay.php  # Gateway class
└── README.md                          # This file
```

### Hooks & Filters

The plugin registers these WooCommerce API endpoints:

- `wc_gateway_trustflowpay_redirect` - Payment handoff page
- `wc_gateway_trustflowpay_return` - Return URL handler
- `wc_gateway_trustflowpay_callback` - Webhook handler

### Order Meta Fields

The plugin stores these meta fields in each order:

- `_trustflowpay_order_id` - Internal ORDER_ID (e.g., TFP-123-1234567890)
- `_trustflowpay_app_id` - APP_ID used for the transaction
- `_trustflowpay_request_params` - JSON of checkout request parameters
- `_trustflowpay_response_code` - RESPONSE_CODE from TrustFlowPay
- `_trustflowpay_status` - STATUS from TrustFlowPay
- `_trustflowpay_txn_id` - TXN_ID (TrustFlowPay transaction ID)
- `_trustflowpay_pg_ref_num` - PG_REF_NUM (payment gateway reference)
- `_trustflowpay_response_message` - RESPONSE_MESSAGE
- `_trustflowpay_full_response` - Complete JSON response

### Extending the Plugin

To customize behavior, you can:

1. **Filter order success status**:
```php
add_filter('woocommerce_payment_complete_order_status', function($status, $order_id, $order) {
    if ($order->get_payment_method() === 'trustflowpay') {
        return 'completed'; // Force to completed
    }
    return $status;
}, 10, 3);
```

2. **Add custom order notes**:
```php
add_action('woocommerce_order_status_processing', function($order_id) {
    $order = wc_get_order($order_id);
    if ($order->get_payment_method() === 'trustflowpay') {
        $order->add_order_note('Custom note for TrustFlowPay orders');
    }
});
```

## Support

### Documentation

- TrustFlowPay PGH API: Contact TrustFlowPay for latest integration docs
- WooCommerce Payment Gateway API: https://woocommerce.com/document/payment-gateway-api/

### Logging

Always enable **Debug Log** when troubleshooting. Logs include:
- Hash generation details
- Request/response data (keys only)
- Validation results
- Error messages

Logs are sanitized and do NOT include:
- Secret keys
- Card numbers
- CVV codes
- Customer personal data (only field names)

## Changelog

### Version 1.1.0
- ✅ Fixed hash validation (removed BROWSER_* fields)
- ✅ Fixed return URL handler
- ✅ Fixed webhook handler (supports array and object responses)
- ✅ Fixed status enquiry (supports both response formats)
- ✅ Added iframe integration mode
- ✅ Enhanced logging and debugging
- ✅ Strict success criteria (000 + Captured only)
- ✅ Pre-configured sandbox credentials

### Version 1.0.0
- Initial release

## License

This plugin is provided as-is for integration with TrustFlowPay payment gateway.

## Credits

Developed for TrustFlowPay PGH Checkout API integration with WooCommerce.
