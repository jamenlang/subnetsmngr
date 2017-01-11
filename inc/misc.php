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

function ip_version($ip)
{
	return (strpos($ip, ':') === false ? 4 : 6);
}

function inet_aton($ip)
{
	if (($pos = strpos($ip, '/')) !== false)
		$ip = substr($ip, 0, $pos);
	if (ip_version($ip) == 4)
		return ip2long($ip);
	return ip2long6($ip);
}

function inet_ntoa($n)
{
	if (gmp_cmp("$n", gmp_pow(2,32)) < 0)
		return long2ip($n);
	return long2ip6($n);
}

function inet_ntocidr($n, $v = 4)
{
	if ($v == 4) {
		return $n == 1 ? 32 : (32 - strlen(decbin($n-1)));
	} else {
		return inet6_ntocidr($n);
	}
}

function inet_cidrton($cidr, $v = 4)
{
	if ($v == 4) {
		return pow(2, 32-$cidr);
	} else {
		return inet6_cidrton($cidr);
	}
}

function inet6_ntocidr($n)
{
	return 128 - gmp_scan1(gmp_strval($n, 2), 0);
}

function inet6_cidrton($cidr)
{
	return gmp_strval(gmp_pow(2, 128 - $cidr));
}

function inet_cidrtonetmask($cidr, $v = 4)
{
	if ($v == 4) {
		return inet_ntoa(-1 << (32 - (int)$cidr));
	} else {
		return inet6_cidr2netmask($cidr);
	}
}

function inet6_cidr2netmask($cidr)
{
	$bin = str_repeat(1, $cidr);
	if ($cidr < 128) {
		$bin .= str_repeat(0, 128-$cidr);
	}
	
	return inet_ntoa(gmp_strval(gmp_init($bin, 2), 10));
}


function ip2long6($ip)
{
	if (!($ip_n = @inet_pton($ip)))
		return false;
	$n = '';
	for ($i = 0; $i < strlen($ip_n); $i++)
		$n .= str_pad(base_convert(ord($ip_n[$i]), 10, 2), 8, 0, STR_PAD_LEFT);
	return gmp_strval(gmp_init($n, 2), 10);
}

function long2ip6($n)
{
	$bin = str_pad(gmp_strval(gmp_init("$n", 10), 2), 128, 0, STR_PAD_LEFT);
	$ip = '';
	for ($i = 0; $i < 8; $i++)
		$ip .= base_convert(substr($bin, $i*16, 16), 2, 16) . ':';
	$ip = rtrim($ip, ':');
	
	return inet_ntop(inet_pton($ip));
}

function inet6_expand($addr)
{
	if (strpos($addr, '::') !== false) {
		$part = explode('::', $addr);
		$part[0] = explode(':', $part[0]);
		$part[1] = explode(':', $part[1]);
		$missing = array();
		for ($i = 0; $i < (8 - (count($part[0]) + count($part[1]))); $i++) {
			array_push($missing, '0000');
		}
		$missing = array_merge($part[0], $missing);
		$part = array_merge($missing, $part[1]);
	} else {
		$part = explode(":", $addr);
    	}
	foreach ($part as &$p) {
		while (strlen($p) < 4) $p = '0' . $p;
	}
	unset($p);

	$result = implode(':', $part);
	if (strlen($result) == 39) {
		return $result;
	} else {
		return false;
	}
}

