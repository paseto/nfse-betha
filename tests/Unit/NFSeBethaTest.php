<?php

namespace Paseto\NFSeBetha\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Paseto\NFSeBetha\NFSeBetha;
use Paseto\NFSeBetha\NFSeBethaInterface;

/**
 * Unit Tests for NFSeBetha Class
 */
class NFSeBethaTest extends TestCase
{
    /**
     * Test that NFSeBetha implements the correct interface
     */
    public function testImplementsInterface()
    {
        $this->assertTrue(
            in_array(NFSeBethaInterface::class, class_implements(NFSeBetha::class))
        );
    }

    /**
     * Test constructor throws exception for invalid certificate path
     */
    public function testConstructorThrowsExceptionForInvalidCertificatePath()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Certificate file not found');

        new NFSeBetha('/invalid/path/certificate.pfx', 'password');
    }

    /**
     * Test that all required methods exist
     */
    public function testRequiredMethodsExist()
    {
        $methods = [
            'consultarNfseServicoPrestado',
            'consultarNfseFaixa',
            'gerarNfse',
            'cancelarNfse',
            'enviarLoteRps',
            'consultarLoteRps',
            'getLastError',
            'testConnection'
        ];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(NFSeBetha::class, $method),
                "Method {$method} does not exist"
            );
        }
    }

    /**
     * Test constants are defined
     */
    public function testConstantsAreDefined()
    {
        $this->assertTrue(defined('Paseto\NFSeBetha\NFSeBetha::LOCAL_WSDL'));
        $this->assertTrue(defined('Paseto\NFSeBetha\NFSeBetha::SERVICE_URL'));
        $this->assertTrue(defined('Paseto\NFSeBetha\NFSeBetha::NAMESPACE_URI'));
    }

    /**
     * Test constants have expected values
     */
    public function testConstantValues()
    {
        $this->assertEquals(
            'wsdl/modified_wsdl.xml',
            NFSeBetha::LOCAL_WSDL
        );
        
        $this->assertEquals(
            'https://nota-eletronica.betha.cloud/rps/ws',
            NFSeBetha::SERVICE_URL
        );
        
        $this->assertEquals(
            'http://www.betha.com.br/e-nota-contribuinte-ws',
            NFSeBetha::NAMESPACE_URI
        );
    }
} 
