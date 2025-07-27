<?php

namespace Paseto\NFSeBetha;

use Exception;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * XML Digital Signature Class for NFSe Betha
 * 
 * Implements XML-DSig (XML Digital Signature) standards for signing NFSe documents
 * with ICP Brasil certificates as required by Betha webservice.
 * 
 * @package Paseto\NFSeBetha
 * @author Paseto Team
 * @version 1.0.0
 * @license MIT
 */
class XMLSigner
{
    private $certificateContent;
    private $certificatePassword;
    private $certificateData;
    private $privateKey;
    private $publicKey;

    /**
     * Constructor
     *
     * @param string $certificateContent Content of PFX certificate (binary)
     * @param string $certificatePassword Certificate password
     * @throws Exception If certificate cannot be loaded
     */
    public function __construct($certificateContent, $certificatePassword)
    {
        $this->certificateContent = $certificateContent;
        $this->certificatePassword = $certificatePassword;
        
        $this->loadCertificate();
    }

    /**
     * Load and validate certificate
     *
     * @throws Exception If certificate is invalid
     */
    private function loadCertificate()
    {
        if (empty($this->certificateContent)) {
            throw new Exception('Certificate content is empty');
        }

        if (!openssl_pkcs12_read($this->certificateContent, $this->certificateData, $this->certificatePassword)) {
            throw new Exception('Unable to read certificate or incorrect password');
        }

        // Extract private key
        $this->privateKey = openssl_pkey_get_private($this->certificateData['pkey']);
        if (!$this->privateKey) {
            throw new Exception('Unable to extract private key from certificate');
        }

        // Extract public key
        $this->publicKey = openssl_pkey_get_public($this->certificateData['cert']);
        if (!$this->publicKey) {
            throw new Exception('Unable to extract public key from certificate');
        }

        // Validate certificate expiration
        $certInfo = openssl_x509_parse($this->certificateData['cert']);
        if (time() > $certInfo['validTo_time_t']) {
            throw new Exception('Certificate is expired');
        }
    }

    /**
     * Sign XML document according to XML-DSig standards
     *
     * @param string $xmlContent XML content to sign
     * @param string $elementId ID of the element to sign (optional)
     * @return string Signed XML content
     * @throws Exception If signing fails
     */
    public function signXML($xmlContent, $elementId = null)
    {
        // Load XML document
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        if (!$dom->loadXML($xmlContent)) {
            throw new Exception('Invalid XML content');
        }

        // Find the element to sign
        if ($elementId) {
            $xpath = new DOMXPath($dom);
            $elements = $xpath->query("//*[@Id='{$elementId}']");
            if ($elements->length === 0) {
                throw new Exception("Element with Id '{$elementId}' not found");
            }
            $elementToSign = $elements->item(0);
        } else {
            $elementToSign = $dom->documentElement;
        }

        if (!$elementToSign) {
            throw new Exception('Element to sign not found');
        }

        // Add ID attribute if not present
        if (!$elementToSign->hasAttribute('Id')) {
            $elementToSign->setAttribute('Id', 'signed-element');
            $elementId = 'signed-element';
        } else {
            $elementId = $elementToSign->getAttribute('Id');
        }

        // Canonicalize the element
        $canonicalizedXML = $elementToSign->C14N(true, false);
        
        // Calculate digest
        $digest = base64_encode(hash('sha1', $canonicalizedXML, true));

        // Create signature structure
        $signature = $this->createSignatureElement($dom, $elementId, $digest);

        // Sign the SignedInfo
        $signedInfo = $signature->getElementsByTagName('SignedInfo')->item(0);
        $canonicalizedSignedInfo = $signedInfo->C14N(true, false);
        
        // Create signature value
        if (!openssl_sign($canonicalizedSignedInfo, $signatureValue, $this->privateKey, OPENSSL_ALGO_SHA1)) {
            throw new Exception('Failed to create digital signature');
        }

        // Set signature value
        $signatureValueElement = $signature->getElementsByTagName('SignatureValue')->item(0);
        $signatureValueElement->nodeValue = base64_encode($signatureValue);

        // Add X509 certificate data
        $this->addCertificateInfo($signature);

        // Append signature to the element
        $elementToSign->appendChild($signature);

        // Save XML without declaration for embedding in SOAP
        $xmlOutput = $dom->saveXML();
        
        // Remove XML declaration if present (for CDATA embedding)
        if (strpos($xmlOutput, '<?xml') === 0) {
            $xmlOutput = preg_replace('/<\?xml[^>]*\?>\s*/', '', $xmlOutput);
        }

        return $xmlOutput;
    }

