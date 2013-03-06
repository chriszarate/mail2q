<?php

/*
	Mail handler
	Mail2q.com
	Chris Zarate
	http://chris.zarate.org/
*/


##	Include

	require_once ('include_oauth.php');
	require_once ('include_ses.php');


##	Configuration

	$mail_agent_code = '...';


##	Messages

	$snippets = Array (

		'subject_success' => 'Your queue has been updated.',
		'subject_failure' => 'Your queue was not updated.',

		'header_text'     => '',
		'header_html'     => '',

		'success_text'    => 'We added %NAME% to your %QUEUE% queue at position #%POSITION%.' . "\n" . '%URL%',
		'success_html'    => '<p><img src="%BOXART%" align="left" style="margin-right: 1em;">We added <a href="%URL%"><strong>%NAME%</strong></a> to your %QUEUE% queue at position #%POSITION%.%WATCH%</p><br clear="left">',

		'saved_text'      => 'We added %NAME% to the “Saved” section of your %QUEUE% queue.' . "\n" . '%URL%',
		'saved_html'      => '<p><img src="%BOXART%" align="left" style="margin-right: 1em;">We added <a href="%URL%"><strong>%NAME%</strong></a> to the “Saved” section of your %QUEUE% queue.%WATCH%</p><br clear="left">',

		'duplicate_text'  => '%NAME% is already in your %QUEUE% queue.',
		'duplicate_html'  => '<p><a href="%URL%"><strong>%NAME%</strong></a> is already in your %QUEUE% queue.</p><br clear="left">',

		'failure_text'    => 'Netflix would not allow us to add %NAME% to your %QUEUE% queue. Sorry.',
		'failure_html'    => '<p>Netflix would not allow us to add <a href="%URL%"><strong>%NAME%</strong></a> to your %QUEUE% queue. Sorry.</p><br clear="left">',

		'not_found_text'  => 'We could not find "%NAME%" in Netflix database. Sorry.',
		'not_found_html'  => '<p>We could not find <strong>"%NAME%"</strong> in Netflix database. Sorry.</p><br clear="left">',

		'invalid_text'    => 'No valid titles were found in your message. Sorry!',
		'invalid_html'    => '<p>No valid titles were found in your message. Sorry!</p><br clear="left">',

		'footer_text'     => 'Manage your queue:' . "\n" . 'http://movies.netflix.com/Queue' . "\n\n" . '________________________________' . "\n" . 'http://mail2q.com / @netflixq',
		'footer_html'     => '<p><a href="http://movies.netflix.com/Queue"><strong>Manage your queue.</strong></a></p>' . "\n" . '<p>_________________________________<br><a href="http://mail2q.com">http://mail2q.com</a> / <a href="http://twitter.com/netflixq">@netflixq</a></p>'

	);


