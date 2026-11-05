<?php

namespace Bsv\Module\Ergebnisse\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/**
 * Erzeugt den API-Client mit den zentral hinterlegten Zugangsdaten.
 */
class ApiFactory
{
    public static function create(array $options = []): BsvApiClient
    {
        $cfg = ConfigHelper::load();

        if ($cfg['base_url'] === '' || $cfg['api_key'] === '') {
            throw new \RuntimeException('Zugangsdaten fehlen: Plugin „System - BSV Ergebnisdienst“ öffnen und Adresse und API-Key eintragen.');
        }

        $cacheRoot = Factory::getApplication()->get('cache_path', JPATH_CACHE);

        return new BsvApiClient($cfg['base_url'], $cfg['api_key'], array_merge([
            'cache_dir'  => rtrim((string) $cacheRoot, '/\\') . '/mod_bsv_ergebnisse',
            'allow_http' => $cfg['allow_http'],
        ], $options));
    }
}
