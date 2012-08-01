<?php  if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$active_group = 'expressionengine';
$active_record = TRUE;

$db['expressionengine']['hostname'] = "localhost";
$db['expressionengine']['username'] = "root";
$db['expressionengine']['password'] = "root";
/*
$db['expressionengine']['hostname'] = "mysql.seoulistmag.com";
$db['expressionengine']['username'] = "seoulistmagcom";
$db['expressionengine']['password'] = "BU#PErW^";
*/
$db['expressionengine']['database'] = "seoulist_ee";
$db['expressionengine']['dbdriver'] = "mysql";
$db['expressionengine']['dbprefix'] = "exp_";
$db['expressionengine']['pconnect'] = FALSE;
$db['expressionengine']['swap_pre'] = "exp_";
$db['expressionengine']['db_debug'] = TRUE;
$db['expressionengine']['cache_on'] = FALSE;
$db['expressionengine']['autoinit'] = FALSE;
$db['expressionengine']['char_set'] = "utf8";
$db['expressionengine']['dbcollat'] = "utf8_general_ci";
$db['expressionengine']['cachedir'] = "/konsol/expressionengine/cache/db_cache/";
/*
$db['expressionengine']['cachedir'] = "/home/seoulistmag/seoulistmag.com/konsol/expressionengine/cache/db_cache/";
*/

/* End of file database.php */
/* Location: ./system/expressionengine/config/database.php */