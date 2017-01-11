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

require('inc/global.php');

if (!isset($_GET['q'])) exit;
$_GET['q'] = trim($_GET['q']);

if (!$_GET['q']) {
	header("Location: {$_SERVER['HTTP_REFERER']}");
	exit;
}

if (!isset($_SESSION['recent_searches']))
	$_SESSION['recent_searches'] = array();
if (!in_array($_GET['q'], $_SESSION['recent_searches']))
	array_unshift($_SESSION['recent_searches'], $_GET['q']);
if (count($_SESSION['recent_searches']) > 2) {
	for ($i = 2; $i < count($_SESSION['recent_searches']); $i++) {
		unset($_SESSION['recent_searches'][$i]);
	}
}

$sql = "select subnets.id, subnets.parent_id, subnets.vlan, subnets.free, subnets.addr, masklen(subnets.addr) as cidr, to_char(subnets.last_updated, 'Mon DD, YYYY HH12:MIam') as last_updated, subnets.description, subnets.notes, " . 
		"subnets.swiped, to_char(last_swiped, 'Mon DD, YYYY HH12:MIam') as lastswiped, customers.id as cust_id, customers.name as cust_name from " .
		"subnets left join customers on customers.id = subnets.customer_id where subnets.instance_id = '{$_SESSION['user']['instance_id']}' and (";

if (preg_match('/^vlan\s+([0-9]+)$/i', trim($_GET['q']), $m)) {
	$search_cols = array('text(vlan)');
	$sql .= make_sql_like_clause($m[1], $search_cols, 'AND');
} else {
	$search_cols = array(
		'text(vlan)',
		'description',
		'notes',
		'text(addr)',
		'customers.name'
	);
	$sql .= make_sql_like_clause($_GET['q'], $search_cols, 'AND');
}
if (inet_aton($_GET['q'])) {
	$sql .= " or (inet '{$_GET['q']}' <<= subnets.addr)"; // <<= inet '{$_GET['q']}')";
}
$sql .= ") order by subnets.addr asc";
$subnets_result = @pg_query($sql) or die("error: failed to run query: $sql");

$sql = "select hosts.*, users.username, subnets.description as subnet_desc, subnets.addr as subnet_addr from hosts left join subnets on subnets.id = hosts.subnet_id left join users on users.id = hosts.last_updated_user_id where subnets.instance_id = '{$_SESSION['user']['instance_id']}' and (";
if (!isset($_GET['history'])) {
	$search_cols = array(
		'subnets.description',
		'subnets.notes',
		'hosts.description',
		'hosts.notes',
	);
}
if (isset($_GET['history'])) {
	$search_cols = array(
		'users.username'
	);
}
$sql .= make_sql_like_clause($_GET['q'], $search_cols, 'AND');
if (inet_aton($_GET['q'])) {
	$sql .= " or (inet '{$_GET['q']}' <<= hosts.addr)";
}
if (!isset($_GET['history']))
	$sql .= ") order by hosts.addr asc";
if (isset($_GET['history']))
        $sql .= ") order by hosts.last_updated desc";
$hosts_result = @pg_query($sql) or die("error: failed to run query: $sql");

layout_header('Search Results');

echo '<h1>Subnet Results';
if (pg_num_rows($subnets_result) > 0) {
	echo ' (' . pg_num_rows($subnets_result) . ')';
}
echo '</h1>';

if (pg_num_rows($subnets_result) == 0) {
	echo '<p>No results found.</p>';
} else {
	echo '<table>';
	echo '<tr>';
	echo '<th>Subnet</th>';
	echo '<th class="un">Free</th>';
	echo '<th class="un">Customer</th>';
	echo '<th>Description</th>';
	echo '<th class="un">VLAN</th>';
	echo '<th class="un">Updated</th>';
	echo '<th>Action</th>';
	echo '</tr>';
	
	while ($row = pg_fetch_array($subnets_result, null, PGSQL_ASSOC)) {
		if (subnet_has_children($row['id'])) {
			$free_hosts = free_hosts($_SESSION['user']['instance_id'], $row['addr'], true);
		} else {
			$free_hosts = free_hosts($_SESSION['user']['instance_id'], $row['addr']);
		}
		$nmask = ($ipv = ip_version($row['addr'])) == 4 ? inet_cidrton($row['cidr']) : inet6_cidrton($row['cidr']);
		$usage_width = floor(bcmul(bcdiv(bcsub($nmask, $free_hosts), ip_version($row['addr']) == 4 ? ($nmask-2) : $nmask, 10), 100));
			
		echo '<tr onclick="rowLink(event, \'hosts.php?subnet_id=' . $row['id'] . '\');" class="clickable">';
		echo '<td>' . $row['addr'] . '<br />';
		echo '<div class="subnet-usage subnet-unused"><div style="width:' . $usage_width . 'px" class="subnet-used">&nbsp;</div></div>';
		echo '</td>';
		echo '<td class="un" align="center">' . ($row['free'] ? 'Yes' : 'No') . '</td>';
		echo '<td class="un">' . ($row['cust_name'] ? $row['cust_name'] : 'N/A') . '</td>';
		echo '<td>';
		if (strstr($row['description'], "\n") || strlen($row['description']) > 42) {
			echo '<span class="info">' . substr(preg_replace('/[\r\n]+/', ' ', $row['description']), 0, 32) . '...<span>' . substr(preg_replace('/[\r\n]+/', ' ', $row['description']), -10) . '</span></span>';
		}
		else
			echo $row['description'];
		echo '</td>';
		echo '<td class="un" align="center">' . ($row['vlan'] ? $row['vlan'] : 'N/A') . '</td>';
		echo '<td class="un">' . ($row['last_updated'] ? ago(strtotime($row['last_updated'])) . ' ago' : 'N/A') . '</td>';
		echo '<td align="center"><a href="index.php?a=mod&amp;id=' . $row['id'] . '">Modify</a> / ';
		if ($row['cust_id'] && (($ipv == 4 && $row['cidr'] <= 29) || ($ipv == 6 && $row['cidr'] <= 64)))
			echo '<a href="index.php?a=swip&id=' . $row['id'] . '">SWIP</a>';
		else
			echo 'SWIP';
		echo '</td>';
		echo '</tr>';
	}
	echo '</table>';
}

