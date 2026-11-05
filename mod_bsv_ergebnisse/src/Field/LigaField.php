<?php

namespace Bsv\Module\Ergebnisse\Site\Field;

\defined('_JEXEC') or die;

use Bsv\Module\Ergebnisse\Site\Helper\ApiFactory;
use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\GroupedlistField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

/**
 * Auswahlliste aller veröffentlichten Ligen, gruppiert nach Saison.
 * Die Daten kommen live aus der API (mit kurzem Zwischenspeicher).
 */
class LigaField extends GroupedlistField
{
    protected $type = 'Liga';

    protected function getGroups(): array
    {
        $groups = ['' => [HTMLHelper::_('select.option', '', Text::_('MOD_BSV_ERGEBNISSE_LIGA_SELECT'))]];

        try {
            $ligen = ApiFactory::create()->ligen()['Liga_Daten'] ?? [];
        } catch (\Throwable $e) {
            error_log('[mod_bsv_ergebnisse] ' . $e->getMessage());
            Factory::getApplication()->enqueueMessage(
                Text::sprintf('MOD_BSV_ERGEBNISSE_LIGA_LOAD_ERROR', htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')),
                'warning'
            );

            return $groups;
        }

        foreach ($ligen as $liga) {
            $saison = (string) ($liga['Saison_Name'] ?? '');
            $groups[$saison][] = HTMLHelper::_('select.option', (string) (int) $liga['Liga_ID'], (string) $liga['Liga_Name']);
        }

        return $groups;
    }
}