##	Process e-mail

	if ( isset ( $_POST['From'], $_POST['To'], $_POST['Subject'], $_POST['Date'], $_POST['Body'], $_GET['Agent'] ) && $_GET['Agent'] == $mail_agent_code ):


	#	Extract user's e-mail address:

		$email_address = ExtractEmailAddress($_POST['From']);
		$udata_hash = UserHash($email_address, $hash_salt_udata);


	#	Parse subject line:
		$instructions = ( trim ( $_POST['Subject'] ) ) ? preg_split ( '/\s+/', trim ( strtolower ( substr ( $_POST['Subject'], 0, 512 ) ) ), 4 ) : Array();


	#	Determine if e-mail is authorized:

		if ( isset ( $users_data [ $udata_hash ] ) && file_exists ( $users_datastore . $users_data [ $udata_hash ] ) ):
			$user_data = unserialize ( file_get_contents ( $users_datastore . $users_data [ $udata_hash ] ) );
		else:
			exit;
		endif;


	#	Extract titles from message:

		$title_candidates = preg_split ( "/\n(--|__|\n\n)/", "\n" . str_replace ( "\r", "\n", str_replace ( "\r\n", "\n", substr ( $_POST['Body'], 0, 1024 ) ) ), 2 );
		$title_candidates = preg_split ( "/\n+/", trim ( urldecode ( $title_candidates[0] ) ), 20 );
		$title_candidates = array_unique ( array_values ( array_filter ( $title_candidates, 'TitleCandidateFilter' ) ) );


	#	Prepare output placeholders:

		$message_text = Array();
		$message_html = Array();
		$update_count = 0;


	#	Process title candidates:

		for ( $i = 0; ( $i < count($title_candidates) && $i < 5 ); $i++ ):

			$title_response = NetflixGetTitle ( $user_data, $title_candidates[$i] );

			if ( $title_response ):

			#	Set queue position request:
				$title_response['position_request'] = ( in_array ( 'top', $instructions ) ) ? '1' : '';


			#	If the movie is available instantly, set correct queue and provide "watch" links:

				if ( $title_response['instant'] ):
					$title_response['queue'] = ( in_array ( 'disc', $instructions ) ) ? 'disc' : 'instant';
					$title_response['watch'] = ( $title_response['id'] ) ? '<br><a href="' . $netflix_instant_player_url . $title_response['id'] . '">Watch it now.</a>' : '';
				else:
					$title_response['queue'] = 'disc';
					$title_response['watch'] = '';
				endif;


			#	Add to queue:
				$queue_response = NetflixAddToQueue ( $user_data, $title_response['ref'], $title_response['queue'], $title_response['position_request'] );


			#	Process response:

				if ( is_array($queue_response) ):
					$response_snippet = ( $queue_response['position'] ) ? 'success' : 'saved';
					$title_response = array_merge ( $title_response, $queue_response );
					$update_count++;
				elseif ( $queue_response == 'duplicate' ):
					$response_snippet = 'duplicate';
				else:
					$response_snippet = 'failure';
					file_put_contents ( $debug_datastore . 'queue_failure_' . time(), $title_candidates[$i] );
				endif;

			elseif ( $title_response ):
				$title_response = Array ( 'name' => $title_candidates[$i] );
				$response_snippet = 'not_found';
				file_put_contents ( $debug_datastore . 'not_found_' . time(), $title_candidates[$i] );
			else:
				$title_response = Array ( 'name' => $title_candidates[$i] );
				$response_snippet = 'failure';
				file_put_contents ( $debug_datastore . 'failure_' . time(), $title_candidates[$i] );
			endif;

			array_push ( $message_text, PopulateMessage ( $snippets[$response_snippet . '_text'], $title_response ) );
			array_push ( $message_html, PopulateMessage ( $snippets[$response_snippet . '_html'], $title_response ) );

		endfor;


	#	Finalize message body:

		if ( empty($message_text) && empty($message_html) ):

			$message_subject = $snippets['subject_failure'];
			$message_text    = $snippets['invalid_text'];
			$message_html    = $snippets['invalid_html'];

			file_put_contents ( $debug_datastore . 'mail_' . time(), $_POST['Subject'] . "\n\n" . $_POST['Body'] );

		else:

			$message_subject = ( $update_count ) ? $snippets['subject_success'] . ' ['. $update_count . ']' : $snippets['subject_failure'];
			$message_text    = trim ( $snippets['header_text'] . "\n\n" . implode("\n\n", $message_text) . "\n\n" . $snippets['footer_text'] );
			$message_html    = trim ( $snippets['header_html'] . "\n"   . implode("\n",   $message_html) . "\n"   . $snippets['footer_html'] );

		endif;


	#	Formulate and send reply:

		$ses = new SimpleEmailService($aws_access_key, $aws_secret_key);

		$message = new SimpleEmailServiceMessage();
		$message->addTo($email_address);
		$message->setFrom('Mail2Q <netflix@mail2q.com>');
		$message->setReturnPath('admin@mail2q.com');
		$message->setSubject($message_subject);
		$message->setMessageFromString($message_text, $message_html);

		$ses->sendEmail($message);


	endif;


?>
