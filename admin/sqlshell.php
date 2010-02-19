<?php
/**
 * Copyright 1999-2010 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL). If you
 * did not receive this file, see http://www.fsf.org/copyleft/lgpl.html.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 */

require_once dirname(__FILE__) . '/../lib/Application.php';
Horde_Registry::appInit('horde', array('admin' => true));

$title = _("SQL Shell");
Horde::addScriptFile('stripe.js', 'horde');
require HORDE_TEMPLATES . '/common-header.inc';
require HORDE_TEMPLATES . '/admin/menu.inc';

?>
<div>
<h1 class="header"><?php echo $title ?></h1><br />
<form name="sqlshell" action="sqlshell.php" method="post">
<?php Horde_Util::pformInput() ?>

<?php

$dbh = DB::connect($conf['sql']);
if (is_a($dbh, 'PEAR_Error')) {
    throw new Horde_Exception_Prior($dbh);
}
$dbh->setOption('portability', DB_PORTABILITY_LOWERCASE | DB_PORTABILITY_ERRORS);

if (Horde_Util::getFormData('list-tables')) {
    $description = 'LIST TABLES';
    $result = $dbh->getListOf('tables');
    $command = null;
} elseif (Horde_Util::getFormData('list-dbs')) {
    $description = 'LIST DATABASES';
    $result = $dbh->getListOf('databases');
    $command = null;
} elseif ($command = trim(Horde_Util::getFormData('sql'))) {
    // Keep a cache of prior queries for convenience.
    if (!isset($_SESSION['_sql_query_cache'])) {
        $_SESSION['_sql_query_cache'] = array();
    }
    if (($key = array_search($command, $_SESSION['_sql_query_cache'])) !== false) {
        unset($_SESSION['_sql_query_cache'][$key]);
    }
    array_unshift($_SESSION['_sql_query_cache'], $command);
    while (count($_SESSION['_sql_query_cache']) > 20) {
        array_pop($_SESSION['_sql_query_cache']);
    }

    // Parse out the query results.
    $result = $dbh->query(Horde_String::convertCharset($command, Horde_Nls::getCharset(), $conf['sql']['charset']));
}

if (isset($result)) {
    if ($command) {
        echo '<h1 class="header">' . _("Query") . '</h1><br /><pre class="text">' . htmlspecialchars($command) . '</pre>';
    }

    echo '<h1 class="header">' . _("Results") . '</h1><br />';

    if (is_a($result, 'PEAR_Error')) {
        echo '<pre class="text">'; var_dump($result); echo '</pre>';
    } else {
        if (is_object($result)) {
            echo '<table cellspacing="1" class="item striped">';
            $first = true;
            $i = 0;
            while ($row = $result->fetchRow(DB_FETCHMODE_ASSOC)) {
                if ($first) {
                    echo '<tr>';
                    foreach ($row as $key => $val) {
                        echo '<th align="left">' . (!strlen($key) ? '&nbsp;' : htmlspecialchars(Horde_String::convertCharset($key, $conf['sql']['charset']))) . '</th>';
                    }
                    echo '</tr>';
                    $first = false;
                }
                echo '<tr>';
                foreach ($row as $val) {
                    echo '<td class="fixed">' . (!strlen($val) ? '&nbsp;' : htmlspecialchars(Horde_String::convertCharset($val, $conf['sql']['charset']))) . '</td>';
                }
                echo '</tr>';
            }
            echo '</table>';
        } elseif (is_array($result)) {
            echo '<table cellspacing="1" class="item striped">';
            $first = true;
            $i = 0;
            foreach ($result as $val) {
                if ($first) {
                    echo '<tr><th align="left">' . (isset($description) ? htmlspecialchars($description) : '&nbsp;') . '</th></tr>';
                    $first = false;
                }
                echo '<tr><td class="fixed">' . (!strlen($val) ? '&nbsp;' : htmlspecialchars(Horde_String::convertCharset($val, $conf['sql']['charset']))) . '</td></tr>';
            }
            echo '</table>';
        } else {
            echo '<strong>' . _("Success") . '</strong>';
        }
    }

    echo '<br />';
}
?>

<?php if (isset($_SESSION['_sql_query_cache']) &&
          count($_SESSION['_sql_query_cache'])): ?>
  <label for="query_cache" class="hidden"><?php echo ("Query cache") ?></label>
  <select id="query_cache" name="query_cache" onchange="document.sqlshell.sql.value = document.sqlshell.query_cache[document.sqlshell.query_cache.selectedIndex].value;">
  <?php foreach ($_SESSION['_sql_query_cache'] as $query): ?>
    <option value="<?php echo htmlspecialchars($query) ?>"><?php echo htmlspecialchars($query) ?></option>
  <?php endforeach; ?>
  </select>
  <input type="button" value="<?php echo _("Paste") ?>" class="button" onclick="document.sqlshell.sql.value = document.sqlshell.query_cache[document.sqlshell.query_cache.selectedIndex].value;" />
  <input type="button" value="<?php echo _("Run") ?>" class="button" onclick="document.sqlshell.sql.value = document.sqlshell.query_cache[document.sqlshell.query_cache.selectedIndex].value; document.sqlshell.submit();" />
  <br />
<?php endif; ?>

<label for="sql" class="hidden"><?php echo ("SQL Query") ?></label>
<textarea class="fixed" id="sql" name="sql" rows="10" cols="80">
<?php if (strlen($command)) echo htmlspecialchars($command) ?></textarea>
<br />
<input type="submit" class="button" value="<?php echo _("Execute") ?>" />
<input type="button" class="button" value="<?php echo _("Clear Query") ?>" onclick="document.sqlshell.sql.value=''" />
<?php if (strlen($command)): ?>
<input type="reset" class="button" value="<?php echo _("Restore Last Query") ?>" />
<?php endif; ?>
<?php if ($dbh->getSpecialQuery('tables') !== null): ?><input type="submit" class="button" name="list-tables" value="<?php echo _("List Tables") ?>" /> <?php endif; ?>
<?php if ($dbh->getSpecialQuery('databases') !== null): ?><input type="submit" class="button" name="list-dbs" value="<?php echo _("List Databases") ?>" /> <?php endif; ?>
<?php echo Horde_Help::link('admin', 'admin-sqlshell') ?>

</form>
</div>
<?php

require HORDE_TEMPLATES . '/common-footer.inc';
