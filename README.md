propel-s3object-behavior
========================

The S3Object behavior allows an object to maintain an association with a file stored in AWS S3.

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
        <!-- you column definitions -->
        <behavior name="s3object" />
    </table>
</database>

```

#### In your code (standalone)

  1. Create an instance of `Aws\S3\S3Client`
  2. Create an instance of `BasicS3ObjectManager`.
  3. Set it on your object instance.
  4. Invoke the methods added by the S3Object behavior to the object instance.

```php

$s3 = Aws::factory('/path/to/config.php')->get('s3');

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

  1. Create an instance of `Aws\S3\S3Client` and define it as a service
  2. Create an instance of `BasicS3ObjectManager` and define it as a service.
  3. Set it on your object instance.
  4. Invoke the methods added by the S3Object behavior to the object instance.

In the example below we use the [UAMAwsBundle](http://knpbundles.com/opichon/UAMAwsBundle) to provide a `S3Client` service . You can use any package or code that returns a valid instance of `Aws\S3\S3Client` as a service.

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
        class: '%my_app.document_manager.class%'
        arguments:
            - '@uam_aws.s3'
            - '%my_app.documents.aws_bucket%'
            - '%my_app.documents.aws_region%'
            - '%my_app.documents.aws_sse%'
            - '%my_app.documents.aws_rrs%'
```

### Uploading a file

This is typically done via a form that would contain a file input widget. When processing the submitted the form, you must set the `originalFilename` and `pathname` properties of the object instance being edited, using the values obtained from the uploaded file. Then save the object instance. As indicated above, you must first obtain an instance of `BasicS3ObjectManager` and set it on the object.

```php
$document_manager = /* see above */;

$document = DocumentQuery::create()
    ->findPk($id);

$document->setS3ObjectManager($document_manager);

$document->setOriginalFilename($filename);
$document->setPathname($path);

$document->save();
```

### Getting a link to the S3 file

Simply call the `getPresignedUrl` method and use that url as you wish.

When that link is clicked, the associated file is downloaded from AWS S3 using the `original_filename` of the object instance, irrespective of the key under which the file is stored in AWS S3.

In order to provide a modest degree of obfuscation, we recommend the following pattern:

  * in your web pages, use an internal url, i.e. a url pointing to a page in your app's own domain
  * server-side, when that page is requested, redirect to the AWS S3 presigned url.

```php

$id = $_GET['document_id'];

$document = DocumentQuery::create()
    ->findPk($id);

$document_manager = /* see above */;

$document->setS3ObjectManager($document_manager);

$url = $document->getPresignedUrl();

Header("Location: " . $url);
```

This approach has 2 benefits:
  * It will avoid confusing your users by showing them a direct link to AWS.
  * From a security point of view, it allows you to create very short-lived presigned urls (the default is 5 minutes, but it can possibly be reduced even further). Because the presigned url is generated for each request, this causes minimal inconvenience for the user (if the page is too old, it just needs to be refreshed), while making sure that the link generated, if it is ever obtained by anyone, will be useless.

### Using defaults

The S3Object allows you to use a different bucket, or even region, for each object instance. Nevertheless, in most cases, all objects will share the same region and bucket. These can be set as defaults in several ways:

The first way is to update your table and set the appropriate values as defaults. This approach is simple, but has a practical drawback: the defaults apply to all development environments. In other words, if you want to use different buckets or other settings during development and in production, you have to use separate databases.

The second approach, which we recommend, is to use the `BasicS3ObjectManager` class for your defaults. When creating an instance of `BasicS3ObjectManager`, the parameters passed to its constructor will act as defaults for all object instances with which the manager will be associated. See the source code of this class.

This allows you to create, or configure, separate instances of `BasicS3ObjectManager` for each environment: dev or prod, and use a separate bucket for each.

### Key policy

It is the developer's responsibility to design a policy for generating keys that ensures the uniqueness of such keys on AWS S3, or, if uniqueness is not required, to design a policy that ensures the consistency of the keys with the set of files stored on AWS S3.

The key is a unique identifyer for a file within an AWS S3 bucket.

The bucket and key are also stored as properties of the object instance, and persisted to the database in the class table, under the `bucket` and `key` columns. (Note that the bucket field may be null, if all objects share the same bucket, and if it is defined elsewhere as a default.)

In most cases, each instance of your class will be associated with a unique file. Therefore the [bucket, key] combination in your table must be unique, reflecting the unicity of the key in that bucket on AWS S3.

In some other cases, your app may require that several object instances share the same file, and you may want to have this file stored only once on AWS S3. In such cases, you must ensure that the key for each object instance concerned is identical.

The key for each object instance, whenever the associated file is uploaded to AWS S3, is generated by the `generateKey` method, and then set as the object instance's `key` property. If you need to implement some custom logic for your keys, just override the `generateKey` method in your class.

The default implementation returns a slug based on the object's `original_filename` property. Be aware that in some circusmtances this may cause inconsistencies in the keys, if, for example, 2 file names only differ by the case (e.g. a letter is in upper case in one file name, but in lower case in the other; the slugs for each will be identical.)

A simple alternative implementation would be to return the object instance's id. This naturally ensures the unicity of the key. However, it does mean that the files on AWS S3, should you need to manage them directly through the AWS management console for example, are not so easily recognizable.

Note that in all cases, the `getPresignedUrl` downloads the file form AWS S3 under its original filename.

### Pruning "orphaned" files

"Orphaned" files are files on AWS S3 that do not match any object instance in your app (or record in your database).

The S3Object behavior provides 2 built-in mechanisms to avoid orphaned files.

  * When deleting an object, the S3Object behavior will automatically delete the associated file on AWS S3.

  * When saving an object, the S3Object behavior will automatically check if the key has changed, and if so, will delete the file associated with the old key.


  This implementation has 2 known limitations:

  1. It assumes that the bucket is unchanged. If the bucket is changed, the document in the old bucket will not be deleted.
  2. It may cause dangling keys to occur if your app allows several object instances to share the same file (in other words if it does not require unicity of keys). See next chapter below.

### Dangling keys

A dangling key is a key set on an object instance (or database record) for which no file exists on AWS S3.

This can occur in the following circumstances:

  1. The file was deleted from AWS S3 through another app, the API, or manually via the AWS console or otherwise. This is beyond the scope of this package.

  2. If your app allows several object instances to share the same file (in other words it does not require unicity of keys), then consistency may be lost when an object is updated with a new file. In such a scenario, the old file will be deleted from AWS S3, but all the other object instances associated with the old file will not have been updated, and so will still have the old key, for which there is now no file on AWS S3. To resolve this, override the `preUpdate`, `postUpdate` and `postDelete` methods generated by the behavior in your class and implement your own logic.

### Property reference

The S3Object behavior defines the following properties on the class to which it is applied.

#### original_filename

The original filename of the file associated with the object instance.

#### bucket

The AWS S3 bucket in which the associated file is stored.

#### region

The AWS region in which the associated file is stored.

#### key

The  AWS S3 key under which the associated file is stored.

#### sse

Whether the associated file on AWS S3 is stored using server-side encryption.

#### rrs

Whether the associated file on AWS S3 is stored using reduced redundancy storage.

#### pathname

The local path to the file associated with the object instance. This property is transient (not persisted to the database) and is meant to be used during a file upload (e.g. via a form to edit the object instance).

### s3object_manager

The `S3ObjectManager` instance associated with the object instance, and to which most methods will be delegated.

### Method reference

In addition to the getters and setters for the properties above, the S3OBject behavior adds the following methods to the class on which it is applied.

All methods below require an instance of `S3ObjectManager`. This can be either be passed explicitly as an argument (most methods below accept such an argument) or it must be set on the object instance beforehand. The latter is the recommended approach.

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

The `S3ObjectManager` interface defines most of the logic required to implement the S3Object behavior. Almost all the methods added by the behavior to the object class are implemented by delegating their logic to the `S3ObjectManager` instance associated with the object instance.

`S3ObjectManager` is an interface. The behavior provides a default implementation named `BasicS3ObjectManager`, which is based on the AWS PHP SDK v2.2.

You are free to create and use your own implementation of the `S3ObjectManager` interface. In particular, if for some reason you need to use v1 of the AWS PHP SDK, then it is quite possible to create an implementation of `S3ObjectManager` based on it.
