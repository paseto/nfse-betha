<?php
// Set error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../vendor/autoload.php';

use Paseto\NFSeBetha\NFSeBetha;

$config = [
    'certificate_path' => __DIR__ . '/../certificates/certificado.pfx',
    'certificate_password' => 'password'
];
try {
    $certificateContent = file_get_contents($config['certificate_path']);
    if ($certificateContent === false) {
        throw new Exception("Unable to read certificate file:");
    }

    // Define prestador data once (no more repetition!)
    $prestadorData = [
        'cnpj' => '30002237001172',
        'inscricao_municipal' => '21490'
    ];

    $nfseAPI = new NFSeBetha($certificateContent, $config['certificate_password'], $prestadorData);


    $consultationParams = [
        // No 'prestador' needed here - uses constructor data!
        'faixa' => [
            'numero_inicial' => '1',
            'numero_final' => '10'
        ],
        'pagina' => '1'
    ];

    $consultation = $nfseAPI->consultarNfseFaixa($consultationParams);

    echo $nfseAPI->getLastError();
    print_r($consultation);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
