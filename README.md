propel-s3object-behavior
========================

The S3Object behavior allows an object to be stored in AWS S3.

Requirements
------------

This behavior requires Propel >= 1.6.0, as well as the AWS SDK for PHP >= 2.2.0.

Installation
------------

Get the code by adding the following line to your `composer.json` file:

```json
	require: {
		…
		"uam/propel-s3object-behavior": "dev-master"
	}
```

Add the following to your `propel.ini` or `build.properties` file.

```
propel.behavior.s3object.class: S3ObjectBehavior
```

You need to have a valid and active account on AWS S3, and/or access to a valid AWS S3 bucket. See the AWS website for details.

Usage
=====

#### In the Propel schema

Add the behavior to the relevant table's schema definition:

```xml

<?xml version="1.0" encoding="utf-8"?>
<database name="default" namespace="My\App\lib" defaultIdMethod="native">
    <table name="document" phpName="Document" idMethod="native">
        <column name="id"          type="integer" primaryKey="true" autoIncrement="false" required="true" />
        <column name="name"        type="varchar" size="128" required="false" />
        <column name="description" type="longvarchar" required="false" />
defaultValue="false" />
        <behavior name="s3object" />
    </table>
</database>

```

#### In your code (standalone)

  1. Create a S3Client
  2. Create an instance of `BasicS3ObjectManager`.
  3. Set it on your object instance.
  4. Invoke the methods added by the S3Object behavior to the object instance.

```php

$s3 = $s3 = Aws::factory('/path/to/config.php')->get('s3');

$bucket = 'my-bucket';
$region = 'eu-west-1'; // Any AWS region
$serverSideEncryption = false; // No need to encrypt the documents on S3
$reducedRedundancyStorage = true; // Use reduced redundancy storage

$manager = new BasicS3ObjectManager(
    $s3,
    $bucket,
    $region,
    $serverSideEncryption,
    $reducedRedundancyStorage
);

$document = DocumentQuery::create()
    ->findPk($id);

$document->setS3ObjectManager($manager);

$url = $document->getPresignedUrl("+5minutes");

```

#### In symfony2

  1. Define an S3Client as a service
  2. Define an instance of `BasicS3ObjectManager` as  a service.
  3. Set it on your object instance.
  4. Invoke the methods added by the S3Object behavior to the object instance.

In the example below we use the [UAMAwsBundle](http://knpbundles.com/opichon/UAMAwsBundle) to provide a service for a S3Client.

```yaml

# services.yml

parameters:
    my_app.document_manager.class: BasicS3ObjectManager
    my_app.documents.aws_bucket: my-bucket
    my_app.documents.aws_region: eu-west-1
    my_app.documents.aws_sse: false
    my_app.documents.aws_rrs: true

services:
    my_app.document_manager:
        class: %my_app.document_manager.class%
        arguments:
            - @uam_aws.s3
            - %my_app.documents.aws_bucket%
            - %my_app.documents.aws_region%
            - %my_app.documents.aws_sse%
            - %my_app.documents.aws_rrs%
```

### Uploading a file

This is typically done via a form that would contain a file input widget. When the form is submitted, set the `originalFilename` and `pathname` properties of the object instance being edited, using the values obtained form the uploaded file. Then save the object instance. You mst, of course, first obtain an instance of `S3ObjectManager` and set it on the object.

```php
$document_manager = …;

$document = DocumentQuery::create()
    ->findPk($id);

$document->setS3ObjectManager($document_manager);

$document->setOriginalFilename($filename);
$document->setpathname($path);

$document->save();
```

### Getting a link to the S3 file

Simply call the `getPresignedUrl` method and use that url as you wish.

In order to provide a modest degree of obfuscation, we recommend the following pattern:

  * in your web pages, use an internal url, that is, a url pointing to a page in your app's own domain
  * server-side, when that page is requested, redirect to the AWS S3 presigned url.

```php

$id = $request[$_GET]['document_id'];

$document = DocumentQuery::create()
    ->findPk($id);

$document_manager = …

$document->setS3ObjectManager($document_manager);

$url = $document->getPresignedUrl();

Header("location: " . $url);
```

### Property reference

The S3Object behavior defines the following properties on the class to which it is applied. All properties can be defined per-instance.

#### originalFilename

The original filename of the file associated with the object instance.

#### bucket

The AWS S3 bucket in which the associated file is stored.

### region

The AWS region in which the associated file is stored.

### key

The  AWS S3 key under which the assocaited file is stored.

### sse

Whether the associated file on AWS S3 is stored using server-side encryption.

### rrs

Whether the associated file on AAWS S3 is tored using reduced redundancy storage.

### pathname

the local path to the file associated with the object instance. This property is transient (not persisted to the database) and is meant to be used during a file upload (e.g. via a form to edit the object instance).

### s3object_manager

The `S3ObjectManager` instance assocaited with the object instance, and to which most methods will be delegated.

### Property/Method reference

In addition to the getters and setters for the properties above, the S3OBject behavior adds the follow methods to the class on which it is applied.

All methods below require an instance of `S3ObjectManager`. This can be either pass explicitly as an argument (most methods below accept such an argument) or the it must be set on the object instance beforehand. The latter is the recommended approach.

#### getPresignedUrl($expires = "+5 minutes")

Returns a presigned url to the S3 file associated to the object. Accepts an argument indicating the time-to-live for the presigned url (see AWS S3 documentation).

#### fileExists

Checks whether the file associated with the object instance does exist on S3.

#### generateKey

Generates a new key under which to store, on S3, the file associated with the object instance.

#### upload($path)

Uploads a file to S3.

#### deleteFile

Deletes, on S3, the file associated with this object instance.

#### The `S3ObjectManager` interface

The `S3ObjectManager` interface defines most of the logic required to implement the S3Object behavior. Almost all the methods added by the behavior to the object class are implemented by delegating theirlogic to the `S3ObjectManager` instance associated with the object instance.

`S3ObjectManager` is an interface. The behavior provides a default implementation named `BasicS3ObjectManager`, which is based on the AWS SDK v2.

You are of course free to create and use your own implementation of the `S3ObjectManager` interface. In particular, you could create one that uses the AWS SDK v1, if this was required.
