#!/usr/bin/php -q
<?php
/*
Autor: cleber.campanel@interop.com.br
Data: 29-07-2016
*/

putenv("ORACLE_HOME=/usr/lib/oracle/11.1/client");
putenv("TNS_ADMIN=/usr/lib/oracle");
putenv("NLS_LANG=AMERICAN_AMERICA.AL32UTF8");

function main() {
	$code = 3;
	$msg = '';
	$perf = '';

	$opts = get_options();
	//var_dump($opts);

	if($opts['optional']){
		$optionalMsg = $opts['optional']." ";//se existir opcional add a mensagem
		$optionalPerf = $opts['optional']."_";//se existir opcional add ao perf
	}
	$msg = strtoupper($opts['mode'])." ";

	$query = getQuery($opts['mode'], $opts['optional']);
	var_dump($query);

	$retorno = execQuery($opts, $query);
	var_dump($retorno);

	if (count($retorno) > 1){//valida retorno da query
		var_dump($retorno);
		quit(3,'The query must contain only one column to return');
	}

	reset($retorno); //coloca o ponteiro para a primeiro elemento do array
	$bar = each($retorno); //extrai o key e o valor

	//se for uma string ele ira usar o comparador de string senao ira usar a funcao metric
	if(!is_numeric($bar[value])){ 

		if($bar[value] == $opts['equal']){
			$code = 0;
			$msg .= 'Return equal to the argument "'.$opts['equal'].'" ';
		}else{
			$code = 2;
			$msg .= 'Return Unlike the argument "'.$opts['equal'].'" ';
		}

		if(!$opts['equal']){
			$msg = 'Add -e argument to compare with the return of query => ';
			$code = 3;
		}

	}else {
		$code = metric($bar['value'], $opts['warning'], $opts['critical']);//verifica qual a metrica
		$perf = " | '".$optionalPerf.$bar['key']."'=".$bar['value'].";". $opts['warning'].";".$opts['critical'].";;";
	}

	$msg .= $optionalMsg.$bar['key'].":".$bar['value'];
	
	quit($code,$msg.$perf);
}	

function execQuery($conection, $query) {
	$arr = array();

	if (!$conn=oci_connect($conection['user'],$conection['password'],$conection['instance'])) {
		$err = OCIError();
	  quit("Erro ao conectar ao Oracle-> " .$err[message],3);
		
	}
	$stid = oci_parse($conn, $query);
	oci_execute($stid);

	while (($row = oci_fetch_array($stid, OCI_ASSOC))) {
    	$arr[] = $row;
 	}
	oci_free_statement($stid);
	oci_close($conn);
	//var_dump($arr);
	if (count($arr) <= 1){
		$arr = $arr[0];
	}
	return $arr;
}

