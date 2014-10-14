<?php

require_once('../incl/old.incl.php');

$dirnm = 'current';

function printprogresspercent($px) {
	static $lastpct = 100;
	if (php_sapi_name() != 'cli') return;
	$p = round($px);
	if ($lastpct == $p) return;
	
	if ($lastpct != 100) fwrite(STDERR, str_repeat(chr(8),5));
	$lastpct = $p;
	fwrite(STDERR, " ".str_pad("$p%",4));
}

function dbcdecode($filenmpart,$fields,$typehints=array()) {
	global $tables, $dirnm;
	$dbctype = '';
	$filenm = $dirnm.'/'.$filenmpart.'.dbc';
	if (!file_exists($filenm)) $filenm = $dirnm.'/'.$filenmpart.'.db2';
	
	if (($f = fopen($filenm,'rb')) === false) {
		fwrite(STDERR,"Could not open $filenm\n");
		return;
	}
	$dbctype = fread($f,4);
	$headersize = 20;
	$postheadersize = 0;
	switch ($dbctype) {
		case 'WDBC':
			$headersize = 20;
			break;
		case 'WDB2':
			$headersize = 48;
			break;
		default:
			fwrite(STDERR, "$filenm not a WDBC/WDB2 file.\n");
			fclose($f);
			return;
	}
	list($numrecords,$numfields,$recsize,$stringblocksize) = array_values(unpack('l*',fread($f,16)));
	if ($dbctype == 'WDB2') {
		list($tablehash,$build,$unk1,$unk2,$unk3,$locale,$unk5) = array_values(unpack('L2a/l5b',fread($f,7*4)));
		if ($unk3 > 0) $postheadersize = $unk3*6 - $headersize*2; //($unk3 * 4 - $headersize) + ($unk3 * 2 - $headersize * 2);
	}

	$totalsizecalc = ($stringblocksize + ($numrecords * $recsize) + $headersize + $postheadersize);
	$totalsizereal = filesize($filenm);
	$summary = "File: $filenm\nHeader size: $headersize\nRecords: $numrecords\nFields: $numfields\nRecord size: $recsize\nString block: $stringblocksize\nValues block: ".($numrecords * $recsize)."\nTotal calc size: $totalsizecalc\nTotal real size: ".(filesize($filenm))."\n";
	if ($dbctype == 'WDB2') $summary .= "Tablehash: $tablehash\nBuild: $build\nUnk3: $unk3\n";
	if ($totalsizecalc != $totalsizereal) {
		fwrite(STDERR, "$summary\nFile size does not check out: Size is ".$totalsizereal.", by headers should be ".$totalsizecalc."\n");
		fclose($f);
		return;
	}
	if ($recsize != ($numfields * 4)) {
		fwrite(STDERR, "$summary\nRecord size != Field Count * 4 (".($numfields * 4).")\n");
		if ($recsize % 4 == 0) {
			$numfields = $recsize / 4;
			fwrite(STDERR, "Fixing Field Count to $numfields\n");
		} else {
			fclose($f);
			return;
		}
	}

	if (function_exists('dtecho')) dtecho('Loading '.$filenmpart.'      ');

	$tbl = rand(1000,9999);

	$sql = "create temporary table ttblNum$tbl (";
	for ($x = 1; $x <= $numfields; $x++) $sql .= (($x > 1)?',':'')."n$x int ";
	$sql .= ") type=heap;";
	fwrite(STDERR, run_sql($sql));
	
	fseek($f,$headersize+$postheadersize);
	$sql = '';
	for ($x = 0; $x < $numrecords; $x++) {
		$sql .= (($sql == '')?'':',').'('.implode(',',unpack('l*',fread($f,$recsize))).')';
		if ($x % 20 == 0) {
			printprogresspercent($x/$numrecords*100);
			fwrite(STDERR, run_sql("insert into ttblNum$tbl values $sql"));
			$sql = '';
		}
	}
	if ($sql != '') fwrite(STDERR, run_sql("insert into ttblNum$tbl values $sql"));
	printprogresspercent(100);

	run_sql("create temporary table ttblStr$tbl (offset int unsigned, srcstring varchar(65000), primary key using hash (offset))");
	run_sql("insert into ttblStr$tbl values (0,'')");
	fseek($f,$headersize+$postheadersize+($numrecords*$recsize));
	$stringblock = '';
	while ((!feof($f)) && ($stringblocksize > strlen($stringblock))) {
		$stringblock .= fread($f,min(32768,($stringblocksize-strlen($stringblock))));
	}	
	fclose($f);


	$lastpos = 0;
	$x = 0;
	while (($z = strpos($stringblock, chr(0), $lastpos)) !== false) {
		$tok = substr($stringblock, $lastpos, $z - $lastpos);
		run_sql("replace into ttblStr$tbl values ($lastpos,'".sql_esc($tok)."');");
		$lastpos = $z+1;
		printprogresspercent($z/$stringblocksize*100);
	}
	printprogresspercent(100);
	unset($stringblock);

/*
	$tokens = explode(chr(0), $stringblock);

/*
echo "<pre>";
print_r($tokens);
echo "</pre>";
*/
/*	unset($stringblock);
	$x = 1;
	for ($z = 0; $z < count($tokens); $z++) {
		if (($z == 1) && ($tokens[1] == $tokens[0]))
			continue;
		run_sql("insert into ttblStr$tbl values ($x,'".sql_esc($tokens[$z])."');");
		$x += strlen($tokens[$z])+1;
		printprogresspercent($x/$stringblocksize*100);
	}
	unset($tokens);
	printprogresspercent(100);

/*	$rst = get_rst("select * from ttblStr$tbl order by offset limit 5");
	while ($row = next_row($rst)) print_r($row);
	
	$rst = get_rst("select * from ttblNum$tbl limit 5");
	while ($row = next_row($rst)) print_r($row);

	echo "<pre>";
	$rst = get_rst("select * from ttblStr$tbl where offset > 0 order by 1");
	while ($row = next_row($rst))
		print_r($row);
	echo "</pre>";
*/
	

	$cols = array();
	$stringlengths = array();
	$doStrings = !isset($_GET['nostring']);
	for ($x = 1; $x <= $numfields; $x++) {
		if (isset($typehints[$x]))
		{
			$cols[$x] = $typehints[$x];
			continue;
		}
		$row = get_single_row("select $x as colnum, max(n$x) m, sum(if(n$x=0,1,0)) zeroes, sum(if(n$x<=2,1,0)) smalls, sum(if(n$x > 8388608, 1, 0)) floatguess, sum((select if(count(*)=0,0,1) from ttblStr$tbl where offset=n$x)) u from ttblNum$tbl");
		$cols[$x] = "number";
		if ($doStrings && (intval($row['m']) <= $stringblocksize) && ($numrecords == $row['u']) && ($row['zeroes'] != $numrecords) && ($row['smalls'] != $numrecords)) {
			$cols[$x] = "string";
			$r2 = get_single_row("select max(length(srcstring))+1 mxln from ttblStr$tbl, ttblNum$tbl where offset=n$x");
			$stringlengths[$x] = $r2['mxln'];
		}
		else if ($numrecords == ($row['floatguess'] + $row['zeroes']))
			$cols[$x] = 'float';
		
		printprogresspercent($x/$numfields*100);
	}
	printprogresspercent(100);
	
	if (gettype($fields) == 'string') {
		if (php_sapi_name() == 'cli') {
			for ($x = 1; $x <= $numfields; $x++) echo ($x>1?',':'')."\"{$cols[$x]}\"";
			echo "\n";
			$rst = get_rst("select * from ttblNum$tbl");
			while ($row = next_row($rst)) {
				for ($x = 1; $x <= $numfields; $x++) 
					echo ($x>1?',':'').(($cols[$x]=='string')?('"'.str_replace('"','""',getdbcstring($row["n$x"],$tbl)).'"'):(($cols[$x] == 'float') ? number_format(array_pop(unpack("f", pack("i",intval($row["n$x"],10)))), 8) : $row["n$x"]));
				echo "\n";
			}
		} else {
			echo '<html><head><title>'.htmlspecialchars($filenmpart).'</title>';
			echo <<<EOF
<style type="text/css">
body {color: black; background-color: white}
* {font-size: 9pt}
th {background-color: #CCC}
td {border: 1px solid black; border-color: #999 #ccc white white}
.num {text-align: right; font-family: monospace}
</style>		
			
EOF;
			echo '</head><body>'.str_replace("\n","<br>",htmlspecialchars($summary)).'<br><table cellspacing="0" cellpadding="2"><tr>';
			$where = '';
			for ($x = 1; $x <= $numfields; $x++) 
			{
				echo '<th>'.$x.'</th>';
				if (isset($_GET['col'.$x]))
					$where .= (($where == '') ? ' where ' : ' and ') . "n$x='".sql_esc($_GET['col'.$x]).'\'';
			}
			$rst = get_rst("select * from ttblNum$tbl$where");
			$c = 0; $dofull = isset($_GET['full']);
			while ($row = next_row($rst)) {
				if (($c++ > 2000) && (!$dofull)) break;
				for ($x = 1; $x <= $numfields; $x++) 
					echo ($x>1?'</td><td':'</td></tr><tr><td').(($cols[$x]=='string')?('>'.htmlspecialchars(getdbcstring($row["n$x"],$tbl))):(' class="num">'.(($cols[$x] == 'float') ? number_format(array_pop(unpack("f", pack("i",intval($row["n$x"],10)))), 8) : $row["n$x"])));
			}
			echo '</tr></table>';
			if (($c >= 2000) && (!$dofull)) echo '<br><a href="?f='.urlencode($_GET['f']).'&full=1">Full</a>';
			echo '</body></html>';
		}
		run_sql("drop temporary table ttblNum$tbl");
		run_sql("drop temporary table ttblStr$tbl");
		return;
	}
	
	$sql = 'create temporary table `ttbl'.$filenmpart.'` (';
	$y = 0;
	$idxfields = array();
	$stringcount = 1;
	$gotfloat = false;
	foreach ($fields as $col=>$val) {
		if ($y++ > 0) $sql .= ',';
		$sql .= $val.' ';
		if (strtolower(substr($val,-2))=='id') $idxfields[]=$val;
		switch ($cols[$col]) {
			case 'number':
				$sql .= 'int';
				break;
			case 'long':
				$sql .= 'int';
				break;
			case 'float':
				$sql .= 'float';
				$gotfloat = true;
				break;
			case 'flags':
				$sql .= 'bigint';
				break;
			case 'string':
				$sql .= 'varchar('.$stringlengths[$col].')';
				$stringcount++;
				break;
			default:
				fwrite(STDERR, "$summary\nUnknown column type: {$cols[$col]}\n");
				cleanup();
		}
	}
	foreach ($idxfields as $idx) $sql .= ', index using hash ('.$idx.')';
	$sql .= ') type=heap;';	
	//dtecho("$sql");
	run_sql($sql);

	$x = 1;
	printprogresspercent($x/$stringcount*100);
	
	if (!$gotfloat)
	{
		$sql = 'insert into `ttbl'.$filenmpart.'` (';
		foreach ($fields as $col=>$val) $sql .= "$val,";
		$sql = substr($sql,0,strlen($sql)-1).') (select ';
		foreach ($fields as $col=>$val) $sql .= "n$col,";
		$sql = substr($sql,0,strlen($sql)-1)." from ttblNum$tbl)";

		run_sql($sql);
	}
	else
	{
		$sql = "select ";
		foreach ($fields as $col=>$val) $sql .= "n$col,";
		$sql = substr($sql,0,strlen($sql)-1)." from ttblNum$tbl";
		
		$rst = get_rst($sql);
		$sql = '';
		$rowcount = 0;
		while ($row = next_row($rst))
		{
			$rowcount++;
			if ($sql == '')
			{
				$sql = 'insert into `ttbl'.$filenmpart.'` (';
				foreach ($fields as $col=>$val) $sql .= "$val,";
				$sql = substr($sql,0,strlen($sql)-1).') values ';
			}
			else
				$sql .= ',';
			
			$sql .= '(';
			$zz = 0;
			foreach ($fields as $col=>$val)
				if ($cols[$col] == 'float')
					$sql .= ($zz++ == 0 ? '' : ',') . number_format(array_pop(unpack("f", pack("i",intval($row["n$col"],10)))), 12);
				else
					$sql .= ($zz++ == 0 ? '' : ',') . $row["n$col"];
			$sql .= ')';
			
			if (strlen($sql) > 20000)
			{
				run_sql($sql);
				$sql = '';
			}
			printprogresspercent($rowcount/$numrecords*100);
		}
		if ($sql != '')
			run_sql($sql);
		$sql = '';
		printprogresspercent(100);
	}
	run_sql("drop temporary table ttblNum$tbl");

	foreach ($fields as $col=>$val)
		if ($cols[$col] == 'string') {
			run_sql("update `ttbl$filenmpart` set $val=(select srcstring from ttblStr$tbl where offset=cast($val as signed))");
			printprogresspercent($x++/$stringcount*100);
		}

    printprogresspercent(100);

	run_sql("drop temporary table ttblStr$tbl");

	$tables[] = $filenmpart;
	$row = get_single_row('select count(*) n from `ttbl'.$filenmpart.'`');
	echo(' '.$row['n'].' rows');
	
}

function getdbcstring($offset,$tbl) {
	$row = get_single_row("select srcstring from ttblStr$tbl where offset='".sql_esc($offset)."'");
	if (!isset($row['srcstring'])) 
		return "$offset(?)";
	return $row['srcstring'];
}


if (substr($_SERVER['PHP_SELF'],-13) == 'dbcdecode.php') {
	if (php_sapi_name() != 'cli') {
		if (isset($_GET['d']) && file_exists($_GET['d']))
			$dirnm = $_GET['d'];
		if (isset($_GET['f'])) {
			$thefile = $_GET['f'];
		} else {
			$d = opendir($dirnm);
			$names = array();
			while ($f = readdir($d)) 
				if (preg_match('/\.db[c2]/i',strtolower(substr($f,-4))) > 0) 
					$names[] = substr($f,0,-4);
			sort($names);
			foreach ($names as $nm)
				echo '<a href="?f='.rawurlencode($nm).($dirnm != 'current' ? ('&d=' . $dirnm) : '').'">'.$nm.'</a><br>';
			closedir($d);
			cleanup();
		}
	} else {
		if (count($argv) < 2) {
			echo "Enter the DBC table (no path or extension) as an argument.\n";
			cleanup();
		}
		$thefile = $argv[1];
	}
	do_connect();
	run_sql('set session max_heap_table_size=268435456');
	$hints = array();
	for ($x = 0; $x < 50; $x++)
		if (isset($_GET["type$x"]))
			$hints[$x] = $_GET["type$x"];
	dbcdecode($thefile,'csv',$hints);
	cleanup();
}

/*
dbcdecode('Achievement',array(1=>'id',2=>'side',3=>'zoneid',4=>'previd',5=>'name',6=>'longdescr',7=>'catid',8=>'points',11=>'iconid'));
$rst = get_rst('select * from ttblAchievement limit 50');
while ($row = next_row($rst)) print_r($row);

cleanup();
*/
?>
