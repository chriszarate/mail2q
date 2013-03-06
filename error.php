<?php

/*
	Netflix Mail2Q
	Chris Zarate
	http://chris.zarate.org/
*/


#	HTTP version:
	$http_version = 'HTTP/1.1';


#	Home page link:
	$home_page_link = '/';


#	Current request URI:
	$current_request = $_SERVER['REQUEST_URI'];


#	Status codes and their respective HTTP headers:

	$http_headers = Array (

		'200' => 'OK', 
		'201' => 'Created', 
		'202' => 'Accepted', 
		'203' => 'Non-Authoritative Information', 
		'204' => 'No Content', 
		'205' => 'Reset Content', 
		'206' => 'Partial Content', 

		'300' => 'Multiple Choices', 
		'301' => 'Moved Permanently', 
		'302' => 'Moved Temporarily', 
		'303' => 'See Other Location', 
		'304' => 'Not Modified', 
		'305' => 'Use Proxy', 
		'306' => 'Temporary Redirect', 

		'400' => 'Bad Request', 
		'401' => 'Unauthorized', 
		'402' => 'Payment Required', 
		'403' => 'Forbidden', 
		'404' => 'Not Found', 
		'405' => 'Method Not Allowed', 
		'406' => 'Not Acceptable', 
		'407' => 'Proxy Authentication Required', 
		'408' => 'Request Timeout', 
		'409' => 'Conflict', 
		'410' => 'Gone', 
		'411' => 'Length Required', 
		'412' => 'Precondition Failed', 
		'413' => 'Request Entity Too Large', 
		'414' => 'Request-URI Too Long', 
		'415' => 'Unsupported Media Type', 
		'416' => 'Requested Range Not Satisfiable', 
		'417' => 'Expectation Failed', 
		'425' => 'Unordered Collection', 
		'426' => 'Upgrade Required', 

		'500' => 'Internal Server Error', 
		'501' => 'Not Implemented', 
		'502' => 'Bad Gateway', 
		'503' => 'Service Unavailable', 
		'504' => 'Gateway Timeout', 
		'505' => 'HTTP Version Not Supported', 
		'506' => 'Variant Also Negotiates', 
		'509' => 'Bandwidth Limit Exceeded', 
		'510' => 'Not Extended'

	);


#	Status codes and their respective user messages:

	$user_messages = Array (

		'001' => 'You are already registered. If you would like to unregister, simply <a href="http://www.netflix.com/ThirdPartyAccess">remove Mail2Q</a> from your Netflix account. If you have questions, head back to the <a href="' . $home_page_link . '">home page</a>.',
		'002' => 'The e-mail address or Twitter handle you supplied is invalid. Please <a href="' . $home_page_link . '">try again</a>.', 

		'200' => 'High-fives all around! Wait, what did we do? Whatever, head for the <a href="' . $home_page_link . '">home page</a> and we’ll celebrate.', 
		'201' => 'We’re ready to add movies and TV shows to your queue—just e-mail them to <a href="mailto:netflix@mail2q.com"><strong>netflix@mail2q.com</strong></a>. If you have questions, head back to the <a href="' . $home_page_link . '">home page</a>.', 
		'202' => 'We’re ready to add movies and TV shows to your queue—just tweet them <a href="http://twitter.com/netflixq"><strong>@netflixq</strong></a>. If you have questions, head back to the <a href="' . $home_page_link . '">home page</a>.', 

		'400' => 'The server did not understand your request. At all. Maybe you did something weird or maybe we broke something. Either way: <a href="' . $home_page_link . '">home page</a>.', 
		'401' => 'You are not authorized to view this page. You should probably get moving towards the <a href="' . $home_page_link . '">home page</a>.', 
		'403' => 'You are not authorized to view this page. You should probably get moving towards the <a href="' . $home_page_link . '">home page</a>.', 
		'404' => 'The page you requested cannot be found. Try the <a href="' . $home_page_link . '">home page</a>, you’ll probably find what you need there.', 

		'500' => 'An unexpected error occurred. We’re sorry, but all we can offer you at this point is a link to the <a href="' . $home_page_link . '">home page</a>.'

	);


#	Status codes and their respective page titles:

	$page_titles = Array (

		'001' => 'Already registered', 
		'002' => 'Invalid e-mail address or Twitter handle', 

		'200' => 'We did it!', 
		'201' => 'Success!', 
		'202' => 'Success!', 

		'400' => 'Huh?', 
		'401' => 'Unauthorized!', 
		'403' => 'Unauthorized!', 
		'404' => 'Not found', 

		'500' => 'Error'

	);


#	Get status code:

	if ( isset($error_message) ):
		$status_code = $error_message . '';
	elseif ( isset($_GET['error_code']) ):
		$status_code = trim($_GET['error_code']);
	else:
		$status_code = '404';
	endif;


#	Send HTTP header:

	$http_header = ( isset($http_headers[$status_code]) ) ? $http_version . ' ' . $status_code . ' ' . $http_headers[$status_code] : $http_version . ' 500 ' . $http_headers['500'];
	header ( $http_header );


#	Get page title, user type, user message, and error visibility:

	$page_title   = ( isset($page_titles[$status_code]) )   ? $page_titles[$status_code]   : $page_titles['500'];
	$user_type    = ( isset($_GET['type']) )                ? $_GET['type']                : false;
	$user_message = ( isset($user_messages[$status_code]) ) ? $user_messages[$status_code] : $user_messages['500'];
	$debug_mode   = ( isset($GLOBALS['error_debug_mode']) ) ? $GLOBALS['error_debug_mode'] : false;


#	Switch out message for Twitter users:

	if ( $user_type == 'twitter' && $status_code == '201' ):
		$user_message = $user_messages['202'];
	endif;

?>

<!DOCTYPE html>
<html>

	<head>

		<meta charset="utf-8">

		<!--[if lt IE 9]>
			<script src="http://html5shiv.googlecode.com/svn/trunk/html5.js"></script>
		<![endif]-->

		<link href="http://fonts.googleapis.com/css?family=Arvo:regular,italic,bold" rel="stylesheet" type="text/css">
		<link href="/style.css" rel="stylesheet" type="text/css">

		<title>Mail2Q.com - <?= $page_title ?></title>

	</head>

	<body>

		<header>

			<div>

				<h1 class="status"><?= $page_title ?></h1>
				<p class="status"><?= $user_message ?></p>

			</div>

		</header>

	<?  if ( $debug_mode ):  ?>

		<section>

			<div class="note">
				<strong><?= $error_message ?></strong> <?= $error_file ?> on line <?= $error_line ?>
			</div>

			<div class="note">
				<?= var_dump($error_context); ?>
			</div>

		</section>

	<?  endif;  ?>

	</body>

</html>