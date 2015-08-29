<?php
/**
 * GCMnotifier The class that contains both client and service functions.
 * It will connect to Cloud Connection Servers and keep the connection and its XMPP session open.
 *
 * @package GCMnotifier
 */

class GCMnotifier
{
	private $host = 'gcm-preprod.googleapis.com';
	private $port = 5236;
	private $sender_id = '000000000000';
	private $api_key = 'XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX';

	private $serviceHost = '127.0.0.1';
	private $servicePort = 1333;

	private $remote;
	private $service;

	private $debugFile = 'php://output';
	private $debugLevel = 1;

	private $onSend;
	private $onFail;
	private $onExpire;

	function __construct($options = array()) {
		foreach ($this as $key => $value)
			if (!empty($options[$key]))
				$this->$key = $options[$key];

		if ($this->debugFile)
			$this->debugFile = fopen($this->debugFile, "a");
	}

	// Client functions

	function connect() {
		$this->debug(2, "Connecting to service on $this->serviceHost:$this->servicePort.");
		$this->service = stream_socket_client("tcp://$this->serviceHost:$this->servicePort", $errno, $errstr, 30)
			or $this->debug(1, "Cannot connect", "($errno) $errstr");
		return $this->service;
	}

	function isConnected() {
		return ($this->service && !feof($this->service));
	}

	function close() {
		$this->debug(1, "Closing connection");
		if ($this->service);
			fclose($this->service);
		unset($this->service);
	}

	function send($json) {
		if (!$this->isConnected())
			$this->connect();
		$json = (is_string($json) ? $json : json_encode($json));
		$result = $this->write($this->service, $json."\n");
		if ($result !== false)
			$result = $this->read($this->service);
		return $result;
	}

	// Service functions

