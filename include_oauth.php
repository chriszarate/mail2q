<?php

/*
	OAuth functions
	Mail2q.com
	Chris Zarate
	http://chris.zarate.org/
*/


##	Configuration

	require_once ( 'config.php' );


##	Load user data

	$users_data = ( is_file ( $users_db ) ) ? unserialize ( file_get_contents ( $users_db ) ) : Array ();



##	Functions

function OAuthApiCall ( $api, $http_method, $api_method, $rest_params, $auth_token_secret )

	{

	/*
		Make a call to an OAuth API using the supplied method 
		and parameters.
	*/

	#	Global values:
		global $api_debug_mode;
		global $debug_datastore;


	#	Additional REST parameters:
		$rest_params['oauth_consumer_key'] = $api['api_key'];
		$rest_params['oauth_nonce'] = substr ( str_shuffle ( MD5 ( microtime() ) ), 0, 16 );
		$rest_params['oauth_signature_method'] = 'HMAC-SHA1';
		$rest_params['oauth_timestamp'] = time();
		$rest_params['oauth_version'] = '1.0';
#		$rest_params['v'] = '1.0';


	#	Build API request:

		ksort($rest_params);
		$rest_params = OAuthBuildQuery($rest_params);

		$api_base_url = $api['base_uri'] . $api_method;
		$api_request  = $rest_params . '&oauth_signature=' . OAuthPercentEncode ( base64_encode ( hash_hmac ( 'sha1', $http_method . '&' . OAuthPercentEncode($api_base_url) . '&' . OAuthPercentEncode($rest_params), $api['shared_secret'] . '&' . $auth_token_secret, true ) ) );


	#	Submit API request:

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		switch ( $http_method ):

			case 'GET':

				curl_setopt($ch, CURLOPT_URL, $api_base_url . '?' . $api_request);
				curl_setopt($ch, CURLOPT_HTTPGET, true);
				break;

			case 'POST':

				curl_setopt($ch, CURLOPT_URL, $api_base_url);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $api_request);
				break;

		endswitch;

		$api_response = curl_exec($ch);
		$http_status  = curl_getinfo($ch, CURLINFO_HTTP_CODE);

		curl_close($ch);


	#	If requested by debug variable, write output:

		if ( $api_debug_mode ):
			file_put_contents ( $debug_datastore . 'debug' . str_replace('/', '_', $api_method) . '_' . time(), $api_request . "\n\n" . $api_response );
		endif;


	#	Respond based on HTTP status code:

		switch ( $http_status ):

			case '200':
			case '201':
			case '304':
				return ( substr($api_method, 0, 6) == '/oauth' ) ? $api_response : simplexml_load_string ( $api_response );
				break;

			case '400':
			case '404':
				return 'invalid';
				break;

			case '401':
			case '403':
				return 'unauthorized';
				break;

			case '412':
				return 'duplicate';
				break;

			case '500':
			default:
				return 'error';
				break;

		endswitch;

	}



function OAuthRequestToken ( $api, $oauth_hash, $udata_hash, $callback_url )

	{

	/*
		Request an API authorization token and send the user to 
		the service to authenticate. Save response for later use.
	*/


	#	Global values:
		global $oauth_datastore;
		global $twitter_id;

	#	Request authorization token:
		parse_str ( OAuthApiCall ( $api, 'GET', '/oauth/request_token', Array(), '' ), $oauth_data );

	#	Add secure "user data" hash:
		$oauth_data['udata_hash'] = $udata_hash;

	#	Add Twitter ID, if available:
		$oauth_data['twitter_id'] = $twitter_id;

	#	Save response:
		file_put_contents ( $oauth_datastore . $oauth_hash, serialize($oauth_data) );

	#	Redirect user to login:
		header ( 'Location: ' . $oauth_data['login_url'] . '&oauth_consumer_key=' . OAuthPercentEncode ( $api['api_key'] ) . '&oauth_callback=' . OAuthPercentEncode ( $callback_url ) );

	#	Stop execution:
		exit;

	}