function getQuery($mode, $aux = null){

	$querys = array();
	
	$querys['teste'] ='SELECT name,trunc((free_mb*100)/total_mb) free_space_percent from v$asm_diskgroup';

	$querys['backup-disk'] = 'SELECT DECODE ( COUNT(STATUS), 0, \'FAILED\', \'COMPLETED\' ) status
		FROM V$RMAN_BACKUP_JOB_DETAILS
		WHERE TO_CHAR (START_TIME, \'RRRRMMDD\') = TO_CHAR (SYSDATE, \'RRRRMMDD\')
			AND STATUS = \'COMPLETED\'
			AND INPUT_TYPE <> \'ARCHIVELOG\'
			AND OUTPUT_DEVICE_TYPE=\'DISK\'
			AND NOT EXISTS
				(SELECT STATUS
				FROM V$RMAN_BACKUP_JOB_DETAILS
				WHERE TO_CHAR (START_TIME, \'RRRRMMDD\') = TO_CHAR (SYSDATE, \'RRRRMMDD\')
					AND OUTPUT_DEVICE_TYPE=\'DISK\'
					AND INPUT_TYPE <> \'ARCHIVELOG\'
					AND STATUS = \'FAILED\')';

	$querys['backup-tape'] = 'SELECT DECODE ( COUNT(STATUS), 0, \'FAILED\', \'COMPLETED\' ) STATUS
		FROM V$RMAN_BACKUP_JOB_DETAILS
		WHERE TO_CHAR (START_TIME, \'RRRRMMDD\') = TO_CHAR (SYSDATE-1, \'RRRRMMDD\')
			AND STATUS = \'COMPLETED\'
			AND INPUT_TYPE <> \'ARCHIVELOG\'
			AND OUTPUT_DEVICE_TYPE=\'SBT_TAPE\'
			AND NOT EXISTS
				(SELECT STATUS
				FROM V$RMAN_BACKUP_JOB_DETAILS
				WHERE TO_CHAR (START_TIME, \'RRRRMMDD\') = TO_CHAR (SYSDATE-1, \'RRRRMMDD\')
					AND OUTPUT_DEVICE_TYPE=\'SBT_TAPE\'
					AND INPUT_TYPE <> \'ARCHIVELOG\'
					AND STATUS = \'FAILED\')';

	$querys['backup-archived-disk'] = 'SELECT DECODE ( COUNT(STATUS), 0, \'FAILED\', \'COMPLETED\' ) STATUS
		FROM V$RMAN_BACKUP_JOB_DETAILS
		WHERE TO_CHAR (START_TIME, \'RRRRMMDD\') = TO_CHAR (SYSDATE, \'RRRRMMDD\')
			AND STATUS = \'COMPLETED\'
			AND INPUT_TYPE = \'ARCHIVELOG\'
			AND OUTPUT_DEVICE_TYPE=\'DISK\'
			AND NOT EXISTS
				(SELECT STATUS
				FROM V$RMAN_BACKUP_JOB_DETAILS
				WHERE TO_CHAR (START_TIME, \'RRRRMMDD\') = TO_CHAR (SYSDATE, \'RRRRMMDD\')
					AND OUTPUT_DEVICE_TYPE=\'DISK\'
					AND INPUT_TYPE = \'ARCHIVELOG\'
					AND STATUS = \'FAILED\')';

	$querys['backup-archived-tape'] = 'SELECT DECODE ( COUNT(STATUS), 0, \'FAILED\', \'COMPLETED\' ) STATUS
		FROM V$RMAN_BACKUP_JOB_DETAILS
		WHERE TO_CHAR (START_TIME, \'RRRRMMDD\') = TO_CHAR (SYSDATE, \'RRRRMMDD\')
			AND STATUS = \'COMPLETED\'
			AND INPUT_TYPE = \'ARCHIVELOG\'
			AND OUTPUT_DEVICE_TYPE=\'SBT_TAPE\'
			AND NOT EXISTS
				(SELECT STATUS
				FROM V$RMAN_BACKUP_JOB_DETAILS
				WHERE TO_CHAR (START_TIME, \'RRRRMMDD\') = TO_CHAR (SYSDATE, \'RRRRMMDD\')
					AND OUTPUT_DEVICE_TYPE=\'SBT_TAPE\'
					AND INPUT_TYPE = \'ARCHIVELOG\'
					AND STATUS = \'FAILED\')';
	
	$querys['jobs-new-scheduler'] = 'SELECT COUNT (*) TOTAL
		FROM dba_scheduler_job_log
		WHERE log_date BETWEEN SYSDATE - 1 AND SYSDATE
			AND operation = \'RUN\'
			AND status <> \'SUCCEEDED\'';
	
	$querys['jobs-old-scheduler'] = 'SELECT COUNT(*) TOTAL
		FROM dba_jobs
		WHERE NVL(failures, 0) <> 0';
	
	$querys['disk-space-group'] = 'SELECT trunc((free_mb*100)/total_mb) free_space_percent from v$asm_diskgroup where name = \''.$aux.'\'';
	
	$querys['blocked-users'] = 'SELECT count(*) total from gv$session where blocking_session is not null';
	
	$querys['corrupted-blocks'] = 'SELECT count(*) as total from v$database_block_corruption';
	
	$querys['errors-alert-log'] = 'SELECT count(*) total
		from v$diag_incident
		where create_time between sysdate-1 and sysdate
			order by create_time desc';
	
	$querys['time-spending-database-sessions-waits'] = 'WITH total_time_waited AS
		(SELECT inst_id,
		SUM(time_waited) total
		FROM gv$session_event
		WHERE wait_class <> \'Idle\'
		GROUP BY inst_id
		),
		sum_time_waited AS
		(SELECT inst_id,
		wait_class,
		SUM(time_waited) total
		FROM gv$session_event
		WHERE wait_class <> \'Idle\'
		GROUP BY inst_id,
		wait_class
		)
		SELECT a.inst_id,
		a.wait_class,
		TRUNC((a.total*100)/
		(SELECT b.total FROM total_time_waited b WHERE a.inst_id=b.inst_id
		)) wait_class_percent
		FROM sum_time_waited a
		WHERE a.wait_class IN (\'Administrative\', \'Application\', \'Commit\', \'Concurrency\', \'Configuration\', \'Network\')
		ORDER BY 3 DESC';
	
	$querys['logons-per-second'] = 'SELECT trunc(average) media
		from gv$sysmetric_summary
		where metric_name like \'%Logons Per Sec%\'';

	return $querys[$mode];
}

function get_options() {
    $shortopts  = "I:U:P:m:w:c:o:e:h";
    $longopts  = array(
        "instance:",
        "user:",
        "password:",
        "mode:",
        "warning:",
        "critical:",
        "optional",
        "equal",
        "help"
        );
    $options = getopt($shortopts, $longopts);

    if(count($options) == 0 || isset($options['h'])){
       help(); 
    }

    $long_opts  = array(
    	"I" => "instance",
        "U" => "user",
        "P" => "password",
        "m" => "mode",
        "w" => "warning",
        "c" => "critical",
        "o" => "optional",
        "e" => "equal",
        "h" => "help"
        );

    foreach ($options as $key => $value) {
        if(array_key_exists($key, $long_opts)){
            $options[$long_opts[$key]] = $value;
            unset($options[$key]);
        }
    }
    
    return $options;
}


function help() {
	$basename = str_replace(".php", "", basename($_SERVER[PHP_SELF]));
	$texto = "./$basename -I... ";
	quit($texto, 3);	
}

function quit($code, $mgs) {

	// opmon exit states
	$stateCodeNagios = array(
		'OK'		=> 0,
		'WARNING'	=> 1,
		'CRITICAL'	=> 2,
		'UNKNOWN '	=> 3);

	$state_names = array(
		0 => 'OK',
		1 => 'WARNING',
		2 => 'CRITICAL',
		3 => 'UNKNOWN');
	
	if ( is_numeric($code) ){
		echo $state_names[$code]." - ".$mgs,"\n";
		exit($code);
	}

	echo $code." - ".$mgs,"\n";
	exit($stateCodeNagios[$code]);
}


function metric($value, $warning, $critical) {

	if(!$warning and !$critical ){
		return 'OK';
	}

	if($warning and !$critical ){
		if ($value >= $warning) {
			return 'WARNING';
		}elseif ($value < $warning) {
			return 'OK';
		}else {
			quit( "UNKNOWN", "Unable to check.");
		}
	}

	if(!$warning and $critical ){
		if ($value >= $critical) {
			return 'CRITICAL';
		}elseif ($value < $critical) {
			return 'OK';
		}else {
			quit( "UNKNOWN", "Unable to check.");
		}
	}
	
	if($warning <= $critical){
		if ($value >= $critical) {
			return 'CRITICAL';
		}elseif ($value >= $warning) {
			return 'WARNING';
		}elseif ($value < $warning) {
			return 'OK';
		}else {
			quit( "UNKNOWN", "Unable to check.");
		}
	}elseif ($warning > $critical){
		if ($value <= $critical) {
			return 'CRITICAL';
		}elseif ($value <= $warning) {
			return 'WARNING';
		}elseif($value > $warning) {
			return 'OK';
		}else {
			quit( "UNKNOWN", "Unable to check.");
		}
	}
}

main();
?>
