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

if (!isset($_GET['subnet_id'])) exit;

$sql = "select subnets.description, subnets.notes, hosts.addr from hosts left join subnets on subnets.id = hosts.subnet_id where hosts.gateway = '1' and hosts.subnet_id = '{$_GET['subnet_id']}'";
$result = @pg_query($sql) or die("error: failed to run query: $sql");
if (pg_num_rows($result) > 0)
{
	$gateway = pg_fetch_result($result, 0, 'addr');
	$notes = pg_fetch_result($result, 0, 'notes');
	$description = pg_fetch_result($result, 0, 'description');
}
else
{
	$description = $notes = $gateway = null;
}
$descnotes = $notes . ' ' . $description;
$description = $notes = null;

if ($_POST)
{
	if (!ip_in_network($_POST['addr'], $_POST['subnet_addr']))
		$_SESSION['error_msg'] = 'Host ' . $_POST['addr'] . ' is not within subnet ' . $_POST['subnet_addr'] . '.';
	else if (isset($_POST['used']) && $_POST['used'] == 1 && (!$_POST['description'] && !$_POST['notes']))
		$_SESSION['error_msg'] = 'Enter a description and/or notes.';
	else
	{
		if (isset($_POST['gateway']) && $_POST['gateway'])
		{
			$sql = "update hosts set gateway = '0' where subnet_id = '$_GET[subnet_id]' and gateway = '1'";
			@pg_query($sql) or die("error: failed to run query: $sql");			
		}
		
		if (isset($_POST['used']) && $_POST['used'] == 1)
		{
			// host exist in db yet?
			$sql = "select id from hosts where addr = '{$_POST['addr']}' and subnet_id = '$_GET[subnet_id]'";
			$result = @pg_query($sql) or die("error: failed to run query: $sql");

			$desc = $_POST['description'];			
			if (pg_num_rows($result) == 1)
			{
				if (isset($_POST['new']))
					$_SESSION['error_msg'] = 'That host already exists.';
				else
					$sql = "update hosts set free = '" . (isset($_POST['used']) ? '0' : '1') . "', description = '$desc', " .
						"notes = '$_POST[notes]', last_updated = now(), last_updated_user_id = '{$_SESSION['user']['id']}', gateway = '" . 
						(isset($_POST['gateway']) && $_POST['gateway'] ? 1 : 0) . "', config = '{$_POST['config']}' where addr = '{$_POST['addr']}' and subnet_id = '$_GET[subnet_id]'";
			}
			else
			{
				$sql = "insert into hosts (subnet_id, addr, free, gateway, description, notes, config, last_updated, last_updated_user_id, created, created_user_id) values(
					'{$_GET['subnet_id']}', 
					'{$_POST['addr']}', 
					'" . (isset($_POST['used']) ? '0' : '1')  . "', 
					'" . (isset($_POST['gateway']) ? 1 : 0) . "', 
					'" . $desc . "',
					'{$_POST['notes']}',
					'{$_POST['config']}',
					now(), 
					'{$_SESSION['user']['id']}',
					now(), 
					'{$_SESSION['user']['id']}')";				
			}
		}
		else
		{
			$sql = "delete from hosts where addr = '{$_POST['addr']}' and subnet_id = '$_GET[subnet_id]'";
		}
		
		if (!isset($_SESSION['error_msg']) || !$_SESSION['error_msg'])
		{
			// remove pending host
			$hosts_pending_sql = "delete from hosts_pending where subnet_id = '{$_GET['subnet_id']}' and addr = '{$_POST['addr']}'";
			@pg_query($hosts_pending_sql) or die("error: failed to run query: $hosts_pending_sql: " . pg_last_error());

			@pg_query($sql) or die("error: failed to run query: $sql: " . pg_last_error());
			
			if (isset($_POST['ptr']) && ($_POST['ptr'] != $_POST['old_ptr']))
			{
				if (!$_POST['ptr_record_id'])
				{
					if (ip_version($_POST['addr']) == 4)
					{
						$ip_parts = array_reverse(explode('.', $_POST['addr']));
						$ip_in_addr_arpa = implode('.', $ip_parts) . '.in-addr.arpa';
						array_shift($ip_parts);
						$in_addr_arpa = implode('.', $ip_parts) . '.in-addr.arpa';
					}
					else
					{
						$ip_parts = array_reverse(preg_split('//', trim(str_replace(':', '', inet6_expand($_POST['addr'])))));
						$ip_in_addr_arpa = implode('.', $ip_parts) . 'ip6.arpa';
						for ($i = 0; $i < 24; $i++)
							array_shift($ip_parts);
						$in_addr_arpa = implode('.', $ip_parts) . 'ip6.arpa';
					}
					$sql = "select id from domains where name = '$in_addr_arpa'";
					$result = @mysql_query($sql) or die("error: failed to run query: $sql: " . mysql_error());
					if (mysql_num_rows($result) == 1)
						$domain_id = mysql_result($result, 0, 'id');
					else
						$domain_id = pdns_add_domain($in_addr_arpa);
					
					if ($domain_id)
					{
						if (!@pdns_insert_record($domain_id, $ip_in_addr_arpa, $_POST['ptr'], 'PTR'))
							$ptr_error = true;
					}
					else
					{
						$ptr_error = true;
					}
				}
				else
				{
					if (!@pdns_update_record($_POST['ptr_record_id'], $_POST['ptr']))
						$ptr_error = true;
				}
				
				if (isset($ptr_error) && $ptr_error)
					$_SESSION['error_msg'] = "Failed to update PTR for host.";
			}
			
			if (!isset($ptr_error) && isset($_POST['ptr_update_forward']) && $_POST['ptr_update_forward'])
			{
				$sql = "select id, content from records where name = '{$_POST['ptr']}' and (type = 'A' or type = 'AAAA')";
				$result = @mysql_query($sql) or die("error: failed to run query: $sql: " . mysql_error());
				if (mysql_num_rows($result) == 1)
				{
					$fwd_row = mysql_fetch_assoc($result);
					
					if ($fwd_row['content'] != $_POST['addr'])
					{
						if (!@pdns_update_record($fwd_row['id'], $_POST['addr']))
							$_SESSION['error_msg'] = "Failed to update DNS forward entry.";
						 else 
							$fwd_success = true;
					}
				}
				else
				{
					$ptr_parts = explode('.', $_POST['ptr']);
					$base_domain = array_pop($ptr_parts);
					$base_domain = array_pop($ptr_parts) . "." . $base_domain;
					
					$sql = "select id from domains where name = '$base_domain'";
					$result = @mysql_query($sql) or die("error: failed to run query: $sql: " . mysql_error());
					if (mysql_num_rows($result) == 1)
					{
						$domain_id = mysql_result($result, 0, 'id');
						if (!@pdns_insert_record($domain_id, $_POST['ptr'], $_POST['addr'], (strstr($_POST['addr'], ':') ? 'AAAA' : 'A')))
							$_SESSION['error_msg'] = "Failed to insert DNS forward entry.";
					 else
							$fwd_success = true;
					}
					else
						$_SESSION['error_msg'] = "The zone '$base_domain' does not exist in DNS server.";
				}
				
				if (isset($fwd_success) && $_POST['ptr'] != $_POST['old_ptr'] && $_POST['old_ptr'])
				{
					$sql = "select id from records where name = '{$_POST['old_ptr']}' and (type = 'A' or type = 'AAAA')";
					$result = @mysql_query($sql) or die("error: failed to run query: $sql: " . mysql_error());
					if (mysql_num_rows($result) == 1)
					{
						$record_id = mysql_result($result, 0, 'id');
						
						if (!@pdns_remove_record($record_id))
							$_SESSION['error_msg'] = "Failed to remove old DNS forward entry.";
					}
				}
			}
			
			$_SESSION['notice_msg'] .= 'Host <a href="hosts.php?subnet_id=' . $_GET['subnet_id'] . '#' . $_POST['addr'] . '">' . $_POST['addr'] . '</a> updated.';
			
			if (isset($_GET['ref']) && $_GET['ref'])
				header("Location: {$_GET['ref']}");
			else
				header("Location: hosts.php?subnet_id={$_GET['subnet_id']}");
			exit;
		}
	}
}