function OAuthRegisterUser ( $api, $oauth_hash, $callback_url )

	{

	/*
		Verify that a user has successfully authenticated  
		and store their OAuth tokens.
	*/


	#	Global values:
		global $users_db;
		global $users_datastore;
		global $oauth_datastore;
		global $users_data;

	#	Load saved OAuth data:
		$oauth_data = unserialize ( file_get_contents ( $oauth_datastore . $oauth_hash ) );

	#	Get secure "user data" hash:
		$udata_hash = $oauth_data['udata_hash'];

	#	Get twitter ID, if present:
		$twitter_id = isset ( $oauth_data['twitter_id'] ) ? $oauth_data['twitter_id'] : false;

	#	Request new authorization token and token secret:
		parse_str ( OAuthApiCall ( $api, 'GET', '/oauth/access_token', Array ( 'oauth_token' => $oauth_data['oauth_token'] ), $oauth_data['oauth_token_secret'] ), $oauth_data );

	#	Save response:
		file_put_contents ( $users_datastore . $oauth_data['user_id'], serialize($oauth_data) );

	#	Add user to user array:
		$users_data [ $udata_hash ] = $oauth_data['user_id'];
		file_put_contents ( $users_db, serialize($users_data) );

	#	Follow Twitter user, if applicable:
		if ( $twitter_id ):
			TwitterFollowUser ( $twitter_id );
		endif;

	#	Delete saved OAuth data:
		unlink ( $oauth_datastore . $oauth_hash );

	#	Redirect to "success" page:
		header ( 'Location: ' . $callback_url );

	#	Stop execution:
		exit;

	}



function NetflixIsAlreadyRegistered ( $udata_hash, $netflix_user_id )

	{

	/*  Determine whether a Netflix user has already registered.  */

	#	Global values:
		global $netflix_api;
		global $users_datastore;


	#	Check for existing user data record:

		if ( file_exists ( $users_datastore . $netflix_user_id ) ):

			$user_data = unserialize ( file_get_contents ( $users_datastore . $netflix_user_id ) );

			if ( isset ( $user_data['user_id'], $user_data['oauth_token'], $user_data['oauth_token_secret'] ) ):

				if ( is_object ( OAuthApiCall ( $netflix_api, 'GET', '/users/' . $user_data['user_id'], Array('oauth_token' => $user_data['oauth_token']), $user_data['oauth_token_secret'] ) ) ):
					return true;
				endif;

			endif;

		else:

		#	Delete user data and allow user to re-register:
			RemoveUserID ( $netflix_user_id );

		endif;

		return false;

	}



function NetflixGetTitle ( $user_data, $string )

	{

	/*
		Search Netflix's catalog for a title and return 
		results.
	*/

	#	Global values:
		global $netflix_api;


	#	REST parameters:

		$rest_params = Array (
			'oauth_token' => $user_data['oauth_token'],
			'max_results' => '1', 
			'term'        => $string
		);


	#	Search for title:
		$title_response = OAuthApiCall ( $netflix_api, 'GET', '/catalog/titles', $rest_params, $user_data['oauth_token_secret'] );


	#	If title is found, get additional details and compile response:

		if ( is_object($title_response) ):

		#	Look for "instant" availability:

			$title_url = $title_response->xpath("catalog_title/link[@title = 'web page']/@href");
			$title_url = $title_url[0]->href . '';

			$title_id = preg_match ('/[0-9]{4,}$/', $title_url, $title_id_candidates);
			$title_id = ( $title_id ) ? $title_id_candidates[0] : false;

			$format_api_url = $title_response->xpath("catalog_title/link[@title = 'formats']/@href");
			$format_api_url = parse_url ( $format_api_url[0]->href . '' );

			$instant_availability = ( $format_api_url['path'] )                  ? OAuthApiCall ( $netflix_api, 'GET', $format_api_url['path'], Array ( 'oauth_token' => $user_data['oauth_token'] ), $user_data['oauth_token_secret'] ) : false;
			$instant_availability = ( is_object ( $instant_availability ) )      ? $instant_availability->xpath("availability[category/@label = 'instant']") : Array ( false );
			$instant_availability = ( is_object ( $instant_availability[0] ) )   ? $instant_availability[0]->attributes()->available_until . '' : false;

		#	Return title information:

			return Array (
				'ref'     => $title_response->catalog_title->id . '',
				'id'      => $title_id,
				'name'    => $title_response->catalog_title->title->attributes()->regular . '',
				'year'    => $title_response->catalog_title->release_year . '',
				'url'     => $title_url,
				'instant' => $instant_availability
			);

		else:
			return ( $title_response == 'error' ) ? false : true;
		endif;

	}



