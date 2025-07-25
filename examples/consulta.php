<?php
// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

use Paseto\NFSeBetha\NFSeBetha;

$config = [
    'certificate_path' => __DIR__ . '/../certificates/certificado.pfx',
    'certificate_password' => '123'
];
try {
    $nfseAPI = new NFSeBetha($config['certificate_path'], $config['certificate_password']);

    $r = $nfseAPI->consultarNfseFaixa([
        'prestador' => [
            'cnpj' => '30002237001172',
            'inscricao_municipal' => '21490'
        ],
        'faixa' => [
            'numero_inicial' => '1',
            'numero_final' => '10'
        ],
        'pagina' => '1'
    ]);

    echo $nfseAPI->getLastError();
    print_r($r);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