function ip_in_network($ip, $network)
{
	$sql = "select 1 as insub where inet '$ip' <<= inet '$network'";
	if (!($result = @pg_query($sql)))
	{
		trigger_error("error: failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return (pg_num_rows($result) != 0);
}

function whois($q, $server = 'whois.arin.net')
{
	if (!($sock = fsockopen($server, 43, $errno, $errstr, 20))) {
		$result = "Unable to connect to whois server $server: $errstr";
	} else {
		fwrite($sock, $q . "\n");
		$result = array();
		while (!feof($sock)) {
			$buf = trim(fgets($sock, 1024));
			$buf = preg_replace('/^#.*$/', '', $buf);
			if ($buf) {
				$result[] = $buf;
			}
		}
		fclose($sock);
	}
	return $result;
}

function get_swip($addr)
{
	// run whois on IP address
	$whois = whois("r < $addr", 'whois.arin.net');
	if (isset($whois[0]) && preg_match('/.* (.*?) \((NET-.+)\)/', $whois[0], $m)) // pull out their NetName
	{
		// run whois on NetName to get specific info on customer
		$whois = whois($m[2]);
		$swip = array();
		foreach ($whois as $line)
		{
			if (preg_match('/^(Org|Cust)Name:\s*(.+)$/', $line, $m))
				$swip['name'] = strtoupper(trim($m[2]));
			else if (preg_match('/^Address:\s*(.+)$/', $line, $m))
			{
				if (isset($customer['Address1']))
					$swip['addr2'] = normalize_address($m[1]);
				else
					$swip['addr1'] = normalize_address($m[1]);
			}
			else if (preg_match('/^City:\s*(.+)$/', $line, $m))
				$swip['city'] = strtoupper(trim($m[1]));
			else if (preg_match('/^StateProv:\s*(.+)$/', $line, $m))
				$swip['state'] = strtoupper(trim($m[1]));
			else if (preg_match('/PostalCode:\s*(.+)$/', $line, $m))
				$swip['zip'] = strtoupper(trim($m[1]));
			else if (preg_match('/^Country:\s*(.+)$/', $line, $m))
				$swip['country'] = strtoupper(trim($m[1]));
			else if (preg_match('/^CIDR:\s*(.+)$/', $line, $m))
				$swip['cidr'] = strtoupper(trim($m[1]));
			else if (preg_match('/^Updated:\s*(.+)$/', $line, $m))
				$swip['lastswiped'] = trim($m[1]);
			else if (preg_match('/^OriginAS:\s*(.+)$/', $line, $m))
				$swip['originas'] = preg_replace('/[^0-9]/', '', trim($m[1]));
			else if (preg_match('/^NetName:\s*(.+)$/', $line, $m))
				$swip['netname'] = trim($m[1]);
		}
		return $swip;
	}
	return false;
}

function host_up($host, $timeout = 5)
{
	if (!($fp = @fsockopen($host, 80, $errno, $errstr, $timeout)))
	{
		if ($errno == 111)
			return true;
		return false;
	}
	fclose($fp);
	return true;
}

function get_txt_record($ip)
{
	if (inet_ntoa(inet_aton($ip)) != $ip)
		return false;
	$ip_parts = explode('.', $ip);
	if ($records = dns_get_record("$ip_parts[3].$ip_parts[2].$ip_parts[1].$ip_parts[0].in-addr.arpa", DNS_TXT))
	{
		$txt = array();
		foreach ($records as $r)
			$txt[] = $r['txt'];
		return $txt;
	}
	return false;
}

function ifsetor(&$var, $or = '')
{
	return isset($var) ? $var : $or;
}

function normalize_address($address)
{
	$address = strtoupper(trim($address));
	$find = array('/\./', '/\bSUITE\b/', '/\bSTREET\b/', '/\bDRIVE\b/', '/\bAVENUE\b/', '/\bAV\b/', '/\bROAD\b/');
	$replace = array('', 'STE', 'DR', 'AVE', 'AVE', 'RD');
	return preg_replace($find, $replace, $address);
}

function send_swip($subnet, $reg_action, $customer_id, $netname = '', $comments = '', $bcc = '', $originas = null)
{
	global $config;
	
	list($addr,$mask) = explode('/', $subnet);
	if (!$addr || !$mask) return false;
	
	if ($reg_action != 'M' && $reg_action != 'N' && $reg_action != 'R')
		return false;
	
	$sql = "select subnets.id as subnet_id, customers.* from subnets left join customers on customers.id = subnets.customer_id where subnets.addr = '$subnet' and subnets.customer_id = '$customer_id'";
	if (!($result = @pg_query($sql))) {
		trigger_error("error: failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	if (pg_num_rows($result) == 0) // just make sure this subnet exists and belongs to the customer
		return false;
	$row = pg_fetch_array($result, null, PGSQL_ASSOC);
	
	if (!$originas) $originas = $config['origin_as'];
	if (!$netname) $netname = "NET-" . str_replace(array(".", "/"), "-", $subnet);
	
	$msg = <<<END_MSG
Template: ARIN-REASSIGN-SIMPLE-4.1
**  As of February 2007
**  Detailed instructions are located below the template.

01. Registration Action (N,M, or R): {$reg_action}
02. Network Name: {$netname}
03. IP Address and Prefix or Range: {$subnet}
04. Origin AS: {$originas}
05. Customer Name: {$row['name']}
06. Customer Address: {$row['addr1']}
06. Customer Address: {$row['addr2']}
07. Customer City: {$row['city']}
08. Customer State/Province: {$row['state']}
09. Customer Postal Code: {$row['zip']}
10. Customer Country Code: {$row['country']}
11. Public Comments: {$comments}

END OF TEMPLATE

If you would like assistance completing this template, please do
not hesitate to contact ARIN's Registration Services Help Desk
at +1 (703) 227-0660. We'll be glad to help you!
END_MSG;
	
	@mail('hostmaster@arin.net', 'REASSIGN SIMPLE', $msg, "From: {$config['swip_from']}" . ($bcc ? "\r\nBcc: $bcc" : ""));
	
	// update lastswiped date/swiped on subnet
	$sql = "update subnets set last_swiped = now()";
	if ($reg_action == 'R')
		$sql .= ", swiped = '0'";
	else
		$sql .= ", swiped = '1'";
	$sql .= " where id = '{$row['subnet_id']}'";
	if (!@pg_query($sql)) {
		trigger_error("error: failed to run query: $sql", E_USER_WARNING);
		return false;
	}
	return true;
}

//$subnet, $reg_action, $netname, $name, $addr1, $addr2, $city, $state, $country, $comments = '', $netname = '', $originas = null, $bcc = ''
function send_manual_swip($attribs)
{
	global $config;
	
	list($addr,$mask) = explode('/', $attribs['subnet']);
	if (!$addr || !$mask) return false;
	
	if ($attribs['template'] == 'netmod') {
		if ($attribs['reg_action'] != 'M' && $attribs['reg_action'] != 'R') {
			return false;
		}
	} else if ($attribs['template'] == 'reassign-simple') {
		if ($attribs['reg_action'] != 'M' && $attribs['reg_action'] != 'R' && $attribs['reg_action'] != 'N') {
			return false;
		}
	}
	
	if (!$attribs['originas']) $attribs['originas'] = $config['origin_as'];
	if (!$attribs['netname']) $attribs['netname'] = "NET-" . str_replace(array(".", "/"), "-", $attribs['subnet']);
	
	if ($attribs['template'] == 'netmod') {
		$subj = 'NETWORK MODIFICATION';
		$msg = <<<END_MSG
Template: ARIN-NET-MOD-5.0
**  As of March 2011
**  Detailed instructions are located below the template.

00. API Key: {$config['swip_apikey']}

01. Registration Action (M or R): {$attribs['reg_action']}
02. IP Address and Prefix or Range: {$attribs['subnet']}
03. Network Name: {$attribs['netname']}
04. Origin AS: {$attribs['originas']}
05. Tech POC Handle: {$attribs['tech_poc_handle']}
06. Abuse POC Handle: {$attribs['abuse_poc_handle']}
07. NOC POC Handle: {$attribs['noc_poc_handle']}
08. Public Comments: {$attribs['comments']}

END OF TEMPLATE

If you would like assistance completing this template, please do
not hesitate to contact ARIN's Registration Services Help Desk
at +1 (703) 227-0660. We'll be glad to help you!
END_MSG;
	} else if ($attribs['template'] == 'reassign-simple') {
		$subj = 'REASSIGN SIMPLE';
		$msg = <<<END_MSG
Template: ARIN-REASSIGN-SIMPLE-4.1
**  As of February 2007
**  Detailed instructions are located below the template.

01. Registration Action (N,M, or R): {$attribs['reg_action']}
02. Network Name: {$attribs['netname']}
03. IP Address and Prefix or Range: {$attribs['subnet']}
04. Origin AS: {$attribs['originas']}
05. Customer Name: {$attribs['name']}
06. Customer Address: {$attribs['addr1']}
06. Customer Address: {$attribs['addr2']}
07. Customer City: {$attribs['city']}
08. Customer State/Province: {$attribs['state']}
09. Customer Postal Code: {$attribs['zip']}
10. Customer Country Code: {$attribs['country']}
11. Public Comments: {$attribs['comments']}

END OF TEMPLATE

If you would like assistance completing this template, please do
not hesitate to contact ARIN's Registration Services Help Desk
at +1 (703) 227-0660. We'll be glad to help you!
END_MSG;
	}
	
	return @mail('hostmaster@arin.net', $subj, $msg, "From: {$config['swip_from']}" . ($attribs['bcc'] ? "\r\nBcc: {$attribs['bcc']}" : ""));
}

/* make_sql_like_clause: generate the WHERE statement for searching a db table. */
function make_sql_like_clause($q, $cols, $bool = 'AND')
{
	if (!is_array($cols))
		$cols = array($cols);
	
	$search_sql = '';
	if ((($bool = strtoupper(trim($bool))) == 'AND' || $bool == 'OR') && 
	    $q != '' && count($cols) > 0) {
		$search_sql .= "(";
		
		$q = preg_split('/\s+/', urldecode(trim($q)));
		$num_words = count($q);
		
		for ($i = 0; $i < $num_words; $i++) {
			$search_sql .= "(";
			
			$num_cols = count($cols);
			for ($j = 0; $j < $num_cols; $j++) {
				$search_sql .= $cols[$j] . " ilike '%" . $q[$i] . "%'";
				if ($j != $num_cols - 1)
					$search_sql .= ' OR ';
			}
			
			$search_sql .= ')';
			
			if ($i != $num_words - 1)
				$search_sql .= " $bool ";
		}
		
		$search_sql .= ')';
	}
	return $search_sql;
}

function wildcard_mask($mask)
{
	$wildcard_mask = array();
	foreach (explode('.', trim($mask)) as $octet) {
		$wildcard_mask[] = 255 - $octet;
	}
	return implode('.', $wildcard_mask);
}

function ip2dottedbin($ip, $prefix_len = null)
{
	$dottedbin = array();
	foreach (explode('.', $ip) as $octet) {
		$bin = str_pad(decbin($octet), 8, 0, STR_PAD_LEFT);
		$dottedbin[] = $bin;
	}
	$dottedbin = implode('.', $dottedbin);
	if ($prefix_len) {
		if ($prefix_len >= 8) $prefix_len++;
		if ($prefix_len >= 16) $prefix_len++;
		if ($prefix_len >= 24) $prefix_len++;
		$dottedbin = substr($dottedbin, 0, $prefix_len) . ' ' . substr($dottedbin, $prefix_len);
	}
	return $dottedbin;
}

function ago($tm,$rcs = 0)
{
        $cur_tm = time();
        $dif = $cur_tm - $tm;
	$pds = array('s','m','h','d','wk','mo','yr','dec');
        $lngh = array(1,60,3600,86400,604800,2630880,31570560,315705600);
        for ($v = sizeof($lngh)-1; ($v >= 0) && (($no = $dif/$lngh[$v])<=1); $v--);
        if ($v < 0) {
                $v = 0;
        }
        $_tm = $cur_tm - ($dif % $lngh[$v]);

        $no = floor($no);
	//if ($no != 1)
        //	$pds[$v] .='s';
        $x = sprintf("%d%s ", $no, $pds[$v]);
        if (($rcs == 1) && ($v >= 1) && (($cur_tm-$_tm) > 0))
                $x .= time_ago($_tm);
        return $x;
}

function fmt_scientific($val, $p = 10)
{
	if ($val > pow(2, 32)-1) {
		return sprintf("%.{$p}e", $val);
	}
	return $val;
}
