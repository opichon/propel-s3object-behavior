<?php

use Aws\S3\S3Client;
use Aws\S3\Enum\CannedAcl;

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

        $this->sse = null === $serverSideEncryption
            ? static::OFF_DEFAULT
            : $serverSideEncryption;

        $this->rrs = null === $reducedRedundancyStorage
            ? statif::OFF_DEFAULT
            : $reducedRedundancyStorage;
    }

    public function getS3Client(S3Object $object)
    {
        $region = $this->getRegion($object);
        $this->s3->setRegion($region);

        return $this->s3;
    }

    public function getRegion(S3Object $object)
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
     * Returns the key for the object to use in AWS S3.
     * This implementation will first check whether the object itself defines a key,
     * and use that if it does. OTherwise, by default, it will return the object's
     * sanitized original filename.
     *
     * @return string
     */
    public function getKey(S3Object $object)
    {
        if ($key = $object->getKey()) {
            return $key;
        }

        $slugify = new Slugify(Slugify::MODEARRAY);

        return $slugify->slugify($object->getOrignalFilename());
    }

    public function getPresignedUrl(S3Object $object, $expires = "+5 minutes")
    {
        $bucket = $this->getBucket($object);

        $key = $this->getKey($object);

        if (empty($bucket) || empty($key)) {
            return;
        }
        $s3 = $this->getS3Client($object);

        $url = sprintf(
            '%s/%s?response-content-disposition=attachment; filename=\"%s\"',
            $bucket,
            $key,
            $object->getOriginalFilename()
        );

        $request = $s3->get($url);
        $signed = $s3->createPresignedUrl($request, $expires);

        return \$signed;
    }

    /**
     * Uploads to AWS S3 the file associated with this object.
     */
    public function uploadFile(S3Object $object, $file)
    {
        if (!$file) {
            return;
        }

        $bucket = $this->getBucket($object);

        $key = $this->getKey($object);

        if (empty($bucket) || empty($key)) {
            return;
        }

        $s3 = $this->getS3Client($object);

        $response = $s3->putObject(array(
            'Bucket' => $bucket,
            'Key'    => $key,
            'Body'   => $file,
            'ACL'    => CannedAcl::PRIVATE_ACCESS,
            'ServerSideEncryption' => $this->getServerSideEncryption($object) ? 'AES256' : null,
            'StorageClass' => $manager->getReducedRedundancyStorage($object) ? 'REDUCED_REDUNDANCY' : 'STANDARD'
        ));

        return $response;
    }

    /**
     * Deletes, on AWS S3, the file associated with this object.
     *
     * @return Guzzle\Service\Resource\Model reponse from S3Client request via Guzzle
     * @throws S3Exception if the request fails
     */
    public function deleteFile(S3Object)
    {
        $bucket = $this->getBucket($object);

        $key = $this->getKey($object);

        if (empty($bucket) || empty($key)) {
            return;
        }

        $s3 = $this->getS3Client($object);

        $s3->registerStreamWrapper();

        if (!file_exists(sprintf('s3://%s/%s', $bucket, $key))) {
            return;
        }

        $response = $s3->deleteObject(array(
            'Bucket' => $bucket,
            'Key'    => $key
        ));

       return $response;
    }
}
