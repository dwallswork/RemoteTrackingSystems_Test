<?php
 require_once "AESCtrClass.include.php";
require_once "pwd.php";
require_once "vGsqlClass.include.php";
define("LOG_FILE","vgd.log");
  function log_message($msg) { 
	if ($msg===false) {
		unlink(LOG_FILE);
		return;
	}
	$logmsg = "[".date("D M d H:i:s Y")."] ";
	$logmsg.= "[vgd]  ";
	$logmsg.= sprintf("[%-22s]",basename($_SERVER["SCRIPT_NAME"]));
	$logmsg.= " [] $msg\n";
	$log=fopen(LOG_FILE,"a");
	fwrite($log,$logmsg);
	fclose($log);
	
  }
function ver(){
	$ver = "2.4j.beta12";
	return $ver;
}
function pwd() {
return "AAAAAAAAAABMEtkFyxXSHVP85Kugiw==";
}
function http_build_url($url,$replace) {
	$urlx = array_merge(parse_url($url),$replace);
	$new = $urlx["scheme"]."://".$urlx["host"];
	if (isset($urlx["port"])) $new.=":".$urlx["port"];
	$path = $urlx["path"];
	if (substr($path,0,1)!=DIRECTORY_SEPARATOR) $path = DIRECTORY_SEPARATOR.$path;
	$new.=$path;
	if (isset($urlx["query"])) $new.="?".$urlx["query"];
	return $new;	
}
function getUserLocation($sessionid = false){
	if (!$sessionid) $sessionid = $_COOKIE["sessionid"];
	$q = "select account.*,user.hier from session join user on session.user = user.id left join account on user.hier = account.name where session.id = $sessionid";
	if (false !== $result = mysql_query($q)
	  and false!==$row = mysql_fetch_array($result,MYSQL_ASSOC)) {
		$loc = $row["url"];
		if ($loc == DIRECTORY_SEPARATOR) $loc = "";
		$url = parse_url($_SERVER["SCRIPT_URI"]);
		if ($row["hier"]=="*" or $row["hier"]=="**") $row["defpage"] = "vMonitor";
		if ($url["path"]== DIRECTORY_SEPARATOR or is_dir(substr($url["path"],1)))
			$url["path"] = $loc.DIRECTORY_SEPARATOR.$row["defpage"];
		else {
			$path = pathinfo($url["path"]);
			if ($path["filename"]!=="vMfwd")
				$url["path"] = $loc.DIRECTORY_SEPARATOR.$path["filename"];
			else
				$url["path"] = $loc;
		}
		$loc = http_build_url($_SERVER["SCRIPT_URI"],$url);

	} else $loc = false;
	return $loc;
}

function validateUserLocation($sessionid = false){
	if (!$sessionid) $sessionid = $_COOKIE["sessionid"];
	$q = "select account.*,user.hier from session join user on session.user = user.id left join account on user.hier = account.name where session.id = $sessionid";
	if (false !== $result = mysql_query($q)
	  and false!==$row = mysql_fetch_array($result,MYSQL_ASSOC)) {
		$loc = $row["url"];
		$url = parse_url($_SERVER["SCRIPT_URI"]);
		if ($row["hier"]=="*" or $row["hier"]=="**")
			$valid = ("/vMonitor"==$url["path"])?true:false;
		else if ($url["path"]== DIRECTORY_SEPARATOR or is_dir(substr($url["path"],1)))
			$valid = ($loc==$url["path"])?true:false;
		else {
			$path = pathinfo($url["path"]);
			$valid=($path["dirname"]==$loc)?true:false;
		}
	} else $valid = false;
	return $valid;
}

function initShm($def = '/var/lib/vmd/vGateBTU.xml') {
	global $smData;
	$smData = array();
	$smData["key"] = ftok($def, 't');
	$smData["smid"] = shm_attach($smData["key"], 10000, 0644);
	$smData["semid"] = sem_get($smData["key"]);
	$smData['keys']['vGgeodPID']=1;
	$smData['keys']['vGgeodCheckCtrl']=2;
	$smData['keys']['vGgeodCheckCtrlStatus']=3;
}

function getShm($index,$useSem = true) {
	global $smData;
	if ($useSem) sem_acquire($smData["semid"]);
	$val = shm_get_var($smData["smid"],$smData['keys'][$index]);
	if ($useSem) sem_release($smData["semid"]);
	return $val;
}

function setShm($index,$val,$useSem = true) {
	global $smData;
	if ($useSem) sem_acquire($smData["semid"]);
	$val = shm_put_var($smData["smid"],$smData['keys'][$index],$val);
	if ($useSem) sem_release($smData["semid"]);
}

function getLic($path = '../vgd/vGnet.xml') {
	if (!is_file($path)
	  or !$net = simplexml_load_file($path)
	  or !isset($net->LicCode)) return false;
	$olic = $lic=(string) $net->LicCode;
	$lic = AesCtr::decrypt($lic,pwd(),128);
	if (!$lic) return false;
	if (false===$pos=strpos($lic,":"))	return false;
	$crc = sprintf("%u\n", crc32(substr($lic,$pos)));
	$licx = explode(":",$lic);
	if ($crc!=$licx[0]) return false;
	return $licx;
}

$smData = array();
initShm();

if (isset($_COOKIE["tz"])) date_default_timezone_set($_COOKIE["tz"]);

?>
