<?php
/**
 * BCV — Servicio de tasa de cambio USD/VES
 * Extrae la tasa oficial del Banco Central de Venezuela (bcv.org.ve)
 * y la almacena en caché (archivo) por 2 horas.
 */

define('BCV_CACHE_FILE', sys_get_temp_dir() . '/TENKI_bcv_rate.json');
define('BCV_CACHE_TTL',  7200); // 2 horas en segundos

/**
 * Retorna la tasa USD→VES publicada por el BCV.
 * Si no puede obtenerla, devuelve null.
 */
function getBcvRate(): ?float {
    // 1. Intentar caché válida
    if (file_exists(BCV_CACHE_FILE)) {
        $cached = json_decode(file_get_contents(BCV_CACHE_FILE), true);
        if ($cached && isset($cached['rate'], $cached['ts'])) {
            if ((time() - $cached['ts']) < BCV_CACHE_TTL) {
                return (float)$cached['rate'];
            }
        }
    }

    // 2. Hacer petición al BCV
    $rate = fetchBcvRate();

    // 3. Guardar en caché solo si se obtuvo la tasa
    if ($rate !== null) {
        file_put_contents(BCV_CACHE_FILE, json_encode([
            'rate' => $rate,
            'ts'   => time(),
        ]));
    } elseif (file_exists(BCV_CACHE_FILE)) {
        // Si falla la petición, usar la caché aunque esté vencida
        $cached = json_decode(file_get_contents(BCV_CACHE_FILE), true);
        if ($cached && isset($cached['rate'])) {
            return (float)$cached['rate'];
        }
    }

    return $rate;
}

/**
 * Hace scraping al sitio del BCV para extraer la tasa del dólar.
 * El BCV publica la tasa en un elemento con id="dolar" dentro de
 * su sección de tipos de cambio en la página principal.
 */
function fetchBcvRate(): ?float {
    $url = 'https://www.bcv.org.ve/';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER     => ['Accept-Language: es-VE,es;q=0.9'],
    ]);

    $html = curl_exec($ch);
    $err  = curl_error($ch);
    curl_close($ch);

    if (!$html || $err) {
        return null;
    }

    // Parsear el HTML silenciosamente
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    // El bloque del dólar: <div id="dolar"> ... <strong>XX,XXXX</strong>
    $nodes = $xpath->query('//*[@id="dolar"]//strong');
    if ($nodes && $nodes->length > 0) {
        $raw  = trim($nodes->item(0)->textContent);
        // El BCV usa coma como separador decimal, ej: "91,6730"
        $rate = (float)str_replace(',', '.', $raw);
        if ($rate > 0) {
            return $rate;
        }
    }

    return null;
}

/**
 * Formatea un monto en bolívares con separador de miles y 2 decimales.
 */
function formatBs(float $amount): string {
    return 'Bs. ' . number_format($amount, 2, ',', '.');
}

/**
 * Retorna la fecha/hora de la última actualización del caché.
 */
function getBcvCacheDate(): ?string {
    if (!file_exists(BCV_CACHE_FILE)) return null;
    $cached = json_decode(file_get_contents(BCV_CACHE_FILE), true);
    if (!$cached || !isset($cached['ts'])) return null;
    return date('d/m/Y H:i', $cached['ts']);
}