    /**
     * Create XML Signature element structure
     *
     * @param DOMDocument $dom DOM document
     * @param string $elementId ID of signed element
     * @param string $digest Digest value
     * @return DOMElement Signature element
     */
    private function createSignatureElement($dom, $elementId, $digest)
    {
        // Create Signature element
        $signature = $dom->createElementNS('http://www.w3.org/2000/09/xmldsig#', 'Signature');
        
        // Create SignedInfo
        $signedInfo = $dom->createElement('SignedInfo');
        $signature->appendChild($signedInfo);

        // CanonicalizationMethod
        $canonicalizationMethod = $dom->createElement('CanonicalizationMethod');
        $canonicalizationMethod->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $signedInfo->appendChild($canonicalizationMethod);

        // SignatureMethod
        $signatureMethod = $dom->createElement('SignatureMethod');
        $signatureMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#rsa-sha1');
        $signedInfo->appendChild($signatureMethod);

        // Reference
        $reference = $dom->createElement('Reference');
        $reference->setAttribute('URI', '#' . $elementId);
        $signedInfo->appendChild($reference);

        // Transforms
        $transforms = $dom->createElement('Transforms');
        $reference->appendChild($transforms);

        $transform = $dom->createElement('Transform');
        $transform->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#enveloped-signature');
        $transforms->appendChild($transform);

        $transform2 = $dom->createElement('Transform');
        $transform2->setAttribute('Algorithm', 'http://www.w3.org/TR/2001/REC-xml-c14n-20010315');
        $transforms->appendChild($transform2);

        // DigestMethod
        $digestMethod = $dom->createElement('DigestMethod');
        $digestMethod->setAttribute('Algorithm', 'http://www.w3.org/2000/09/xmldsig#sha1');
        $reference->appendChild($digestMethod);

        // DigestValue
        $digestValue = $dom->createElement('DigestValue', $digest);
        $reference->appendChild($digestValue);

        // SignatureValue (placeholder)
        $signatureValue = $dom->createElement('SignatureValue');
        $signature->appendChild($signatureValue);

        // KeyInfo
        $keyInfo = $dom->createElement('KeyInfo');
        $signature->appendChild($keyInfo);

        return $signature;
    }

    /**
     * Add certificate information to signature
     *
     * @param DOMElement $signature Signature element
     */
    private function addCertificateInfo($signature)
    {
        $dom = $signature->ownerDocument;
        $keyInfo = $signature->getElementsByTagName('KeyInfo')->item(0);

        // X509Data
        $x509Data = $dom->createElement('X509Data');
        $keyInfo->appendChild($x509Data);

        // X509Certificate
        $certPem = $this->certificateData['cert'];
        $certContent = str_replace(['-----BEGIN CERTIFICATE-----', '-----END CERTIFICATE-----', "\n", "\r"], '', $certPem);
        
        $x509Certificate = $dom->createElement('X509Certificate', $certContent);
        $x509Data->appendChild($x509Certificate);
    }

    /**
     * Sign RPS XML for NFSe generation
     *
     * @param string $rpsXML RPS XML content
     * @return string Signed RPS XML
     */
    public function signRPS($rpsXML)
    {
        // Load RPS XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        if (!$dom->loadXML($rpsXML)) {
            throw new Exception('Invalid RPS XML content');
        }

        // Find InfDeclaracaoPrestacaoServico element to ensure it has Id
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ns', 'http://www.betha.com.br/e-nota-contribuinte-ws');
        
        // Try with and without namespace
        $infElements = $xpath->query('//ns:InfDeclaracaoPrestacaoServico');
        if ($infElements->length === 0) {
            $infElements = $xpath->query('//InfDeclaracaoPrestacaoServico');
        }
        if ($infElements->length === 0) {
            throw new Exception('InfDeclaracaoPrestacaoServico element not found');
        }

        $infElement = $infElements->item(0);
        
        // Add Id attribute if not present
        if (!$infElement->hasAttribute('Id')) {
            $infElement->setAttribute('Id', 'InfDeclaracaoPrestacaoServico');
        }

        // Find the Rps element (parent of InfDeclaracaoPrestacaoServico)
        $rpsElements = $xpath->query('//ns:Rps');
        if ($rpsElements->length === 0) {
            $rpsElements = $xpath->query('//Rps');
        }
        if ($rpsElements->length === 0) {
            throw new Exception('Rps element not found');
        }

        $rpsElement = $rpsElements->item(0);

        // Create signature for InfDeclaracaoPrestacaoServico but place it in Rps
        $canonicalizedXML = $infElement->C14N(true, false);
        $digest = base64_encode(hash('sha1', $canonicalizedXML, true));

        // Create signature structure
        $signature = $this->createSignatureElement($dom, 'InfDeclaracaoPrestacaoServico', $digest);

        // Sign the SignedInfo
        $signedInfo = $signature->getElementsByTagName('SignedInfo')->item(0);
        $canonicalizedSignedInfo = $signedInfo->C14N(true, false);
        
        // Create signature value
        if (!openssl_sign($canonicalizedSignedInfo, $signatureValue, $this->privateKey, OPENSSL_ALGO_SHA1)) {
            throw new Exception('Failed to create digital signature');
        }

        // Set signature value
        $signatureValueElement = $signature->getElementsByTagName('SignatureValue')->item(0);
        $signatureValueElement->nodeValue = base64_encode($signatureValue);

        // Add X509 certificate data
        $this->addCertificateInfo($signature);

