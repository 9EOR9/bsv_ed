<?php

namespace Bsv\Module\Ergebnisse\Site\Helper;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;

/**
 * Liest die zentral hinterlegten Zugangsdaten aus dem Plugin
 * "System - BSV Ergebnisdienst (Zugangsdaten)".
 * Es wird direkt aus der Datenbank gelesen, das Plugin muss daher nicht aktiviert sein.
 */
class ConfigHelper
{
    public static function load(): array
    {
        $db      = Factory::getContainer()->get(DatabaseInterface::class);
        $type    = 'plugin';
        $folder  = 'system';
        $element = 'bsvergebnisse';

        $query = $db->getQuery(true)
            ->select($db->quoteName('params'))
            ->from($db->quoteName('#__extensions'))
            ->where($db->quoteName('type') . ' = :type')
            ->where($db->quoteName('folder') . ' = :folder')
            ->where($db->quoteName('element') . ' = :element')
            ->bind(':type', $type)
            ->bind(':folder', $folder)
            ->bind(':element', $element);

        $params = new Registry((string) $db->setQuery($query)->loadResult());

        return [
            'base_url'   => trim((string) $params->get('base_url', '')),
            'api_key'    => trim((string) $params->get('api_key', '')),
            'allow_http' => (bool) $params->get('allow_http', 0),
        ];
    }
}
