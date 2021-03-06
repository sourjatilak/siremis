<?php

function getSystemStatus()
{
	$status[0]['item'] = 'Operation System';
	$status[0]['value'] = PHP_OS;
	$status[0]['status'] = 'OK';
	
	$status[1]['item'] = 'PHP version';
	$status[1]['value'] = PHP_VERSION;
	//$ver_num = intval(str_replace('.', '', PHP_VERSION));
	$status[1]['status'] = version_compare(PHP_VERSION, "5.1.4") >= 0 ? 'OK' : 'FAIL - Zend Framework required PHP5.1.4 or later';
	
	$status[2]['item'] = 'Openbiz Path';
	$status[2]['value'] = OPENBIZ_HOME;
	$status[2]['status'] = "OK";
	if (!file_exists(OPENBIZ_HOME))
		$status[2]['status'] = "FAIL - OPENBIZ_HOME doesn't point to Openbiz installed path";
	
	$status[3]['item'] = 'Zend Framework Path';
	$status[3]['value'] = defined('ZEND_FRWK_HOME') ? ZEND_FRWK_HOME : 'Undefined';
	if (defined('ZEND_FRWK_HOME') && !file_exists(ZEND_FRWK_HOME))
		$status[3]['status'] = "FAIL - ZEND_FRWK_HOME doesn't point to Zend Framework installed path. Please modify ZEND_FRWK_HOME in ".OPENBIZ_HOME."/bin/sysheader.inc";
	else if (defined('ZEND_FRWK_HOME') && file_exists(ZEND_FRWK_HOME))
		$status[3]['status'] = 'OK';
	else
		$status[3]['status'] = 'FAIL';
	
	if ($status[3]['status'] == 'OK')  {
		require_once 'Zend/Version.php';
		$status[4]['item'] = 'Zend Framework';
		$status[4]['value'] = Zend_Version::VERSION;
		$status[4]['status'] = Zend_Version::compareVersion('1.0.0') < 0 ? 'OK - Version 1.0.0 or later is recommended' : 'FAIL';
	}
	
	$status[5]['item'] = 'PDO extensions';
	$pdos = array();
	if (extension_loaded('pdo')) $pdos[] = "pdo";
	if (extension_loaded('pdo_mysql')) $pdos[] = "pdo_mysql";
	if (extension_loaded('pdo_mssql')) $pdos[] = "pdo_mssql";
	if (extension_loaded('pdo_oci')) $pdos[] = "pdo_oci";
	if (extension_loaded('pdo_pgsql')) $pdos[] = "pdo_pgsql";
	$status[5]['value'] = implode(", ", $pdos);
	$status[5]['status'] = $pdos[0]=='pdo' ? 'OK' : 'FAIL - PDO extensions are required.';
	
	$status[6]['item'] = 'HTTP Server';
	$status[6]['value'] = 'Rewrite Engine - unknown';
	$status[6]['status'] = 'OK';
	if (function_exists('apache_get_modules')) {
		$modules = apache_get_modules();
		if(in_array('mod_rewrite', $modules)) {
			$status[6]['value'] = 'Rewrite Engine - server module';
		}
	} else {
		if(getenv('HTTP_MOD_REWRITE')=='On') {
			$status[6]['value'] = 'Rewrite Engine';
		}
	}
	return $status;
}

function getApplicationStatus()
{
	//$status[0]['item'] = 'Application root';
	//$status[0]['value'] = APP_HOME;
	//$status[0]['status'] = is_writable(APP_HOME) ? 'OK' : 'FAIL - not writable';
	
	$status[2]['item'] = 'Session path';
	$status[2]['value'] = SESSION_PATH;
	$status[2]['status'] = is_writable(SESSION_PATH) ? 'OK' : 'FAIL - not writable';
	
	$status[3]['item'] = 'Smarty template path';
	$status[3]['value'] = THEME_PATH."/default/template"; // SMARTY_TPL_PATH;
	$status[3]['status'] = is_writable(THEME_PATH."/default/template") ? 'OK' : 'FAIL - not writable';
	
	$status[4]['item'] = 'Log path';
	$status[4]['value'] = LOG_PATH;
	$status[4]['status'] = is_writable(LOG_PATH) ? 'OK' : 'FAIL - not writable';
	
	$status[5]['item'] = 'Cache files path';
	$status[5]['value'] = APP_FILE_PATH;
	$status[5]['status'] = is_writable(APP_FILE_PATH) ? 'OK' : 'FAIL - not writable';
	
	return $status;
}

function connectDB($noDB=false) {
    require_once 'Zend/Db.php';

    // Automatically load class Zend_Db_Adapter_Pdo_Mysql and create an instance of it.
    $param = array(
        'host'     => $_REQUEST['dbHostName'],
        'username' => $_REQUEST['dbUserName'],
        'password' => $_REQUEST['dbPassword'],
        'port'     => $_REQUEST['dbHostPort'],
        'dbname'   => $_REQUEST['dbName']
    );
    if ($noDB) $param['dbname'] = '';
    
    try {
        $db = Zend_Db::factory($_REQUEST['dbtype'], $param);
        $conn = $db->getConnection();
    } catch (Zend_Db_Adapter_Exception $e) {
        // perhaps a failed login credential, or perhaps the RDBMS is not running
        echo 'ERROR: '.$e->getMessage(); 
        exit;
    } catch (Zend_Exception $e) {
        // perhaps factory() failed to load the specified Adapter class
        echo 'ERROR: '.$e->getMessage(); exit;
    }
    return $conn;
}

