<?php
/**
 * An example that represents a service, ie. a background process listening for client TCP connections,
 * taking their notifications and delivering them to Google Cloud Connection servers.
 *
 * Replace sender_id and api_key below with your official credentials obtained from Google's Developer Console.
 *
 * @package XMPPnotifier
 */
require __DIR__."/../XMPPnotifier.php";

$xmpp = new GCMnotifier([
		'sender_id' => '248099740444',
		'api_key' => 'AIzaSyCW7x0JBFdnLc6P6vuGPdezM54T6-9jT9g',

		'onSend' => function ($message_id) {
			echo "Sent message #$message_id successfully.\n";
		},

		'onFail' => function ($message_id, $error, $description) {
			echo "Failed message #$message_id with $error ($description).\n";
		},

		'onExpire' => function ($old_token, $new_token) {
			if ($new_token)
				echo "Need to replace expired token $old_token with #$new_token\n";
			else
				echo "Need to forget invalid token $old_token\n";
		},
	]);

$xmpp->listen()
	or die("Cannot start service.\n");
