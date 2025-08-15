# Timeout Configuration

The NFSe Betha client now supports configurable timeouts for all HTTP operations. This allows you to adjust timeout values based on your specific needs without modifying the source code.

## Problem

By default, the client uses a 30-second timeout for all operations. When the NFSe service takes longer to respond (due to high load, network issues, or complex operations), requests may fail with timeout errors.

## Solution

The client provides several methods to configure timeouts:

### Quick Setup (Recommended)

Set all timeouts at once:

```php
$nfse = new NFSeBetha($certificateContent, $certificatePassword, $prestadorData);

// Set all timeouts to 60 seconds
$nfse->setTimeout(60);
```

### Individual Timeout Configuration

Configure specific timeouts individually:

```php
$nfse = new NFSeBetha($certificateContent, $certificatePassword, $prestadorData);

// Set cURL timeout for service requests (default: 30s)
$nfse->setCurlTimeout(90);

// Set SOAP timeout for service requests (default: 30s)
$nfse->setSoapTimeout(90);

// Set SOAP connection timeout (default: 30s)
$nfse->setSoapConnectionTimeout(90);

// Set test connection timeout (default: 5s)
$nfse->setTestConnectionTimeout(10);
```

### Check Current Configuration

View your current timeout settings:

```php
$timeoutConfig = $nfse->getTimeoutConfig();
print_r($timeoutConfig);

// Output:
// Array
// (
//     [curl_timeout] => 90
//     [soap_timeout] => 90
//     [soap_connection_timeout] => 90
//     [test_connection_timeout] => 10
// )
```

## Available Methods

| Method | Description | Default Value |
|--------|-------------|---------------|
| `setTimeout(int $timeout)` | Sets all timeouts to the specified value | - |
| `setCurlTimeout(int $timeout)` | Sets cURL timeout for service requests | 30s |
| `setSoapTimeout(int $timeout)` | Sets SOAP timeout for service requests | 30s |
| `setSoapConnectionTimeout(int $timeout)` | Sets SOAP connection timeout | 30s |
| `setTestConnectionTimeout(int $timeout)` | Sets test connection timeout | 5s |
| `getTimeoutConfig()` | Returns current timeout configuration | - |

## Usage Examples

### Example 1: Increase Timeout for Slow Networks

```php
$nfse = new NFSeBetha($certificateContent, $certificatePassword, $prestadorData);

// Increase timeout for slow network connections
$nfse->setTimeout(120); // 2 minutes

// Now all operations will wait up to 2 minutes
$result = $nfse->gerarNfse($rpsData);
```

### Example 2: Different Timeouts for Different Operations

```php
$nfse = new NFSeBetha($certificateContent, $certificatePassword, $prestadorData);

// Set aggressive timeout for test connections
$nfse->setTestConnectionTimeout(5);

// Set longer timeout for actual service operations
$nfse->setCurlTimeout(90);
$nfse->setSoapTimeout(90);
```

### Example 3: Production Environment with Long Timeouts

```php
$nfse = new NFSeBetha($certificateContent, $certificatePassword, $prestadorData);

// Production environment - allow longer processing times
$nfse->setTimeout(300); // 5 minutes

// This is useful when the NFSe service is under heavy load
$result = $nfse->consultarNfseServicoPrestado($params);
```

## Best Practices

1. **Start with reasonable values**: Begin with 60-90 seconds for most operations
2. **Monitor performance**: Use `getTimeoutConfig()` to verify your settings
3. **Consider network conditions**: Increase timeouts for slower network connections
4. **Production vs Development**: Use longer timeouts in production environments
5. **Test thoroughly**: Verify that increased timeouts resolve your timeout issues

## Backward Compatibility

All existing code will continue to work without modification. The default timeout values remain the same (30 seconds), ensuring that existing implementations are not affected.

## Troubleshooting

If you're still experiencing timeout issues after increasing the values:

1. Check your network connection stability
2. Verify the NFSe service status
3. Consider if the operation itself is taking too long
4. Monitor server resources during operations

## Example Implementation

See `examples/timeout_configuration.php` for a complete working example of timeout configuration.
