<?php

namespace Bsv\Module\Ergebnisse\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\Registry\Registry;

class ErgebnisseHelper
{
    /**
     * Holt die Daten über den API-Client und liefert sie fürs Template.
     * Rückgabe: ['type' => 'tabelle'|'runde', 'data' => array]  oder  ['error' => string, 'detail' => string]
     */
    public function getData(Registry $params): array
    {
        $liga = (int) $params->get('liga', 0);

        if ($liga <= 0) {
            return ['error' => 'config', 'detail' => 'Im Modul ist keine Liga ausgewählt.'];
        }

        try {
            $api = ApiFactory::create(['ttl_results' => max(10, (int) $params->get('cache_ttl', 60))]);

            if ($params->get('anzeige', 'tabelle') === 'runde') {
                return [
                    'type' => 'runde',
                    'data' => $api->runde($liga, max(1, (int) $params->get('runde', 1)), max(1, (int) $params->get('dg', 1))),
                ];
            }

            return ['type' => 'tabelle', 'data' => $api->tabelle($liga)];
        } catch (\Throwable $e) {
            // Details ins Log, Besucher sehen nur eine neutrale Meldung (Admins sehen die Ursache)
            error_log('[mod_bsv_ergebnisse] ' . $e->getMessage());

            $status = $e instanceof BsvApiException ? $e->getHttpStatus() : 0;

            return ['error' => $status === 404 ? 'notfound' : 'api', 'detail' => $e->getMessage()];
        }
    }
}
