# ABS Loja Protheus Connector - API Documentation

## Overview

This document describes the REST API endpoints provided by the ABS Loja Protheus Connector plugin for receiving webhooks from Protheus ERP.

## Base URL

```
https://your-wordpress-site.com/wp-json/absloja-protheus/v1
```

## Authentication

All webhook endpoints require authentication using a token in the request header:

```
X-Protheus-Token: your_webhook_token_here
```

The webhook token is configured in the plugin settings under **Advanced** tab.

### Alternative Authentication: HMAC Signature

You can also use HMAC-SHA256 signature for authentication:

```
X-Protheus-Signature: sha256=<hmac_signature>
```

The signature is calculated as:
```
HMAC-SHA256(webhook_secret, request_body)
```

## Endpoints

### 1. Order Status Update

Updates the status of a WooCommerce order based on Protheus order status.

**Endpoint:** `POST /webhook/order-status`

**Headers:**
```
Content-Type: application/json
X-Protheus-Token: your_webhook_token
```

**Request Body:**
```json
{
  "order_id": "123456",
  "woo_order_id": "789",
  "status": "approved",
  "tracking_code": "BR123456789",
  "invoice_number": "000123",
  "invoice_date": "2024-01-15",
  "invoice_key": "35240112345678901234567890123456789012345678"
}
```

**Request Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `order_id` | string | Yes | Protheus order number (C5_NUM) |
| `woo_order_id` | string | Yes | WooCommerce order ID (C5_PEDWOO) |
| `status` | string | Yes | Protheus order status |
| `tracking_code` | string | No | Shipping tracking code |
| `invoice_number` | string | No | Invoice number (nota fiscal) |
| `invoice_date` | string | No | Invoice date (YYYY-MM-DD) |
| `invoice_key` | string | No | Invoice access key (chave de acesso) |

**Status Mapping:**

| Protheus Status | WooCommerce Status |
|----------------|-------------------|
| `pending` | `pending` |
| `approved` | `processing` |
| `invoiced` | `completed` |
| `shipped` | `completed` |
| `cancelled` | `cancelled` |
| `rejected` | `failed` |

**Success Response:**
```json
{
  "success": true,
  "message": "Order status updated successfully",
  "order_id": 789,
  "new_status": "completed"
}
```

**HTTP Status:** `200 OK`

**Error Responses:**

**401 Unauthorized** - Invalid or missing authentication token
```json
{
  "code": "unauthorized",
  "message": "Invalid authentication token",
  "data": {
    "status": 401
  }
}
```

**404 Not Found** - Order not found
```json
{
  "code": "order_not_found",
  "message": "Order not found with ID: 789",
  "data": {
    "status": 404
  }
}
```

**400 Bad Request** - Invalid request data
```json
{
  "code": "invalid_request",
  "message": "Missing required field: woo_order_id",
  "data": {
    "status": 400
  }
}
```

### 2. Stock Update

Updates the stock quantity of a WooCommerce product.

**Endpoint:** `POST /webhook/stock`

**Headers:**
```
Content-Type: application/json
X-Protheus-Token: your_webhook_token
```

**Request Body:**
```json
{
  "sku": "PROD001",
  "quantity": 50,
  "warehouse": "01",
  "reserved": 5
}
```

**Request Fields:**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `sku` | string | Yes | Product SKU (B1_COD) |
| `quantity` | integer | Yes | Available stock quantity (B2_QATU) |
| `warehouse` | string | No | Warehouse code (B2_LOCAL) |
| `reserved` | integer | No | Reserved quantity |

**Success Response:**
```json
{
  "success": true,
  "message": "Stock updated successfully",
  "product_id": 123,
  "sku": "PROD001",
  "old_quantity": 30,
  "new_quantity": 50,
  "visibility_changed": false
}
```

**HTTP Status:** `200 OK`

**Visibility Rules:**
- If `quantity` = 0, product visibility is set to "hidden"
- If `quantity` > 0 and product was hidden, visibility is restored

**Error Responses:**

**401 Unauthorized** - Invalid or missing authentication token
```json
{
  "code": "unauthorized",
  "message": "Invalid authentication token",
  "data": {
    "status": 401
  }
}
```

**404 Not Found** - Product not found
```json
{
  "code": "product_not_found",
  "message": "Product not found with SKU: PROD001",
  "data": {
    "status": 404
  }
}
```

**400 Bad Request** - Invalid request data
```json
{
  "code": "invalid_request",
  "message": "Invalid quantity value",
  "data": {
    "status": 400
  }
}
```

## Webhook Testing

### Using cURL

**Test Order Status Update:**
```bash
curl -X POST \
  https://your-site.com/wp-json/absloja-protheus/v1/webhook/order-status \
  -H 'Content-Type: application/json' \
  -H 'X-Protheus-Token: your_token_here' \
  -d '{
    "order_id": "123456",
    "woo_order_id": "789",
    "status": "approved",
    "tracking_code": "BR123456789"
  }'
```

