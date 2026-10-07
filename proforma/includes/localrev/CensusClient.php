<?php
declare(strict_types=1);

/**
 * Fetches demographic data from the US Census ACS 5-year estimates.
 * No API key required. Results are cached in api_cache for 90 days.
 *
 * Returns array with keys: population, median_income (may be null if unavailable)
 * Returns null on failure.
 */
function fetchCensusData(string $zip): ?array
{
    if (!preg_match('/^\d{5}$/', $zip)) return null;

    $cacheKey = 'census:acs5:' . $zip;
    $cached   = getCachedApiResponse($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    // ACS 5-year estimates — B19013_001E = median household income, B01001_001E = total population
    $url = 'https://api.census.gov/data/2022/acs/acs5?'
         . http_build_query([
               'get' => 'B19013_001E,B01001_001E',
               'for' => 'zip code tabulation area:' . $zip,
           ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        CURLOPT_USERAGENT      => 'ProForma/1.0 (proforma.bizorca.com)',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) return null;

    $rows = json_decode($response, true);
    // Response: [["B19013_001E","B01001_001E","zip code tabulation area"], [income, pop, zip]]
    if (!is_array($rows) || count($rows) < 2 || !isset($rows[1][0], $rows[1][1])) return null;

    $income = (int)$rows[1][0];
    $pop    = (int)$rows[1][1];

    // Census returns -666666666 for missing/suppressed data
    $result = [
        'population'    => $pop > 0 ? $pop : null,
        'median_income' => ($income > 0) ? $income : null,
    ];

    // Cache for 90 days (Census data is annual)
    setCachedApiResponse($cacheKey, $result, 90 * 24 * 60 * 60);

    return $result;
}

/**
 * Geocodes a ZIP code to lat/lng using the US Census Geocoder.
 * Falls back to Nominatim (OpenStreetMap) if Census geocoder fails.
 * Returns ['lat' => float, 'lng' => float] or null.
 */
function geocodeZip(string $zip, string $state = ''): ?array
{
    if (!preg_match('/^\d{5}$/', $zip)) return null;

    $cacheKey = 'geocode:zip:' . $zip;
    $cached   = getCachedApiResponse($cacheKey);
    if ($cached !== null) {
        return $cached;
    }

    // Try Census Geocoder first (free, US government, reliable for US ZIPs)
    $address = $zip . ($state !== '' ? ', ' . $state : '');
    $url = 'https://geocoding.geo.census.gov/geocoder/locations/onelineaddress?'
         . http_build_query([
               'address'   => $address,
               'benchmark' => 'Public_AR_Current',
               'format'    => 'json',
           ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'ProForma/1.0 (proforma.bizorca.com)',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $data = json_decode($response, true);
        $matches = $data['result']['addressMatches'] ?? [];
        if (!empty($matches[0]['coordinates'])) {
            $coords = $matches[0]['coordinates'];
            $result = ['lat' => (float)$coords['y'], 'lng' => (float)$coords['x']];
            setCachedApiResponse($cacheKey, $result, 90 * 24 * 60 * 60);
            return $result;
        }
    }

    // Fallback: Nominatim (OpenStreetMap) — requires polite user-agent and rate limit
    $nominatimUrl = 'https://nominatim.openstreetmap.org/search?'
                  . http_build_query([
                        'postalcode' => $zip,
                        'country'    => 'US',
                        'format'     => 'json',
                        'limit'      => 1,
                    ]);

    $ch = curl_init($nominatimUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_USERAGENT      => 'ProForma/1.0 (proforma.bizorca.com; mailto:jassen@bizorca.com)',
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response !== false && $httpCode === 200) {
        $data = json_decode($response, true);
        if (!empty($data[0]['lat']) && !empty($data[0]['lon'])) {
            $result = ['lat' => (float)$data[0]['lat'], 'lng' => (float)$data[0]['lon']];
            setCachedApiResponse($cacheKey, $result, 90 * 24 * 60 * 60);
            return $result;
        }
    }

    return null;
}
