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

    $r = $nfseAPI->gerarNfse([
        'rps' => [
            'numero' => '2',
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
            'item_lista_servico' => '0103',  // Fixed: was 'codigo_servico'
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
            'razao_social' => 'MARLON MOTOS LTDA ME',
            'endereco' => [
                'logradouro' => 'CENTENARIO',
                'numero' => '2301',
                'bairro' => 'OPERARIA NOVA',
                'codigo_municipio' => '4204608',
                'uf' => 'SC',
                'cep' => '88809000'
            ],
            'contato' => [
                'telefone' => '4834333807'
            ]
        ],
        'optante_simples_nacional' => '1',
        'incentivo_fiscal' => '2'
    ]);

    echo '<pre>';
    print_r($r);
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
