<?php
class dynect
{

	protected $token;
	public $response;
	protected $allowed_records = array('A', 'AAAA', 'CNAME', 'DNSKEY', 'DS', 'KEY', 'LOC', 'MX', 'NS', 'PTR', 'RP', 'SOA', 'SRV', 'TXT');

	/*
	 * execute a call to the Dynect API
	 * @command string the API command to invoke
	 * @method string HTTP method to use (GET, PUT, POST, or DELETE)
	 * @args array associative array of data to send
	 * @return mixed the Dynect response
	 */
	protected function execute( $command, $method = 'GET', $args = array() )
	{
		// Reset the response cache
		$this->response = null;

		$headers = array( 'Content-Type: application/json' );
		
		if ( ! empty( $this->token ) )
		{
			$headers[] = 'Auth-Token: ' . $this->token;
		}

		$ch = curl_init();

		// Return the transfer as a string of the return value instead of outputting it out directly
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		
		// Do not fail silently. We want a response regardless
		curl_setopt( $ch, CURLOPT_FAILONERROR, false );
		
		// Disables response header and only returns the response body
		curl_setopt( $ch, CURLOPT_HEADER, false );
		
		// Set the content type of the post body via HTTP headers
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );

		// Set the custom request method
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
		
		// Set the URL to send the request to the API
		curl_setopt( $ch, CURLOPT_URL, 'https://api2.dynect.net/REST/' . $command . '/' );
		
