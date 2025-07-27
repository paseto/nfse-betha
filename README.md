# NFSe Betha PHP Library

[![Latest Version on Packagist](https://img.shields.io/packagist/v/paseto/nfse-betha.svg?style=flat-square)](https://packagist.org/packages/paseto/nfse-betha)
[![Total Downloads](https://img.shields.io/packagist/dt/paseto/nfse-betha.svg?style=flat-square)](https://packagist.org/packages/paseto/nfse-betha)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE.md)


- Wrapper for the non working version of NFSe Betha API in PHP. https://iss.ajuda.betha.cloud/e-nota-cloud/ajuda/outros-conteudos/web-service/

- Layout: **Abrasf 2.02** with modified wsdl


A comprehensive PHP library for NFSe Betha integration with XML digital signing support using ICP Brasil certificates.

## Features

- 🔐 **Complete XML Digital Signing** with ICP Brasil certificates
- 📋 **Full NFSe Operations**: Generation, Cancellation, and Consultation
- 🛡️ **XML-DSig Compliance** following W3C standards
- 🏛️ **XSD Schema Validation** according to nfse_v202.xsd
- 🔄 **Automatic SOAP Envelope** creation and handling
- 🌐 **Multi-mode Support**: SoapClient and cURL fallback
- 📊 **Comprehensive Error Handling** and validation
- 📁 **Local WSDL Support** for reliable offline development
- 🚀 **Production Ready** with extensive testing
- 🔒 **Secure Certificate Handling** - accepts certificate content (not file paths)
- 🎯 **Simplified API** - prestador data configured once in constructor
- 🧹 **Clean Code** - no repetitive prestador arrays in method calls

## Requirements

- PHP >= 7.4
- OpenSSL extension
- DOM extension
- XML extension
- cURL extension
- SOAP extension
- ICP Brasil certificate in PFX format

## Installation

Install via Composer:

```bash
composer require paseto/nfse-betha
```

## Quick Start

### Basic Usage

```php
<?php

use Paseto\NFSeBetha\NFSeBetha;

// Read certificate content
$certificateContent = file_get_contents('/path/to/your/certificate.pfx');

// Define prestador data once
$prestadorData = [
    'cnpj' => '12345678000195',
    'inscricao_municipal' => '12345'
];

// Initialize the API with certificate content and prestador data
$nfseAPI = new NFSeBetha($certificateContent, $certificatePassword, $prestadorData);

// Generate NFSe (no prestador needed - uses constructor data!)
$nfse = $nfseAPI->gerarNfse([
    'rps' => [
        'numero' => '123',
        'serie' => '1',
        'tipo' => '1',
        'data_emissao' => date('Y-m-d'),
        'status' => '1'
    ],
    'competencia' => date('Y-m-d'),
    'servico' => [
        'valores' => [
            'valor_servicos' => '100.00',
            'valor_deducoes' => '0.00',
            'valor_iss' => '3.75',
            'aliquota' => '3.75',
            'desconto_incondicionado' => '0.00',
            'desconto_condicionado' => '0.00'
        ],
        'iss_retido' => '2',
        'item_lista_servico' => '0103',
        'discriminacao' => 'Serviços de consultoria em tecnologia da informação',
        'codigo_municipio' => '4204608',
        'exigibilidade_iss' => '1'
    ],
    'tomador' => [
        'identificacao' => [
            'cnpj' => '02509015000105',
            'inscricao_municipal' => '22540'
        ],
        'razao_social' => 'EMPRESA EXEMPLO LTDA',
        'endereco' => [
            'logradouro' => 'RUA EXEMPLO',
            'numero' => '123',
            'bairro' => 'CENTRO',
            'codigo_municipio' => '4204608',
            'uf' => 'SC',
            'cep' => '88000000'
        ]
    ],
    'optante_simples_nacional' => '1',
    'incentivo_fiscal' => '2'
]);

// Check result
if ($nfse !== false) {
    echo "NFSe generated successfully!";
    echo "Number: " . $nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['Numero'];
} else {
    echo "Error: " . $nfseAPI->getLastError();
}
```

### Cancel NFSe

```php
// Cancel NFSe
$cancellation = $nfseAPI->cancelarNfse([
    'numero' => '1',    
    'codigo_municipio' => '4204608',
    'codigo_cancelamento' => '1'
]);

if ($cancellation !== false) {
    echo "NFSe cancelled successfully!";
} else {
    echo "Error: " . $nfseAPI->getLastError();
}
```

### Consult NFSe

```php
// Consult NFSe by range
$consultation = $nfseAPI->consultarNfseFaixa([    
    'faixa' => [
        'numero_inicial' => '1',
        'numero_final' => '10'
    ],
    'pagina' => '1'
]);

```

## Configuration

### Certificate Setup

1. Obtain your ICP Brasil certificate in PFX format
2. Store it securely (outside web root)
3. Set appropriate file permissions (600)

```php
$certificatePath = '/secure/path/to/certificate.pfx';
$certificatePassword = 'your-certificate-password';
```

### Environment Variables

For enhanced security, use environment variables:

```php
$certificatePath = $_ENV['NFSE_CERTIFICATE_PATH'];
$certificatePassword = $_ENV['NFSE_CERTIFICATE_PASSWORD'];
```

## Digital Signing

This library implements complete XML digital signing according to:

- **XML-DSig W3C Standards**
- **ICP Brasil Requirements**
- **NFSe Betha XSD Schema**

### Signature Features

- ✅ Automatic signature placement per XSD schema
- ✅ Canonical XML (C14N) processing
- ✅ X509 certificate embedding
- ✅ SHA1 digest and RSA-SHA1 signature algorithms
- ✅ Enveloped signature transforms

## Available Operations

| Operation | Method | Signing Required |
|-----------|--------|------------------|
| Generate NFSe | `gerarNfse()` | ✅ Yes |
| Cancel NFSe | `cancelarNfse()` | ✅ Yes |
| Consult NFSe Range | `consultarNfseFaixa()` | ❌ No |
| Consult NFSe by Provider | `consultarNfseServicoPrestado()` | ❌ No |
| Send RPS Batch | `enviarLoteRps()` | ✅ Yes |
| Consult RPS Batch | `consultarLoteRps()` | ❌ No |

## Error Handling

```php
try {
    $result = $nfseAPI->gerarNfse($rpsData);
    
    if ($result === false) {
        // Check specific error
        $error = $nfseAPI->getLastError();
        echo "Error: " . $error;
    } else {
        // Success - process result
        echo "Success!";
    }
} catch (Exception $e) {
    // Handle exceptions
    echo "Exception: " . $e->getMessage();
}
```

## Testing

Run the test suite:

```bash
composer test
```

Check code quality:

```bash
composer quality
```

## Security

### Best Practices

1. **Certificate Security**
   - Store certificates outside web root
   - Use environment variables for passwords
   - Set proper file permissions (600)
   - Never commit certificates to version control

2. **Environment Configuration**
   - Use `.env` files for sensitive configuration
   - Validate all input parameters
   - Log errors securely without exposing sensitive data

3. **Production Deployment**
   - Use HTTPS in production
   - Implement proper error logging
   - Monitor certificate expiration
   - Regular security updates

## Contributing

Contributions are welcome! Please read our [Contributing Guide](CONTRIBUTING.md) for details.

### Development Setup

```bash
git clone https://github.com/paseto/nfse-betha.git
cd nfse-betha
composer install
```

### Code Standards

- PSR-2 coding standards
- PHPStan level 7 analysis
- 100% test coverage for critical paths

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for recent changes.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

## Support

- 📧 Email: giovaniw2@gmail.com
- 🐛 Issues: [GitHub Issues](https://github.com/paseto/nfse-betha/issues)
- 📖 Documentation: [Wiki](https://github.com/paseto/nfse-betha/wiki)