function NetflixAddToQueue ( $user_data, $title_ref, $queue, $position )

	{

	/*  Add an item to a user's queue.  */

	#	Global values:
		global $netflix_api;


	#	Set API method:
		$api_method = '/users/' . $user_data['user_id'] . '/queues/' . $queue;


	#	REST parameters:

		$rest_params = Array (
			'oauth_token' => $user_data['oauth_token'],
			'position'    => $position,
			'title_ref'   => $title_ref
		);


	#	Add title to queue and get status:
		$queue_response = OAuthApiCall ( $netflix_api, 'POST', $api_method, $rest_params, $user_data['oauth_token_secret'] );


	#	If successful, compile response:

		if ( is_object($queue_response) ):

			return Array (
				'position' => $queue_response->resources_created->queue_item->position . '',
				'boxart'   => $queue_response->resources_created->queue_item->box_art->attributes()->medium . ''
			);

		else:
			return $queue_response;
		endif;

	}



function TwitterFollowUser ( $user_id )

	{

	/*  Add an item to a user's queue.  */

	#	Global values:
		global $twitter_api;
		global $twitter_auth_token;
		global $twitter_auth_token_secret;


	#	Set API method:
		$api_method = '/friendships/create.xml';


	#	REST parameters:

		$rest_params = Array (
			'oauth_token' => $twitter_auth_token,
			'user_id' => $user_id
		);


	#	Follow user:
		$api_response = OAuthApiCall ( $twitter_api, 'POST', $api_method, $rest_params, $twitter_auth_token_secret );

		return ( is_object($api_response) ) ? true : false;

	}



function OAuthPercentEncode ( $string )

	{

	/*  Escape string per OAuth API requirements.  */


	#	Additional escaping:

		$escape_chars = 
			Array(
				'+'  => '%20',
				'!'  => '%21',
				'*'  => '%2A',
				'\'' => '%27',
				'('  => '%28',
				')'  => '%29'
			);

		return strtr ( urlencode($string), $escape_chars );

	}



function OAuthBuildQuery ( $vars )

	{

	/*  Build URL query per OAuth API requirements.  */


		$url_query = '';

		foreach ( $vars as $key => $value ):
			$url_query .= $key . '=' . OAuthPercentEncode($value) . '&';
		endforeach;

		return rtrim ( $url_query, '&' );

	}



function RemoveUserID ( $netflix_user_id )

	{

	/*  Remove non-existent user from user array.  */

	#	Global values:
		global $users_data;


		foreach ( $users_data as $key => $value ):
			if ( $value == $netflix_user_id ):
				unset ( $users_data [ $key ] );
			endif;
		endforeach;

		return true;

	}



function ValidateEmailAddress ( $string )

	{

	/*  Validate e-mail address.  */

		return ( filter_var ( CustomTrim ( $string ), FILTER_VALIDATE_EMAIL ) && preg_match('/@.+\./', CustomTrim ( $string ) ) ) ? true : false;

	}



function ValidateTwitterHandle ( $string )

	{

	/*  Validate Twitter handle.  */

		global $twitter_api;

		$api_response = OAuthApiCall ( $twitter_api, 'GET', '/users/lookup.xml', Array ( 'screen_name' => CustomTrim ( $string ) ), '' );

		return ( is_object ( $api_response ) ) ? $api_response->user->id . '' : false;

	}



