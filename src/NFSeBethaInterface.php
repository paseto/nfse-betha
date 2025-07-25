<?php

namespace Paseto\NFSeBetha;

/**
 * Interface for NFSe Betha Service
 *
 * Defines the contract for NFSe webservice operations based on nfse_v202.xsd
 *
 * @package Paseto\NFSeBetha
 * @author Paseto Team
 * @version 2.0.0
 * @license MIT
 */
interface NFSeBethaInterface
{
    /**
     * Consult NFSe by service provider
     *
     * @param array $params Consultation parameters
     * @return array|false Response array or false on failure
     */
    public function consultarNfseServicoPrestado($params);

    /**
     * Consult NFSe by range
     *
     * @param array $params Range consultation parameters
     * @return array|false Response array or false on failure
     */
    public function consultarNfseFaixa($params);

    /**
     * Generate NFSe from RPS
     *
     * @param array $rpsData RPS data for NFSe generation
     * @return array|false Response array or false on failure
     */
    public function gerarNfse($rpsData);

    /**
     * Cancel NFSe
     *
     * @param array $cancelData Cancellation data
     * @return array|false Response array or false on failure
     */
    public function cancelarNfse($cancelData);

    /**
     * Send RPS batch
     *
     * @param array $loteData Batch data
     * @return array|false Response array or false on failure
     */
    public function enviarLoteRps($loteData);

    /**
     * Consult RPS batch status
     *
     * @param array $params Batch consultation parameters
     * @return array|false Response array or false on failure
     */
    public function consultarLoteRps($params);

    /**
     * Get the last error message
     *
     * @return string|null Last error message
     */
    public function getLastError();

    /**
     * Test connection to the webservice
     *
     * @return array Test results
     */
    public function testConnection();
}
