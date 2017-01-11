<?php
$config = array(
	/* relative website path (no trailing slash) (e.g. for mydomain.com/subnetsmngr this would be /subnetsmngr) */
	'web_path' => '/subnetsmngr',
	
	/*
	 * postgresql configuration
	 */
	'psql_host' => 'localhost',
	'psql_user' => 'subnets',
	'psql_pass' => 'xxxl',
	'psql_db' => 'subnets',
	
	/* your Origin AS used when SWIPing to ARIN */
	'origin_as' => '12345',
	/* address ARIN expects your SWIPs to come from */
	'swip_from' => 'Foo <foo@bar.com>',
	'swip_apikey' => 'API-XXX',
	
	/* commands to ping and traceroute. %d in ping_cmd is the ping count, %s is the host in both commands */
	'ping_cmd' => '/bin/ping -c %d %s',
	'ping6_cmd' => '/bin/ping6 -c %d %s',
	'traceroute_cmd' => '/bin/traceroute %s',
	'traceroute6_cmd' => '/bin/traceroute6 %s',
	
	/* do reverse DNS for hosts? */
	'dnslookups' => false,
	
	/* do reverse DNS lookup for hosts in private subnets? */
	'dnslookup_for_private' => false,
	
	/* PowerDNS integration.  MySQL backend support only
	'pdns_ns1' => 'ns1.host.com',
	'pdns_hostmaster' => 'root.host.com',
	'pdns_mysql_host' => 'ns1.host.com',
	'pdns_mysql_user' => 'pdns',
	'pdns_mysql_pass' => 'xxxx',
	'pdns_mysql_db' => 'pdns',
	*/
	
	/*
	 * Route Check configuration
	 * this is currently only tested using a Juniper MX router
	 * as the netconf source device
	 */
	'route_check' => [
		'enabled' => false,
		[
			'instance_id' => 1,
			'host' => 'x.x.x.x', // netconf router
			'user' => 'netconf',
			'pass' => 'xxx'
		]
	]
);
