<?php 

interface S3Object
{
	/**
	 *
	 * The region in which this object stores its document in AWS S3.
	 *
	 * @return string
	 */
	public function getRegion();

	/**
	 *
	 * The bucket in which this object stores its document in AWS S3.
	 *
	 * @return string
	 */
	public function getBucket();

	/**
	 * The key under which this object stores its document in AWS S3.
	 *
	 * @ return string
	 */
	public function getKey();

	/**
	 * Whether this objects uses server-side encryption to store its document in AWS S3.
	 *
	 * @return Boolean
	 */
	public function getServerSideEncryption();

	/**
	 * Whether this object uses reduced redundancy storage to store its document in AWS S3.
	 *
	 * @return Boolean
	 */
	public function getReducedRedundancyStorage();
}