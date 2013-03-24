<?php

use Aws\S3\S3Client;

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

	public function getS3Client(S3Object $object = null)
	{
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

	public function getReduceRedundancyStorage(S3Object $object)
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
}