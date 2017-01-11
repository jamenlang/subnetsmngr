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

function subnet_get_tree($subnet_id, $subnet_tree = array())
{
	$sql = "select *, netmask(addr) as subnet_mask, family(addr) as ip_family, masklen(addr) as cidr from subnets where id = '$subnet_id'";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$subnet_tree[] = ($r = pg_fetch_array($result, null, PGSQL_ASSOC));
	if ($r['parent_id'] != 0) {
		$subnet_tree = subnet_get_tree($r['parent_id'], $subnet_tree);
	}
	return $subnet_tree;
}

function subnet_has_children($subnet_id)
{
	$sql = "select id from subnets where parent_id = '$subnet_id'";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return pg_num_rows($result) > 0;
}

function subnet_get($subnet, $instance_id = null)
{
	if (preg_match('/^[0-9]+$/', $subnet)) {
		$sql = "select subnets.*, instances.name as instance_name, family(subnets.addr) as ip_family, masklen(subnets.addr) as cidr from subnets left join instances on instances.id = subnets.instance_id where subnets.id = '$subnet'";
	} else {
		$sql = "select subnets.*, instances.name as instance_name, family(subnets.addr) as ip_family, masklen(subnets.addr) as cidr from subnets left join instances on instances.id = subnets.instance_id where subnets.addr = '$subnet' and subnets.instance_id = '$instance_id'";
	}
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return pg_fetch_array($result, null, PGSQL_ASSOC);
}

function subnet_get_parent($instance_id, $subnet)
{
	if (preg_match('/^[0-9]+$/', $subnet)) {
		$sql = "select *, family(addr) as ip_family, masklen(addr) as cidr from subnets where id = (select parent_id from subnets where id = '$subnet' and instance_id = '$instance_id')";
		/*
		$sql = "select parent.*, family(parent.addr) as ip_family, masklen(parent.addr) as cidr from subnets as child " .
			"left join subnets as parent on child.parent_id = parent.id where child.id = '$subnet'";
		*/
	} else {
		$sql = "select *, family(addr) as ip_family, masklen(addr) as cidr from subnets where inet '$subnet' <<= addr and instance_id = '$instance_id' order by masklen(addr) desc limit 1";
	}

	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return pg_num_rows($result) == 0 ? 0 : pg_fetch_array($result, null, PGSQL_ASSOC);
}

function subnet_conflicts($instance_id, $subnet)
{
	list($ip,$mask) = explode('/', $subnet);
	if (!$ip || !$mask) return false;
	
	$sql = "select id, addr from subnets where free = '0' and instance_id = '$instance_id' and ((inet '$subnet' <<= addr) or (inet '$subnet' < addr and broadcast('$subnet') > broadcast(addr)))";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$conflicts = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		$conflicts[] = array('id' => $row['id'], 'addr' => $row['addr']);
	}
	return $conflicts;
}