	function connectRemote() {
		$this->debug(2, "Connecting to $this->host:$this->port...");
		if (!($this->remote = stream_socket_client("tls://$this->host:$this->port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT|STREAM_CLIENT_PERSISTENT))) {
			$this->debug(1, "Failed to connect", "($errno) $errstr");
			return false;
		}

		$this->write($this->remote,
			'<stream:stream to="'.$this->host.'" version="1.0" xmlns="jabber:client" xmlns:stream="http://etherx.jabber.org/streams">');

		stream_set_blocking($this->remote, 1);
		while (($xml = $this->read($this->remote)) !== false)
			if ($xml)
				if ($root = $this->parseXML($xml)) {
					foreach ($root->childNodes as $node)
						if ($node->localName == 'features') {
							foreach ($node->childNodes as $node)
								if ($node->localName == 'mechanisms')
									$this->write($this->remote,
										'<auth mechanism="PLAIN" xmlns="urn:ietf:params:xml:ns:xmpp-sasl">'.base64_encode(chr(0).$this->sender_id.'@gcm.googleapis.com'.chr(0).$this->api_key).'</auth>');
								elseif ($node->localName == 'bind')
									$this->write($this->remote,
										'<iq to="'.$this->host.'" type="set" id="bind_1"><bind xmlns="urn:ietf:params:xml:ns:xmpp-bind"><resource>test</resource></bind></iq>');
								elseif ($node->localName == 'session')
									$this->write($this->remote,
										'<iq to="'.$this->host.'" type="set" id="session_1"><session xmlns="urn:ietf:params:xml:ns:xmpp-session"/></iq>');
						}
						elseif ($node->localName == 'success')
							$this->write($this->remote,
								'<stream:stream to="'.$this->host.'" version="1.0" xmlns="jabber:client" xmlns:stream="http://etherx.jabber.org/streams">');
						elseif ($node->localName == 'failure')
							break;
						elseif ($node->localName == 'iq' && $node->getAttribute('type') == 'result')
							if ($node->getAttribute('id') == 'session_1')
								return true;
				} else
					$this->debug(2, "Unparseable", $xml);

		fclose($this->remote);
		$this->remote = null;
		return false;
	}

	function isRemoteConnected() {
		return ($this->remote && !feof($this->remote));
	}

	function closeRemote() {
		if ($this->remote) {
			$this->debug(1, "Closing connection");
			$this->write($this->remote, $this->closing);
			fclose($this->remote);
		}
		$this->remote = null;
	}

	function listen() {
		if (!($this->service = stream_socket_server("tcp://$this->serviceHost:$this->servicePort", $errno, $errstr))) {
			$this->debug(1, "Cannot start service on $this->serviceHost:$this->servicePort", "($errno) $errstr");
			return false;
		}
		$this->debug(1, "Service started on port $this->servicePort.");

		while ($this->service) {
			if (!$this->isRemoteConnected())
				$this->connectRemote();
			if ($this->isRemoteConnected())
				$this->poll();

			$this->debug(2, "Listening on port $this->servicePort...");
			if (($client = @stream_socket_accept($this->service, 10))) {
				$this->debug(2, "Accepted a client");
				socket_set_timeout($client, 5);
				while ($this->service && ($json = $this->read($client, $timeout)) !== false && !$timeout) {
					if (in_array($json, ['stop', 'quit', 'exit'])) {
						$success = fclose($this->service);
						$this->service = null;
					}
					elseif (@json_decode($json))
						$success = $this->write($this->remote, '<message><gcm xmlns="google:mobile:data">'.$json.'</gcm></message>');
					else
						$success = false;
					$this->write($client, json_encode(!!$success));
				}
				fclose($client);
				$this->debug(2, "Client disconnected");
			}
		}

		$this->closeRemote();
		return true;
	}

	private function poll() {
		$this->debug(2, "Polling Cloud Connection Server...");
		stream_set_blocking($this->remote, 0);
		while (($xml = $this->read($this->remote)))
			if (($root = $this->parseXML($xml)))
				foreach ($root->childNodes as $node)
					if ($node->localName == 'message')
						if ($node->getAttribute('type') == 'error') {
							foreach ($node->childNodes as $subnode)
								if ($subnode->localName == 'error')
									$this->debug(1, "ERROR ".$subnode->textContent);
						} elseif ($node->firstChild->localName == 'gcm'
								&& ($json = $node->firstChild->textContent)
								&& ($data = json_decode($json))
								&& @$data->message_type && @$data->message_id) {
							if ($data->message_type == 'ack') {
								$this->debug(2, "ACK message #$data->message_id");
								@call_user_func($this->onSend, $data->message_id);
							}
							elseif ($data->message_type == 'nack') {
								$this->debug(1, "$data->error ($data->error_description) $data->from");
								if ($data->error == 'BAD_REGISTRATION' || $data->error == 'DEVICE_UNREGISTERED')
									@call_user_func($this->onExpire, $data->from, null);
								else
									@call_user_func($this->onFail, $data->message_id, $data->error, $data->error_description);
							}
							if (@$data->registration_id) {
								$this->debug(1, "CANONICAL ID $data->from -> $data->registration_id");
								@call_user_func($this->onExpire, $data->from, $data->registration_id);
							}
						}
	}

	// Auxiliary functions

	private function write($socket, $xml) {
		$length = fwrite($socket, $xml."\n");
		$this->debug(2, is_numeric($length) ? "Sent $length bytes" : "Failed sending", $xml);
		return $length;
	}

	private function read($socket, &$timed_out = false) {
		if (!$socket || feof($socket))
			return false;
		$response = fread($socket, 2048);
		$timed_out = (($meta = stream_get_meta_data($socket)) && $meta['timed_out']);
		$length = (is_string($response) ? (strlen($response) == 1 ? 'character '.ord($response) : strlen($response).' bytes') : json_encode($response));
		$this->debug(2, $response === false ? "Failed reading" : "Read $length", $response);
		return (is_string($response) ? trim($response) : $response);
	}

	private $opening, $closing;

	private function parseXML($xml) {
		$doc = new DOMDocument();
		$doc->recover = true;
		if ($doc->loadXML($xml, LIBXML_NOWARNING|LIBXML_NOERROR) && $doc->documentElement && $doc->documentElement->localName == 'stream')
			$this->opening = substr($xml, 0, strpos($xml, '>')+1) and $this->closing = "</{$doc->documentElement->tagName}>";
		elseif ($this->opening && $this->closing && $doc->loadXML($this->opening.$xml.$this->closing, LIBXML_NOWARNING|LIBXML_NOERROR) && $doc->documentElement && $doc->documentElement->localName == 'stream')
			return $doc->documentElement;
		else
			return false;
		return $doc->documentElement;
	}

	private function debug($level, $title, $content = '') {
		if ($this->debugFile && $level <= $this->debugLevel)
			fwrite($this->debugFile, "=== $title === ".date('H:i:s')." ===\n". (strlen(trim($content)) ? trim($content)."\n" : ""));
	}

}