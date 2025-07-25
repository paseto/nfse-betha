<?php
/**
 * NFSe Betha API - Clean Standalone Integration
 *
 * This file contains everything needed to integrate with the NFSe Betha service.
 * No external dependencies required - just place your certificate and run!
 *
 * Features:
 * - Complete SOAP client implementation
 * - Certificate authentication (PFX format)
 * - ConsultarNfseFaixa method implementation
 * - Clean, production-ready code
 *
 * Usage:
 * 1. Place your .pfx certificate file in the same directory
 * 2. Update the configuration below
 * 3. Run: php nfse_betha_standalone.php
 *
 * @author NFSe Integration Team
 * @version 2.0
 * @date 2025-07-25
 */

// =============================================================================
// CONFIGURATION - UPDATE THESE VALUES
// =============================================================================

$config = [
    'certificate_path' => __DIR__ . '/certificado.pfx',  // Path to your .pfx certificate
    'certificate_password' => 'password',                  // Certificate password
];

// Test parameters - update with your real data
$test_params = [
    'prestador' => [
        'cnpj' => '20002537000171',        // Your CNPJ (14 digits)
        'inscricao_municipal' => '12345'   // Your municipal registration
    ],
    'faixa' => [
        'numero_inicial' => '1',           // Initial NFSe number
        'numero_final' => '10'             // Final NFSe number
    ],
    'pagina' => '1'                        // Page number
];

// =============================================================================
// NFSe BETHA API CLASS - NO CHANGES NEEDED BELOW THIS LINE
// =============================================================================

/**
 * NFSe Betha API Client
 *
 * Clean implementation for NFSe Betha webservice integration
 */
class NFSeBethaS
{
    private $serviceUrl;
    private $certificatePath;
    private $certificatePassword;
    private $soapClient;
    private $lastError;
    private $useCurlMode = false;

    // Betha NFSe service endpoints
    const HOSTED_WSDL = 'http://localhost/paseto/nfse-betha/modified_wsdl.xml?wsdl';
    const SERVICE_URL = 'https://nota-eletronica.betha.cloud/rps/ws';

    /**
     * Constructor
     */
    public function __construct($serviceUrl = null, $certificatePath, $certificatePassword)
    {
        $this->certificatePath = $certificatePath;
        $this->certificatePassword = $certificatePassword;
        $this->serviceUrl = self::SERVICE_URL;

        $this->validateCertificate();
        $this->initializeConnection();
    }

    /**
     * Validate certificate
     */
    private function validateCertificate()
    {
        if (!file_exists($this->certificatePath)) {
            throw new Exception("Certificate file not found: {$this->certificatePath}");
        }

        $pfxContent = file_get_contents($this->certificatePath);
        if ($pfxContent === false) {
            throw new Exception('Unable to read certificate file');
        }

        $certificateData = [];
        if (!openssl_pkcs12_read($pfxContent, $certificateData, $this->certificatePassword)) {
            throw new Exception('Unable to read certificate or incorrect password');
        }

        $certInfo = openssl_x509_parse($certificateData['cert']);
        if (time() > $certInfo['validTo_time_t']) {
            throw new Exception('Certificate is expired');
        }
    }

    /**
     * Initialize connection
     */
    private function initializeConnection()
    {
        try {
            $this->initializeSoapClient();
        } catch (Exception $e) {
            $this->useCurlMode = true;
        }
    }

