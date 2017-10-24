<?php

use Aws\S3\S3Client;

use Cocur\Slugify\Slugify;

class BasicS3ObjectManager implements S3ObjectManager
{
    const ON_ALWAYS = 1;
    const ON_DEFAULT = 2;
    const OFF_DEFAULT = 3;
    const OFF_ALWAYS = 4;

    protected $s3;
    protected $region;
    protected $bucket;
    protected $sse;
    protected $rrs;

    public function __construct(
        S3Client $s3,
        $bucket = null,
        $region = null,
        $serverSideEncryption = null,
        $reducedRedundancyStorage = null)
    {
        $this->s3 = $s3;

        $this->bucket = $bucket;

        $this->region = $region;

        $this->sse = null === $serverSideEncryption
            ? static::OFF_DEFAULT
            : $serverSideEncryption;

        $this->rrs = null === $reducedRedundancyStorage
            ? static::OFF_DEFAULT
            : $reducedRedundancyStorage;
    }

    public function getS3Client(S3Object $object)
    {
        return $this->s3;
    }

    public function getRegion(S3Object $object = null)
    {
        if (null === $object) {
            return $this->region;
        }

        $region = $object->getRegion();

        return $region ? $region : $this->getRegion();
    }

    public function getBucket(S3Object $object = null)
    {
        if (null === $object) {
            return $this->bucket;
        }

        $bucket = $object->getBucket();

        return $bucket ? $bucket : $this->bucket;
    }

    public function getServerSideEncryption(S3Object $object = null)
    {
        if ($object === null) {
            return $this->sse;
        }

        switch ($this->sse) {
            case static::ON_ALWAYS :
                return true;

            case static::OFF_ALWAYS :
                return false;

            case static::ON_DEFAULT :
                return $object->getServerSideEncryption() === null ? true : $object->getServerSideEncryption();

            case static::OFF_DEFAULT :
                return $object->getServerSideEncryption() === null ? false : $object->getServerSideEncryption();
        }
    }

    public function getReducedRedundancyStorage(S3Object $object = null)
    {
        if ($object === null) {
            return $this->rrs;
        }

        switch ($this->rrs) {
            case static::ON_ALWAYS :
                return true;

            case static::OFF_ALWAYS :
                return false;

            case static::ON_DEFAULT :
                return $object->getReduceRedundancyStorage() === null ? true : $object->getReduceRedundancyStorage();

            case static::OFF_DEFAULT :
                return $object->getReduceRedundancyStorage() === null ? false : $object->getReduceRedundancyStorage();
        }
    }

    /**
     * Generates a new key for the object to use in AWS S3.
     * This implementation will return the object's slugified original filename.
     *
     * @return string
     */
    public function generateKey(S3Object $object)
    {
        $pathinfo = pathinfo($object->getOriginalFilename());

        $slugify = new Slugify();

        return sprintf(
            '%s.%s',
            $slugify->slugify($pathinfo['filename']),
            $pathinfo['extension']
        );
    }

    /**
     * Returns the key for the object to use in AWS S3.
     *
     * @return string
     */
    public function getKey(S3Object $object)
    {
        return $object->getKey();
    }

    public function getPresignedUrl(S3Object $object, $expires = "+5 minutes")
    {
        $bucket = $this->getBucket($object);

        $key = $this->getKey($object);

        if (empty($bucket) || empty($key)) {
            return;
        }

        $s3 = $this->getS3Client($object);

        $cmd = $s3->getCommand('GetObject', [
           'Bucket' => $bucket,
           'Key' => $key,
        ]);

        $request = $s3->createPresignedRequest($cmd, $expires);
        $signed = (string) $request->getUri();

        return $signed;
    }

    /**
     * Uploads to AWS S3 the file associated with this object.
     */
    public function uploadFile(S3Object $object, $file, $acl = 'private')
    {
        if (!$file || !file_exists($file)) {
            return;
        }

        $bucket = $this->getBucket($object);

        $key = $this->getKey($object);

        if (empty($bucket) || empty($key)) {
            return;
        }

        $s3 = $this->getS3Client($object);

        $response = $s3->upload(
            $bucket,
            $key,
            fopen($file, 'r'),
            $acl,
            array(
                'params' => array(
                    'ServerSideEncryption' => $this->getServerSideEncryption($object) ? 'AES256' : null,
                    'StorageClass' => $this->getReducedRedundancyStorage($object) ? 'REDUCED_REDUNDANCY' : 'STANDARD'
                )
            )
        );

        return $response;
    }

    /**
     * Deletes, on AWS S3, the file associated with this object.
     *
     * @return Guzzle\Service\Resource\Model reponse from S3Client request via Guzzle
     * @throws S3Exception if the request fails
     */
    public function deleteFile(S3Object $object)
    {
        $bucket = $this->getBucket($object);

        $key = $this->getKey($object);

        if (empty($bucket) || empty($key)) {
            return;
        }

        $s3 = $this->getS3Client($object);

        if (!$s3->doesObjectExist($bucket, $key)) {
            return;
        }

        $response = $s3->deleteObject(array(
            'Bucket' => $bucket,
            'Key'    => $key
        ));

       return $response;
    }

    public function fileExists(S3Object $object)
    {
        $bucket = $this->getBucket($object);

        $key = $this->getKey($object);

        if (empty($bucket) || empty($key)) {
            return false;
        }

        $s3 = $this->getS3Client($object);

        return $s3->doesObjectExist($bucket, $key);
    }
}
