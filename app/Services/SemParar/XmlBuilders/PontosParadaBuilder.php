<?php

namespace App\Services\SemParar\XmlBuilders;

/**
 * PontosParadaBuilder - XML builder for SemParar routing requests
 *
 * Based on Progress pontosParadaDset structure (SEMPARAR_AI_REFERENCE.md lines 335-373)
 * Builds complex nested XML datasets for SOAP calls
 */
class PontosParadaBuilder
{
    /**
     * Build pontosParada XML for roteirizarPracasPedagio
     *
     * Progress structure:
     *   pontosParada (status=0)
     *     -> pontoParada
     *       -> ponto[] (codigoIBGE, descricao, latLong)
     *         -> latLong (latitude, longitude)
     *
     * @param array $pontos Array of points with keys: cod_ibge, desc, latitude, longitude
     * @return string XML string
     */
    public static function buildPontosParadaXml(array $pontos): string
    {
        // Progress: CREATE pontosParada. ASSIGN pontosParada.status1 = 0.
        $xml = '<pontosParada xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">';
        $xml .= '<pontoParada>';

        // Progress: FOR EACH t-entrega: CREATE ponto...
        foreach ($pontos as $ponto) {
            $codIbge = $ponto['cod_ibge'] ?? 0;
            $descricao = htmlspecialchars($ponto['desc'] ?? '', ENT_XML1, 'UTF-8');
            $latitude = $ponto['latitude'] ?? 0;
            $longitude = $ponto['longitude'] ?? 0;

            $xml .= '<ponto>';
            $xml .= "<codigoIBGE>{$codIbge}</codigoIBGE>";
            $xml .= '<latLong>';
            $xml .= "<latitude>{$latitude}</latitude>";
            $xml .= "<longitude>{$longitude}</longitude>";
            $xml .= '</latLong>';
            $xml .= "<descricao>{$descricao}</descricao>";
            $xml .= '</ponto>';
        }

        $xml .= '</pontoParada>';
        $xml .= '<status>0</status>';
        $xml .= '</pontosParada>';

        return $xml;
    }

    /**
     * Build opcoesRota XML for roteirizarPracasPedagio
     *
     * Progress sends:
     *   <alternativas>false</alternativas>
     *   <status>0</status>
     *   <tipoRota>1</tipoRota>
     *
     * @param bool $alternativas Whether to return alternative routes
     * @param int $tipoRota Route type (1 = standard)
     * @return string XML string
     */
    public static function buildOpcoesRotaXml(bool $alternativas = false, int $tipoRota = 1): string
    {
        $alternativasStr = $alternativas ? 'true' : 'false';

        $xml = '<opcoesRota>';
        $xml .= "<alternativas>{$alternativasStr}</alternativas>";
        $xml .= '<status>0</status>';
        $xml .= "<tipoRota>{$tipoRota}</tipoRota>";
        $xml .= '</opcoesRota>';

        return $xml;
    }

    /**
     * Build pracas XML for cadastrarRotaTemporaria
     *
     * Progress: FOR EACH pracaPedagio: envia-Xml += "<id>" + STRING(pracaPedagio.id) + "</id>".
     *
     * @param array $pracas Array of toll plaza IDs
     * @return string XML string (just the IDs, no wrapper)
     */
    public static function buildPracasArrayXml(array $pracas): string
    {
        // SemParar expects ArrayOf_xsd_int, which PHP SoapClient sends as array
        // No XML wrapping needed - just return the array as-is for positional params
        return $pracas;
    }

    /**
     * Extract praça IDs from pracaPedagio dataset
     *
     * @param array $pracaPedagioArray Array of stdClass objects from SOAP response
     * @return array Array of integer IDs
     */
    public static function extractPracaIds(array $pracaPedagioArray): array
    {
        $ids = [];

        foreach ($pracaPedagioArray as $praca) {
            if (isset($praca->id)) {
                $ids[] = (int)$praca->id;
            }
        }

        return $ids;
    }

    /**
     * Parse pracaPedagio response from roteirizarPracasPedagio
     *
     * @param mixed $response SOAP response (stdClass or array)
     * @return array Parsed toll plazas with structure: [id, praca, rodovia, km, concessionaria, status]
     */
    public static function parsePracaPedagio($response): array
    {
        $pracas = [];

        // Response can be object with pracaPedagio property or direct array
        $pracaArray = null;

        if (is_object($response) && isset($response->pracaPedagio)) {
            $pracaArray = $response->pracaPedagio;
        } elseif (is_array($response)) {
            $pracaArray = $response;
        }

        if (!$pracaArray) {
            return [];
        }

        // Ensure it's an array (might be single object)
        if (!is_array($pracaArray)) {
            $pracaArray = [$pracaArray];
        }

        foreach ($pracaArray as $praca) {
            if (!is_object($praca)) {
                continue;
            }

            $pracas[] = [
                'id' => $praca->id ?? null,
                'praca' => $praca->praca ?? '',
                'rodovia' => $praca->rodovia ?? '',
                'km' => $praca->km ?? 0,
                'concessionaria' => $praca->concessionaria ?? '',
                'status' => $praca->status ?? 0
            ];
        }

        return $pracas;
    }
}