$subnet_tree = subnet_get_tree($_GET['subnet_id']);
$subnet_info = $subnet_tree[0];
$parent_subnet_info = isset($subnet_tree[1]) ? $subnet_tree[1] : null;

$sql = "select addr from hosts where gateway = '1' and subnet_id = '{$_GET['subnet_id']}'";
$result = @pg_query($sql) or die("error: failed to run query: $sql");
if (pg_num_rows($result) > 0)
	$gateway = pg_fetch_result($result, 0, 'addr');
else
	$gateway = null;

$sql = "select *, users.username as last_updated_user from hosts left join users on users.id = hosts.last_updated_user_id where hosts.addr = '{$_REQUEST['addr']}' and hosts.subnet_id = '{$_GET['subnet_id']}'";
$result = @pg_query($sql) or die("error: failed to run query: $sql");
$row = pg_fetch_array($result, null, PGSQL_ASSOC);

layout_header($row ? $_REQUEST['addr'] : 'Add Host in ' . $subnet_info['addr']);

if ($row)
{
	$a = array(array('addr' => $_REQUEST['addr'], 'id' => -1));
	$a = array_merge($a, $subnet_tree);
	echo subnet_tree_breadcrumb($a, -1);
}
else
	echo subnet_tree_breadcrumb($subnet_tree);

