propel-s3object-behavior
========================

The S3Object behavior allows an object to be stored in AWS S3.

Requirements
------------

This behavior requires Propel >= 1.6.0, as well as the AWS SDK for PHP >= 2.2.0.

Installation
------------

Get the code by adding the following line to your `composer.json` file:

```
	require: {
		…
		"uam/propel-s3object-behavior": "dev-master"
	}
```

Add the following to your `propel.ini` or `build.properties` file.

```
propel.behavior.s3object.class: S3ObjectBehavior
```