function AreTwitterFriends ( $twitter_handle )

	{

	/*  Determine if user A is following user B.  */

		global $twitter_api;
		global $twitter_auth_token;
		global $twitter_auth_token_secret;
		global $twitter_screen_name;

		$rest_params = Array (
			'oauth_token'   => $twitter_auth_token,
			'screen_name_a' => $twitter_handle,
			'screen_name_b' => $twitter_screen_name
		);

		$api_response = OAuthApiCall ( $twitter_api, 'GET', '/friendships/exists.xml', $rest_params, $twitter_auth_token_secret );

		return ( is_object ( $api_response ) && $api_response . '' == 'true' ) ? true : false;

	}



function SendTweet ( $message, $status_id )

	{

	/*  Send a tweet.  */

		global $twitter_api;
		global $twitter_auth_token;
		global $twitter_auth_token_secret;

		$rest_params = Array (
			'oauth_token' => $twitter_auth_token,
			'status'      => $message
		);

		if ( $status_id ):
			$rest_params['in_reply_to_status_id'] = $status_id;
		endif;

		$api_response = OAuthApiCall ( $twitter_api, 'POST', '/statuses/update.xml', $rest_params, $twitter_auth_token_secret );

		return ( is_object ( $api_response ) ) ? true : false;

	}



function SendDM ( $message, $screen_name )

	{

	/*  Send a tweet.  */

		global $twitter_api;
		global $twitter_auth_token;
		global $twitter_auth_token_secret;

		$rest_params = Array (
			'oauth_token' => $twitter_auth_token,
			'screen_name' => $screen_name,
			'text'        => $message
		);

		$api_response = OAuthApiCall ( $twitter_api, 'POST', '/direct_messages/new.xml', $rest_params, $twitter_auth_token_secret );

		return ( is_object ( $api_response ) ) ? true : false;

	}



function UserHash ( $string, $salt )

	{

	/*  Generate salted hash of user handle.  */

		return trim ( strtr ( base64_encode ( sha1 ( CustomTrim ( $string ) . $salt, true ) ), '+/=', '-_ ' ) );

	}



function ExtractEmailAddress ( $string )

	{

	/*  Extract e-mail address from mail header.  */

		return ( preg_match ( '/^\s*(([^\<]*?) <)?<?(.+?)>?\s*$/i', $string, $matches ) ) ? $matches[3] : false;

	}



function ExtractTwitterHandle ( $string )

	{

	/*  Extract twitter handle from mail header.  */

		return ( preg_match ( '/\(@([^\(]+)\)/', $string, $matches ) ) ? $matches[1] : false;

	}



function TitleCandidateFilter ( $title_candidate )

	{

	/*  Filter array to remove invalid title candidates.  */

		return ( preg_match ( '/[A-Za-z0-9]/', $title_candidate ) ) ? true : false;

	}



function PopulateMessage ( $string, $title_response )

	{

	/*  Replace template variables with values.  */

		foreach ( array_keys ( $title_response ) as $key ):
			$string = str_replace ( '%' . strtoupper($key) . '%', $title_response[$key], $string );
		endforeach;

		return $string;

	}



function CustomTrim ( $string )

	{

	/*  Custom trim for user handles.  */

		return trim ( $string, " @\t\n\r\0\x0B" );

	}



function StandardizeQuotes ( $string )

	{

	/*  Custom trim for user handles.  */

		$quotes = Array ( '“', '”', '“', '”' );

		foreach ( $quotes as $quote ):
			$string = str_replace ( $quote, '"', $string );
		endforeach;

		return $string;

	}



function HandleError ( $error_level, $error_message, $error_file, $error_line, $error_context )

	{

	/*  Provide safe, consistent error output.  */


	#	Prevent caching and conditional GET:
		header( 'Cache-Control: no-store, no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );


	#	Unset sensitive variables:

		$secrets = Array ( 'netflix_api', 'twitter_api', 'twitter_auth_token', 'twitter_auth_token_secret', 'aws_access_key', 'aws_secret_key', 'aws_request_id', 'users_data', 'user_data', 'hash_salt_oauth', 'hash_salt_udata');

		foreach ( $secrets as $secret ):
			unset ( $GLOBALS [ $secret ] );
		endforeach;

		unset ( $secrets );


	#	Call the error page:
		require ( 'error.php' );


	#	Stop execution:
		exit;


	}



?>