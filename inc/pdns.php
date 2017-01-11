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

function pdns_add_domain($domain)
{
	global $config;
	
	$sql = "insert into domains (name, type) values('$domain', 'NATIVE')";
	if (!@mysql_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$domain_id = mysql_insert_id();
	
	@pdns_insert_record($domain_id, $domain, "$config[pdns_ns1] $config[pdns_hostmaster] " . date('Ymd') . "01 10800 3600 604800 86400", 'SOA');
	return $domain_id;
}

function pdns_insert_record($domain_id, $name, $content, $type, $prio = 0, $ttl = 86400)
{
	if (pdns_domain_increment_serial($domain_id) === false)
		return false;
	
	$sql = "insert into records (domain_id, name, content, type, prio, ttl, change_date) values('$domain_id', '$name', '$content', '$type', '$prio', '$ttl', unix_timestamp())";
	if (!@mysql_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return mysql_insert_id();
}

function pdns_remove_record($record_id)
{
	$sql = "select domain_id from records where id = '$record_id'";
	if (!($result = @mysql_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$domain_id = mysql_result($result, 0, 'domain_id');
	
	if (pdns_domain_increment_serial($domain_id) === false)
		return false;
	
	$sql = "delete from records where id = '$record_id'";
	if (!mysql_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return true;
}

function pdns_update_record($record_id, $content, $name = null, $type = null, $prio = null, $ttl = null)
{
	$sql = "select domain_id from records where id = '$record_id'";
	if (!($result = @mysql_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$domain_id = mysql_result($result, 0, 'domain_id');
	
	if (pdns_domain_increment_serial($domain_id) === false)
		return false;
	
	$sql = "update records set change_date = unix_timestamp(), content = '$content'";
	if ($name !== null) {
		$sql .= ", name = '$name'";
	}
	if ($type !== null) {
		$sql .= ", type = '$type'";
	}
	if ($prio !== null) {
		$sql .= ", prio = '$prio'";
	}
	if ($ttl !== null) {
		$sql .= ", ttl = '$ttl'";
	}
	$sql .= " where id = '$record_id'";
	
	if (!mysql_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return true;
}

function pdns_domain_increment_serial($domain_id)
{
	$sql = "select id, content from records where type = 'SOA' and domain_id = '$domain_id'";
	if (!($result = @mysql_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	if (mysql_num_rows($result) == 1) {
		$soa_row = mysql_fetch_assoc($result);
		
		$soa_content = preg_split('/\s+/', $soa_row['content']);
		if (isset($soa_content[2])) {
			if (strlen($soa_content[2]) == 10) {
				$date = substr($soa_content[2], 0, 8);
				if ($date == date('Ymd')) {
					$n = str_pad(substr($soa_content[2], 8)+1, 2, '0', STR_PAD_LEFT);
					$serial = "$date$n";
				}
			}
			if (!isset($serial)) {
				$serial = date("Ymd") . '01';
			}
			$soa_content[2] = $serial;
			
			$soa_content = implode(' ', $soa_content);
			$sql = "update records set content = '$soa_content', change_date = unix_timestamp() where id = '{$soa_row['id']}'";
			if (!mysql_query($sql)) {
				trigger_error("failed to run query: $sql", E_USER_WARNING);
				return false;
			}
			return true;
		}
	}
	return 0;
}
