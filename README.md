# Dynect-REST-PHP

A simple PHP library for using the [Dynect REST API](http://dyn.com/developer).

## About
This is a simple single-file class for accessing the Dynect REST API.

Methods are generally named after the corresponding API endpoint and HTTP verb associated with the desired action:

* PUT /REST/Zone/       == zoneCreate()
* DELETE /REST/Zone/    == zoneDelete()

* PUT /REST/ARecord/    == arecordCreate()
* DELETE /REST/ARecord/ == arecordDelete()

Some endpoints accept variable inputs, in which case discrete methods have been provided to reduce ambiguity. For example, the REST/ARecord/ GET endpoint accepts an optional record ID. Without it, the API returns a list of A records assigned to the specified FQDN. With it, the API returns the specific data of the singularly identified A record. Within Dynect-PHP, this functionality has been made into two separate methods: arecordGetList() and arecordGet().

Most methods return either the data requested from the API endpoint, or boolean false on failure of any sort.

The raw response from the Dynect API is available as a public variable `$response`.

## Usage
```php
include_once( 'dynect.php' );
$customer_name = 'somecustomer';
$user_name = 'someuser';
$password = 'somepass';

$dyn = new Dynect();
if ( $dyn->login( $customer_name, $user_name, $password ) ) {
	// get a list of zones:
	$zones = $dyn->zoneGetlist();
	// show the raw API response for the previous action:
	print_r( $dyn->result );
	
	// create a new zone
	if ( $dyn->zoneCreate( 'hostmaster@example.com', 'example.com', 3600 ) ) {
		echo 'Zone example.com successfully created.';
	}
	// Publish and commit changes
        $published = $dyn->zoneUpdate($domain, 'publish');

	// Get contents of BIND file
        $BIND = file_get_contents('/files/example.com.zone.txt');  

	// Bulk update from BIND file
	$resultBulk = $dyn->bulkUpdate( 'example.com' , $BIND );

	// Track job 
	$job_id = $resultBulk->job_id ; 
	$Jobresult = $dyn->jobGet( $job_id );
	print_r( $Jobresult );
	
	// Publish and commit changes
        $published = $dyn->zoneUpdate($domain, 'publish');

	
	$dyn->logout();
}
```

## License
Copyright 2011 Scott Merrill

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

	http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.
