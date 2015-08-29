
GCMnotifier
============
PHP framework for sending GCM push notifications to Android mobile devices using XMPP protocol (CCS)

Usage
-----
The system comprises of a background service and one or more clients that use the service for sending.
The service will open and keep a persistent connection with Google's CCS servers.
It will accept TCP connections from clients, take one or more notification packets in JSON strings,
wrap them up in XMPP packets and send them up to the servers.
The client disconnects immediately, as the acknowledgement can arrive much later, asynchronously.
It's the service that registers callback functions to handle the acknowledgements when they arrive.

The example
-----------
One can start the example service from the shell:

	php examples/service.php

(You may need to prefix "php" with the full path of the executable.)

Make sure you configure the code for your project,
filling in the Sender ID and API key with those obtained from Google Developer Console.
Default server address and port have values intended for development;
the production version will need to have its final values (probably `gcm.googleapis.com` and `5235`).
Please assure also that no firewall blocks the port specified.

The service is meant to be started and left open indefinitely.
When its connection with Cloud Connection Servers is lost, it will try to reconnect.

You may want to try the client side using telnet first.
Just connect to the service and type in the JSON packets you want to send as notifications.
More comfortable would be to try the client example that is provided.
It has a simple HTML interface, and it's meant to be started in your browser:

	http://localhost/GCMnotifier/examples/client.php

(This assumes you have a web server running locally and the examples located in the path shown.)
