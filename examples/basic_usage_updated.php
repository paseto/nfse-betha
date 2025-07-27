<?php

/**
 * Updated Basic Usage Example for NFSe Betha Library
 * 
 * This example demonstrates the new API where:
 * - Certificate is passed as content (not file path)
 * - Prestador data is configured in constructor (no repetition)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Paseto\NFSeBetha\NFSeBetha;

// Configuration (use environment variables in production)
$certificatePath = $_ENV['NFSE_CERTIFICATE_PATH'] ?? '/path/to/your/certificate.pfx';
$certificatePassword = $_ENV['NFSE_CERTIFICATE_PASSWORD'] ?? 'your-certificate-password';

try {
    // Read certificate content
    $certificateContent = file_get_contents($certificatePath);
    if ($certificateContent === false) {
        throw new Exception("Unable to read certificate file: $certificatePath");
    }

    // Define prestador data once (no more repetition!)
    $prestadorData = [
        'cnpj' => '20002537000171',
        'inscricao_municipal' => '12345'
    ];

    // Initialize the NFSe API with certificate content and prestador data
    $nfseAPI = new NFSeBetha($certificateContent, $certificatePassword, $prestadorData);
    
    echo "=== NFSe Betha Library - Updated API Examples ===\n\n";
    
    // Example 1: Generate NFSe (no prestador needed in data!)
    echo "1. Generating NFSe...\n";
    
    $rpsData = [
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
        // No 'prestador' needed here - uses constructor data!
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
    ];
    
    $nfse = $nfseAPI->gerarNfse($rpsData);
    
    if ($nfse !== false) {
        echo "✅ NFSe generated successfully!\n";
        echo "   Number: " . ($nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['Numero'] ?? 'N/A') . "\n";
        echo "   Verification Code: " . ($nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['CodigoVerificacao'] ?? 'N/A') . "\n";
        echo "   Issue Date: " . ($nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['DataEmissao'] ?? 'N/A') . "\n";
        
        $nfseNumber = $nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['Numero'] ?? null;
    } else {
        echo "❌ Error generating NFSe: " . $nfseAPI->getLastError() . "\n";
        return;
    }
    
    echo "\n";
    
    // Example 2: Consult NFSe by Range (no prestador needed!)
    echo "2. Consulting NFSe by range...\n";
    
    $consultationParams = [
        // No 'prestador' needed here - uses constructor data!
        'faixa' => [
            'numero_inicial' => '1',
            'numero_final' => '10'
        ],
        'pagina' => '1'
    ];
    
    $consultation = $nfseAPI->consultarNfseFaixa($consultationParams);
    
    if ($consultation !== false) {
        echo "✅ NFSe consultation successful!\n";
        if (isset($consultation['ListaNfse'])) {
            $count = is_array($consultation['ListaNfse']['CompNfse']) ? 
                     count($consultation['ListaNfse']['CompNfse']) : 1;
            echo "   Found {$count} NFSe(s)\n";
        } else {
            echo "   No NFSe found in the specified range\n";
        }
    } else {
        echo "❌ Error consulting NFSe: " . $nfseAPI->getLastError() . "\n";
    }
    
    echo "\n";
    
    // Example 3: Cancel NFSe (no prestador needed!)
    if (isset($nfseNumber)) {
        echo "3. Cancelling NFSe...\n";
        
        $cancelData = [
            'numero' => $nfseNumber,
            // No 'prestador' needed here - uses constructor data!
            'codigo_municipio' => '4204608',
            'codigo_cancelamento' => '1' // 1 = Error in issuance
        ];
        
        $cancellation = $nfseAPI->cancelarNfse($cancelData);
        
        if ($cancellation !== false) {
            echo "✅ NFSe cancelled successfully!\n";
            echo "   Cancellation Date: " . ($cancellation['RetCancelamento']['NfseCancelamento']['Confirmacao']['DataHora'] ?? 'N/A') . "\n";
        } else {
            echo "❌ Error cancelling NFSe: " . $nfseAPI->getLastError() . "\n";
        }
    }
    
    echo "\n";
    
    // Example 4: Consult NFSe by Service Provider (minimal parameters!)
    echo "4. Consulting NFSe by service provider...\n";
    
    $serviceConsultationParams = [
        // No 'prestador' needed here - uses constructor data!
        'periodo_emissao' => [
            'data_inicial' => date('Y-m-01'), // First day of current month
            'data_final' => date('Y-m-d')     // Today
        ],
        'pagina' => '1'
    ];
    
    $serviceConsultation = $nfseAPI->consultarNfseServicoPrestado($serviceConsultationParams);
    
    if ($serviceConsultation !== false) {
        echo "✅ Service consultation successful!\n";
        if (isset($serviceConsultation['ListaNfse'])) {
            $count = is_array($serviceConsultation['ListaNfse']['CompNfse']) ? 
                     count($serviceConsultation['ListaNfse']['CompNfse']) : 1;
            echo "   Found {$count} NFSe(s) for this period\n";
        } else {
            echo "   No NFSe found for this period\n";
        }
    } else {
        echo "❌ Error consulting service NFSe: " . $nfseAPI->getLastError() . "\n";
    }
    
    echo "\n";
    
    // Example 5: Test Connection
    echo "5. Testing connection...\n";
    
    $connectionTest = $nfseAPI->testConnection();
    
    if ($connectionTest['success']) {
        echo "✅ Connection test successful!\n";
        echo "   HTTP Code: " . $connectionTest['http_code'] . "\n";
        echo "   Service URL: " . $connectionTest['service_url'] . "\n";
        echo "   Using cURL mode: " . ($connectionTest['using_curl_mode'] ? 'Yes' : 'No') . "\n";
    } else {
        echo "❌ Connection test failed!\n";
        echo "   HTTP Code: " . $connectionTest['http_code'] . "\n";
    }
    
    echo "\n";
    
    // Example 6: Show prestador data
    echo "6. Current prestador configuration...\n";
    $currentPrestador = $nfseAPI->getPrestadorData();
    echo "   CNPJ: " . $currentPrestador['cnpj'] . "\n";
    echo "   Inscrição Municipal: " . $currentPrestador['inscricao_municipal'] . "\n";
    
    echo "\n=== Examples completed ===\n";
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n";
echo "🎉 Improvements in this version:\n";
echo "   ✅ Certificate passed as content (more secure, no file path dependencies)\n";
echo "   ✅ Prestador data configured once in constructor\n";
echo "   ✅ No repetitive prestador arrays in method calls\n";
echo "   ✅ Cleaner, more maintainable code\n";
echo "   ✅ Better encapsulation and separation of concerns\n";
echo "\n";
echo "💡 Migration tips:\n";
echo "   - Read certificate file content: file_get_contents(\$certificatePath)\n";
echo "   - Define prestador data once in constructor\n";
echo "   - Remove 'prestador' arrays from method parameters\n";
echo "   - Use setPrestadorData() to change prestador if needed\n"; 