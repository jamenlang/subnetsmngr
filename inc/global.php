<?php
/*
 * subnetsmngr
 * Author(s): Sean Kennedy <sean@kndy.net> 
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 *
*/

error_reporting(E_ALL);
set_time_limit(0);
session_start();

$VERSION = '3.0';

if (get_magic_quotes_gpc() == 0) {
	function cb(&$v, $k) { $v = addslashes($v); }
	array_walk_recursive($_POST, 'cb');
	array_walk_recursive($_GET, 'cb');
	array_walk_recursive($_COOKIE, 'cb');
	array_walk_recursive($_REQUEST, 'cb');
}

require('inc/config.php');
require('inc/layout.php');
require('inc/misc.php');
require('inc/subnets.php');
require('inc/instances.php');
if (isset($config['route_check']) && $config['route_check']['enabled']) {
	define('ROUTE_CHECK', true);
	include('contrib/netconf/Device.php'); /* for Route Check capabilities */
	include('inc/route-check.php');
}

if (!($pgh = pg_connect("host={$config['psql_host']} user={$config['psql_user']} password={$config['psql_pass']} dbname={$config['psql_db']}"))) {
	die("error: failed to connect to postgresql: " . pg_last_error($pgh));
}

if (isset($config['pdns_mysql_host']) && $config['pdns_mysql_host']) {
	if (!($pdns_dbh = @mysql_connect($config['pdns_mysql_host'], $config['pdns_mysql_user'], $config['pdns_mysql_pass']))) {
		die("error: failed to connect to pdns mysql server: " . mysql_error());
	}
	if (!@mysql_select_db($config['pdns_mysql_db'])) {
		die("error: failed to select pdns database '{$config['pdns_mysql_db']}': " . mysql_error());
	}
	require('inc/pdns.php');
}

$valid_ipv4_subnets = array(
	8 => "255.0.0.0",
	"255.128.0.0",
	"255.192.0.0",
	"255.224.0.0",
	"255.240.0.0",
	"255.248.0.0",
	"255.252.0.0",
	"255.254.0.0",
	"255.255.0.0",
	"255.255.128.0",
	"255.255.192.0",
	"255.255.224.0",
	"255.255.240.0",
	"255.255.248.0",
	"255.255.252.0",
	"255.255.254.0",
	"255.255.255.0",
	"255.255.255.128",
	"255.255.255.192",
	"255.255.255.224",
	"255.255.255.240",
	"255.255.255.248",
	"255.255.255.252",
	"255.255.255.254",
	"255.255.255.255"
);

if (!defined('NO_CHECK_LOGIN')) {
	if (!isset($_SESSION['user']) && $_SERVER['PHP_SELF'] != "{$config['web_path']}/auth.php") {
		header("Location: auth.php?ref={$_SERVER['REQUEST_URI']}");
		exit;
	} else {
		$sql = "update users set lastip = '{$_SERVER['REMOTE_ADDR']}' where id = '{$_SESSION['user']['id']}'";
		@pg_query($sql);
	}
}
