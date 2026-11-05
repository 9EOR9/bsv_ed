<?php

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;

/** @var \Joomla\Registry\Registry $params */
/** @var array $result */
/** @var \Joomla\CMS\Application\CMSApplicationInterface $app */

$h = static fn ($v): string => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

// Schachpunkte: 4.5 -> "4½", 0.5 -> "½", 3.0 -> "3"
$punkte = static function ($v): string {
    $f    = (float) $v;
    $int  = (int) floor($f);
    $half = abs($f - $int - 0.5) < 0.001;

    if ($half) {
        return ($int > 0 ? (string) $int : '') . '½';
    }

    return number_format($f, $f == floor($f) ? 0 : 1, ',', '');
};

$highlight = trim((string) $params->get('highlight', ''));
$isOwn     = static fn (string $name): bool => $highlight !== '' && mb_stripos($name, $highlight) !== false;
$sfx       = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');
$user      = $app->getIdentity();
$isAdmin   = $user && $user->authorise('core.admin');
?>
<div class="mod-bsv-ergebnisse<?php echo $sfx ? ' ' . $sfx : ''; ?>">
<?php if (isset($result['error'])) : ?>
    <div class="alert alert-warning">
        <?php echo Text::_($result['error'] === 'notfound' ? 'MOD_BSV_ERGEBNISSE_NOTFOUND' : 'MOD_BSV_ERGEBNISSE_UNAVAILABLE'); ?>
        <?php if ($isAdmin) : ?>
            <br><small><?php echo Text::_('MOD_BSV_ERGEBNISSE_ADMIN_DETAIL'); ?> <?php echo $h($result['detail'] ?? ''); ?></small>
        <?php endif; ?>
    </div>

<?php elseif ($result['type'] === 'tabelle') : $t = $result['data']; ?>
    <div class="table-responsive">
        <table class="table table-sm table-striped">
            <caption><?php echo $h($t['Liga_Name'] ?? ''); ?> (<?php echo $h($t['Saison_Name'] ?? ''); ?>)</caption>
            <thead>
                <tr>
                    <th scope="col"><?php echo Text::_('MOD_BSV_ERGEBNISSE_COL_PLATZ'); ?></th>
                    <th scope="col"><?php echo Text::_('MOD_BSV_ERGEBNISSE_COL_MANNSCHAFT'); ?></th>
                    <th scope="col" class="text-end"><?php echo Text::_('MOD_BSV_ERGEBNISSE_COL_SPIELE'); ?></th>
                    <th scope="col" class="text-end"><?php echo Text::_('MOD_BSV_ERGEBNISSE_COL_MP'); ?></th>
                    <th scope="col" class="text-end"><?php echo Text::_('MOD_BSV_ERGEBNISSE_COL_BP'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($t['Tabelle_Daten'] ?? [] as $z) :
                    $name = (string) ($z['Tabelle_Mannschaft_Name'] ?? ''); ?>
                    <tr<?php echo $isOwn($name) ? ' class="table-primary fw-bold"' : ''; ?>>
                        <td><?php echo $h($z['Tabelle_Platz'] ?? ''); ?></td>
                        <td><?php echo $h($name); ?></td>
                        <td class="text-end"><?php echo $h($z['Tabelle_Spiele'] ?? ''); ?></td>
                        <td class="text-end"><?php echo $h($punkte($z['Tabelle_MP'] ?? 0)); ?></td>
                        <td class="text-end"><?php echo $h($punkte($z['Tabelle_BP'] ?? 0)); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

<?php else : $r = $result['data']; $einzel = (bool) $params->get('einzel', 0); ?>
    <h4><?php echo Text::sprintf('MOD_BSV_ERGEBNISSE_RUNDE_N', (int) ($r['Runde'] ?? 0)); ?></h4>
    <?php foreach ($r['Begegnungen'] ?? [] as $b) :
        $heim = (string) ($b['Heim_Mannschaft'] ?? '');
        $gast = (string) ($b['Gast_Mannschaft'] ?? ''); ?>
        <div class="mb-3<?php echo ($isOwn($heim) || $isOwn($gast)) ? ' fw-bold' : ''; ?>">
            <div>
                <?php echo $h($heim); ?> – <?php echo $h($gast); ?>
                <span class="float-end"><?php echo $h($punkte($b['Heim_Brettpunkte'] ?? 0)); ?> : <?php echo $h($punkte($b['Gast_Brettpunkte'] ?? 0)); ?></span>
            </div>
            <?php if ($einzel && !empty($b['Einzelergebnisse'])) : ?>
                <table class="table table-sm small mb-0 fw-normal">
                    <?php foreach ($b['Einzelergebnisse'] as $e) : ?>
                        <tr>
                            <td><?php echo $h($e['Brett'] ?? ''); ?></td>
                            <td><?php echo $h($e['Ergebnis_Heim_Spieler_Nachname'] ?? ''); ?></td>
                            <td class="text-center"><?php echo $h($e['Ergebnis_Heim_Ergebnis'] ?? ''); ?> : <?php echo $h($e['Ergebnis_Gast_Ergebnis'] ?? ''); ?></td>
                            <td><?php echo $h($e['Ergebnis_Gast_Spieler_Nachname'] ?? ''); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>
