<?php

namespace Paseto\NFSeBetha;

use Exception;
use SoapClient;
use DOMDocument;
use DOMXPath;

/**
 * NFSe Betha API Client
 *
 * Professional implementation for NFSe Betha webservice integration.
 * Handles certificate authentication, SOAP envelope creation, and webservice communication.
 *
 * @package Paseto\NFSeBetha
 * @author Paseto Team
 * @version 2.0.0
 * @license MIT
 */
class NFSeBetha implements NFSeBethaInterface
{
    private $serviceUrl;
    private $certificatePath;
    private $certificatePassword;
    private $soapClient;
    private $lastError;
    private $useCurlMode = false;
    private $xmlSigner;

    // Betha NFSe service endpoints
    const LOCAL_WSDL = 'https://paseto.github.io/modified_wsdl.xml';
    const SERVICE_URL = 'https://nota-eletronica.betha.cloud/rps/ws';
    const NAMESPACE_URI = 'http://www.betha.com.br/e-nota-contribuinte-ws';

    /**
     * Constructor
     *
     * @param string|null $serviceUrl Service URL (optional)
     * @param string $certificatePath Path to the .pfx certificate file
     * @param string $certificatePassword Password for the certificate
     * @throws Exception If certificate validation fails
     */
    public function __construct($certificatePath, $certificatePassword, $serviceUrl = null)
    {
        $this->certificatePath = $certificatePath;
        $this->certificatePassword = $certificatePassword;
        $this->serviceUrl = $serviceUrl ?: self::SERVICE_URL;

        $this->validateCertificate();
        $this->initializeConnection();
        $this->initializeXMLSigner();
    }

    /**
     * Validate certificate file
     *
     * @throws Exception If certificate is invalid or expired
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
     * Initialize XML signer
     */
    private function initializeXMLSigner()
    {
        $this->xmlSigner = new XMLSigner($this->certificatePath, $this->certificatePassword);
    }

    /**
     * Initialize SOAP client
     *
     * @throws Exception If SOAP client initialization fails
     */
    private function initializeSoapClient()
    {
//        $wsdlPath = __DIR__ . '/../' . self::LOCAL_WSDL;
//
//        if (!file_exists($wsdlPath)) {
//            throw new Exception("WSDL file not found: " . $wsdlPath);
//        }

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

        $this->soapClient = new SoapClient(self::LOCAL_WSDL, $soapOptions);
    }

