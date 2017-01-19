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

if ($_POST){

	$lines = explode("\r\n",$_POST['input']);
	foreach($lines as $line){
		$line_array = '';
		$column_order = explode(',',$_POST['column_order']);
		//the data is now in 3 columns.
		if($_POST['delimiter'] == '  '){
      			/* hack, excel columns were not copied with two or more spaces before the ip column. */
			$line = preg_replace('/\s?(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})\s?/','#$#' . "$1" . '#$#',$line,1);
			$line = preg_replace('/\s{2,}/','#$#',$line);
			$line = preg_replace('/#\$##\$#/','#$#',$line);
			$new_delimiter = '#$#';
		}
    		//make these sticky for next import
		$_SESSION['delimiter'] = $_POST['delimiter'];
		$_SESSION['column_order'] = $_POST['column_order'];

		$line_array = explode((($new_delimiter) ? $new_delimiter : $_POST['delimiter']),$line);

		foreach($column_order as $id => $column_name){
			if($column_name == 'description')
				$description = $line_array[$id];
			if($column_name == 'notes')
				$notes = $line_array[$id];
			if($column_name == 'ip')
				$ip = $line_array[$id];
		}
		//no range support
		if(stristr($ip,'-'))
			continue;
		
		$description = pg_escape_string($description);
		$notes = pg_escape_string($notes);
		
		if(!$ip){
			//$_SESSION['error_msg'] .= 'Host missing in ' . $description . '/' . $notes . ' line, skipping.';
			continue;
		}
		
		if (!ip_in_network($ip, $_POST['subnet_addr']))
			$_SESSION['error_msg'] .= 'Host ' . $ip . ' is not within subnet ' . $_POST['subnet_addr'] . '.';
		else {
			// host exist in db yet?
			$sql = "select id from hosts where addr = '{$ip}'";
			$result = @pg_query($sql) or die("error: failed to run query: $sql");
			if (pg_num_rows($result) == 1) {
				if (isset($_POST['overwrite'])){
					$_SESSION['notice_msg'] .= 'Host ' . $ip . ' already exists, overwriting.';
					$sql = "update hosts set free = '" . (isset($_POST['used']) ? '0' : '1') . "', description = '$description', " .
						"notes = '$notes', last_updated = now(), last_updated_user_id = '{$_SESSION['user']['id']}', gateway = '" . 
						(isset($_POST['gateway']) && $_POST['gateway'] ? 1 : 0) . "' where addr = '{$ip}' and subnet_id = '$_GET[subnet_id]'";
				}
			} else
			{
				$sql = "insert into hosts (subnet_id, addr, free, gateway, description, notes, last_updated, last_updated_user_id, created, created_user_id) values(
					'{$_GET['subnet_id']}', 
					'{$ip}', 
					'" . (isset($_POST['used']) ? '0' : '1')  . "', 
					'" . (isset($_POST['gateway']) ? 1 : 0) . "', 
					'" . $description . "',
					'{$notes}',
					now(), 
					'{$_SESSION['user']['id']}',
					now(), 
					'{$_SESSION['user']['id']}')";	
			}
			if (!isset($_SESSION['error_msg']) || !$_SESSION['error_msg']) {
				@pg_query($sql) or die("error: failed to run query: $sql: " . pg_last_error());

				/*
				if (isset($_POST['ptr']) && ($_POST['ptr'] != $_POST['old_ptr'])) {
					if (!$_POST['ptr_record_id']) {
						if (ip_version($_POST['addr']) == 4) {
							$ip_parts = array_reverse(explode('.', $_POST['addr']));
							$ip_in_addr_arpa = implode('.', $ip_parts) . '.in-addr.arpa';
							array_shift($ip_parts);
							$in_addr_arpa = implode('.', $ip_parts) . '.in-addr.arpa';
						} else {
							$ip_parts = array_reverse(preg_split('//', trim(str_replace(':', '', inet6_expand($_POST['addr'])))));
							$ip_in_addr_arpa = implode('.', $ip_parts) . 'ip6.arpa';
							for ($i = 0; $i < 24; $i++)
								array_shift($ip_parts);
							$in_addr_arpa = implode('.', $ip_parts) . 'ip6.arpa';
						}
						$sql = "select id from domains where name = '$in_addr_arpa'";
						$result = @mysql_query($sql) or die("error: failed to run query: $sql: " . mysql_error());
						if (mysql_num_rows($result) == 1) {
							$domain_id = mysql_result($result, 0, 'id');
						} else {
							$domain_id = pdns_add_domain($in_addr_arpa);
						}
						if ($domain_id) {
							if (!@pdns_insert_record($domain_id, $ip_in_addr_arpa, $_POST['ptr'], 'PTR')) {
								$ptr_error = true;
							}
						} else {
							$ptr_error = true;
						}					
					} else {
						if (!@pdns_update_record($_POST['ptr_record_id'], $_POST['ptr'])){
							$ptr_error = true;
						}
					}
				
					if (isset($ptr_error) && $ptr_error) {
						$_SESSION['error_msg'] = "Failed to update PTR for host.";
					}
				}
			
				if (!isset($ptr_error) && isset($_POST['ptr_update_forward']) && $_POST['ptr_update_forward']) {
					$sql = "select id, content from records where name = '{$_POST['ptr']}' and (type = 'A' or type = 'AAAA')";
					$result = @mysql_query($sql) or die("error: failed to run query: $sql: " . mysql_error());
					if (mysql_num_rows($result) == 1) {
						$fwd_row = mysql_fetch_assoc($result);
					
						if ($fwd_row['content'] != $_POST['addr']) {
							if (!@pdns_update_record($fwd_row['id'], $_POST['addr'])) {
								$_SESSION['error_msg'] = "Failed to update DNS forward entry.";
							} else {
								$fwd_success = true;
							}
						}
					} else {
						$ptr_parts = explode('.', $_POST['ptr']);
						$base_domain = array_pop($ptr_parts);
						$base_domain = array_pop($ptr_parts) . "." . $base_domain;
					
						$sql = "select id from domains where name = '$base_domain'";
						$result = @mysql_query($sql) or die("error: failed to run query: $sql: " . mysql_error());
						if (mysql_num_rows($result) == 1) {
							$domain_id = mysql_result($result, 0, 'id');
							if (!@pdns_insert_record($domain_id, $_POST['ptr'], $_POST['addr'], (strstr($_POST['addr'], ':') ? 'AAAA' : 'A'))) {
								$_SESSION['error_msg'] = "Failed to insert DNS forward entry.";
							} else {
								$fwd_success = true;
							}
						} else {
							$_SESSION['error_msg'] = "The zone '$base_domain' does not exist in DNS server.";
						}
					}
				
					if (isset($fwd_success) && $_POST['ptr'] != $_POST['old_ptr'] && $_POST['old_ptr']) {
						$sql = "select id from records where name = '{$_POST['old_ptr']}' and (type = 'A' or type = 'AAAA')";
						$result = @mysql_query($sql) or die("error: failed to run query: $sql: " . mysql_error());
						if (mysql_num_rows($result) == 1) {
							$record_id = mysql_result($result, 0, 'id');
							
							if (!@pdns_remove_record($record_id)) {
								$_SESSION['error_msg'] = "Failed to remove old DNS forward entry.";
							}
						}
					}
				}
				*/
				$_SESSION['notice_msg'] .= 'Host <a href="hosts.php?subnet_id=' . $_GET['subnet_id'] . '#' . $ip . '">' . $ip . '</a> updated.';
			}
		}
	}
	if (isset($_GET['ref']) && $_GET['ref'])
		header("Location: {$_GET['ref']}");
	else
		header("Location: hosts.php?subnet_id={$_GET['subnet_id']}");
	exit;
}

