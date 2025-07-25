<?php

/**
 * Basic Usage Example for NFSe Betha Library
 * 
 * This example demonstrates how to use the NFSe Betha library
 * for common operations like generating, cancelling, and consulting NFSe.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Paseto\NFSeBetha\NFSeBetha;

// Configuration (use environment variables in production)
$certificatePath = $_ENV['NFSE_CERTIFICATE_PATH'] ?? '/path/to/your/certificate.pfx';
$certificatePassword = $_ENV['NFSE_CERTIFICATE_PASSWORD'] ?? 'your-certificate-password';

try {
    // Initialize the NFSe API
    $nfseAPI = new NFSeBetha($certificatePath, $certificatePassword);
    
    echo "=== NFSe Betha Library - Basic Usage Examples ===\n\n";
    
    // Example 1: Generate NFSe
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
        'prestador' => [
            'cnpj' => '20002537000171',
            'inscricao_municipal' => '12345'
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
    ];
    
    $nfse = $nfseAPI->gerarNfse($rpsData);
    
    if ($nfse !== false) {
        echo "✅ NFSe generated successfully!\n";
        echo "   Number: " . $nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['Numero'] . "\n";
        echo "   Verification Code: " . $nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['CodigoVerificacao'] . "\n";
        echo "   Issue Date: " . $nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['DataEmissao'] . "\n";
        
        $nfseNumber = $nfse['ListaNfse']['CompNfse']['Nfse']['InfNfse']['Numero'];
    } else {
        echo "❌ Error generating NFSe: " . $nfseAPI->getLastError() . "\n";
        return;
    }
    
    echo "\n";
    
    // Example 2: Consult NFSe by Range
    echo "2. Consulting NFSe by range...\n";
    
    $consultationParams = [
        'prestador' => [
            'cnpj' => '20002537000171',
            'inscricao_municipal' => '12345'
        ],
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
    
    // Example 3: Cancel NFSe (if we have one to cancel)
    if (isset($nfseNumber)) {
        echo "3. Cancelling NFSe...\n";
        
        $cancelData = [
            'numero' => $nfseNumber,
            'prestador' => [
                'cnpj' => '20002537000171',
                'inscricao_municipal' => '12345'
            ],
            'codigo_municipio' => '4204608',
            'codigo_cancelamento' => '1' // 1 = Error in issuance
        ];
        
        $cancellation = $nfseAPI->cancelarNfse($cancelData);
        
        if ($cancellation !== false) {
            echo "✅ NFSe cancelled successfully!\n";
            echo "   Cancellation Date: " . $cancellation['RetCancelamento']['NfseCancelamento']['Confirmacao']['DataHora'] . "\n";
        } else {
            echo "❌ Error cancelling NFSe: " . $nfseAPI->getLastError() . "\n";
        }
    }
    
    echo "\n";
    
    // Example 4: Test Connection
    echo "4. Testing connection...\n";
    
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
    
    echo "\n=== Examples completed ===\n";
    
} catch (Exception $e) {
    echo "❌ Fatal error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . ":" . $e->getLine() . "\n";
}

echo "\n";
echo "💡 Tips:\n";
echo "   - Store certificates outside web root\n";
echo "   - Use environment variables for sensitive data\n";
echo "   - Monitor certificate expiration dates\n";
echo "   - Implement proper error logging in production\n";
echo "   - Test thoroughly in sandbox environment\n"; 