		if ( ! empty( $args ) )
		{
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $args ) );
		}

		$response = curl_exec( $ch );

		curl_close( $ch );
		
		$this->response = $response;

		return json_decode( $response );
	}

	/*
	 * parse a Dyn object into an associative array
	 * @data object an object of Dyn data
	 * @return array Associative array of object key/value pairs
	 */
	protected function parse_dyn_object( $data )
	{
		$arr = array();
		foreach ( $data as $key => $value )
		{
			if ( 'rdata' == $key )
			{
				continue;
			}
			$arr[$key] = $value;
		}
		if ( isset( $data->rdata ) )
		{
			foreach ( $data->rdata as $key => $value )
			{
				$arr[$key] = $value;
			}
		}
		return $arr;
	}

	/*
	 * log into the Dynect API and obtain an API token
	 * @credentials array Dynect credentials
	 * @return bool success or failure
	 */
	public function login( $customer_name, $user_name, $password )
	{
		$result = $this->execute( 'Session', 'POST', array('customer_name' => $customer_name, 'user_name' => $user_name, 'password' => $password) );
		if ( 'success' == $result->status )
		{
			$this->token = $result->data->token;
			return true;
		}
		return false;
	}

	/*
	 * logout, destroying a Dynect API token
	 * @return bool success or failure
	 */
	public function logout()
	{
		$result = $this->execute( 'Session', 'DELETE' );
		if ( 'success' == $result->status )
		{
			return true;
		}
		return false;
	}

	/*
	 * create a new Dynect zone
	 * @contact string email address for the contact of this zone
	 * @name string the name of the zone
	 * @ttl int the default TTL to set for this zone
	 * @return bool success or failure
	 */
	public function zoneCreate( $contact, $name, $ttl = 3600 )
	{
		if ( empty( $contact) || empty( $name ) ) {
			return false;
		}
		$result = $this->execute( "Zone/$name", 'POST', array( 'rname' => $contact, 'zone' => $name, 'ttl' => $ttl ) );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * delete a Dynect zone
	 * @zone string name of the zone to delete
	 * @return bool success or failure
	 */
	public function zoneDelete( $zone )
	{
		$result = $this->execute( "Zone/$zone", 'DELETE' );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * update a zone
	 * @zone string name of the zone to publish
	 * @status string status of the zone to update
	 * @return bool success or failure
	 */
	public function zoneUpdate ( $zone, $status )
	{
		if ( ! in_array( $status, array( 'publish', 'freeze', 'thaw' ) ) )
		{
			return FALSE;
		}
		$result = $this->execute( "Zone/$zone", 'PUT', array( $status => 'TRUE' ) );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * get a list of zones
	 * @return mixed Array of avialable zones or boolean false
	 */
	public function zoneGetList()
	{
		$result = $this->execute( "Zone", 'GET' );
		if ( 'success' == $result->status )
		{
			$domains = array();
			foreach ( $result->data as $value )
			{
				$domains[] = rtrim( str_replace( '/REST/Zone/', '', $value ), '/' );
			}
			return $domains;
		}
		return FALSE;
	}

	/*
	 * get details of a specific zone
	 * @zone string Zone name
	 * @return mixed Associative array of zone data or boolean false
	 */
	public function zoneGet( $zone )
	{
		$result = $this->execute( "Zone/$zone", 'GET' );
		if ( 'success' == $result->status )
		{
			return $this->parse_dyn_object( $result->data );
		}
		return FALSE;
	}

	/*
	 * delete a node, any records in it, and any nodes underneath it
	 * @zone string Zone containing the node
	 * @fqdn string FQDN of the node to delete
	 * @return bool success or failure
	 */
	public function nodeDelete( $zone, $fqdn )
	{
		$result = $this->execute( "Node/$zone/$fqdn", 'DELETE' );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}
 
	/*
	 * list all the nodes in a zone
	 * @zone string the zone to query
	 * @fqdn string a top-level node in the zone
	 * @return mixed Array of node data, or boolean false
	 */
	public function nodeList( $zone, $fqdn = '' )
	{
		$command = "NodeList/$zone";
		if ( ! empty( $fqdn ) )
		{
			$command .= "/$fqdn";
		}
		$result = $this->execute( $command, 'GET' );
		if ( 'success' == $result->status )
		{
			return $result->data;
		}
		return FALSE;
	}

	/*
	 * get a list of A record IDs for an FQDN
	 * @zone string name of the zone containing the A record
	 * @fqdn string FQDN fo the A record to query
	 * @return mixed array of Dynect IDs or boolean false
	 */
	public function allRecordsGetList( $zone, $fqdn ) 
	{
		$result = $this->execute( "AllRecord/$zone/$fqdn", 'GET' );
		if ( 'success' == $result->status )
		{
			if ( empty( $result->data ) )
			{
				return FALSE;
			}
			$records = array();
			foreach ( $result->data as $data )
			{
				$data = str_replace( "/REST/", '', $data );
				$record = $this->execute( $data, 'GET');
				if ( ! empty( $record->data ) )
				{
					$records[] = $record->data;
				}
			}
			return $records;
		}
		return FALSE;
	}

	/*
	 * get a list of record IDs for an FQDN
	 * @type string type of record to get list
	 * @zone string name of the zone containing the record
	 * @fqdn string FQDN fo the record to query
	 * @return mixed array of Dynect IDs or boolean false
	 */
	public function recordGetList( $type, $zone, $fqdn ) 
	{
		$result = $this->execute( "{$type}Record/$zone/$fqdn", 'GET' );
		if ( 'success' == $result->status )
		{
			if ( empty( $result->data ) )
			{
				return FALSE;
			}
			$records = array();
			foreach ( $result->data as $data )
			{
				$records[] = str_replace( "/REST/{$type}Record/$zone/$fqdn/", '', $data );
			}
			return $records;
		}
		return FALSE;
	}

	/*
	 * get data about a specific record
	 * @type string type of record to get
	 * @zone string name of the zone containing the record
	 * @fqdn string FQDN of the record to query
	 * @id int Dynect ID of the record
	 * @return mixed Associative array of record data, or boolean false
	 */
	public function recordGet( $type, $zone, $fqdn, $id = '' )
	{
		$result = $this->execute( "{$type}Record/$zone/$fqdn/$id", 'GET' );
		if ( 'success' == $result->status )
		{
			return $this->parse_dyn_object( $result->data );
		}
		return FALSE;
	}

	/*
	 * create a new record in a zone
	 * @type string type of record to create
	 * @zone string name of the zone to contain the record
	 * @fqdn string FQDN of the record to create
	 * @rdata string Rdata of the record to create
	 * @ttl int TTL value for the record
	 * @return bool success or failure 
	 */
	public function recordAdd ( $type, $zone, $fqdn, $rdata, $ttl = 0 )
	{
		if ( ! in_array( $type, $this->allowed_records ) )
		{
			return FALSE;
		}
		$record = array( 'rdata' => $rdata, 'ttl' => $ttl, );
		$result = $this->execute( "{$type}Record/$zone/$fqdn", 'POST', $record );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * change a record in a zone
	 * @type string type of record to update
	 * @zone string name of the zone to contain the record
	 * @fqdn string FQDN of the record to update
	 * @rdata string Rdata of the record to update
	 * @ttl int TTL value for the record
	 * @return bool success or failure 
	 */
	public function recordUpdate ( $type, $zone, $fqdn, $rdata, $ttl = 0 )
	{
		if ( ! in_array( $type, $this->allowed_records ) )
		{
			return FALSE;
		}
		$record = array( 'rdata' => $rdata, 'ttl' => $ttl, );
		$result = $this->execute( "{$type}Record/$zone/$fqdn", 'PUT', $record );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * delete a record in a zone
	 * @type string type of record to delete
	 * @zone string name of the zone to contain the record
	 * @fqdn string FQDN of the record to delete
	 * @id int Dynect ID of the record to delete
	 * @return bool success or failure 
	 */
	public function recordDelete ( $type, $zone, $fqdn, $id )
	{
		if ( ! in_array( $type, $this->allowed_records ) )
		{
			return FALSE;
		}
		$result = $this->execute( "{$type}Record/$zone/$fqdn/$id", 'DELETE' );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * create a new HTTP redirect
	 * @zone string name of the zone in which the redirect will be created
	 * @fqdn string FQDN to redirect
	 * @target string full URI (http://.../) of target to which request will be redirected
	 * @code int 301 (permanent) or 302 (temporary) response to send
	 * @uri bool whether to preserve the original URI
	 * @return bool success or failure
	 */
	public function redirectCreate( $zone, $fqdn, $target, $code = 302, $uri = true )
	{
		$keep = ( $uri ) ? 'Y' : 'N';
		$args = array( 'code' => $code, 'keep_uri' => $keep, 'url' => $target );
		$result = $this->execute( "HTTPRedirect/$zone/$fqdn", 'POST', $args );
                if ( 'success' == $result->status ) {
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * delete an HTTP redirect
	 * @zone string name of zone containing the redirect to delete
	 * @fqdn string FQDN of the redirect to delete
	 * @return bool success or failure
	 */
	public function redirectDelete( $zone, $fqdn )
	{
		$result = $this->execute( "HTTPRedirect/$zone/$fqdn", 'DELETE' );
		if ( 'success' == $result->status ) {
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * get a list of HTTP redirects
	 * @zone string name of zone to query
	 * @return mixed array of redirects or boolean false
	 */
	public function redirectGetList( $zone )
	{
		$result = $this->execute( "HTTPRedirect/$zone", 'GET' );
		if ( 'success' == $result->status ) {
			$redirects = array();
			foreach ( $result->data as $data ) {
				$redirects[] = rtrim( str_replace( "/REST/HTTPRedirect/$zone/", '', $data ), '/' );
			}
			return $redirects;
		}
		return FALSE;
	}

	/*
	 * get details of a specific HTTP redirect
	 * @zone string name of zone to query
	 * @fqdn string FQDN of the redirect to query
	 * @return mixed Object of Dynect data or boolean false
	 */
	public function redirectGet( $zone, $fqdn )
	{
		$result = $this->execute( "HTTPRedirect/$zone/$fqdn", 'GET' );
		if ( 'success' == $result->status ) {
			return $result->data;
		}
		return FALSE;
	}
	/*
	 * GET Job data
	 * @job string job ID
	 * @return mixed Object of Dynect data or boolean false
	 */
	public function jobGet( $job)
	{
		$result = $this->execute( "Job/$job/", 'GET' );
			return $result;
		if ( 'success' == $result->status ) {
			return $result;
		}
		return FALSE;
	}

	/*
	 * Bulk Upload a BIND file to create or update zone
	 * @zone string name of zone to upload
	 * @BINDfile string path to file containing BIND zone
	 * @return mixed Object of Dynect data or boolean false
	 */
	protected function ZoneFile( $zone, $BINDfile, $BulkMethod )
	{
		$args = array( 'file' => $BINDfile );
		$result = $this->execute( "ZoneFile/$zone/", $BulkMethod, $args );
		if ( 'success' == $result->status ) {
			return $result;
		}
		return FALSE;
	}
	/*
	 * Proxy for ZoneFile to create zone
	 * @zone string name of zone to upload
	 * @BINDfile string path to file containing BIND zone
	 * @return mixed Object of Dynect data or boolean false
	 */

	public function bulkCreate( $zone, $BINDfile )
	{
		$result = $this->ZoneFile( $zone, $BINDfile, 'POST' );
		return $result;
	}

	/*
	 * Proxy for ZoneFile to update zone
	 * @zone string name of zone to upload
	 * @BINDfile string path to file containing BIND zone
	 * @return mixed Object of Dynect data or boolean false
	 */
	public function bulkUpdate( $zone, $BINDfile )
	{
		$result = $this->ZoneFile( $zone, $BINDfile, 'PUT' );
		return $result;
	}

}