    /**
     * Initialize SOAP client
     */
    private function initializeSoapClient()
    {
        $soapOptions = [
            'soap_version' => SOAP_1_1,
            'encoding' => 'UTF-8',
            'trace' => true,
            'exceptions' => true,
            'connection_timeout' => 30,
            'cache_wsdl' => WSDL_CACHE_NONE,
            'location' => $this->serviceUrl,
            'stream_context' => stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                    'local_cert' => $this->certificatePath,
                    'passphrase' => $this->certificatePassword
                ],
                'http' => [
                    'timeout' => 30,
                    'user_agent' => 'NFSeBetha-PHP-Client/2.0'
                ]
            ])
        ];

        $this->soapClient = new SoapClient(self::HOSTED_WSDL, $soapOptions);
    }

    /**
     * Consult NFSe by range
     */
    public function consultarNfseFaixa($params)
    {
        try {
            if ($this->useCurlMode) {
                return $this->sendCurlSoapRequest($params);
            } else {
                return $this->sendSoapRequest($params);
            }
        } catch (Exception $e) {
            $this->lastError = "Error: " . $e->getMessage();
            return false;
        }
    }

    /**
     * Send SOAP request using curl
     */
    private function sendCurlSoapRequest($params)
    {
        $soapEnvelope = $this->buildSoapEnvelope($params);
        $tempFiles = $this->createTempCertificateFiles();

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $this->serviceUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $soapEnvelope,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_SSLCERT => $tempFiles['cert'],
                CURLOPT_SSLKEY => $tempFiles['key'],
                CURLOPT_HTTPHEADER => [
                    'Content-Type: text/xml; charset=utf-8',
                    'Content-Length: ' . strlen($soapEnvelope),
                    'SOAPAction: "ConsultarNfseFaixa"',
                    'User-Agent: NFSeBetha-PHP-Client/2.0',
                    'Accept: text/xml'
                ]
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response === false || !empty($error)) {
                throw new Exception("Curl error: " . $error);
            }

            if ($httpCode !== 200) {
                throw new Exception("HTTP error: " . $httpCode);
            }

            return $this->parseResponse($response);

        } finally {
            $this->cleanupTempCertificateFiles($tempFiles);
        }
    }

    /**
     * Send SOAP request using standard client
     */
    private function sendSoapRequest($params)
    {
        $cabecMsg = '<![CDATA[<cabecalho xmlns="http://www.betha.com.br/e-nota-contribuinte-ws" versao="2.02"><versaoDados>2.02</versaoDados></cabecalho>]]>';
        $dadosMsg = '<![CDATA[' . $this->buildConsultarNfseFaixaEnvio($params) . ']]>';

        $request = [
            'nfseCabecMsg' => $cabecMsg,
            'nfseDadosMsg' => $dadosMsg
        ];

        $response = $this->soapClient->ConsultarNfseFaixa($request);

        return $this->parseResponse($response);
    }

    /**
     * Build SOAP envelope for ConsultarNfseFaixa
     */
    private function buildSoapEnvelope($params)
    {
        $cabecalho = '<cabecalho xmlns="http://www.betha.com.br/e-nota-contribuinte-ws" versao="2.02"><versaoDados>2.02</versaoDados></cabecalho>';
        $consultarEnvio = $this->buildConsultarNfseFaixaEnvio($params);

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" ' .
               'xmlns:e="http://www.betha.com.br/e-nota-contribuinte-ws">' . "\n" .
               '   <soapenv:Header/>' . "\n" .
               '   <soapenv:Body>' . "\n" .
               '      <e:ConsultarNfseFaixa>' . "\n" .
               '        <nfseCabecMsg>' . "\n" .
               '					<![CDATA[' . "\n" .
               '						 ' . $cabecalho . "\n" .
               '					]]>' . "\n" .
               '				</nfseCabecMsg>' . "\n" .
               '        <nfseDadosMsg>' . "\n" .
               '					<![CDATA[' . "\n" .
               '						' . $consultarEnvio . "\n" .
               '					]]>' . "\n" .
               '				</nfseDadosMsg>' . "\n" .
               '      </e:ConsultarNfseFaixa>' . "\n" .
               '   </soapenv:Body>' . "\n" .
               '</soapenv:Envelope>';
    }

    /**
     * Build ConsultarNfseFaixaEnvio XML content
     */
    private function buildConsultarNfseFaixaEnvio($params)
    {
        $xml = '<ConsultarNfseFaixaEnvio xmlns="http://www.betha.com.br/e-nota-contribuinte-ws">' . "\n";

        // Prestador (required)
        $xml .= '	<Prestador>' . "\n";
        $xml .= '		<CpfCnpj>' . "\n";
        $xml .= '			<Cnpj>' . ($params['prestador']['cnpj'] ?? '') . '</Cnpj>' . "\n";
        $xml .= '		</CpfCnpj>' . "\n";
        $xml .= '		<InscricaoMunicipal>' . ($params['prestador']['inscricao_municipal'] ?? '') . '</InscricaoMunicipal>' . "\n";
        $xml .= '	</Prestador>' . "\n";

        // Faixa (required)
        $xml .= '	<Faixa>' . "\n";
        $xml .= '		<NumeroNfseInicial>' . ($params['faixa']['numero_inicial'] ?? '') . '</NumeroNfseInicial>' . "\n";
        if (isset($params['faixa']['numero_final']) && !empty($params['faixa']['numero_final'])) {
            $xml .= '		<NumeroNfseFinal>' . $params['faixa']['numero_final'] . '</NumeroNfseFinal>' . "\n";
        }
        $xml .= '	</Faixa>' . "\n";

        // Pagina (required)
        $xml .= '	<Pagina>' . ($params['pagina'] ?? '1') . '</Pagina>' . "\n";

        $xml .= '</ConsultarNfseFaixaEnvio>';

        return $xml;
    }

    /**
     * Create temporary certificate files for curl
     */
    private function createTempCertificateFiles()
    {
        $pfxContent = file_get_contents($this->certificatePath);
        if ($pfxContent === false) {
            throw new Exception('Unable to read certificate file');
        }

        $certificateData = [];
        if (!openssl_pkcs12_read($pfxContent, $certificateData, $this->certificatePassword)) {
            throw new Exception('Unable to read certificate or incorrect password');
        }

        $tempCert = tempnam(sys_get_temp_dir(), 'nfse_cert_');
        $tempKey = tempnam(sys_get_temp_dir(), 'nfse_key_');

        if (file_put_contents($tempCert, $certificateData['cert']) === false) {
            throw new Exception('Unable to create temporary certificate file');
        }

        if (file_put_contents($tempKey, $certificateData['pkey']) === false) {
            unlink($tempCert);
            throw new Exception('Unable to create temporary key file');
        }

        return [
            'cert' => $tempCert,
            'key' => $tempKey
        ];
    }

    /**
     * Clean up temporary certificate files
     */
    private function cleanupTempCertificateFiles($tempFiles)
    {
        if (isset($tempFiles['cert']) && file_exists($tempFiles['cert'])) {
            unlink($tempFiles['cert']);
        }
        if (isset($tempFiles['key']) && file_exists($tempFiles['key'])) {
            unlink($tempFiles['key']);
        }
    }

    /**
     * Parse SOAP response
     */
    private function parseResponse($response)
    {
        if (is_string($response)) {
            // First decode HTML entities
            $decodedResponse = html_entity_decode($response, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            // Extract the inner XML from SOAP envelope
            if (preg_match('/<return>(.*?)<\/return>/s', $decodedResponse, $matches)) {
                $innerXml = $matches[1];
                // Decode HTML entities in the inner XML
                $innerXml = html_entity_decode($innerXml, ENT_QUOTES | ENT_HTML5, 'UTF-8');

                $xml = simplexml_load_string($innerXml);
                if ($xml !== false) {
                    return json_decode(json_encode($xml), true);
                }
            }

            // Fallback: try to parse the whole response
            $xml = simplexml_load_string($decodedResponse);
            if ($xml !== false) {
                return json_decode(json_encode($xml), true);
            }
        }

        return [
            'success' => true,
            'data' => $response,
            'raw_response' => $response
        ];
    }

    /**
     * Get last error
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Check if using curl mode
     */
    public function isUsingCurlMode()
    {
        return $this->useCurlMode;
    }

    /**
     * Get service URL
     */
    public function getServiceUrl()
    {
        return $this->serviceUrl;
    }
}

// =============================================================================
// MAIN EXECUTION - EXAMPLE USAGE
// =============================================================================

echo "NFSe Betha API - Clean Integration\n";
echo "==================================\n\n";

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0);
//echo '<pre>';
try {
    // Check if certificate file exists
    if (!file_exists($config['certificate_path'])) {
        echo "ERROR: Certificate file not found!\n";
        echo "Expected location: {$config['certificate_path']}\n";
        echo "Please place your .pfx certificate file in the correct location.\n\n";
        exit(1);
    }

    // Initialize API
    $api = new NFSeBetha(null, $config['certificate_path'], $config['certificate_password']);

//    echo "Test Parameters:\n";
//    print_r($test_params);
//    echo "\n" . str_repeat("-", 50) . "\n\n";

    // Make the request
    echo "Making ConsultarNfseFaixa request...\n";
    $result = $api->consultarNfseFaixa($test_params);

    if ($result !== false) {
        echo "SUCCESS! Response received:\n\n";
        print_r($result);
    } else {
        echo "ERROR: " . $api->getLastError() . "\n";
    }

} catch (Exception $e) {
    echo "EXCEPTION: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Integration completed!\n";

?>
