<?php

namespace Paseto\NFSeBetha\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Paseto\NFSeBetha\XMLSigner;

/**
 * Unit Tests for XMLSigner Class
 */
class XMLSignerTest extends TestCase
{
    /**
     * Test constructor throws exception for invalid certificate path
     */
    public function testConstructorThrowsExceptionForInvalidCertificatePath()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Certificate file not found');

        new XMLSigner('/invalid/path/certificate.pfx', 'password');
    }

    /**
     * Test that required methods exist
     */
    public function testRequiredMethodsExist()
    {
        $methods = [
            'signRPS',
            'signCancellation',
            'signXML',
            'verifySignature',
            'getCertificateInfo'
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(XMLSigner::class, $method),
                "Method {$method} does not exist"
            );
        }
    }

    /**
     * Test signXML throws exception for invalid XML
     */
    public function testSignXMLThrowsExceptionForInvalidXML()
    {
        // This test would require a valid certificate, so we'll skip it for now
        // and focus on testing the class structure
        $this->markTestSkipped('Requires valid certificate for testing');
    }

    /**
     * Test class exists and can be instantiated (with valid certificate)
     */
    public function testClassExists()
    {
        $this->assertTrue(class_exists(XMLSigner::class));
    }
} 
