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

$rss_add_host = (isset($_GET['rss']) && $_GET['rss'] == 'true' && isset($_GET['a']) && $_GET['a'] == 'add_host' && isset($_GET['key']) && $_GET['key'] == 'nA7nvEbphEciWlvXee');
if ($rss_add_host) define('NO_CHECK_LOGIN', 1);

require('inc/global.php');
if (!isset($_GET['subnet_id'])) exit;

$action = isset($_GET['a']) ? $_GET['a'] : 'main';
if ($action == 'main')
{
	if (subnet_has_children($_GET['subnet_id'])) {
		header("Location: index.php?subnet_id={$_GET['subnet_id']}");
		exit;
	}
	
	$subnet_tree = subnet_get_tree($_GET['subnet_id']);

	$sql = "select c.name, s.vlan, s.swiped, family(s.addr) as ip_family, " .
		"s.parent_id, s.addr, masklen(s.addr) as mask, broadcast(s.addr) as bcast, s.description, s.notes, " .
		"to_char(s.last_updated, 'Mon DD, YYYY HH12:MIam') as last_updated, u.username as last_updated_user " .
		"from subnets as s left join customers as c on c.id = s.customer_id " .
		"left join users as u on u.id = s.last_updated_user_id where s.id = '{$_GET['subnet_id']}'";
	
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	if (pg_num_rows($result) == 0) die("error: subnet non-existant");
	$subnet_info = pg_fetch_array($result, null, PGSQL_ASSOC);
	pg_free_result($result);
	$nmask = ($subnet_info['ip_family'] == 4 ? inet_cidrton($subnet_info['mask']) : inet6_cidrton($subnet_info['mask']));
	$free_hosts = free_hosts($_SESSION['user']['instance_id'], $subnet_info['addr']);
	$sql = "select * from hosts where subnet_id = '{$_GET['subnet_id']}' order by addr asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$hosts = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
	{
		$hosts[$row['addr']] = $row;
		
		// gateway host?
		if ($row['gateway'] != 0) {
			$gateway_host = $row['addr'];
		}
	}
	pg_free_result($result);
	
	$sidebar = '<aside class="rightbar">';
	$sidebar .= '<div class="header">' . $subnet_info['addr'] . '</div>';
	$sidebar .= '<div class="subheader">description</div>';
	$sidebar .= '<p>' . ($subnet_info['description'] ? nl2br($subnet_info['description']) : 'N/A') . '</p>';
	$sidebar .= '<div class="subheader">notes</div>';
	$sidebar .= '<p>' . ($subnet_info['notes'] ? nl2br($subnet_info['notes']) : 'N/A') . '</p>';
	if ($subnet_info['ip_family'] == 4) {
		$sidebar .= '<div class="inline-kvp"><div class="key">network</div><div class="value">' . $subnet_info['addr'] . '</div></div>';
		$sidebar .= '<div class="inline-kvp"><div class="key">broadcast</div><div class="value">' . $subnet_info['bcast'] . '</div></div>';
		$sidebar .= '<div class="inline-kvp"><div class="key">mask</div><div class="value">' . $valid_ipv4_subnets[inet_ntocidr($nmask)] . '</div></div>';
		$sidebar .= '<div class="inline-kvp un"><div class="key">wildcard</div><div class="value">' . wildcard_mask($valid_ipv4_subnets[inet_ntocidr($nmask)]) . '</div></div>';
		$sidebar .= '<div class="inline-kvp"><div class="key">gateway</div><div class="value">' . (isset($gateway_host) ? $gateway_host : 'Unspecified') . '</div></div>';
	}
	$sidebar .= '<div class="inline-kvp un"><div class="key">hosts</div><div class="value">' . fmt_scientific($nmask, 8);
	if ($subnet_info['ip_family'] == 4) {
		$sidebar .= ' (' . usable_hosts($nmask) . ' usable)';
	}
	$sidebar .= '</div></div>';
	$sidebar .= '<div class="inline-kvp"><div class="key">used/free</div><div class="value">' . fmt_scientific(gmp_strval(gmp_sub($nmask, $free_hosts)), 8) . '/' . fmt_scientific($free_hosts, 8) . '</div></div>';
	$sidebar .= '<div class="inline-kvp"><div class="key">vlan</div><div class="value">' . ($subnet_info['vlan'] ? $subnet_info['vlan'] : 'N/A') . '</div></div>';
	$sidebar .= '<div class="inline-kvp un"><div class="key">SWIPed</div><div class="value">' . ($subnet_info['swiped'] ? 'Yes' : 'No') . '</div></div>';
	$sidebar .= '<div class="inline-kvp"><div class="key">updated</div><div class="value">' . ($subnet_info['last_updated'] ? ago(strtotime($subnet_info['last_updated'])) . ' ago (' . $subnet_info['last_updated_user'] . ')' : 'N/A') . '</div></div>';
	$sidebar .= '<div class="header">options</div>';
	$sidebar .= '<ul>';
	$sidebar .= '<li><a href="?subnet_id=' . $_GET['subnet_id'] . '&a=add_host">Add Host</a></li>';
	if ($subnet_info['ip_family'] == 4 && $free_hosts < $nmask) {
		$sidebar .= '<li class="un"><a href="?subnet_id=' . $_GET['subnet_id'] . '&scanhosts=1">Scan Hosts</a></li>';
	}
	if (($subnet_info['ip_family'] == 4 && $subnet_info['mask'] < 30) || ($subnet_info['ip_family'] == 6 && $subnet_info['mask'] < 127)) {
		$sidebar .= '<li class="un"><a href="allocate.php?in_subnet_id=' . $_GET['subnet_id'] . '">Variably Subnet</a></li>';
	}
	if ($subnet_info['parent_id'] != 0 && (($subnet_info['ip_family'] == 4 && $subnet_info['mask'] <= 29) || ($subnet_info['ip_family'] == 6 && $subet_info['mask'] <= 127))) {
	        $sidebar .= '<li class="un"><a href="allocate.php?variably_subnet=0&in_subnet_id=' . $_GET['subnet_id'] . '">Split Apart Subnet</a></li>';
	}
	$sidebar .= '<li><a href="index.php?a=mod&amp;id=' . $_GET['subnet_id'] . '">Modify Subnet</a></li>';
	if (!$subnet_info['parent_id']) { // we dont want people deleting subnets ; they should be marked unused instead
		$sidebar .= '<li><a href="index.php?a=del&amp;id=' . $_GET['subnet_id'] . '" onclick="if (!confirm(\'Are you sure you want to delete this subnet?  Every subnet/host under it will be removed!\')) { return false; }">Delete Subnet</a></li>';
	}
	if (defined('ROUTE_CHECK') && rc_get_device_config($_SESSION['user']['instance_id'])) {
		$sidebar .= '<li class="un"><a href="index.php?a=route-check&route=' . $subnet_info['addr'] . '">Route Check</a></li>';
	}
	if ($subnet_info['name'] && (($subnet_info['ip_family'] == 4 && $subnet_info['mask'] <= 29) || ($subnet_info['ip_family'] == 6 && $subnet_info['ip_family'] <= 64)))
		$sidebar .= '<li class="un"><a href="index.php?a=swip&id=' . $_GET['subnet_id'] . '">SWIP</a></li>';
	$sidebar .= '<li class="un"><a href="?subnet_id=' . $_GET['subnet_id'] . '&a=exportcsv">Export CSV</a></li>';

	$sidebar .= '</ul>';
	$sidebar .= '</aside>';
	
	layout_header($subnet_info['addr'], null, $sidebar);
	
	echo subnet_tree_breadcrumb($subnet_tree);
	
	echo '<table>';
	echo '<tr>';
	echo '<th>Address</th>';
	if ($config['dnslookups']) {
		echo '<th class="un">Hostname</th>';
	}
	echo '<th>Description</th>';
	echo '<th class="un">Notes</th>';
	echo '<th>Used?</th>';
	echo '<th class="un">Updated</th>';
	echo '<th>Action</th>';
	echo '</tr>';
	
	foreach ($hosts as $host)
	{
		$onclick = "onclick=\"rowLink(event, 'host.php?subnet_id={$_GET['subnet_id']}&amp;addr={$host['addr']}');\" id=\"{$host['addr']}\"";
		
		if ($config['dnslookups'])
		{
			if (!$config['dnslookup_for_private'] && (ip_in_network($host['addr'], '10.0.0.0/8') || ip_in_network($host['addr'], '172.16.0.0/12') || ip_in_network($host, '192.168.0.0/16')))
				$hostname = '';
			else
				$hostname = gethostbyaddr($host);
		}

		if (isset($_GET['scanhosts']))
			echo '<tr class="clickable ' . (host_up($host['addr'], 2) ? 'uphost' : 'downhost') . '" ' . $onclick . '>';
		else
			echo '<tr class="clickable" ' . $onclick . '>';
		
		echo '<td><nobr>';
		echo '<span ' . (isset($deactivated) && in_array($host['addr'], $deactivated) ? 'style="color: red"' : '') . ' name="' . $host['addr'] . '">' . $host['addr'] . '</span>';
		echo '</nobr></td>';
		if ($config['dnslookups'])
			echo '<td class="un">' . ($hostname != $host['addr'] ? $hostname : 'N/A') . '</td>';
		echo '<td>';
		if (strstr($host['description'], "\n") || strlen($host['description']) > 32)
			echo substr(preg_replace('/[\r\n]+/', ' ', $host['description']), 0, 32) . '...' . substr(preg_replace('/[\r\n]+/', ' ', $host['description']), -10);
		else
			echo $host['description'];
		echo '</td>';
		echo '<td class="un">';
		if (strstr($host['notes'], "\n") || strlen($host['notes']) > 32) {
			echo substr(preg_replace('/[\r\n]+/', ' ', $host['notes']), 0, 32) . '...' . substr(preg_replace('/[\r\n]+/', ' ', $host['notes']), -10);
		} else {
			echo preg_replace('/[\r\n]+/', ' ', $host['notes']);
		}
		echo '</td>';
		echo '<td align="center">';
		if (isset($gateway_host) && $gateway_host == $host['addr'])
			echo '&nbsp;<img src="img/gateway.png" alt="Gateway" border="0" align="absmiddle" />';
		else if (isset($ap_host) && $ap_host == $host['addr'])
			echo '&nbsp;<img src="img/ap.png" alt="Access Point" border="0" align="absmiddle" />';
		else
			echo (!$host['free'] ? 'Yes' : '<span style="color:#82c659">No</span>');
		echo '</td>';
		echo '<td class="un">' . ($host['last_updated'] ? ago(strtotime($host['last_updated'])) . ' ago' : 'N/A') . '</td>';
		echo '<td align="center">';
		echo '<select onchange="eval(this.options[this.selectedIndex].value);">';
		echo '<option value=""></option>';
		echo '<option value="window.open(\'http://' . $host['addr'] . '\', \'\');">Web</option>';
		echo '<option value="poptastic(\'cmd.php?a=ping&amp;addr=' . $host['addr'] . '\');">Ping</option>';
		echo '<option value="poptastic(\'cmd.php?a=tracert&amp;addr=' . $host['addr'] . '\');">Traceroute</option></option>';
		echo '<option value="poptastic(\'cmd.php?a=whois&amp;addr=' . $host['addr'] . '\');">Whois</option>';
		echo '</select>';
		echo '</td>';
		echo '</tr>';
	}
	echo '</table>';
	
	layout_footer();
}
else if ($action == 'add_host')
{
	$sql = "select addr, family(addr) as ip_version, masklen(addr) as cidr from subnets where id = '{$_GET['subnet_id']}'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	if (pg_num_rows($result) == 0)
		die("error: subnet non-existant");
	$subnet_info = pg_fetch_array($result, null, PGSQL_ASSOC);
	
	// cleanup old 'pending hosts' that must've never gotten actually created
	$sql = "delete from hosts_pending where added < now() - interval '15 minutes'";
	@pg_query($sql) or die("error: failed to run query: $sql");
	
	// get any recent 'pending hosts' for this subnet
	$sql = "select addr from hosts_pending where subnet_id = '{$_GET['subnet_id']}'";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$pending_hosts = [];
	while ($r = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		$pending_hosts[$r['addr']] = 1;
	}
	
	$sql = "select addr, free from hosts where subnet_id = '{$_GET['subnet_id']}' order by addr asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$hosts = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
		$hosts[$row['addr']] = $row['free'] ? 1 : 0;
	
	$start = inet_aton($subnet_info['addr']);
	$end = gmp_strval(gmp_add($start, $subnet_info['ip_version'] == 4 ? inet_cidrton($subnet_info['cidr']) : inet6_cidrton($subnet_info['cidr'])));
	
	if ($subnet_info['ip_version'] == 4)
	{
		$end = gmp_strval(gmp_sub($end, 1));
		$start = gmp_strval(gmp_add($start, 1));
	}
	
	for ($host = $start; gmp_cmp($host, $end) < 0; $host = gmp_strval(gmp_add($host, 1))) {
		if ((!isset($hosts[inet_ntoa($host)]) && !isset($pending_hosts[inet_ntoa($host)])) || $hosts[inet_ntoa($host)] == 1) {
			$sql = "insert into hosts_pending (subnet_id, addr, added) values('{$_GET['subnet_id']}', '" . inet_ntoa($host) . "', now())";
			@pg_query($sql) or die("error: failed to run query: $sql");
			
			if ($rss_add_host) {
				header('Content-type: application/json');
				echo json_encode(['result' => 'ok', 'host' => inet_ntoa($host)]);
				exit;
			} else {	
				header("Location: host.php?subnet_id={$_GET['subnet_id']}&addr=" . inet_ntoa($host) . '&add_host=1');
				exit;
			}
		}
	}
	if ($rss_add_host) {
		header('Content-type: application/json');
		echo json_encode(['result' => 'subnet-full']);
		exit;
	} else {
		$_SESSION['error_msg'] = "Subnet is full.";
		header("Location: ?subnet_id={$_GET['subnet_id']}");
		exit;
	}
}
else if ($action == 'exportcsv')
{
	$sql = "select * from hosts where subnet_id = '{$_GET['subnet_id']}' order by addr asc";
	$result = @pg_query($sql) or die("error: failed to run query: $sql");
	$hosts = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		$hosts[$row['addr']] = $row;		
	}
	pg_free_result($result);

	header("Content-type: application/octet-stream");  
	header("Content-Disposition: attachment; filename=subnet-export.csv");
	header("Pragma: no-cache");
	header("Expires: 0");
	
	echo "IP,Used,Description,Notes,Last Updated\r\n";
	foreach ($hosts as $host) {
		$desc = trim(str_replace(array('"', "\r", "\n", '&nbsp;'), array('""', ' ', ' ', ' '), strip_tags($host['description'])));
		$notes = trim(str_replace(array('"', "\r", "\n", '&nbsp;'), array('""', ' ', ' ', ' '), strip_tags($host['notes'])));
		
		echo "{$host['addr']}," . ($host['free'] ? "No" : "Yes") . ",\"$desc\",\"$notes\",{$host['last_updated']}\r\n";
	}
}
