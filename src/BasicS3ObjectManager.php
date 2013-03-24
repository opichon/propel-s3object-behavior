<?php

use Aws\S3\S3Client;

class BasicS3ObjectManager implements S3ObjectManager
{
	protected $s3;

	protected $bucket;

	public function __construct(S3Client $s3, $bucket = null)
	{
		$this->s3 = $s3;
		$this->bucket = $bucket;
	}

	public function getS3Client(S3Object $object)
	{
		return $this->s3;
	}

	public function getBucket(S3Object $object)
	{
		$bucket = $object->getBucket();
		return $bucket ? $bucket : $this->bucket;
	}
}