// find what subnet $subnet is within from our db of subnets, false if not
function which_subnet($subnet)
{
	list($ip,$mask) = explode('/', $subnet);
	if (!$ip || !$mask) return false;
	$naddr = inet_aton($ip);
	$nmask = inet_cidrton($mask);
	
	$sql = "select addr from subnets where addr <<= inet '$subnet' order by masklen(addr) desc limit 1";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	if (pg_num_rows($result) == 1)
		return pg_fetch_result($result, 0, 'addr');
	else {
		$sql = "select addr from base_subnets where addr <<= inet '$subnet' order by masklen(addr) desc limit 1";
		if (!($result = @pg_query($sql))) {
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
		if (pg_num_rows($result) == 1)
			return pg_fetch_result($result, 0, 'addr');
	}
	return false;
}

// find an used /$find_mask within $in_subnet (if null, in any that are marked free)
// $version is ip version
// $single_depth indicates that you only want subnets directly beneath $in_subnet
function find_free_subnet($instance_id, $version, $find_mask, $in_subnet = null, $single_depth = true)
{
	$in_subnets = array();
	if (!$in_subnet) { // no parent subnet provided, so find one available within our root subnets
		$direct_subnet_req = false;
		
		$sql = "select addr from subnets where family(addr) = '$version' and free = '1' and noautoalloc = '0' and masklen(addr) <= '$find_mask' and instance_id = '$instance_id'  ";
		if ($single_depth) {
			// only subnets who are a root or their parent's are root
			$sql .= "and (parent_id = '0' or parent_id in(select id from subnets where parent_id = '0' and family(addr) = '$version' and noautoalloc = '0')) ";
		}
		$sql .= "order by masklen(addr) desc, id asc";
		if (!($result = @pg_query($sql))) {
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
		while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
			list($addr) = explode('/', $row['addr']);
			if (ip_version($addr) == $version) {
				$in_subnets[] = $row['addr'];
			}
		}
	}
	else
	{
		$in_subnets[] = $in_subnet;
		$direct_subnet_req = true;
	}
	
	$find_nmask = $version == 4 ? inet_cidrton($find_mask) : inet6_cidrton($find_mask);
	
	foreach ($in_subnets as $in_subnet) {
		list($addr,$mask) = explode('/', $in_subnet);
		if (!$addr || !$mask) {
			return false;
		}
		$naddr = inet_aton($addr);
		$nmask = $version == 4 ? inet_cidrton($mask) : inet6_cidrton($mask);
		
		// first check to see if there are any subnets allocated in this subnet yet, if not,
		// make sure the desired subnet fits inside the subnet.  if it does, return the subnet as usable.
		$sql = "select addr from subnets where addr << inet '$in_subnet' and noautoalloc = '0' and instance_id = '$instance_id'";
		if (!($result = @pg_query($sql))) {
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
		if (pg_num_rows($result) == 0) {
			if (gmp_cmp($find_mask, $mask) >= 0) {
				return $in_subnet;
			}
			//continue;
			return false;
		}
		
		// get $in_subnet's ID
		$sql = "select id from subnets where addr = '$in_subnet'";
		if (!($result = @pg_query($sql))) {
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
		if (pg_num_rows($result) == 1) {
			$parent_subnet_id = pg_fetch_result($result, 0, 'id');
		} else {
			$parent_subnet_id = null;
		}

		//
		// if we get this far, the subnet has others allocated in it, so find one available (unused and no subnets allocated in it) or one bigger that we can split
		//		
		
		// we are asking for a subnet within $in_subnet argument at single-depth, so we don't care if noautoalloc is "true" for $in_subnet
		if ($direct_subnet_req && $single_depth && $parent_subnet_id) {
			$sql = "select addr from subnets as s where s.free = '1' and family(s.addr) = '$version' and masklen(s.addr) <= '$find_mask' and (s.addr <<= '$in_subnet') and s.instance_id = '$instance_id' and " .
					"s.id not in(select parent_id from subnets where instance_id = '$instance_id')";
			$sql .= " and parent_id = '$parent_subnet_id'";
			$sql .= " order by masklen(addr) desc limit 1";
		} else {
			$sql = "select addr from subnets as s where s.free = '1' and family(s.addr) = '$version' and masklen(s.addr) <= '$find_mask' and (s.addr <<= '$in_subnet') and s.instance_id = '$instance_id' and s.noautoalloc = '0' and " .
					"s.id not in(select parent_id from subnets where instance_id = '$instance_id') and ((select noautoalloc from subnets where id = s.parent_id and parent_id != 0) = '0' or (select noautoalloc from subnets where id = s.parent_id and parent_id != 0) is null)";
			if ($single_depth && $parent_subnet_id) {
				$sql .= " and parent_id = '$parent_subnet_id'";					
			}
			$sql .= " order by masklen(addr) desc limit 1";
		}
		if (!($result = @pg_query($sql))) {
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
		if (pg_num_rows($result) == 1) {
			$row = pg_fetch_array($result, null, PGSQL_ASSOC);
			return $row['addr'];
		}
	}
	return false;
}

// manually allocate subnet.  for example, if i want to add 192.168.100.0/24 specifcially, this function
// will find the free parent subnet lets say that is 192.168.0.0/16 and then add allocate it appropriately
// splitting it up into appropriate chunks to allow for 192.168.100.0/24 to exist within it
// return values:
//   -1 = no unused subnets available
//   false  = error occurred
//   array  = successfull allocation; returns array('replace_subnet_id' => int, 'subnets' => array('x.x.x.x/x', ...))
function subnet_manual_allocate($instance_id, $alloc_subnet)
{
	list($alloc_addr,$alloc_mask) = explode('/', $alloc_subnet);
	if (!$alloc_addr || !$alloc_mask) {
		return false;
	}
	
	$sql = "select subnets.*, masklen(addr) as mask, family(addr) as version from subnets where addr >>= inet '$alloc_subnet' and instance_id = '$instance_id' and free = '1'";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$row = pg_fetch_array($result, null, PGSQL_ASSOC);
	if (!$row) {
		return -1;
	}
	
	$parent_naddr = inet_aton($row['addr']);
	$parent_nmask = $row['version'] == 4 ? inet_cidrton($row['mask']) : inet6_cidrton($row['mask']);
	
	$alloc_subnet_naddr = inet_aton($alloc_addr);
	$alloc_subnet_nmask = $row['version'] == 4 ? inet_cidrton($alloc_mask) : inet6_cidrton($alloc_mask);
	
	// find front cidrs
	$front = gmp_sub($alloc_subnet_naddr, $parent_naddr);
	$cidrs = array();
	if (gmp_cmp($front, "0") != 0) {
		if (!subnet_simplify(gmp_strval($front), $row['version'], $cidrs)) {
			return false;
		}
	}
	
	// our manually allocated subnet
	$cidrs[] = $alloc_mask;
	
	// find rear cidrs
	$rear = gmp_sub(
		gmp_add($parent_naddr, $parent_nmask),
		gmp_add($alloc_subnet_naddr, $alloc_subnet_nmask)
	);
	if (gmp_cmp($rear, "0") != 0) {
		if (!subnet_simplify(gmp_strval($rear), $row['version'], $cidrs)) {
			return false;
		}
	}
	
	$n = $parent_naddr;
	$subnets = array();
	foreach ($cidrs as $c) {
		$subnets[] = inet_ntoa($n) . '/' . $c;
		$n = gmp_strval(gmp_add("$n", ($row['version'] == 4 ? inet_cidrton($c) : inet6_cidrton($c))));
	}
	return array(
		'parent_subnet_id' => $row['id'],
		'subnets' => $subnets
	);
}

// takes a host chunk ($n) and returns appropriate simplified subnets cidrs in $cidrs array
// i.e. if i pass in 1024 it will return array(22); if i pass in 25600 it will return array(18, 19, 22);
function subnet_simplify($n, $ip_version, &$cidrs)
{
	$nmask = 0;
	$mask = null;
	for ($i = 1; $i <= ($ip_version == 4 ? 32 : 128); $i++) {
		$nmask = ($ip_version == 4 ? inet_cidrton($i) : inet6_cidrton($i));
		if (gmp_cmp("$nmask", "$n") <= 0) {
			$mask = $i;
			break;
		}
	}
	if (!$mask) {
		$cidrs = array();
		return false;
	}
	$cidrs[] = $mask;
	$r = gmp_sub("$n", "$nmask");
	if (gmp_cmp($r, "0") != 0) {
		// if remainder is < /30 for v4 or /127 for v6, bail
		if (gmp_cmp($r, $ip_version == 4 ? "4" : "2") < 0) {
			$cidrs = array();
			return false;
		} else {
			return subnet_simplify(gmp_strval($r), $ip_version, $cidrs);
		}
	}
	return true;
}

// if the subnet can be used by auto-allocator
function subnet_is_considered_for_autoallocation($subnet_id)
{
	$sql = "select parent_id, noautoalloc from subnets where id = '$subnet_id'";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	if (pg_num_rows($result) == 1) {
		$row = pg_fetch_array($result, null, PGSQL_ASSOC);
		if ($row['parent_id'] > 0) {
			return subnet_is_considered_for_autoallocation($row['parent_id']);
		}
		if ($row['noautoalloc'] == 0) {
			return true;
		} else {
			return false;
		}
	}
	return false;
}

function subnet_delete($subnet_id)
{
	if (!subnet_delete_children($subnet_id)) {
		return false;
	}
	$sql = "delete from subnets where id = '$subnet_id'";
	if (!@pg_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return true;
}

// return subnets necessary to allocate $alloc_subnet within $in_subnet.
// i.e. if you allocate a /25 within a /19, return blocks of /20,21,22,23,24,25,25
function subnet_allocate($in_subnet, $alloc_subnet)
{
	list($subnet,$mask) = explode('/', $in_subnet);
	list($alloc_subnet,$alloc_mask) = explode('/', $alloc_subnet);
	if (!$subnet || !$mask || !$alloc_subnet || !$alloc_mask) return false;		
	
	$ipv = ip_version($subnet);
	if ($ipv != ip_version($alloc_subnet)) {
		return false;
	}
	
	$nsubnet = inet_aton($subnet);
	$nmask = $ipv == 4 ? inet_cidrton($mask) : inet6_cidrton($mask);
	$alloc_nsubnet = inet_aton($alloc_subnet);
	$alloc_nmask = $ipv == 4 ? inet_cidrton($alloc_mask) : inet6_cidrton($alloc_mask);
	
	// make sure the subnet we're wanting to allocate fits in the subnet
	if (gmp_cmp("$alloc_nsubnet", "$nsubnet") < 0 || gmp_cmp(gmp_add("$alloc_nsubnet", "$alloc_nmask"), gmp_add("$nsubnet", "$nmask")) > 0) {
		return false;
	}
	// if $in_subnet and $alloc_subnet are the same, no allocation needed.
	if (gmp_cmp("$nsubnet", "$alloc_nsubnet") == 0 && gmp_cmp("$nmask", "$alloc_nmask") == 0) {
		return array("$alloc_subnet/$alloc_mask");
	}
	
	$nmask_ab = gmp_strval(gmp_div("$nmask", "2"));
	$nsubnet_a = $nsubnet;
	$nsubnet_b = gmp_strval(gmp_add("$nsubnet", "$nmask_ab"));
	
	// if the split mask does not equal our intended mask we're wanting to allocate, split again
	// otherwise we're done, return the two split subnets
	if (gmp_cmp("$nmask_ab", "$alloc_nmask") != 0) {
		// find out which subnet we need to split again by determing which of the two our alloc_subnet resides
		if (gmp_cmp("$alloc_nsubnet", "$nsubnet_a") >= 0 && gmp_cmp(gmp_add("$alloc_nsubnet", "$alloc_nmask"), gmp_add("$nsubnet_a", "$nmask_ab")) <= 0) {
			$break_subnet = $nsubnet_a;
			$subnets = array(inet_ntoa("$nsubnet_b") . "/" . ($ipv == 4 ? inet_ntocidr($nmask_ab) : inet6_ntocidr($nmask_ab)));
		} else {
			$break_subnet = $nsubnet_b;
			$subnets = array(inet_ntoa("$nsubnet_a") . "/" . ($ipv == 4 ? inet_ntocidr($nmask_ab) : inet6_ntocidr($nmask_ab)));
		}
		
		$subnets = array_merge($subnets, subnet_allocate(inet_ntoa("$break_subnet") . '/' . ($ipv == 4 ? inet_ntocidr($nmask_ab) : inet6_ntocidr($nmask_ab)), "$alloc_subnet/$alloc_mask"));
	} else
		$subnets = array(inet_ntoa("$nsubnet_a") . "/" . ($ipv == 4 ? inet_ntocidr($nmask_ab) : inet6_ntocidr($nmask_ab)), inet_ntoa($nsubnet_b) . "/" . ($ipv == 4 ? inet_ntocidr($nmask_ab) : inet6_ntocidr($nmask_ab)));
	
	return $subnets;
}

// provide array of subnets to collapse into largest subnets possible
function collapse_subnets($subnets)
{
	do {
		$last_subnets = $subnets;
		
		// convert subnets into ints and sort them so possible collapses will be adjacent
		$nsubnets = array();
		foreach ($subnets as $subnet) {
			list($addr,$mask) = explode('/', $subnet);
			if (!$addr || !$mask) return false;
			
			$nsubnets[] = inet_aton($addr) . '/' . (($ipv = ip_version($addr)) == 6 ? inet6_cidrton($mask) : inet_cidrton($mask));
		}
		usort($nsubnets, create_function('$a,$b', '
			list($addr_a,$mask_a) = explode("/", $a);
			list($addr_b,$mask_b) = explode("/", $b);
			if (gmp_cmp($mask_a, $mask_b) < 0)
				return -1;
			else if (gmp_cmp($mask_a, $mask_b) > 0)
				return 1;
			return (gmp_cmp($addr_a, $addr_b) < 0 ? -1 : 1);
		'));
		
		$count = count($nsubnets);
		for ($i = 0; $i < $count; $i++) {
			if (!isset($nsubnets[$i+1])) {
				break;
			}
			
			list($naddr_a,$nmask_a) = explode('/', $nsubnets[$i]);
			list($naddr_b,$nmask_b) = explode('/', $nsubnets[$i+1]);
			
			// must be same size blocks to collapse
			if (gmp_cmp($nmask_a, $nmask_b) != 0) {
				continue;
			}
			
			// after some swapping has gone on, the 2 adjacent blocks may not be in ascending order anymore
			// so swap if that's the case.
			if (gmp_cmp($naddr_b, $naddr_a) < 0) {
				$temp = $naddr_a;
				$naddr_a = $naddr_b;
				$naddr_b = $temp;
			}
			// if the end of the first subnet lines up with the second subnet
			// and it's on a bit boundary, collapse it
			if (gmp_cmp(gmp_add($naddr_a, $nmask_a), $naddr_b) == 0 &&
				gmp_cmp(
					gmp_and(
						$naddr_a,
						inet_aton(inet_cidrtonetmask(inet_ntocidr(gmp_strval(gmp_mul($nmask_a, 2)), $ipv), $ipv))
					),
					$naddr_a
				) == 0) {
				
				//&& gmp_strval(gmp_mod(gmp_and(gmp_strval($naddr_a, 2), $ipv == 4 ? 0x000000ff : 0xffff), gmp_mul($nmask_a, 2))) == 0) {
				
				$nsubnets[$i+1] = $naddr_a . '/' . gmp_strval(gmp_mul($nmask_a, 2));
				unset($nsubnets[$i]);
			}
		}
		
		$subnets = array();
		foreach ($nsubnets as $nsubnet) {
			list($naddr,$nmask) = explode('/', $nsubnet);
			$subnets[] = inet_ntoa($naddr) . '/' . ($ipv == 4 ? inet_ntocidr($nmask) : inet6_ntocidr($nmask));
		}
	} while ($subnets != $last_subnets);
	
	return $subnets;
}

// return number of free hosts within $subnet
// if $wholistic_calc is true, calculate all hosts in a subnet as 'used' if the parent subnet is 'used'
function free_hosts($instance_id, $subnet, $wholistic_calc = false)
{
	if (strpos($subnet, '/') === false) {
		$addr = $subnet;
		$mask = 32;
	} else {
		list($addr,$mask) = explode('/', $subnet);
	}
	if (!$addr || !$mask) return false;
	$naddr = inet_aton($addr);
	$nmask = ip_version($addr) == 4 ?  inet_cidrton($mask) : inet6_cidrton($mask);
	
	if ($wholistic_calc) {
		$sql = "select addr from subnets where addr <<= inet '$subnet' and instance_id = '$instance_id' and free = '1'";
		if (!($result = @pg_query($sql))) {
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
		// if no subnets marked as free in this subnet, all hosts 'used', return 0
		if (($num_free = pg_num_rows($result)) == 0)
			return 0;
		$tot_free = 0;
		while ($row = pg_fetch_assoc($result)) {
			list($a,$m) = explode('/', $row['addr']);
			$nm = ip_version($row['addr']) == 4 ?  inet_cidrton($m) : inet6_cidrton($m);
			$tot_free = gmp_add($nm, $tot_free);
		}
		return gmp_strval($tot_free);
	} else {
		$sql = "select hosts.free from hosts left join subnets on subnets.id = hosts.subnet_id where hosts.free = '0' and hosts.addr <<= inet '$subnet' and subnets.instance_id = '$instance_id'";
		if (!($result = @pg_query($sql))) {
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
		// if no hosts marked as used in this subnet, all hosts free, return $nmask
		if (($num_used = pg_num_rows($result)) == 0)
			return $nmask;
		return gmp_strval(gmp_sub($nmask, $num_used));
	}
}

// return free subnets beneath $parent_id (single-level)
function free_subnets_in_subnet($parent_id)
{
	$sql = "select addr from subnets where free = '1' and parent_id = '$parent_id' order by addr asc";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$free_subnets = array();
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		$free_subnets[] = $row['addr'];
	}
	return $free_subnets;
}

function subnet_valid($subnet)
{
	list($addr,$mask) = explode('/', $subnet);
	if (!$addr || !$mask) return false;
	
	if (ip_version($addr) == 4)
	{
		if ((ip2long($addr) & ((1 << (32 - $mask)) - 1)) != 0)
			return false;
	}
	else
	{
		if (gmp_scan1(gmp_strval(ip2long6($addr), 2), $mask) < 128-$mask)
			return false;
	}
	return true;
}

// return usable hosts in $mask
function usable_hosts($mask)
{
	if (inet_ntocidr($mask) == 32)
		return 1;
	return $mask-2;
}

// count number of subnets
function count_total_subnets()
{
	$sql = "select count(id) as n from subnets";
	if (!($result = @pg_query($sql)))
	{
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return pg_fetch_result($result, 0, 'n');
}

function count_total_hosts()
{
	$sql = "select addr from base_subnets";
	if (!($result = @pg_query($sql)))
	{
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$total = "0";
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
	{
		list($addr,$mask) = explode('/', $row['addr']);
		$nmask = ($v = ip_version($addr)) == 4 ? inet_cidrton($mask) : inet6_cidrton($mask);
		$total = gmp_strval(gmp_add($total, $nmask));
	}
	return $total;
}	

function count_total_usable_hosts()
{
	$sql = "select id, addr from base_subnets";
	if (!($result = @pg_query($sql)))
	{
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$total = "0";
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC))
	{
		$sql = "select addr from subnets where base_id = '{$row['id']}'";
		if (!($iresult = @pg_query($sql)))
		{
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
		if (pg_num_rows($iresult) > 0)
		{
			while ($irow = pg_fetch_array($iresult, null, PGSQL_ASSOC))
			{
				list($addr,$mask) = explode('/', $irow['addr']);
				$nmask = ($v = ip_version($addr)) == 4 ? inet_cidrton($mask) : inet6_cidrton($mask);
				$total = gmp_strval(gmp_add($total, "$nmask"));
				
				if ($v == 4)
				{
					if (gmp_cmp("$mask", "1") != 0) // not a /32 ?
						$total -= 2;
				}
			}
		}
		else
		{
			list($addr,$mask) = explode('/', $row['addr']);
			$nmask = ($v = ip_version($addr)) == 4 ? inet_cidrton($mask) : inet6_cidrton($mask);
			if ($version == 4)
				$total = gmp_strval(gmp_add($total, gmp_sub($nmask, 2)));
			else
				$total = gmp_strval(gmp_add($total, $nmask));
		}
	}
	return $total;
}

// TODO: make function to recursively send swip removals for every eligible subnet beneath $subnet_id
function subnet_send_swip_removals($subnet_id)
{
	$sql = "select *, masklen(addr) as cidr from subnets where parent_id = '$subnet_id' and swiped = '1'";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		subnet_send_swip_removals($row['id']);
		if ($row['cidr'] <= 29) {
			$swip = get_swip($row['addr']);
			if (isset($swip['netname']) && isset($swip['originas'])) { 
				send_swip($row['addr'], 'R', $row['customer_id'], $swip['netname'], null, null, $swip['originas']);
			}
                }
	}
}

function subnet_mark_unused($subnet_id)
{
	// get subnet's instance id
	$sql = "select instance_id from subnets where id = '$subnet_id'";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$instance_id = pg_fetch_result($result, 0, 'instance_id');	
	
	// check to see if this subnet is SWIPed, and if so, send a remove SWIP
	$sql = "select customer_id, addr, swiped from subnets where id = '$subnet_id' and swiped = '1' and customer_id is not null";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	if (pg_num_rows($result) == 1) {
		$row = pg_fetch_array($result, null, PGSQL_ASSOC);
		$swip = get_swip($row['addr']);
		if (isset($swip['netname']) && isset($swip['originas'])) {
			if (!send_swip($row['addr'], 'R', $row['customer_id'], $swip['netname'], null, null, $swip['originas']))
				$_SESSION['error_msg'] = 'Failed to send removal SWIP.';
		}
	}
	
	$sql = "delete from hosts where subnet_id = '$subnet_id'";
	if (!@pg_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	
	$sql = "delete from group_subnets where subnet_id = '$subnet_id'";
	if (!@pg_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	
	if (!subnet_delete_children($subnet_id)) {
		return false;
	}
	
	$sql = "update subnets set description = 'UNUSED', notes = '', free = '1', customer_id = null where id = '$subnet_id'";
	if (!@pg_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	
	// get the dealloced subnet's parent
	$parent = subnet_get_parent($instance_id, $subnet_id);
	
	if (!$parent) { // if no parent, it's a root subnet so we're done
		return true;
	}
	
	// pull out all the subnets in the parent that aren't free
	$sql = "select id from subnets where free = '0' and parent_id = '{$parent['id']}'";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	$num_used_subnets = pg_num_rows($result);
	
	// if all subnets marked free and the # of free hosts in this subnet is now equal to the total number of hosts in the subnet, then 
	// clear out the base subnet as all space is now unused
	if ($num_used_subnets == 0 && gmp_cmp(free_hosts($parent['addr']), ($parent['ip_family'] == 4 ? inet_cidrton($parent['cidr']) : inet6_cidrton($parent['cidr']))) == 0) {
		$sql = "delete from subnets where parent_id = '$parent[id]'";
		if (!@pg_query($sql)) {
			trigger_error("failed to run query: $sql", E_USER_WARNING);
			return false;
		}
	} else {
		// collapse unused subnets into larger subnets if possible
		$free_subnets = free_subnets_in_subnet($parent['id']);
		if ($collapsed_subnets = collapse_subnets($free_subnets)) {
			//print_r($collapsed_subnets);exit;
			foreach ($free_subnets as $subnet) {
				if (!in_array($subnet, $collapsed_subnets)) {
					$subnet_info = subnet_get($subnet, $instance_id);
					$fp = fopen('/tmp/log.txt', 'a+');
					fwrite($fp, "got here: " . $subnet_info['id']);
					fclose($fp);
					$sql = "delete from hosts where subnet_id = '$subnet_info[id]'";
					if (!@pg_query($sql)) {
						trigger_error("failed to run query: $sql", E_USER_WARNING);
						return false;
					}
					
					$sql = "delete from subnets where parent_id = '$subnet_info[id]'";
					if (!@pg_query($sql)) {
						trigger_error("failed to run query: $sql", E_USER_WARNING);
						return false;
					}
					
					$sql = "delete from subnets where id = '$subnet_info[id]'";
					if (!@pg_query($sql)) {
						trigger_error("failed to run query: $sql", E_USER_WARNING);
						return false;
					}
				}
			}
			
			// if collapsed_subnets only has 1 entry and it's the parent subnet, don't add
			if (count($collapsed_subnets) > 1 || $collapsed_subnets[0] != $parent['addr']) {
				// add the collapsed subnets
				foreach ($collapsed_subnets as $subnet) {
					if (!in_array($subnet, $free_subnets)) {
						$sql = "insert into subnets (instance_id, parent_id, addr, free, description, last_updated_user_id, last_updated) values('$parent[instance_id]', '$parent[id]', '$subnet', '1', " .
							"'UNUSED; AUTO-ALLOCATED', '{$_SESSION['user']['id']}', current_timestamp)";
						if (!@pg_query($sql)) {
							trigger_error("failed to run query: $sql", E_USER_WARNING);
							return false;
						}
					}
				}
			}
		}
	}
	return true;
}

function subnet_delete_children($subnet_id)
{
	$sql = "select id from subnets where parent_id = '$subnet_id'";
	if (!($result = @pg_query($sql))) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	while ($row = pg_fetch_array($result, null, PGSQL_ASSOC)) {
		subnet_delete_children($row['id']);
	}
	$sql = "delete from subnets where parent_id = '$subnet_id'";
	if (!@pg_query($sql)) {
		trigger_error("failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return true;
}

function subnet_mask_dropdown($name, $version, $min = 1, $details = false)
{
	$ret = '<select name="' . $name . '">';
	if ($version == 4) {
		//$min = 1;
		$max = 30;
	} else {
		//$min = 1;
		$max = 127;
	}
	for ($i = $min; $i <= $max; $i++) {
		$ret .= '<option value="' . $i . '"';
		if (isset($_REQUEST[$name])) {
			if (ifsetor($_REQUEST[$name]) == $i)
				$ret .= ' selected="selected"';
		} else if (($version == 6 && $i == 64) || ($version == 4 && $i == 30)) {
			$ret .= ' selected="selected"';
		}
		if ($version == 6) {
			$ret .= '>/' . $i;
			if ($details) {
				$ret .= ' (' . gmp_strval(gmp_pow("2", ($version == 4 ? 32 : 128)-$i)) . ' hosts)';
			}
			$ret .= '</option>';
		} else {
			$ret .= '>/' . $i;
			if ($details) {
				$ret .= ' - ' . long2ip(ip2long('255.255.255.255') << 32-$i);
				$ret .= ' (' . pow(2, 32-$i) . ' hosts)';
			}
			$ret .= '</option>';
		}
	}
	$ret .= '</select>';
	return $ret;
}

function get_recently_added_hosts($days = 3, $max = 25)
{
	//$sql = "select * from hosts where 
}
