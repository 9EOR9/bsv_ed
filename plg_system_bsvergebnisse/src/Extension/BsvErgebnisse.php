<?php

namespace Bsv\Plugin\System\Bsvergebnisse\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;

/**
 * Dieses Plugin hat keine eigene Funktion. Es dient nur dazu, die Zugangsdaten
 * zum Ergebnisdienst (Adresse und API-Key) einmal zentral abzulegen.
 * Das Modul "BSV Ergebnisdienst" liest sie aus den Plugin-Parametern.
 */
final class BsvErgebnisse extends CMSPlugin
{
}