$subnet_tree = subnet_get_tree($_GET['subnet_id']);
$subnet_info = $subnet_tree[0];
$parent_subnet_info = isset($subnet_tree[1]) ? $subnet_tree[1] : null;

$sql = "select addr from hosts where gateway = '1' and subnet_id = '{$_GET['subnet_id']}'";
$result = @pg_query($sql) or die("error: failed to run query: $sql");
if (pg_num_rows($result) > 0) {
	$gateway = pg_fetch_result($result, 0, 'addr');
} else {
	$gateway = null;
}

$sql = "select * from hosts where hosts.addr = '{$_REQUEST['addr']}'";
$result = @pg_query($sql) or die("error: failed to run query: $sql");
$row = pg_fetch_array($result, null, PGSQL_ASSOC);

layout_header($row ? $_REQUEST['addr'] : 'Add Host in ' . $subnet_info['addr']);

echo '<div class="main-header">';
if ($row) {
	$a = array(array('addr' => $_REQUEST['addr'], 'id' => -1));
	$a = array_merge($a, $subnet_tree);
	echo subnet_tree_breadcrumb($a, -1);
} else {
	echo subnet_tree_breadcrumb($subnet_tree);
}
echo '</div>';
echo '<div class="main-body">';
echo '<form action="import.php?subnet_id=' . $_GET['subnet_id'] . '&ref=' . (isset($_GET['ref']) ? $_GET['ref'] : '') . '" method="post">';
echo '<input type="hidden" name="subnet_addr" value="' . $subnet_info['addr'] . '" />';
echo '<input type="hidden" name="addr" value="' . $_REQUEST['addr'] . '" />';
echo '<input type="hidden" name="new" value="1" />';
echo '<table border="0" cellspacing="0" cellpadding="0" class="formtbl">';
if ($gateway && (isset($_GET['addr']) && $_GET['addr'] != $gateway)) {
	echo '<tr><td><b>Gateway:</b></td><td>' . $gateway . '</td></tr>';
}
echo '<tr><td width="180"><b>Subnet Mask:</b></td><td>' . $subnet_info['subnet_mask'] . '</td></tr>';
if (isset($pdns_dbh)) {
	if ($subnet_info['ip_family'] == 4) {
		$ip_parts = array_reverse(explode('.', ifsetor($_POST['addr'], ifsetor($_GET['addr']))));
		$in_addr_arpa = implode('.', $ip_parts) . '.in-addr.arpa';
	} else {
		$ip_parts = array_reverse(preg_split('//', trim(str_replace(':', '', inet6_expand($_GET['addr'])))));
		$in_addr_arpa = implode('.', $ip_parts) . 'ip6.arpa';
	}
	$sql = "select id, content from records where type = 'PTR' and name = '$in_addr_arpa'";
	if ($result = @mysql_query($sql)) {
		$ptr_row = mysql_fetch_assoc($result);
		echo '<tr><td width="180"><b>Reverse DNS:</b></td><td><input type="text" name="ptr" value="' . $ptr_row['content'] . '" size="35" /><input type="hidden" name="old_ptr" value="' . $ptr_row['content'] . '" /><input type="hidden" name="ptr_record_id" value="' . $ptr_row['id'] . '" /> <input type="checkbox" name="ptr_update_forward" value="1" /> Add/update forward entry</td></tr>';
	}
}
echo '<tr><td width="180" valign="top"><b>INPUT:</b></td><td><textarea name="input" rows="200" cols="65">' . 
	ifsetor($_POST['input'], ifsetor($row['input'])) . '</textarea></td></tr>';