    /**
     * Consult NFSe by service provider
     *
     * @param array $params Consultation parameters
     * @return array|false Response array or false on failure
     */
    public function consultarNfseServicoPrestado($params)
    {
        try {
            if ($this->useCurlMode) {
                return $this->sendCurlRequest('ConsultarNfseServicoPrestado', $this->buildConsultarNfseServicoPrestadoEnvio($params));
            } else {
                $cabecMsg = $this->buildCabecalho();
                $dadosMsg = '<![CDATA[' . $this->buildConsultarNfseServicoPrestadoEnvio($params) . ']]>';

                $request = [
                    'nfseCabecMsg' => $cabecMsg,
                    'nfseDadosMsg' => $dadosMsg
                ];

                $response = $this->soapClient->ConsultarNfseServicoPrestado($request);
                return $this->parseResponse($response);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Consult NFSe by range
     *
     * @param array $params Range consultation parameters
     * @return array|false Response array or false on failure
     */
    public function consultarNfseFaixa($params)
    {
        try {
            if ($this->useCurlMode) {
                return $this->sendCurlRequest('ConsultarNfseFaixa', $this->buildConsultarNfseFaixaEnvio($params));
            } else {
                $cabecMsg = $this->buildCabecalho();
                $dadosMsg = '<![CDATA[' . $this->buildConsultarNfseFaixaEnvio($params) . ']]>';

                $request = [
                    'nfseCabecMsg' => $cabecMsg,
                    'nfseDadosMsg' => $dadosMsg
                ];

                $response = $this->soapClient->ConsultarNfseFaixa($request);
                return $this->parseResponse($response);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Generate NFSe from RPS
     *
     * @param array $rpsData RPS data for NFSe generation
     * @return array|false Response array or false on failure
     */
    public function gerarNfse($rpsData)
    {
        try {
            // Build and sign the RPS XML
            $rpsXML = $this->buildGerarNfseEnvio($rpsData);
            $signedRpsXML = $this->xmlSigner->signRPS($rpsXML);

            if ($this->useCurlMode) {
                return $this->sendCurlRequest('GerarNfse', $signedRpsXML);
            } else {
                $cabecMsg = $this->buildCabecalho();
                $dadosMsg = '<![CDATA[' . $signedRpsXML . ']]>';

                $request = [
                    'nfseCabecMsg' => $cabecMsg,
                    'nfseDadosMsg' => $dadosMsg
                ];

                $response = $this->soapClient->GerarNfse($request);
                return $this->parseResponse($response);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Cancel NFSe
     *
     * @param array $cancelData Cancellation data
     * @return array|false Response array or false on failure
     */
    public function cancelarNfse($cancelData)
    {
        try {
            // Build and sign the cancellation XML
            $cancelXML = $this->buildCancelarNfseEnvio($cancelData);
            $signedCancelXML = $this->xmlSigner->signCancellation($cancelXML);

            if ($this->useCurlMode) {
                return $this->sendCurlRequest('CancelarNfse', $signedCancelXML);
            } else {
                $cabecMsg = $this->buildCabecalho();
                $dadosMsg = '<![CDATA[' . $signedCancelXML . ']]>';

                $request = [
                    'nfseCabecMsg' => $cabecMsg,
                    'nfseDadosMsg' => $dadosMsg
                ];

                $response = $this->soapClient->CancelarNfse($request);
                return $this->parseResponse($response);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Send RPS batch
     *
     * @param array $loteData Batch data
     * @return array|false Response array or false on failure
     */
    public function enviarLoteRps($loteData)
    {
        try {
            if ($this->useCurlMode) {
                return $this->sendCurlRequest('EnviarLoteRps', $this->buildEnviarLoteRpsEnvio($loteData));
            } else {
                $cabecMsg = $this->buildCabecalho();
                $dadosMsg = '<![CDATA[' . $this->buildEnviarLoteRpsEnvio($loteData) . ']]>';

                $request = [
                    'nfseCabecMsg' => $cabecMsg,
                    'nfseDadosMsg' => $dadosMsg
                ];

                $response = $this->soapClient->EnviarLoteRps($request);
                return $this->parseResponse($response);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Consult RPS batch status
     *
     * @param array $params Batch consultation parameters
     * @return array|false Response array or false on failure
     */
    public function consultarLoteRps($params)
    {
        try {
            if ($this->useCurlMode) {
                return $this->sendCurlRequest('ConsultarLoteRps', $this->buildConsultarLoteRpsEnvio($params));
            } else {
                $cabecMsg = $this->buildCabecalho();
                $dadosMsg = '<![CDATA[' . $this->buildConsultarLoteRpsEnvio($params) . ']]>';

                $request = [
                    'nfseCabecMsg' => $cabecMsg,
                    'nfseDadosMsg' => $dadosMsg
                ];

                $response = $this->soapClient->ConsultarLoteRps($request);
                return $this->parseResponse($response);
            }
        } catch (Exception $e) {
            $this->lastError = $e->getMessage();
            return false;
        }
    }

    /**
     * Build SOAP header (cabecalho)
     *
     * @return string CDATA wrapped header
     */
    private function buildCabecalho()
    {
        return '<![CDATA[<cabecalho xmlns="' . self::NAMESPACE_URI . '" versao="2.02"><versaoDados>2.02</versaoDados></cabecalho>]]>';
    }

    /**
     * Build ConsultarNfseServicoPrestadoEnvio XML
     *
     * @param array $params Parameters
     * @return string XML content
     */
    private function buildConsultarNfseServicoPrestadoEnvio($params)
    {
        $xml = '<ConsultarNfseServicoPrestadoEnvio xmlns="' . self::NAMESPACE_URI . '">';

        // Prestador
        $xml .= '<Prestador>';
        $xml .= '<CpfCnpj><Cnpj>' . ($params['prestador']['cnpj'] ?? '') . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . ($params['prestador']['inscricao_municipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';

        // NumeroNfse (optional)
        if (isset($params['numero_nfse'])) {
            $xml .= '<NumeroNfse>' . $params['numero_nfse'] . '</NumeroNfse>';
        }

        // PeriodoEmissao or PeriodoCompetencia
        if (isset($params['periodo_emissao'])) {
            $xml .= '<PeriodoEmissao>';
            $xml .= '<DataInicial>' . $params['periodo_emissao']['data_inicial'] . '</DataInicial>';
            $xml .= '<DataFinal>' . $params['periodo_emissao']['data_final'] . '</DataFinal>';
            $xml .= '</PeriodoEmissao>';
        } elseif (isset($params['periodo_competencia'])) {
            $xml .= '<PeriodoCompetencia>';
            $xml .= '<DataInicial>' . $params['periodo_competencia']['data_inicial'] . '</DataInicial>';
            $xml .= '<DataFinal>' . $params['periodo_competencia']['data_final'] . '</DataFinal>';
            $xml .= '</PeriodoCompetencia>';
        }

        // Tomador (optional)
        if (isset($params['tomador'])) {
            $xml .= '<Tomador>';
            if (isset($params['tomador']['cpf_cnpj'])) {
                $xml .= '<CpfCnpj>';
                if (isset($params['tomador']['cpf_cnpj']['cnpj'])) {
                    $xml .= '<Cnpj>' . $params['tomador']['cpf_cnpj']['cnpj'] . '</Cnpj>';
                } elseif (isset($params['tomador']['cpf_cnpj']['cpf'])) {
                    $xml .= '<Cpf>' . $params['tomador']['cpf_cnpj']['cpf'] . '</Cpf>';
                }
                $xml .= '</CpfCnpj>';
            }
            if (isset($params['tomador']['inscricao_municipal'])) {
                $xml .= '<InscricaoMunicipal>' . $params['tomador']['inscricao_municipal'] . '</InscricaoMunicipal>';
            }
            $xml .= '</Tomador>';
        }

        // Pagina
        $xml .= '<Pagina>' . ($params['pagina'] ?? '1') . '</Pagina>';

        $xml .= '</ConsultarNfseServicoPrestadoEnvio>';

        return $xml;
    }

    /**
     * Build ConsultarNfseFaixaEnvio XML
     *
     * @param array $params Parameters
     * @return string XML content
     */
    private function buildConsultarNfseFaixaEnvio($params)
    {
        $xml = '<ConsultarNfseFaixaEnvio xmlns="' . self::NAMESPACE_URI . '">';

        // Prestador
        $xml .= '<Prestador>';
        $xml .= '<CpfCnpj><Cnpj>' . ($params['prestador']['cnpj'] ?? '') . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . ($params['prestador']['inscricao_municipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';

        // Faixa
        $xml .= '<Faixa>';
        $xml .= '<NumeroNfseInicial>' . ($params['faixa']['numero_inicial'] ?? '') . '</NumeroNfseInicial>';
        if (isset($params['faixa']['numero_final']) && !empty($params['faixa']['numero_final'])) {
            $xml .= '<NumeroNfseFinal>' . $params['faixa']['numero_final'] . '</NumeroNfseFinal>';
        }
        $xml .= '</Faixa>';

        // Pagina
        $xml .= '<Pagina>' . ($params['pagina'] ?? '1') . '</Pagina>';

        $xml .= '</ConsultarNfseFaixaEnvio>';

        return $xml;
    }

    /**
     * Build GerarNfseEnvio XML
     *
     * @param array $rpsData RPS data
     * @return string XML content
     */
    private function buildGerarNfseEnvio($rpsData)
    {
        $xml = '<GerarNfseEnvio xmlns="' . self::NAMESPACE_URI . '">';
        $xml .= $this->buildRps($rpsData);
        $xml .= '</GerarNfseEnvio>';

        return $xml;
    }

    /**
     * Build CancelarNfseEnvio XML
     *
     * @param array $cancelData Cancellation data
     * @return string XML content
     */
    private function buildCancelarNfseEnvio($cancelData)
    {
        $xml = '<CancelarNfseEnvio xmlns="' . self::NAMESPACE_URI . '">';
        $xml .= '<Pedido>';
        $xml .= '<InfPedidoCancelamento Id="InfPedidoCancelamento">';

        // IdentificacaoNfse
        $xml .= '<IdentificacaoNfse>';
        $xml .= '<Numero>' . ($cancelData['numero'] ?? '') . '</Numero>';
        $xml .= '<CpfCnpj><Cnpj>' . ($cancelData['prestador']['cnpj'] ?? '') . '</Cnpj></CpfCnpj>';
        if (isset($cancelData['prestador']['inscricao_municipal'])) {
            $xml .= '<InscricaoMunicipal>' . $cancelData['prestador']['inscricao_municipal'] . '</InscricaoMunicipal>';
        }
        $xml .= '<CodigoMunicipio>' . ($cancelData['codigo_municipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '</IdentificacaoNfse>';

        // CodigoCancelamento (optional)
        if (isset($cancelData['codigo_cancelamento'])) {
            $xml .= '<CodigoCancelamento>' . $cancelData['codigo_cancelamento'] . '</CodigoCancelamento>';
        }

        $xml .= '</InfPedidoCancelamento>';
        $xml .= '</Pedido>';
        $xml .= '</CancelarNfseEnvio>';

        return $xml;
    }

    /**
     * Build EnviarLoteRpsEnvio XML
     *
     * @param array $loteData Batch data
     * @return string XML content
     */
    private function buildEnviarLoteRpsEnvio($loteData)
    {
        $xml = '<EnviarLoteRpsEnvio xmlns="' . self::NAMESPACE_URI . '">';
        $xml .= '<LoteRps versao="2.02">';

        $xml .= '<NumeroLote>' . ($loteData['numero_lote'] ?? '') . '</NumeroLote>';
        $xml .= '<CpfCnpj><Cnpj>' . ($loteData['prestador']['cnpj'] ?? '') . '</Cnpj></CpfCnpj>';
        if (isset($loteData['prestador']['inscricao_municipal'])) {
            $xml .= '<InscricaoMunicipal>' . $loteData['prestador']['inscricao_municipal'] . '</InscricaoMunicipal>';
        }
        $xml .= '<QuantidadeRps>' . count($loteData['lista_rps'] ?? []) . '</QuantidadeRps>';

        $xml .= '<ListaRps>';
        foreach ($loteData['lista_rps'] ?? [] as $rps) {
            $xml .= $this->buildRps($rps);
        }
        $xml .= '</ListaRps>';

        $xml .= '</LoteRps>';
        $xml .= '</EnviarLoteRpsEnvio>';

        return $xml;
    }

    /**
     * Build ConsultarLoteRpsEnvio XML
     *
     * @param array $params Parameters
     * @return string XML content
     */
    private function buildConsultarLoteRpsEnvio($params)
    {
        $xml = '<ConsultarLoteRpsEnvio xmlns="' . self::NAMESPACE_URI . '">';

        $xml .= '<Prestador>';
        $xml .= '<CpfCnpj><Cnpj>' . ($params['prestador']['cnpj'] ?? '') . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . ($params['prestador']['inscricao_municipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';

        $xml .= '<Protocolo>' . ($params['protocolo'] ?? '') . '</Protocolo>';

        $xml .= '</ConsultarLoteRpsEnvio>';

        return $xml;
    }

    /**
     * Build RPS XML structure
     *
     * @param array $rpsData RPS data
     * @return string XML content
     */
    private function buildRps($rpsData)
    {
        $xml = '<Rps>';
        $xml .= '<InfDeclaracaoPrestacaoServico Id="InfDeclaracaoPrestacaoServico">';

        // RPS identification
        if (isset($rpsData['rps'])) {
            $xml .= '<Rps>';
            $xml .= '<IdentificacaoRps>';
            $xml .= '<Numero>' . ($rpsData['rps']['numero'] ?? '') . '</Numero>';
            $xml .= '<Serie>' . ($rpsData['rps']['serie'] ?? '') . '</Serie>';
            $xml .= '<Tipo>' . ($rpsData['rps']['tipo'] ?? '1') . '</Tipo>';
            $xml .= '</IdentificacaoRps>';
            $xml .= '<DataEmissao>' . ($rpsData['rps']['data_emissao'] ?? date('Y-m-d')) . '</DataEmissao>';
            $xml .= '<Status>' . ($rpsData['rps']['status'] ?? '1') . '</Status>';
            $xml .= '</Rps>';
        }

        // Competencia
        $xml .= '<Competencia>' . ($rpsData['competencia'] ?? date('Y-m-d')) . '</Competencia>';

        // Servico
        $xml .= '<Servico>';
        $xml .= '<Valores>';
        $xml .= '<ValorServicos>' . ($rpsData['servico']['valores']['valor_servicos'] ?? '0.00') . '</ValorServicos>';
        $xml .= '<ValorDeducoes>' . ($rpsData['servico']['valores']['valor_deducoes'] ?? '0.00') . '</ValorDeducoes>';
        $xml .= '<ValorIss>' . ($rpsData['servico']['valores']['valor_iss'] ?? '0.00') . '</ValorIss>';
        $xml .= '<Aliquota>' . ($rpsData['servico']['valores']['aliquota'] ?? '0.00') . '</Aliquota>';
        $xml .= '<DescontoIncondicionado>' . ($rpsData['servico']['valores']['desconto_incondicionado'] ?? '0.00') . '</DescontoIncondicionado>';
        $xml .= '<DescontoCondicionado>' . ($rpsData['servico']['valores']['desconto_condicionado'] ?? '0.00') . '</DescontoCondicionado>';
        $xml .= '</Valores>';
        $xml .= '<IssRetido>' . ($rpsData['servico']['iss_retido'] ?? '2') . '</IssRetido>';
        $xml .= '<ItemListaServico>' . ($rpsData['servico']['item_lista_servico'] ?? '') . '</ItemListaServico>';
        $xml .= '<Discriminacao>' . htmlspecialchars($rpsData['servico']['discriminacao'] ?? '', ENT_XML1, 'UTF-8') . '</Discriminacao>';
        $xml .= '<CodigoMunicipio>' . ($rpsData['servico']['codigo_municipio'] ?? '') . '</CodigoMunicipio>';
        $xml .= '<ExigibilidadeISS>' . ($rpsData['servico']['exigibilidade_iss'] ?? '1') . '</ExigibilidadeISS>';

        // MunicipioIncidencia (required when ExigibilidadeISS is 1, 6, or 7)
        $exigibilidade = $rpsData['servico']['exigibilidade_iss'] ?? '1';
        if (in_array($exigibilidade, ['1', '6', '7'])) {
            $xml .= '<MunicipioIncidencia>' . ($rpsData['servico']['municipio_incidencia'] ?? $rpsData['servico']['codigo_municipio'] ?? '') . '</MunicipioIncidencia>';
        }
        $xml .= '</Servico>';

        // Prestador
        $xml .= '<Prestador>';
        $xml .= '<CpfCnpj><Cnpj>' . ($rpsData['prestador']['cnpj'] ?? '') . '</Cnpj></CpfCnpj>';
        $xml .= '<InscricaoMunicipal>' . ($rpsData['prestador']['inscricao_municipal'] ?? '') . '</InscricaoMunicipal>';
        $xml .= '</Prestador>';

        // Tomador (optional)
        if (isset($rpsData['tomador'])) {
            $xml .= '<Tomador>';
            if (isset($rpsData['tomador']['identificacao'])) {
                $xml .= '<IdentificacaoTomador>';
                $xml .= '<CpfCnpj>';
                if (isset($rpsData['tomador']['identificacao']['cnpj'])) {
                    $xml .= '<Cnpj>' . $rpsData['tomador']['identificacao']['cnpj'] . '</Cnpj>';
                } elseif (isset($rpsData['tomador']['identificacao']['cpf'])) {
                    $xml .= '<Cpf>' . $rpsData['tomador']['identificacao']['cpf'] . '</Cpf>';
                }
                $xml .= '</CpfCnpj>';
                if (isset($rpsData['tomador']['identificacao']['inscricao_municipal'])) {
                    $xml .= '<InscricaoMunicipal>' . $rpsData['tomador']['identificacao']['inscricao_municipal'] . '</InscricaoMunicipal>';
                }
                $xml .= '</IdentificacaoTomador>';
            }
            if (isset($rpsData['tomador']['razao_social'])) {
                $xml .= '<RazaoSocial>' . htmlspecialchars($rpsData['tomador']['razao_social'], ENT_XML1, 'UTF-8') . '</RazaoSocial>';
            }

            // Endereco (required when tomador is present)
            if (isset($rpsData['tomador']['endereco'])) {
                $xml .= '<Endereco>';
                $xml .= '<Endereco>' . htmlspecialchars($rpsData['tomador']['endereco']['logradouro'] ?? '', ENT_XML1, 'UTF-8') . '</Endereco>';
                $xml .= '<Numero>' . ($rpsData['tomador']['endereco']['numero'] ?? '') . '</Numero>';
                if (isset($rpsData['tomador']['endereco']['complemento'])) {
                    $xml .= '<Complemento>' . htmlspecialchars($rpsData['tomador']['endereco']['complemento'], ENT_XML1, 'UTF-8') . '</Complemento>';
                }
                $xml .= '<Bairro>' . htmlspecialchars($rpsData['tomador']['endereco']['bairro'] ?? '', ENT_XML1, 'UTF-8') . '</Bairro>';
                $xml .= '<CodigoMunicipio>' . ($rpsData['tomador']['endereco']['codigo_municipio'] ?? '') . '</CodigoMunicipio>';
                $xml .= '<Uf>' . ($rpsData['tomador']['endereco']['uf'] ?? '') . '</Uf>';
                $xml .= '<Cep>' . ($rpsData['tomador']['endereco']['cep'] ?? '') . '</Cep>';
                $xml .= '</Endereco>';
            }

            // Contato (optional)
            if (isset($rpsData['tomador']['contato'])) {
                $xml .= '<Contato>';
                if (isset($rpsData['tomador']['contato']['telefone'])) {
                    $xml .= '<Telefone>' . $rpsData['tomador']['contato']['telefone'] . '</Telefone>';
                }
                if (isset($rpsData['tomador']['contato']['email'])) {
                    $xml .= '<Email>' . $rpsData['tomador']['contato']['email'] . '</Email>';
                }
                $xml .= '</Contato>';
            }

            $xml .= '</Tomador>';
        }

        // RegimeEspecialTributacao (required for optante_simples_nacional = 1)
        if (isset($rpsData['optante_simples_nacional']) && $rpsData['optante_simples_nacional'] == '1') {
            $xml .= '<RegimeEspecialTributacao>' . ($rpsData['regime_especial_tributacao'] ?? '6') . '</RegimeEspecialTributacao>';
        }

        // OptanteSimplesNacional and IncentivoFiscal
        $xml .= '<OptanteSimplesNacional>' . ($rpsData['optante_simples_nacional'] ?? '2') . '</OptanteSimplesNacional>';
        $xml .= '<IncentivoFiscal>' . ($rpsData['incentivo_fiscal'] ?? '2') . '</IncentivoFiscal>';

        $xml .= '</InfDeclaracaoPrestacaoServico>';
        $xml .= '</Rps>';

        return $xml;
    }

    /**
     * Send CURL request
     *
     * @param string $action SOAP action
     * @param string $xmlContent XML content
     * @return array|false Response or false on failure
     */
    private function sendCurlRequest($action, $xmlContent)
    {
        $soapEnvelope = $this->buildSoapEnvelope($action, $xmlContent);
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
                    'SOAPAction: "' . $action . '"',
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
     * Build SOAP envelope
     *
     * @param string $action SOAP action
     * @param string $xmlContent XML content
     * @return string SOAP envelope
     */
    private function buildSoapEnvelope($action, $xmlContent)
    {
        $cabecalho = '<cabecalho xmlns="' . self::NAMESPACE_URI . '" versao="2.02"><versaoDados>2.02</versaoDados></cabecalho>';

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" .
               '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" ' .
               'xmlns:e="' . self::NAMESPACE_URI . '">' . "\n" .
               '   <soapenv:Header/>' . "\n" .
               '   <soapenv:Body>' . "\n" .
               '      <e:' . $action . '>' . "\n" .
               '        <nfseCabecMsg>' . "\n" .
               '          <![CDATA[' . $cabecalho . ']]>' . "\n" .
               '        </nfseCabecMsg>' . "\n" .
               '        <nfseDadosMsg>' . "\n" .
               '          <![CDATA[' . $xmlContent . ']]>' . "\n" .
               '        </nfseDadosMsg>' . "\n" .
               '      </e:' . $action . '>' . "\n" .
               '   </soapenv:Body>' . "\n" .
               '</soapenv:Envelope>';
    }

    /**
     * Create temporary certificate files for curl
     *
     * @return array Temporary file paths
     * @throws Exception If unable to create temporary files
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
     *
     * @param array $tempFiles Temporary file paths
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
     *
     * @param mixed $response SOAP response
     * @return array Parsed response
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
     * Get the last error message
     *
     * @return string|null Last error message
     */
    public function getLastError()
    {
        return $this->lastError;
    }

    /**
     * Test connection to the webservice
     *
     * @return array Test results
     */
    public function testConnection()
    {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->serviceUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'NFSeBetha-PHP-Client/2.0'
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'success' => $result !== false && ($httpCode == 200 || $httpCode == 405 || $httpCode == 500),
            'http_code' => $httpCode,
            'service_url' => $this->serviceUrl,
            'using_curl_mode' => $this->useCurlMode,
            'soap_client_available' => $this->soapClient !== null
        ];
    }

    /**
     * Get service URL
     *
     * @return string Service URL
     */
    public function getServiceUrl()
    {
        return $this->serviceUrl;
    }

    /**
     * Check if using curl mode
     *
     * @return bool True if using curl mode
     */
    public function isUsingCurlMode()
    {
        return $this->useCurlMode;
    }
}
