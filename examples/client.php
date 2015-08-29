<!--
 * An example that represents a client that connects to the service.
 * The simple interface provided asks you for a device token, a title and a text of the notification.
 • Then it hands it over to the service to send to Cloud Connection Servers.
 *
 * @package GCMnotifier
 *
--><!DOCTYPE html>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
	body {max-width: 960px; margin: 0 auto}
	pre {background: #999; color: white; padding: 0 4px}
	input, textarea {width: 100%; font: inherit; background: #eee; border: 0; padding: 2px 4px}
	button {font: inherit}
</style>
<body>
<h1>GCMnotifier Client</h1>

<pre><?php

require __DIR__."/../GCMnotifier.php";

error_reporting(E_ERROR);

$client = new GCMnotifier();

if (@$_POST['command'] == 'send') {
	$data = ['title' => $_POST['title'], 'body' => $_POST['body'], 'id' => (string)time()];
	$notification = ['to' => $_POST['to'], 'data' => $data, "message_id" => (string)time()];
	$result = $client->send($notification);
}
if (@$_POST['command'] == 'stop')
	$result = $client->send('stop');

?></pre>

<?php if (isset($result)) { ?>
	<p>Result: <?=json_encode($result)?></p>
<?php } ?>

<form action="" method="post">
	<p><input type="text" name="to" placeholder="Device token" value="">
	<p><input type="text" name="title" placeholder="Notification title" value="">
	<p><textarea name="body" placeholder="Notification text"></textarea>
	<p>
	<button type="submit" name="command" value="send">Send Notification</button>
	<button type="submit" name="command" value="stop">Stop Service</button>
</form>
