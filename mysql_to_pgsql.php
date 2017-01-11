<?php
define('NO_CHECK_LOGIN', 1);
require('inc/global.php');

die("remove me");

$mysql_conf = array(
	'host' => 'localhost',
	'user' => 'xxx',
	'pass' => 'xxx',
	'db' => 'subnets'
);

$mdbh = mysql_connect($mysql_conf['host'], $mysql_conf['user'], $mysql_conf['pass']) or die("error: failed to connect to mysql");
mysql_select_db($mysql_conf['db']);

$sql = "select * from users order by id asc";
$result = @mysql_query($sql) or die("error: failed to run query: $sql\n");
while ($row = mysql_fetch_assoc($result))
{
	$row['lastlogin'] = (!$row['lastlogin'] || $row['lastlogin'] == '0000-00-00 00:00:00' ? "null" : "'$row[lastlogin]'");
	$sql = "insert into users values($row[id], '$row[username]', '$row[password]', '$row[name]', $row[lastlogin])";
	@pg_query($sql) or die("error: failed to run query: $sql\n");
}
$sql = "select setval(pg_get_serial_sequence('users', 'id'), (select max(id) from users)+1)";
$result = @pg_query($sql) or die("error: failed to run query: $sql\n");

$sql = "select * from customers order by id asc";
$result = @mysql_query($sql) or die("error: failed to run query: $sql\n");
while ($row = mysql_fetch_assoc($result))
{
	$sql = "insert into customers values($row[id], '$row[name]', '$row[addr1]', '$row[addr2]', '$row[city]', '$row[state]', '$row[zip]','$row[country]')";
	@pg_query($sql) or die("error: failed to run query: $sql\n");
}
$sql = "select setval(pg_get_serial_sequence('customers', 'id'), (select max(id) from customers)+1)";
$result = @pg_query($sql) or die("error: failed to run query: $sql\n");

$sql = "select * from base_subnets order by id asc";
$result = @mysql_query($sql) or die("error: failed to run query: $sql\n");
while ($row = mysql_fetch_assoc($result))
{
	$subnet = inet_ntoa($row['addr']) . '/' . inet_ntocidr($row['mask']);
	$sql = "insert into base_subnets values($row[id], '$subnet', '$row[description]', '$row[reserved]')";
	@pg_query($sql) or die("error: failed to run query: $sql\n");
}
$sql = "select setval(pg_get_serial_sequence('base_subnets', 'id'), (select max(id) from base_subnets)+1)";
$result = @pg_query($sql) or die("error: failed to run query: $sql\n");

$sql = "select * from subnets order by id asc";
$result = @mysql_query($sql) or die("error: failed to run query: $sql\n");
while ($row = mysql_fetch_assoc($result))
{
	if (!$row['customer_id'])
		$row['customer_id'] = 0;
	if (!$row['last_updated_user_id'])
		$row['last_updated_user_id'] = 0;
	if (!$row['swiped'])
		$row['swiped'] = 0;
	if (!$row['lastswiped'] || $row['lastswiped'] == '0000-00-00 00:00:00')
		$row['lastswiped'] = 'null';
	else
		$row['lastswiped'] = "'{$row['lastswiped']}'";
	if (!$row['last_updated'] || $row['last_updated'] == '0000-00-00 00:00:00')
		$row['last_updated'] = 'null';
	else
		$row['last_updated'] = "'{$row['last_updated']}'";
	$subnet = inet_ntoa($row['addr']) . '/' . inet_ntocidr($row['mask']);
	$sql = "insert into subnets values($row[id], $row[base_id], '$row[customer_id]', '$subnet', '$row[free]', '$row[swiped]', $row[lastswiped], '" . addslashes($row['description']) . "', '" . addslashes($row['notes']) . "', '$row[last_updated_user_id]', $row[last_updated])";
	if (!@pg_query($sql))
		echo "error: failed to run query: $sql\n";
}
$sql = "select setval(pg_get_serial_sequence('subnets', 'id'), (select max(id) from subnets)+1)";
$result = @pg_query($sql) or die("error: failed to run query: $sql\n");

$sql = "select * from hosts order by id asc";
$result = @mysql_query($sql) or die("error: failed to run query: $sql\n");
while ($row = mysql_fetch_assoc($result))
{
	if (!$row['last_updated'] || $row['last_updated'] == '0000-00-00 00:00:00')
		$row['last_updated'] = 'null';
	else
		$row['last_updated'] = "'{$row['last_updated']}'";

	if (!$row['last_updated_user_id'])
		$row['last_updated_user_id'] = 0;
	
	$addr = inet_ntoa($row['addr']);
	$sql = "insert into hosts values($row[id], $row[subnet_id], '$addr', '$row[free]', '$row[gateway]', '" . addslashes($row['description']) . "', '" . addslashes($row['notes']) . "', '$row[last_updated_user_id]', $row[last_updated])";
	if (!@pg_query($sql))
		echo "error: failed to run query: $sql\n";
}
$sql = "select setval(pg_get_serial_sequence('hosts', 'id'), (select max(id) from hosts)+1)";
$result = @pg_query($sql) or die("error: failed to run query: $sql\n");

$sql = "select * from groups order by id asc";
$result = @mysql_query($sql) or die("error: failed to run query: $sql\n");
while ($row = mysql_fetch_assoc($result))
{
	$sql = "insert into groups values($row[id], '$row[name]')";
	@pg_query($sql) or die("error: failed to run query: $sql\n");
}
$sql = "select setval(pg_get_serial_sequence('groups', 'id'), (select max(id) from groups)+1)";
$result = @pg_query($sql) or die("error: failed to run query: $sql\n");

$sql = "select * from group_subnets";
$result = @mysql_query($sql) or die("error: failed to run query: $sql\n");
while ($row = mysql_fetch_assoc($result))
{
	$sql = "insert into group_subnets values($row[group_id], $row[subnet_id])";
	@pg_query($sql) or die("error: failed to run query: $sql\n");
}

echo "Finished.\n";