echo '<tr><td width="180" valign="top"><b>Delimiter:</b></td><td><input type="text" name="delimiter" value="' . (($_SESSION['delimiter']) ? $_SESSION['delimiter'] : ',' ) . '"><label> ** ' . (($_SESSION['delimiter'] == '  ') ? ' delimiter is whitespace' : 'use two spaces for whitespace' ) . '</label></td></tr>';
echo '<tr><td width="180" valign="top"><b>Column Order:</b></td><td><input type="text" name="column_order" value="' . (($_SESSION['column_order']) ? $_SESSION['column_order'] : 'notes,ip,description' ) . '"></td></tr>';
echo '<tr><td><b>Mark as Used?</td><td><input type="checkbox" name="used" value="1"' . 
	(ifsetor($_POST['used'], !ifsetor($row['free'])) ? ' checked="checked"' : '') . ' /></td></tr>';
echo '<tr><td><b>Overwrite existing?</td><td><input type="checkbox" name="overwrite" value="1"' . 
	(ifsetor($_POST['overwrite']) ? ' checked="checked"' : '') . ' /></td></tr>';
echo '</table>';
echo '<br /><br />';
echo '<input type="submit" value="  ' . ($row ? 'IMPORT' : 'IMPORT') . ' Hosts  " />';
echo '</form>';
echo '</div>';

layout_footer();
