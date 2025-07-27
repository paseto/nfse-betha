<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Paseto\NFSeBetha\NFSeBetha;

$config = [
    'certificate_path' => __DIR__ . '/certificates/certificado.pfx',
    'certificate_password' => 'password'
];

try {
    $certificateContent = file_get_contents($config['certificate_path']);
    $prestadorData = [
        'cnpj' => '30002237001172',
        'inscricao_municipal' => '21490'
    ];
    $nfseAPI = new NFSeBetha($certificateContent, $config['certificate_password'], $prestadorData);

    $r = $nfseAPI->cancelarNfse([
        'numero' => '2', // NFSe number to cancel
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
