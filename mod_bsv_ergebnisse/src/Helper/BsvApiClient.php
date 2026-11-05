<?php
declare(strict_types=1);

namespace Bsv\Module\Ergebnisse\Site\Helper;

\defined('_JEXEC') or die;

/**
 * Client für die JSON-API des Ergebnisdienstes (Berliner Schachverband).
 *
 * Nur serverseitig verwenden: Der API-Key darf nie im Browser (JavaScript, HTML)
 * oder in einer URL landen.
 *
 * Beispiel:
 *   $api = new BsvApiClient('https://www.example.de/json', $key, ['cache_dir' => '/tmp/bsv']);
 *   $tabelle = $api->tabelle(51);
 *   foreach ($tabelle['Tabelle_Daten'] as $zeile) { ... }
 *
 * Optionen:
 *   timeout        Sekunden pro Abruf (Standard 10)
 *   cache_dir      Verzeichnis für den Cache; ohne Angabe kein Cache
 *   ttl_results    Cache-Dauer für Tabelle/Runde in Sekunden (Standard 60)
 *   ttl_lists      Cache-Dauer für Saisons/Ligen in Sekunden (Standard 300)
 *   stale_on_error veraltete Cache-Daten ausliefern, wenn die API nicht erreichbar ist
 *                  oder das Limit erreicht ist (Standard true)
 *   allow_http     unverschlüsselte Verbindung erlauben, nur für Tests (Standard false)
 */
final class BsvApiClient
{
    private string $baseUrl;
    private string $apiKey;
    private int $timeout;
    private ?string $cacheDir;
    private int $ttlResults;
    private int $ttlLists;
    private bool $staleOnError;

    public function __construct(string $baseUrl, string $apiKey, array $options = [])
    {
        if ($apiKey === '') {
            throw new \InvalidArgumentException('API-Key fehlt.');
        }

        $this->baseUrl = rtrim($baseUrl, '/');

        if (!str_starts_with($this->baseUrl, 'https://') && empty($options['allow_http'])) {
            throw new \InvalidArgumentException('Nur HTTPS erlaubt (für Tests Option allow_http verwenden).');
        }

        $this->apiKey       = $apiKey;
        $this->timeout      = (int) ($options['timeout'] ?? 10);
        $this->cacheDir     = $options['cache_dir'] ?? null;
        $this->ttlResults   = (int) ($options['ttl_results'] ?? 60);
        $this->ttlLists     = (int) ($options['ttl_lists'] ?? 300);
        $this->staleOnError = (bool) ($options['stale_on_error'] ?? true);
    }

    /** Veröffentlichte Saisons. Ergebnis: ['Saison_Daten' => [['Saison_ID'=>..,'Saison_Name'=>..], ...]] */
    public function saisons(): array
    {
        return $this->get('saisons', [], $this->ttlLists);
    }

    /** Veröffentlichte Ligen, optional einer Saison. Ergebnis: ['Liga_Daten' => [...]] */
    public function ligen(?int $saison = null): array
    {
        return $this->get('ligen', $saison ? ['saison' => $saison] : [], $this->ttlLists);
    }

    /** Tabelle einer Liga, optional bis zu einer Runde. Ergebnis: ['Tabelle_Daten' => [...], ...] */
    public function tabelle(int $liga, ?int $runde = null): array
    {
        $params = ['liga' => $liga];
        if ($runde) {
            $params['runde'] = $runde;
        }

        return $this->get('tabelle', $params, $this->ttlResults);
    }

    /** Begegnungen und Einzelergebnisse einer Runde. Ergebnis: ['Begegnungen' => [...], ...] */
    public function runde(int $liga, int $runde, int $durchgang = 1, ?int $saison = null): array
    {
        $params = ['liga' => $liga, 'runde' => $runde, 'dg' => $durchgang];
        if ($saison) {
            $params['saison'] = $saison;
        }

        return $this->get('runde', $params, $this->ttlResults);
    }

    // ------------------------------------------------------------------

    private function get(string $script, array $params, int $ttl): array
    {
        $url       = $this->baseUrl . '/' . $script . '.php' . ($params ? '?' . http_build_query($params) : '');
        $cacheFile = $this->cacheDir !== null ? rtrim($this->cacheDir, '/') . '/bsvapi_' . sha1($url) . '.json' : null;
        $cached    = $this->readCache($cacheFile);

        if ($cached !== null && (time() - $cached['time']) < $ttl) {
            return $cached['data'];
        }

        try {
            $data = $this->request($url);
        } catch (BsvApiException $e) {
            // Veraltete Daten nur bei vorübergehenden Problemen; bei 401/403/404
            // (Key gesperrt, Liga nicht mehr veröffentlicht) soll der Fehler sichtbar werden.
            $temporary = $e->getHttpStatus() === 0 || $e->getHttpStatus() === 429 || $e->getHttpStatus() >= 500;
            if ($cached !== null && $this->staleOnError && $temporary) {
                return $cached['data'];
            }
            throw $e;
        }

        $this->writeCache($cacheFile, $data);

        return $data;
    }

    private function request(string $url): array
    {
        $retryAfter = null;

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['X-API-Key: ' . $this->apiKey, 'Accept: application/json'],
            CURLOPT_TIMEOUT        => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_FOLLOWLOCATION => false, // Key nicht an eine Weiterleitung senden
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$retryAfter): int {
                if (stripos($line, 'Retry-After:') === 0) {
                    $retryAfter = (int) trim(substr($line, 12));
                }
                return strlen($line);
            },
        ]);

        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($ch);

        if ($body === false) {
            throw new BsvApiException('Verbindung zur API fehlgeschlagen: ' . $error, 0);
        }

        try {
            $json = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BsvApiException('Ungültige Antwort der API (HTTP ' . $status . ').', $status, null, $e);
        }

        if ($status !== 200 || !is_array($json) || ($json['status'] ?? '') !== 'success') {
            // Fehlerantworten enthalten "error" (Key-Prüfung) oder "message" (Skripte)
            $message = is_array($json) ? (string) ($json['error'] ?? $json['message'] ?? 'Unbekannter Fehler') : 'Unbekannter Fehler';
            throw new BsvApiException('API-Fehler (HTTP ' . $status . '): ' . $message, $status, $retryAfter);
        }

        return $json;
    }

    private function readCache(?string $file): ?array
    {
        if ($file === null || !is_file($file)) {
            return null;
        }

        $raw = @file_get_contents($file);
        if ($raw === false) {
            return null;
        }

        $cache = json_decode($raw, true);

        return (is_array($cache) && isset($cache['time'], $cache['data'])) ? $cache : null;
    }

    private function writeCache(?string $file, array $data): void
    {
        if ($file === null) {
            return;
        }

        $dir = dirname($file);
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }

        // Atomar schreiben, damit parallele Requests keine halben Dateien lesen
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, json_encode(['time' => time(), 'data' => $data], JSON_UNESCAPED_UNICODE)) !== false) {
            @rename($tmp, $file);
        }
    }
}