function createDB() {
	$conn = connectDB(true);
	try {
	   $conn->exec("CREATE DATABASE " . $_REQUEST['dbName']);
	}
	catch (Exception $e) {
	   echo 'ERROR: '.$e->getMessage(); exit;
   }
   unset($conn);

	$conn = connectDB();
    if (!$conn) {
		echo 'ERROR: Unable to create Database!';
		return false;
	}
	
	replaceDbConfig();
	
	return true;
}

function fillDB()
{   
	include_once (MODULE_PATH."/system/lib/ModuleLoader.php");

    replaceDbConfig();

	$modules = array ('system','menu');
	foreach (glob(MODULE_PATH.DIRECTORY_SEPARATOR."*") as $dir){
		$modName = str_replace(MODULE_PATH.DIRECTORY_SEPARATOR,"",$dir);
		if($modName != "system" && $modName !="menu"){
			array_push($modules,$modName);		
		}
	}

	// find all modules
	foreach ($modules as $mod)
	{
		$loader = new ModuleLoader($mod);
		$loader->debug=0;
	    $loader->loadModule(true);
	}

	// admin to access all actions
	giveActionAccess("", 1);
   	
	// sipadmin to access siremis user profile and sip admin pages
	giveActionAccess("module='user' OR module='ser'", 2);

	// sipuser to access siremis user profile and sip user pages
	giveActionAccess("module='user' OR module='sipuser'", 3);

	return true;
}

function giveActionAccess($where, $role_id)
{
	$db = connectDB();
	try {
		$sql = "DELETE FROM acl_role_action WHERE role_id=$role_id";
		$db->query($sql);
		
		if (empty($where))
			$sql = "SELECT * FROM acl_action";
		else
			$sql = "SELECT * FROM acl_action WHERE $where";
	    BizSystem::log(LOG_DEBUG, "DATAOBJ", $sql);
	    $stmt = $db->prepare($sql);
	    $stmt->execute();
	    $rs = $stmt->fetchAll();
	    unset($stmt);
	    
	    $sql = "";
		foreach ($rs as $r) {
			$sql = "INSERT INTO acl_role_action (role_id, action_id, access_level) VALUE ($role_id,$r[0],1)";
			BizSystem::log(LOG_DEBUG, "DATAOBJ", $sql);
	    	$db->query($sql);
		}
	}
	catch (Exception $e) {
	    echo "ERROR: ".$e->getMessage()."".PHP_EOL;
	    return false;
	}
}

function replaceDbConfig()
{
   $filename = APP_HOME.'/Config.xml';
   $xml = simplexml_load_file($filename);
   $xml->DataSource->Database[0]['Driver'] = $_REQUEST['dbtype'];
   $xml->DataSource->Database[0]['Server'] = $_REQUEST['dbHostName'];
   $xml->DataSource->Database[0]['User'] = $_REQUEST['dbUserName'];
   $xml->DataSource->Database[0]['Password'] = $_REQUEST['dbPassword'];
   $xml->DataSource->Database[0]['DBName'] = $_REQUEST['dbName'];
   $xml->DataSource->Database[0]['Port'] = $_REQUEST['dbHostPort'];
   $fp = fopen ($filename, 'w');
   if (fwrite($fp, $xml->asXML()) === FALSE) {
        echo "Cannot write to file ($filename)";
        exit;
    }
    fclose($fp);
    //showDBConfig();
	return true;
}

function getDefaultDB()
{
	$filename = APP_HOME.'/Config.xml';
   	$xml = simplexml_load_file($filename);
   	$db['Name'] = $xml->DataSource->Database[0]['Name'];
   	$db['Driver'] = $xml->DataSource->Database[0]['Driver'];
   	$db['Server'] = $xml->DataSource->Database[0]['Server'];
   	$db['User'] = $xml->DataSource->Database[0]['User'];
   	$db['Password'] = $xml->DataSource->Database[0]['Password'];
   	$db['DBName'] = $xml->DataSource->Database[0]['DBName'];
   	$db['Port'] = $xml->DataSource->Database[0]['Port'];
   	return $db;
}

function showDBConfig()
{
   $xml = simplexml_load_file(APP_HOME.'/Config.xml');
   //print_r($xml);
   echo "<b>Current setting of Default Database:</b>";
   echo '<table><tr>';
   echo '<th>Name</th><th>Driver</th><th>Server</th><th>Port</th><th>DBName</th><th>User</th><th>Password</th></tr>';
   echo '<tr>';
   echo '<td>'.$xml->DataSource->Database[0]['Name'].'</td>';
   echo '<td>'.$xml->DataSource->Database[0]['Driver'].'</td>';
   echo '<td>'.$xml->DataSource->Database[0]['Server'].'</td>';
   echo '<td>'.$xml->DataSource->Database[0]['Port'].'</td>';
   echo '<td>'.$xml->DataSource->Database[0]['DBName'].'</td>';
   echo '<td>'.$xml->DataSource->Database[0]['User'].'</td>';
   echo '<td>'.$xml->DataSource->Database[0]['Password'].'</td>'; 
   echo '</tr></table>';  
}
?>
