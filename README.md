# Google-Cloud-Messaging-Server
PHP Library that implements Google's GCM server.

## Sample usage:

```php
	try{
		$gcm = new GCMRequest("YOUR_API_KEY");
		$gcm->setTargetDevices(array("device_1", "device_2", "device_3"));
		$gcm->setData(array("title" => "Example title", "description" => "Example description"));
		$result = $gcm->sendMessage();

		echo '<pre>';
		print_r($result);
		echo '</pre>';
	}
	catch (Exception $e){
		echo $e->getMessage();
	}
```

> - You should replace original registration ID (getOriginalRegistrationId()) with getRegistrationId() on your database if getRegistrationId() is set and returns a value.
> - You should delete the registration from your database if getError() returns "NotRegistered"
> - You should retry sending to those devices where getError() returns "Unavailable"
