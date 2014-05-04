<?php
include('config.php');

// Plane mode : +CFUN=0,0
// Exit plane mode : +CFUN=1,1
// Query plane mode : +CFUN?

// +WS46 : 12, 22, 25*

// Enable unsollicited creg : +CREG=2 (not working?)
// creg stat: 0=not_reg 1=reg_home 2=notreg_searching 3=reg_Denied 4=unknown 5=reg_roaming
// AT+CREG result: current_Setting,creg_stat,location_id,cell_id

// CSUD
///// AT+CSUD=1,"#123#",15 (send msg)
///// +CUSD: 1,"Service indisponible. Merci de vous reconnecter ulterieurement.Appuyez sur repondre,tapez 1,envoyez
///// 1:Menu
///// 2:Suivi conso+
///// 3:Aide",15
// Multiline possible Oo
// Code at the beginning meaning if answer required (1)
///// AT+CSUD=2 (close session)

// SMS
// AT+CNMI 3,3,2,1,0
//
// Unsollicited result : +CMTI ME|SM,index

// AT+CGATT? = Got GPRS?

$modem_network_types = array(
	4 => '900/1800MHz (Europe)',
	5 => '900/1900MHz (USA)',
);

function getok($fd) {
	$dat = array();
	while(1) {
		$lin = fgets($fd);
		if ($lin === false) die();
		$lin = rtrim($lin);
		if($lin == '') continue;
		if (strpos($lin, 'OK') !== false) {
//			echo '< OK'."\n";
			return $dat;
		}
		if (strpos($lin, 'ERROR') !== false) die($lin);
		$dat[] = $lin;
//		echo '< '.$lin."\n";
	}
}

function getresult($res, $prefix) {
	$prefix .= ':';
	$r = array();
	$l = strlen($prefix);
	foreach($res as $lin) {
		if (substr($lin, 0, $l) == $prefix) $r[] = ltrim(substr($lin, $l));
	}
	return $r;
}

function getfresult($res, $prefix) {
	$prefix .= ':';
	$l = strlen($prefix);
	foreach($res as $lin) {
		if (substr($lin, 0, $l) == $prefix) return ltrim(substr($lin, $l));
	}
	return false;
}

function runcmd($fd, $cmd) {
//	echo '> AT'.$cmd."\n";
	fputs($fd, 'AT'.$cmd."\r\n");
	return getok($fd);
}

$fd = fopen('/dev/noz2', 'r+');

// init
runcmd($fd, '');  // check for modem presence
runcmd($fd, 'Z'); // simple reset
runcmd($fd, '&F'); // factory reset
runcmd($fd, 'E0V1&D2&C1S0=0'); // configure stuff

list($product_manufacturer) = runcmd($fd, '+CGMI');
list($product_name) = runcmd($fd, '+CGMM');
list($product_revision) = runcmd($fd, '+CGMR');
list($tmp) = runcmd($fd, '+CGSN');
list($imei, $serial) = explode(',', $tmp);
// check for a PN lock
$lock = getfresult(runcmd($fd, '+CLCK="PN",2'), '+CLCK');

echo 'Found a '.$product_manufacturer.' '.$product_name.' modem.'."\n";
echo 'Revision : '. $product_revision."\n";
echo 'Serial : '.$serial.' - IMEI : '. $imei . "\n";
if ($lock) echo "NB: This modem is network-locked. It will not work with other provider's SIM cards\n";
echo "\n";

// auth loop
while(1) {
	fputs($fd, "AT+CPIN?\r\n");
	$res = getok($fd);
	list($auth) = getresult($res, '+CPIN');
	if ($auth == 'READY') break;
	if (!isset($modem_auth[$auth])) die("Authentification impossible. Missing code for $auth\n");
	echo 'Sending code for '.$auth."...\n";
	fputs($fd, "AT+CPIN=\"".$modem_auth[$auth]."\"\r\n");
	getok($fd);
}

echo "Ready.\n\n";
while(1) {
	list($tmp) = getresult(runcmd($fd, '+COPS?'), '+COPS');
	list(,,$net_name, $net_type) = explode(',', $tmp);
	$net_name = str_replace('"', '', $net_name);

	list($tmp) = getresult(runcmd($fd, '+CSQ'), '+CSQ');
	list($cur, $max) = explode(',', $tmp);
	$level = round($cur*100/31).'%';

	$type = getfresult(runcmd($fd, '_OSBM?'), '_OSBM');
	if (isset($modem_network_types[$type])) {
		$type = $modem_network_types[$type];
	} else {
		$type = 'Unknown'.$type;
	}

	list(,$reg) = explode(',', getfresult(runcmd($fd, '+CREG?'), '+CREG'));
	if ($reg == 1) $reg='home';
	if ($reg == 2) $reg='restricted';

	echo 'Net: '.$net_name.' ('.$level.' '.$type.' '.$reg.')          '."\r";
	sleep(4);
}