echo '<form action="host.php?subnet_id=' . $_GET['subnet_id'] . '&ref=' . (isset($_GET['ref']) ? $_GET['ref'] : '') . '" method="post" class="general">';
echo '<input type="hidden" name="subnet_addr" value="' . $subnet_info['addr'] . '" />';
if ($row)
	echo '<input type="hidden" name="addr" value="' . $_REQUEST['addr'] . '" />';
else
	echo '<input type="hidden" name="new" value="1" />';
echo '<ul>';
if (!$row)
{
	echo '<li><label for="addr" class="label">Host Address:</label> <input type="text" name="addr" value="' .
		ifsetor($_POST['addr'], ifsetor($_GET['addr'])) . '" id="addr" size="30" /></li>';
}
else
{
	echo '<li><div class="label">Host Address:</div> ' . $_GET['addr'] . '</li>';
}
echo '<li><div class="label">Subnet Mask:</div> ' . $subnet_info['subnet_mask'] . '</li>';
if ($gateway && (isset($_GET['addr']) && $_GET['addr'] != $gateway))
{
	echo '<li><div class="label">Gateway:</div> ' . $gateway . '</li>';
}
if (isset($pdns_dbh))
{
	if ($subnet_info['ip_family'] == 4)
	{
		$ip_parts = array_reverse(explode('.', ifsetor($_POST['addr'], ifsetor($_GET['addr']))));
		$in_addr_arpa = implode('.', $ip_parts) . '.in-addr.arpa';
	}
	else
	{
		$ip_parts = array_reverse(preg_split('//', trim(str_replace(':', '', inet6_expand($_GET['addr'])))));
		$in_addr_arpa = implode('.', $ip_parts) . 'ip6.arpa';
	}
	$sql = "select id, content from records where type = 'PTR' and name = '$in_addr_arpa'";
	if ($result = @mysql_query($sql)) {
		$ptr_row = mysql_fetch_assoc($result);
		echo '<li class="un"><label class="label" for="ptr">Reverse DNS:</label> <input type="text" id="ptr" name="ptr" value="' . $ptr_row['content'] . '" style="width:20%" /><input type="hidden" name="old_ptr" value="' . 
			$ptr_row['content'] . '" /><input type="hidden" name="ptr_record_id" value="' . $ptr_row['id'] . '" /> <input type="checkbox" id="ptr_update_forward" name="ptr_update_forward" value="1" /> <label for="ptr_update_forward">Add/update forward entry</label></li>';
	}
}
echo '<li><label class="label" for="description">Description:</label> <textarea id="description" name="description" rows="8" style="width:40%">' . 
	ifsetor($_POST['description'], ifsetor($row['description'])) . '</textarea></li>';
if (isset($_POST['notes']))
	$notes = $_POST['notes'];
else if (isset($row['notes']) && $row['notes'])
	$notes = $row['notes'];
echo '<li><label class="label" for="notes">Notes:</label> <textarea id="notes" name="notes" rows="8" style="width:40%">' . $notes . '</textarea></li>';
echo '<li><label class="label" for="config">Config:</label> <textarea id="config" name="config" rows="15" style="width:40%">' . ifsetor($_POST['config'], isset($row['config']) ? $row['config'] : '') . '</textarea></li>';
echo '<li><label class="label" for="gateway">Gateway?</label> <input id="gateway" type="checkbox" name="gateway" onclick="if (this.checked) { this.form.used.checked = true; }" value="1"' . (ifsetor($_POST['gateway'], ifsetor($row['gateway'], 0)) ? ' checked="checked"' : '') . ' /></li>';
echo '<li><label class="label" for="used">Mark as Used?</label> <input type="checkbox" id="used" name="used" value="1"' .  (ifsetor($_POST['used'], !ifsetor($row['free'])) ? ' checked="checked"' : '') . ' /></li>';
echo '<li><div class="label">Last Updated:</div> ' . (isset($row['last_updated']) && $row['last_updated'] ? $row['last_updated'] . ' (' . $row['last_updated_user'] . ')' : 'N/A') . '</li>';
echo '<li><div class="label">&nbsp;</div> <input type="submit" value="  ' . ($row ? 'Update' : 'Add') . ' Host  " /></li>';
echo '</ul>';
echo '</form>';

layout_footer();
