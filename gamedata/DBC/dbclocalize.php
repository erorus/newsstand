<?php
require_once('../incl/old.incl.php');
require_once('dbcdecode.php');

$LOCALES = ['dede','eses','frfr','itit','ptbr','ruru'];

header('Content-type: text/plain');
error_reporting(E_ALL);

DBConnect();

$tables = array();
dtecho(run_sql('set session max_heap_table_size='.(1024*1024*1024)));

foreach ($LOCALES as $locale) {
    dtecho("Starting locale $locale");

    foreach ($tables as $tbl) {
        run_sql('drop temporary table `ttbl'.$tbl.'`');
    }
    $tables = array();

    $dirnm = 'current/' . substr($locale, 0, 2) . strtoupper(substr($locale, 2, 2));

    dtecho(dbcdecode('Item-sparse', array(
                1=>'id',
                71=>'name')));

    dtecho(run_sql('delete from `ttblItem-sparse` where id not in (select id from tblDBCItem)'));
    dtecho(run_sql("insert into tblDBCItem (id, name_$locale) (select id, name from `ttblItem-sparse`) on duplicate key update tblDBCItem.name_$locale=ifnull(values(name_$locale),tblDBCItem.name_$locale)"));
}

/* */
dtecho("Done.\n ");

function dtecho($msg) {
	if ($msg == '') return;
	if (substr($msg, -1, 1)=="\n") $msg = substr($msg, 0, -1);
	echo "\n".Date('H:i:s').' '.$msg;
}

foreach ($tables as $tbl) {
	run_sql('drop temporary table `ttbl'.$tbl.'`');
}
cleanup('');
