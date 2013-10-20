propel-s3object-behavior
========================

The S3Object behavior allows an object to be stored in AWS S3.

Requirements
------------

This behavior requires Propel >= 1.6.0, as well as the AWS SDK for PHP >= 2.2.0.

Installation
------------

Get the code by adding the following line to your `composer.json` file:

```yaml
	require: {
		…
		"uam/propel-s3object-behavior": "dev-master"
	}
```

Add the following to your `propel.ini` or `build.properties` file.

```ini
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
            - '%my_app.documents.aws_bucket%'
            - '%my_app.documents.aws_region%'
            - '%my_app.documents.aws_sse%'
            - '%my_app.documents.aws_rrs%'
```

### Uploading a file

This is typically done via a form that would contain a file input widget. When the form is submitted, set the `originalFilename` and `pathname` properties of the object instance being edited, using the values obtained form the uploaded file. Then save the object instance. You mst, of course, first obtain an instance of `S3ObjectManager` and set it on the object.

```php
$document_manager = /* see above */;

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

$document_manager = /* see above */;

$document->setS3ObjectManager($document_manager);

$url = $document->getPresignedUrl();

Header("Location: " . $url);
```

### Using defaults

The S3Object allows you to use a different buvcket, or even region, for each object instance. Nevertheless, in most cases, all objects will share the same region and bucket. These can be set as defaults in several ways:

The first way is to update your table and set the appropriate values as defaults. This approach is simple, but has a potentially serious weakness: the defaults apply to all development environments. That is, using a different bucket during development and for production requires some messy switching.

The second, and recommended approach, is to use the `BasicS3ObjectManager` for your defaults. When creating an instance of `BasicS3ObjectManager`, the parameters passed ot its constructor will act as defaults for all object instances with which the manager will be associated.

This allows you to create, or configure, separate instances of `BasicS3ObjectManager` for each environment: dev or prod, and use a separate bucket for each.

### Key policy

It is the developer's responsibility to design a policy for generating keys that ensures the uniqueness of such keys on AWS S3, or, if uniqueness is not required, for designing a policy that ensures the consistency of the keys with the set of files stored on AWS S3.

The key is a unique identifyer for a file within an AWS S3 bucket. It follow that it must be unique (within that bucket).

The bucket and key are also stored as a property of the object instance, and persisted to the database in the table assocaited with the class, under the `bucket` and `key` columns. (Note: the bucket field may be null, if all objects share the same bucket, and if it is defined elsewhere as a default.)

In most cases, each instance of your class will be associated with a unique file. Therefore the combination [bucket, key] in your table must be unique.

In some cases, your app may require that several object instances share the same file. In that case, you may want to have this file stored only once on AWS S3, and you would therefore have to ensure that the key for each object instance concerned is identical.

The key for each object instance, whenever the associated file is first uploaded to AWS S3, is generated by the `generateKey` method. You are free to override this method in your own class.

The default implementation returns a slug based on the `original_filename` property.

A simple alternative implementation would be to return the object instance's id. This naturally ensures the unicity of the key. However, it does mean that the files on AWS S3, should you need to manage them directly through the AWS management console, are not so easily recognizable. Note that in all cases, the `getPresignedUrl` downloads the file form AWS S3 under its original filename.

### Pruning "orphaned" files

"Orphaned" files are fileson AWS S3 that do not match any object instance in your app (or record in your database).

The S3Object behavior provides built-in mechanism to avoid orphaned files.

  * Upon deleting an object, the S3Object behavior will automatically delete the associated file on AWS S3.

  * Upon saving an object, the S3bject behavior will automatically check if the key has changed, and if so, will delete the file associated with the old key.

Note that the current implementation assumes that the bucket is unchanged. If the bucket is changed, the document in the old bucket will not be deleted.

Note also that if your app does not require unicity of keys, in other words if the same file is shared by several object instances, then consistency may be lost. The old file will have been deleted from AWS S3, but only one record will have been updated. All other records will still have the old key, which now points to nothing. This is a limitation that we are unlikely to address inside the S3Object behavior. To resolve this, override the `preUpdate`, `postUpdate` and `postDelete` methods generated by the behavior in your class.

### Direct manipulation of files in AWS S3

Obviously, if you delete files diretly in AWS S3 via browser extensions or via thw AWS management console, you'll have to update the data in your tables yourself.

### Property reference

The S3Object behavior defines the following properties on the class to which it is applied.

#### originalFilename

The original filename of the file associated with the object instance.

#### bucket

The AWS S3 bucket in which the associated file is stored.

### region

The AWS region in which the associated file is stored.

### key

The  AWS S3 key under which the associated file is stored.

### sse

Whether the associated file on AWS S3 is stored using server-side encryption.

### rrs

Whether the associated file on AWS S3 is stored using reduced redundancy storage.

### pathname

the local path to the file associated with the object instance. This property is transient (not persisted to the database) and is meant to be used during a file upload (e.g. via a form to edit the object instance).

### s3object_manager

The `S3ObjectManager` instance assocaited with the object instance, and to which most methods will be delegated.

### Method reference

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

### The `S3ObjectManager` interface

The `S3ObjectManager` interface defines most of the logic required to implement the S3Object behavior. Almost all the methods added by the behavior to the object class are implemented by delegating theirlogic to the `S3ObjectManager` instance associated with the object instance.

`S3ObjectManager` is an interface. The behavior provides a default implementation named `BasicS3ObjectManager`, which is based on the AWS SDK v2.

You are of course free to create and use your own implementation of the `S3ObjectManager` interface. In particular, you could create one that uses the AWS SDK v1, if this was required.
