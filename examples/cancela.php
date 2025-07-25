<?php
// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/src/NFSeBetha.php';

$config = [
    'certificate_path' => __DIR__ . '/certificates/certificado.pfx',
    'certificate_password' => 'password'
];

try {
    $nfseAPI = new NFSeBetha($config['certificate_path'], $config['certificate_password']);

    $r = $nfseAPI->cancelarNfse([
        'numero' => '2', // NFSe number to cancel
        'prestador' => [
            'cnpj' => '20002537000171',
            'inscricao_municipal' => '12345'
        ],
        'codigo_municipio' => '4204608',
        'codigo_cancelamento' => '1' // Optional: 1=Erro na emissão
    ]);

    echo '<pre>';
    print_r($r);

    if ($r === false) {
        echo "\nError: " . $nfseAPI->getLastError();
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