echo '<br />';
echo '<h1>Host Results';
if (pg_num_rows($hosts_result) > 0) {
	echo ' (' . pg_num_rows($hosts_result) . ')';
}
echo '</h1>';

if (pg_num_rows($hosts_result) == 0) {
	echo '<p>No results.</p>';
} else {
	echo '<table>';
	echo '<tr>';
	echo '<th>Address</th>';
	echo '<th class="un">Subnet</th>';
	echo '<th class="un">Used?</th>';
	if ($config['dnslookups'])
		echo '<th class="un">Hostname</th>';
	echo '<th>Description</th>';
	echo '<th class="un">Notes</th>';
	echo '<th class="un">Updated</th>';
	echo '<th>Action</th>';
	echo '</tr>';
	while ($row = pg_fetch_array($hosts_result, null, PGSQL_ASSOC)) {
		if ($config['dnslookups']) {
			if (!$config['dnslookup_for_private'] && (ip_in_network(inet_ntoa($host), '10.0.0.0/8') || ip_in_network(inet_ntoa($host), '172.16.0.0/12')
				|| ip_in_network(inet_ntoa($host), '192.168.0.0/16')))
				$hostname = '';
			else
				$hostname = gethostbyaddr(inet_ntoa($row['addr']));
		}
		echo '<tr class="clickable" onclick="rowLink(event, \'host.php?subnet_id=' . $row['subnet_id'] . '&amp;addr=' . $row['addr'] . '&ref=' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '\');">';
		echo '<td><span ' . (isset($deactivated) && in_array($row['addr'], $deactivated) ? 'style="color: red"' : '') . '>' . $row['addr'] . '</span></td>';
		echo '<td class="un" align="center"><a title="' . str_replace('"', '&quot;', $row['subnet_desc']) . '" href="hosts.php?subnet_id=' . $row['subnet_id'] . '&ref=' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING'] . '">' . $row['subnet_addr'] . '</a></td>';
		echo '<td  class="un"align="center">' . ($row['free'] ? '<span style="color:#82c659">No</span>' : 'Yes') . '</td>';
		if ($config['dnslookups'])
			echo '<td class="un">' . ($hostname != inet_ntoa($row['addr']) ? $hostname : 'N/A') . '</td>';
		echo '<td>';
		if (strstr($row['description'], "\n") || strlen($row['description']) > 42) {
			echo '<span class="info">' . substr(preg_replace('/[\r\n]+/', ' ', $row['description']), 0, 32) . '...<span>' . substr(preg_replace('/[\r\n]+/', ' ', $row['description']), -10) . '</span></span>';
		}
		else
			echo $row['description'];
		echo '</td>';
		echo '<td class="un">';
		if (strstr($row['notes'], "\n") || strlen($row['notes']) > 42)
		{
			echo '<span class="info">' . substr(preg_replace('/[\r\n]+/', ' ', $row['notes']), 0, 32) . '...<span>' . substr(preg_replace('/[\r\n]+/', ' ', $row['notes']), -10) . '</span></span>';
		}
		else
			echo $row['notes'];
		echo '</td>';
		echo '<td class="un"><nobr>' . ($row['last_updated'] ? $row['last_updated'] : 'N/A') . '</nobr></td>';
		echo '<td align="center">';
		echo '<select onchange="eval(this.options[this.selectedIndex].value);">';
		echo '<option value=""></option>';
		echo '<option value="window.open(\'http://' . $row['addr'] . '\', \'\');">Web</option>';
		echo '<option value="poptastic(\'cmd.php?a=ping&amp;addr=' . $row['addr'] . '\');">Ping</option>';
		echo '<option value="poptastic(\'cmd.php?a=tracert&amp;addr=' . $row['addr'] . '\');">Traceroute</option></option>';
		echo '<option value="poptastic(\'cmd.php?a=whois&amp;addr=' . $row['addr'] . '\');">Whois</option>';
		echo '</select>';
		echo '</td>';

//		echo '<td align="center"><a href="javascript:poptastic(\'cmd.php?a=ping&amp;addr=' . inet_ntoa($row['addr']) . '\');">Ping</a> / <a href="javascript:poptastic(\'cmd.php?a=tracert&amp;addr=' . inet_ntoa($row['addr']) . '\');">Tracert</a> / <a href="javascript:poptastic(\'cmd.php?a=whois&amp;addr=' . inet_ntoa($row['addr']) . '\');">Whois</a></td>';
		echo '</tr>';
	}
	echo '</table>';
}

layout_footer();

