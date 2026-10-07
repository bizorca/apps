<?php
declare(strict_types=1);

/**
 * Fetches nearby organizations using the OpenStreetMap Overpass API.
 * Free, no API key required. Results cached in api_cache for 30 days.
 *
 * Returns array of org candidates:
 *   [['name' => string, 'org_type' => string, 'place_id' => string, 'vicinity' => string], ...]
 * Returns null on failure.
 */
function fetchNearbyOrgs(float $lat, float $lng, int $radiusMeters = 16000): ?array
{
    $cacheKey = "overpass:{$lat},{$lng}:{$radiusMeters}";
    $cached   = getCachedApiResponse($cacheKey);
    if ($cached !== null) return $cached;

    // Overpass QL — one union query covering all org types we care about.
    // Each node/way/relation gets an OSM tag that maps to our org_type.
    $query = <<<OQL
[out:json][timeout:30];
(
  node["amenity"="school"](around:{$radiusMeters},{$lat},{$lng});
  way["amenity"="school"](around:{$radiusMeters},{$lat},{$lng});
  node["amenity"="college"](around:{$radiusMeters},{$lat},{$lng});
  way["amenity"="college"](around:{$radiusMeters},{$lat},{$lng});
  node["amenity"="university"](around:{$radiusMeters},{$lat},{$lng});
  way["amenity"="university"](around:{$radiusMeters},{$lat},{$lng});
  node["amenity"="hospital"](around:{$radiusMeters},{$lat},{$lng});
  way["amenity"="hospital"](around:{$radiusMeters},{$lat},{$lng});
  node["amenity"="clinic"](around:{$radiusMeters},{$lat},{$lng});
  node["amenity"="doctors"](around:{$radiusMeters},{$lat},{$lng});
  node["amenity"="physiotherapist"](around:{$radiusMeters},{$lat},{$lng});
  node["healthcare"="physiotherapist"](around:{$radiusMeters},{$lat},{$lng});
  node["amenity"="place_of_worship"](around:{$radiusMeters},{$lat},{$lng});
  way["amenity"="place_of_worship"](around:{$radiusMeters},{$lat},{$lng});
  node["office"="company"](around:{$radiusMeters},{$lat},{$lng});
  way["office"="company"](around:{$radiusMeters},{$lat},{$lng});
  node["office"="government"](around:{$radiusMeters},{$lat},{$lng});
  way["office"="government"](around:{$radiusMeters},{$lat},{$lng});
  node["office"="financial"](around:{$radiusMeters},{$lat},{$lng});
  way["office"="financial"](around:{$radiusMeters},{$lat},{$lng});
  node["amenity"="social_facility"]["social_facility"~"nursing_home|assisted_living|group_home"](around:{$radiusMeters},{$lat},{$lng});
  way["amenity"="social_facility"]["social_facility"~"nursing_home|assisted_living|group_home"](around:{$radiusMeters},{$lat},{$lng});
);
out center tags;
OQL;

    $ch = curl_init('https://overpass-api.de/api/interpreter');
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'data=' . urlencode($query),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 35,
        CURLOPT_USERAGENT      => 'ProForma/1.0 (proforma.bizorca.com; mailto:jassen@bizorca.com)',
        CURLOPT_HTTPHEADER     => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) return null;

    $data = json_decode($response, true);
    if (!isset($data['elements'])) return null;

    $results = [];
    $seen    = [];

    foreach ($data['elements'] as $el) {
        $tags = $el['tags'] ?? [];
        $name = $tags['name'] ?? ($tags['operator'] ?? null);
        if (!$name) continue;

        // De-duplicate by name (OSM has nodes + ways for the same place)
        $dedupeKey = strtolower(trim($name));
        if (isset($seen[$dedupeKey])) continue;
        $seen[$dedupeKey] = true;

        $orgType  = osmTagsToOrgType($tags);
        $vicinity = buildVicinity($tags);

        // Stable surrogate ID from element type + OSM id (no Google dependency)
        $placeId = 'osm:' . ($el['type'] ?? 'node') . ':' . ($el['id'] ?? '0');

        $results[] = [
            'name'     => $name,
            'org_type' => $orgType,
            'place_id' => $placeId,
            'vicinity' => $vicinity,
        ];
    }

    // Cap at 30 total to keep things manageable, prioritize named entities
    $results = array_slice($results, 0, 30);

    setCachedApiResponse($cacheKey, $results, 30 * 24 * 60 * 60);
    return $results;
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

function osmTagsToOrgType(array $tags): string
{
    $amenity  = $tags['amenity']  ?? '';
    $office   = $tags['office']   ?? '';
    $social   = $tags['social_facility'] ?? '';
    $healthcare = $tags['healthcare'] ?? '';

    if (in_array($amenity, ['school', 'college', 'university'], true)) return 'school';
    if (in_array($amenity, ['hospital', 'clinic', 'doctors', 'physiotherapist'], true)) return 'medical';
    if ($healthcare === 'physiotherapist') return 'medical';
    if ($amenity === 'place_of_worship') return 'church';
    if ($amenity === 'social_facility' && $social !== '') return 'senior_living';
    if (in_array($office, ['company', 'government', 'financial', 'insurance', 'lawyer', 'accountant'], true)) return 'corporate';

    return 'other';
}

function buildVicinity(array $tags): string
{
    $parts = array_filter([
        $tags['addr:housenumber'] ?? '',
        $tags['addr:street']      ?? '',
        $tags['addr:city']        ?? '',
    ]);
    return implode(', ', $parts);
}

/**
 * Geocode an address string via Nominatim (OpenStreetMap).
 * Returns ['lat' => float, 'lng' => float] or null.
 * This function is used as a fallback when Google Geocoding is not available.
 */
function googleGeocode(string $address): ?array
{
    // Google key not configured — delegate to Nominatim (already in CensusClient.php
    // as the Nominatim fallback inside geocodeZip). Return null here so the caller
    // falls through to the Census/Nominatim path.
    if (!defined('GOOGLE_PLACES_API_KEY') || GOOGLE_PLACES_API_KEY === '') {
        return null;
    }

    $cacheKey = 'geocode:google:' . md5($address);
    $cached   = getCachedApiResponse($cacheKey);
    if ($cached !== null) return $cached;

    $url = 'https://maps.googleapis.com/maps/api/geocode/json?'
         . http_build_query(['address' => $address, 'key' => GOOGLE_PLACES_API_KEY]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $httpCode !== 200) return null;

    $data = json_decode($response, true);
    $loc  = $data['results'][0]['geometry']['location'] ?? null;
    if (!$loc) return null;

    $result = ['lat' => (float)$loc['lat'], 'lng' => (float)$loc['lng']];
    setCachedApiResponse($cacheKey, $result, 90 * 24 * 60 * 60);
    return $result;
}
