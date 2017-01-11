<?php
/*
 * subnetsmngr v1.1
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

define('NO_CHECK_LOGIN', 1);
define('VERSION', 1.0);
require('inc/global.php');

header('Content-Type: text/plain');

if (isset($_GET['username']) && isset($_GET['password'])) {
	$sql = "select id from users where username = '$_GET[username]' and password = MD5('$_GET[password]')";
	$result = @pg_query($sql) or die("error:Database error.");
	if (pg_num_rows($result) == 0) {
		die("error:Invalid login.");
	} else {
		$user_id = pg_result($result, 0, 'id');
	}
} else {
	die("error:Login required.");
}

$req = isset($_GET['req']) ? $_GET['req'] : null;
if (!$req) {
	die("error:No request specified.");
}
if (!isset($_GET['version']) || $_GET['version'] < VERSION) {
	die("error:Incompatible web/app versions.");
}
switch ($req) {
	case 'get_subnets':
		if (isset($_GET['subnet_id'])) {
			$sql = "select subnets.*, (select count(id) from subnets as inner_subnets where inner_subnets.parent_id = subnets.id) as num_children, customers.name as customer_name from subnets left join customers on subnets.customer_id = customers.id " .
				"where subnets.parent_id = '$_GET[subnet_id]'";
		}
		$result = @pg_query($sql) or die("error:Database error: " . pg_last_error());
		$subnets = array();
		while ($row = pg_fetch_assoc($result)) {
			$row['last_swiped'] = $row['last_swiped'] && $row['last_swiped'] != '0000-00-00 00:00:00' ? strtotime($row['last_swiped']) : 0;
			$row['last_updated'] = $row['last_updated'] && $row['last_updated'] != '0000-00-00 00:00:00' ? strtotime($row['last_updated']) : 0;
			$subnets[] = $row;
		}
		echo json_encode($subnets);
		break;
	case 'get_hosts':
		if (isset($_GET['host_id'])) {
			$sql = "select * from hosts where id = '$_GET[host_id]'";
		} else {
			$sql = "select * from hosts where subnet_id = '$_GET[subnet_id]' order by addr asc";
		}
		$result = @mysql_query($sql) or die("error:Database error.");
		$hosts = array();
		while ($row = mysql_fetch_assoc($result)) {
			$row['last_updated'] = $row['last_updated'] && $row['last_updated'] != '0000-00-00 00:00:00' ? strtotime($row['last_updated']) : 0;
			$hosts[] = $row;
		}
		echo json_encode($hosts);
		break;
	case 'get_customers':
		$sql = "select * from customers order by name asc";
		$result = @mysql_query($sql) or die("error:Database error.");
		$customers = array();
		while ($row = mysql_fetch_assoc($result)) {
			$customers[] = $row;
		}
		echo json_encode($customers);
		break;
	case 'update_host':
		$sql = "select id from hosts where id = '$_GET[host_id]'";
		$result = @mysql_query($sql) or die("error:Database error.");
		if (mysql_num_rows($result) > 0) {
			$sql = "update hosts set last_updated = now(), last_updated_user_id = '$user_id', description = '$_GET[description]', " .
				"notes = '$_GET[notes]', free = '$_GET[free]' where id = '$_GET[host_id]'";
		} else {
			$sql = "insert into hosts (subnet_id, addr, free, description, notes, last_updated_user_id, last_updated) values(" .
				"'$_GET[subnet_id]', '$_GET[addr]', '$_GET[free]', '$_GET[description]', '$_GET[notes]', '$user_id', " .
				"now())";
		}
		@mysql_query($sql) or die("error:Database error.");
		break;
	case 'search':
		$_GET['q'] = trim($_GET['q']);
		$sql = "select subnets.id, subnets.base_id, subnets.free, subnets.addr, subnets.mask, subnets.last_updated, subnets.description, " .
			"subnets.notes, subnets.swiped, date_format(subnets.last_swiped, '%m/%d/%Y') as last_swiped, customers.id as cust_id, " .
			"customers.name as cust_name from subnets left join customers on customers.id = subnets.customer_id where " .
			"(description like '%{$_GET['q']}%' or notes like '%{$_GET['q']}%' or inet_ntoa(addr) like '%{$_GET['q']}%' or " .
			"customers.name like '%{$_GET['q']}%'";
		if (preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)(\/([0-9]+))?$/', $_GET['q'], $m)) {
			if (isset($m[3])) {
				$sql .= " or (subnets.addr between " . inet_aton($m[1]) . " and " . inet_aton($m[1]) . "+" . 
					inet_cidrton($m[3]) . " and subnets.addr+mask <= " . inet_aton($m[1]) . "+" . inet_cidrton($m[3]) . ")";
			} else {
				$sql .= " or " . inet_aton($m[1]) . " between subnets.addr and subnets.addr+mask-1";
			}
		}
		$sql .= ") order by subnets.addr asc";
		$subnets_result = @mysql_query($sql) or die("error:Database error.");
		
		$sql = "select hosts.* from hosts left join subnets on subnets.id = hosts.subnet_id where (hosts.description like '%{$_GET['q']}%' " .
			"or hosts.notes like '%{$_GET['q']}%'";
		if (preg_match('/^([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)(\/([0-9]+))?$/', $_GET['q'], $m)) {
			if (isset($m[3])) {
				$sql .= " or hosts.addr between " . inet_aton($m[1]) . " and " . inet_aton($m[1]) . "+" . inet_cidrton($m[3]);
			} else {
				$sql .= " or hosts.addr = '" . inet_aton($m[1]) . "'";
			}
		}
		$sql .= ") order by hosts.addr asc";
		$hosts_result = @mysql_query($sql) or die("error:Database error.");
		
		$results = array();
		while ($row = mysql_fetch_assoc($subnets_result)) {
			$row['type'] = 'subnet';
			$row['last_swiped'] = $row['last_swiped'] && $row['last_swiped'] != '0000-00-00 00:00:00' ? strtotime($row['last_swiped']) : 0;
			$row['last_updated'] = $row['last_updated'] && $row['last_updated'] != '0000-00-00 00:00:00' ? strtotime($row['last_updated']) : 0;
			
			$results[] = $row;
		}
		while ($row = mysql_fetch_assoc($hosts_result)) {
			$row['type'] = 'host';
			$row['last_updated'] = $row['last_updated'] && $row['last_updated'] != '0000-00-00 00:00:00' ? strtotime($row['last_updated']) : 0;
			
			$results[] = $row;
		}
		echo json_encode($results);
		break;
	case 'update_subnet':
		$sql = "update subnets set last_updated = now(), last_updated_user_id = '$user_id', description = '$_GET[description]', " .
			"notes = '$_GET[notes]', free = '$_GET[free]' where id = '$_GET[subnet_id]'";
		@mysql_query($sql) or die("error:Database error.");
		if (isset($_GET['mark_unused']) && $_GET['mark_unused'] == 1) {
			subnet_mark_unused($_GET['subnet_id']) or die("error:Database error.");
		}
		break;
	case 'add_subnet':
		break;
}
