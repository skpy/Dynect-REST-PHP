<?php
class dynect
{

	private $api_url;
	private $token;
	private $credentials;
	public $result;

	/*
	 * instantiate a Dynect object
	 * @credentials array Dynect credentials
	 * @return object a Dynect object
	 */
	public function __construct( $credentials )
	{
		$this->api_url = 'https://api2.dynect.net/REST';
		$this->credentials = $credentials;
	}

	/*
	 * execute a call to the Dynect API
	 * @command string the API command to invoke
	 * @crud string HTTP verb to use (GET, PUT, POST, or DELETE)
	 * @args array associative array of data to send
	 * @return mixed the Dynect response
	 */
	private function execute( $command, $crud, $args = array() )
	{
		// empty result cache
		$this->result = '';
		$headers = array( 'Content-Type: application/json' );
		if ( ! empty( $this->token ) ) {
			$headers[] = 'Auth-Token: ' . $this->token;
		}
		$ch = curl_init();
		// return the transfer as a string of the return value 
		// instead of outputting it out directly. 
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		// Do not fail silently. We want a response regardless
		curl_setopt( $ch, CURLOPT_FAILONERROR, false );
		// disables response header and only returns the response body 
		curl_setopt( $ch, CURLOPT_HEADER, false );
		// Set the content type of the post body via HTTP headers
		curl_setopt( $ch, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $crud );
		// API endpoint to use
		curl_setopt( $ch, CURLOPT_URL, $this->api_url . "/$command/" );
		if ( ! empty( $args ) ) {
			curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $args ) );
		}
		$result = curl_exec( $ch );
		$this->result = $result;
		curl_close( $ch );
		return json_decode( $result );
	}

	/*
	 * parse a Dyn object into an associative array
	 * @data object an object of Dyn data
	 * @return array Associative array of object key/value pairs
	 */
	private function parse_dyn_object( $data )
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
	 * @return bool success or failure
	 */
	public function login()
	{
		$result = $this->execute( 'Session', 'POST', $this->credentials );
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

/***** ZONES *****/

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
	 * publish a zone
	 * @zone string name of the zone to publish
	 * @return bool success or failure
	 */
	public function zonePublish ( $zone )
	{
		$result = $this->execute( "Zone/$zone", 'PUT', array( 'publish' => 'TRUE' ) );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * freeze a zone, preventing changes
	 * @zone string Zone name
	 * @return bool success or failure
	 */
	public function zoneFreeze( $zone )
	{
		$result = $this->execute( "Zone/$zone", 'PUT', array( 'freeze' => 'TRUE' ) );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * thaw a zone, permitting changes
	 * @zone string Zone name
	 * @return bool success or failure
	 */
	public function zoneThaw( $zone )
	{
		$result = $this->execute( "Zone/$zone", 'PUT', array( 'thaw' => 'TRUE' ) );
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

/***** NODES *****/

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

/***** A RECORDS *****/
	/*
	 * create a new A record in a zone
	 * @zone string name of the zone to contain the record
	 * @fqdn string FQDN of the A record to create
	 * @ip string IP address of the A record to create
	 * @ttl int TTL value for the record
	 * @return bool success or failure 
	 */
	public function arecordAdd ( $zone, $fqdn, $ip, $ttl = 0 )
	{
		$record = array( 'rdata' => array( 'address' => $ip, ),
				 'ttl' => $ttl,
				);
		$result = $this->execute( "ARecord/$zone/$fqdn", 'POST', $record );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
         * change the A record in a zone
         * @zone string name of the zone to contain the record
         * @fqdn string FQDN of the A record to create
         * @ip string IP address of the A record to create
         * @ttl int TTL value for the record, 0 uses zone default
         * @return bool success or failure 
         */
        public function arecordUpdate ( $zone, $fqdn, $ip, $ttl = 0 )
        {
                $record = array( 'rdata' => array( 'address' => $ip, ),
                                 'ttl' => $ttl,
                                );
                $result = $this->execute( "ARecord/$zone/$fqdn", 'PUT', $record );
                if ( 'success' == $result->status )
                {
                        return TRUE;
                }
                return FALSE;
        }

        /*
	 * delete an A record
	 * @zone string name of the zone containing the A record
	 * @fqdn string FQDN of the A record to delete
	 * @id int Dynect ID of the A record to delete
	 * @return bool success or failure
	 */
	public function arecordDelete ( $zone, $fqdn, $id )
	{
		$result = $this->execute( "ARecord/$zone/$fqdn/$id", 'DELETE' );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * get a list of A record IDs for an FQDN
	 * @zone string name of the zone containing the A record
	 * @fqdn string FQDN fo the A record to query
	 * @return mixed array of Dynect IDs or boolean false
	 */
	public function arecordGetList( $zone, $fqdn ) 
	{
		$result = $this->execute( "ARecord/$zone/$fqdn", 'GET' );
		if ( 'success' == $result->status )
		{
			if ( empty( $result->data ) )
			{
				return FALSE;
			}
			$records = array();
			foreach ( $result->data as $data )
			{
				$records[] = str_replace( "/REST/ARecord/$zone/$fqdn/", '', $data );
			}
			return $records;
		}
		return FALSE;
	}

	/*
	 * get data about a specific A record
	 * @zone string name of the zone containing the A record
	 * @fqdn string FQDN of the A record to query
	 * @id int Dynect ID of the record
	 * @return mixed Associative array of record data, or boolean false
	 */
	public function arecordGet( $zone, $fqdn, $id = '' )
	{
		$result = $this->execute( "ARecord/$zone/$fqdn/$id", 'GET' );
		if ( 'success' == $result->status )
		{
			return $this->parse_dyn_object( $result->data );
		}
		return FALSE;
	}

/***** CNAMEs *****/

	/*
	 * create a new CNAME record
	 * @zone string the name of the zone to contain the CNAME
	 * @fqdn string the FQDN of the target of the CNAME record
	 * @cname string the FQDN of the CNAME to create
	 * @ttl int the TTL for the CNAME
	 * @return bool success or failure
	 */
	public function cnameAdd ( $zone, $fqdn, $cname, $ttl = 0 )
	{
		$record = array( 'rdata' => array( 'cname' => $cname ),
				'ttl' => $ttl,
				);
		$result = $this->execute( "CNAMERecord/$zone/$fqdn", 'POST', $record );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * delete a CNAME
	 * @zone string name of the zone containing the CNAME
	 * @fqdn string FQDN of the CNAME to delete
	 * @id int Dynect ID of the CNAME
	 * @return bool success or failure
	 */
	public function cnameDelete( $zone, $fqdn, $id )
	{
		$result = $this->execute( "CNAMERecord/$zone/$fqdn/$id", 'DELETE' );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * get a list of CNAME records
	 * @zone string the name of the zone to query
	 * @fqdn string FQDN of the CNAME
	 * @return mixed array of Dynect IDs or boolean false
	 */
	public function cnameGetList( $zone, $fqdn )
	{
		$result = $this->execute( "CNAMERecord/$zone/$fqdn", 'GET' );
		if ( 'success' == $result->status )
		{
			if ( empty( $result->data ) )
			{
				return FALSE;
			}
			$records = array();
			foreach ( $result->data as $data );
			{
				$records[] = str_replace( "/REST/CNAMERecord/$zone/$fqdn/", '', $data );
			}
			return $records;
		}
		return FALSE;
	}

	/*
	 * get data about a specific CNAME
	 * @zone string name of the zone containing the CNAME
	 * @fqdn string FQDN of the CNAME
	 * @id int Dynect ID of the CNAME
	 * @return mixed Associative array of Dynect data or boolean false
	 */
	public function cnameGet( $zone, $fqdn, $id )
	{
		$result = $this->execute( "CNAMERecord/$zone/$fqdn/$id", 'GET' );
		if ( 'success' == $result->status )
		{
			return $this->parse_dyn_object( $result->data );
		}
		return FALSE;
	}

/***** MX Records *****/
	/*
	 * add a new MX record
	 * @zone string the zone in which to add the MX
	 * @fqdn string the FQDN of the host for which the MX is added
	 * @exchange string the FQDN of the host handling mail
	 * @preference int the ranked preference for the exchange
	 * @ttl int optional TTL. Zone default will be used if not supplied
	 * @return bool success or failure
	 */
	public function mxAdd( $zone, $fqdn, $exchange, $preference, $ttl = 0 )
	{
		$result = $this->execute( "MXRecord/$zone/$fqdn", 'POST', array( 'exchange' => $exchange, 'preference' => $preference, 'ttl' => $ttl ) );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * delete an MX record
	 * @zone string the zone from which to delete the MX
	 * @fqdn string the FQDN of the host from which to delete the MX
	 * @id int the Dynect ID of the MX record to delete
	 * @return bool success or failure
	 */
	public function mxDelete( $zone, $fqdn, $id )
	{
		$result = $this->execute( "MXRecord/$zone/$fqdn/$id", 'DELETE' );
		if ( 'success' == $result->status )
		{
			return TRUE;
		}
		return FALSE;
	}

	/*
	 * get a list of MX records
	 * @zone string the name of the zone to query
	 * @fqdn string FQDN the FQDN of the host to query
	 * @return mixed an array of MX records, or boolean false
	 */
	public function mxGetList( $zone, $fqdn )
	{
		$result = $this->execute( "MXRecord/$zone/$fqdn", 'GET' );
		if ( 'success' == $result->status )
		{
			$exchanges = array();
			foreach( $result->data as $data )
			{
				$exchanges[] = str_replace( "/REST/MXRecord/$zone/$fqdn/", '', $data );
			}
			return $exchanges;
		}
		return FALSE;
	}

	/*
	 * get data about a specific MX record
	 * @zone string the name of the zone to query
	 * @fqdn string the FQDN of the host to query
	 * @id int the Dynect record ID to query
	 * @return mixed Associative array Dynect data, or boolean false
	 */
	public function mxGet( $zone, $fqdn, $id )
	{
		$result = $this->execute( "MXRecord/$zone/$fqdn/$id", 'GET' );
		if ( 'success' == $result->status )
		{
			return $this->parse_dyn_object( $result->data );
		}
		return FALSE;
	}

/***** HTTP Redirect *****/

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
			if ( empty ( $result->status ) ) {
				return FALSE;
			}
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
}
?>