        // Append signature to the Rps element (not InfDeclaracaoPrestacaoServico)
        $rpsElement->appendChild($signature);

        // Save XML without declaration for embedding in SOAP
        $xmlOutput = $dom->saveXML();
        
        // Remove XML declaration if present (for CDATA embedding)
        if (strpos($xmlOutput, '<?xml') === 0) {
            $xmlOutput = preg_replace('/<\?xml[^>]*\?>\s*/', '', $xmlOutput);
        }

        return $xmlOutput;
    }

    /**
     * Sign cancellation request XML
     *
     * @param string $cancelXML Cancellation XML content
     * @return string Signed cancellation XML
     */
    public function signCancellation($cancelXML)
    {
        // Load cancellation XML
        $dom = new DOMDocument('1.0', 'UTF-8');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        
        if (!$dom->loadXML($cancelXML)) {
            throw new Exception('Invalid cancellation XML content');
        }

        // Find InfPedidoCancelamento element
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('ns', 'http://www.betha.com.br/e-nota-contribuinte-ws');
        
        // Try with and without namespace
        $infElements = $xpath->query('//ns:InfPedidoCancelamento');
        if ($infElements->length === 0) {
            $infElements = $xpath->query('//InfPedidoCancelamento');
        }
        
        if ($infElements->length === 0) {
            throw new Exception('InfPedidoCancelamento element not found');
        }

        $infElement = $infElements->item(0);
        
        // Add Id attribute if not present
        if (!$infElement->hasAttribute('Id')) {
            $infElement->setAttribute('Id', 'InfPedidoCancelamento');
        }

        // Find the Pedido element (parent of InfPedidoCancelamento)
        $pedidoElements = $xpath->query('//ns:Pedido');
        if ($pedidoElements->length === 0) {
            $pedidoElements = $xpath->query('//Pedido');
        }
        if ($pedidoElements->length === 0) {
            throw new Exception('Pedido element not found');
        }

        $pedidoElement = $pedidoElements->item(0);

        // Create signature for InfPedidoCancelamento but place it in Pedido
        $canonicalizedXML = $infElement->C14N(true, false);
        $digest = base64_encode(hash('sha1', $canonicalizedXML, true));

        // Create signature structure
        $signature = $this->createSignatureElement($dom, 'InfPedidoCancelamento', $digest);

        // Sign the SignedInfo
        $signedInfo = $signature->getElementsByTagName('SignedInfo')->item(0);
        $canonicalizedSignedInfo = $signedInfo->C14N(true, false);
        
        // Create signature value
        if (!openssl_sign($canonicalizedSignedInfo, $signatureValue, $this->privateKey, OPENSSL_ALGO_SHA1)) {
            throw new Exception('Failed to create digital signature');
        }

        // Set signature value
        $signatureValueElement = $signature->getElementsByTagName('SignatureValue')->item(0);
        $signatureValueElement->nodeValue = base64_encode($signatureValue);

        // Add X509 certificate data
        $this->addCertificateInfo($signature);

        // Append signature to the Pedido element (not InfPedidoCancelamento)
        $pedidoElement->appendChild($signature);

        // Save XML without declaration for embedding in SOAP
        $xmlOutput = $dom->saveXML();
        
        // Remove XML declaration if present (for CDATA embedding)
        if (strpos($xmlOutput, '<?xml') === 0) {
            $xmlOutput = preg_replace('/<\?xml[^>]*\?>\s*/', '', $xmlOutput);
        }

        return $xmlOutput;
    }

    /**
     * Verify XML signature
     *
     * @param string $signedXML Signed XML content
     * @return bool True if signature is valid
     */
    public function verifySignature($signedXML)
    {
        try {
            $dom = new DOMDocument();
            $dom->loadXML($signedXML);

            $xpath = new DOMXPath($dom);
            $xpath->registerNamespace('ds', 'http://www.w3.org/2000/09/xmldsig#');

            // Get signature elements
            $signatureNodes = $xpath->query('//ds:Signature');
            if ($signatureNodes->length === 0) {
                return false;
            }

            $signature = $signatureNodes->item(0);
            $signedInfo = $xpath->query('ds:SignedInfo', $signature)->item(0);
            $signatureValue = $xpath->query('ds:SignatureValue', $signature)->item(0);

            // Get canonicalized SignedInfo
            $canonicalizedSignedInfo = $signedInfo->C14N(true, false);
            
            // Decode signature value
            $signatureValueBinary = base64_decode($signatureValue->nodeValue);

            // Verify signature
            $result = openssl_verify($canonicalizedSignedInfo, $signatureValueBinary, $this->publicKey, OPENSSL_ALGO_SHA1);
            
            return $result === 1;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get certificate information
     *
     * @return array Certificate information
     */
    public function getCertificateInfo()
    {
        return openssl_x509_parse($this->certificateData['cert']);
    }

    /**
     * Cleanup resources
     */
    public function __destruct()
    {
        // Note: openssl_pkey_free() is deprecated in PHP 8.0+
        // Resources are automatically freed when variables go out of scope
    }
} 