**Test Stock Update:**
```bash
curl -X POST \
  https://your-site.com/wp-json/absloja-protheus/v1/webhook/stock \
  -H 'Content-Type: application/json' \
  -H 'X-Protheus-Token: your_token_here' \
  -d '{
    "sku": "PROD001",
    "quantity": 50
  }'
```

### Using Postman

1. Create a new POST request
2. Set URL to webhook endpoint
3. Add header: `X-Protheus-Token: your_token`
4. Set body type to JSON
5. Add request body
6. Send request

## Protheus Integration

### Configuring Webhooks in Protheus

To send webhooks from Protheus to WordPress, you need to configure HTTP requests in your Protheus business logic.

**Example: Send Order Status Update**

```advpl
#Include "Protheus.ch"
#Include "RestFul.ch"

User Function SendOrderStatus(cOrderNum, cWooOrderId, cStatus)
    Local cUrl := "https://your-site.com/wp-json/absloja-protheus/v1/webhook/order-status"
    Local cToken := "your_webhook_token"
    Local cJson := ""
    Local oRest
    
    // Build JSON payload
    cJson := '{'
    cJson += '"order_id":"' + AllTrim(cOrderNum) + '",'
    cJson += '"woo_order_id":"' + AllTrim(cWooOrderId) + '",'
    cJson += '"status":"' + AllTrim(cStatus) + '"'
    cJson += '}'
    
    // Create REST client
    oRest := FWRest():New(cUrl)
    oRest:setPostParams(cJson)
    oRest:SetHeader("Content-Type", "application/json")
    oRest:SetHeader("X-Protheus-Token", cToken)
    
    // Send request
    If oRest:Post()
        ConOut("Order status sent successfully")
    Else
        ConOut("Error sending order status: " + oRest:GetLastError())
    EndIf
    
Return
```

**Example: Send Stock Update**

```advpl
User Function SendStockUpdate(cSku, nQuantity)
    Local cUrl := "https://your-site.com/wp-json/absloja-protheus/v1/webhook/stock"
    Local cToken := "your_webhook_token"
    Local cJson := ""
    Local oRest
    
    // Build JSON payload
    cJson := '{'
    cJson += '"sku":"' + AllTrim(cSku) + '",'
    cJson += '"quantity":' + cValToChar(nQuantity)
    cJson += '}'
    
    // Create REST client
    oRest := FWRest():New(cUrl)
    oRest:setPostParams(cJson)
    oRest:SetHeader("Content-Type", "application/json")
    oRest:SetHeader("X-Protheus-Token", cToken)
    
    // Send request
    If oRest:Post()
        ConOut("Stock update sent successfully")
    Else
        ConOut("Error sending stock update: " + oRest:GetLastError())
    EndIf
    
Return
```

## Logging

All webhook requests are automatically logged by the plugin. You can view logs in:

**WooCommerce > Protheus Connector > Logs**

Each log entry includes:
- Timestamp
- Endpoint called
- Request payload
- Response status
- Processing duration
- Any errors

## Security Best Practices

1. **Use HTTPS**: Always use HTTPS for webhook endpoints
2. **Rotate Tokens**: Regularly rotate webhook tokens
3. **IP Whitelist**: Configure firewall to only allow requests from Protheus server IP
4. **Monitor Logs**: Regularly check logs for suspicious activity
5. **Rate Limiting**: Consider implementing rate limiting for webhook endpoints

## Rate Limiting

The plugin does not implement rate limiting by default. If you need rate limiting, consider using:

- WordPress security plugins (e.g., Wordfence, Sucuri)
- Server-level rate limiting (nginx, Apache)
- Cloudflare or similar CDN with rate limiting

## Troubleshooting

### Webhook Returns 401 Unauthorized

**Cause:** Invalid or missing authentication token

**Solution:**
1. Verify the token in plugin settings (**Advanced** tab)
2. Ensure the `X-Protheus-Token` header is included in the request
3. Check for typos in the token

### Webhook Returns 404 Not Found

**Cause:** Order or product not found

**Solution:**
1. Verify the `woo_order_id` or `sku` is correct
2. Check if the order/product exists in WooCommerce
3. Review logs for more details

### Webhook Times Out

**Cause:** Server performance issues or large payload

**Solution:**
1. Check server resources (CPU, memory)
2. Optimize database queries
3. Increase PHP max_execution_time
4. Consider using a queue system for large batches

### Webhooks Not Being Received

**Cause:** Firewall blocking requests or incorrect URL

**Solution:**
1. Verify the webhook URL is correct
2. Check firewall rules on WordPress server
3. Test with cURL from Protheus server
4. Check WordPress permalink settings

## Support

For technical support or questions about the API, please contact:

**ABS Global**
- Website: https://absglobal.com.br
- Email: suporte@absglobal.com.br

## Changelog

### Version 1.0.0
- Initial release
- Order status update webhook
- Stock update webhook
- Token-based authentication
- HMAC signature authentication
