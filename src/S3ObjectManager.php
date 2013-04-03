<?php

interface S3ObjectManager
{
    /**
     * Returns the S3Client to use for cummunicating with the AWS S3 service.
     *
     * @param S3Object a document
     *
     * @return S3Client
     */
    public function getS3Client(S3Object $object);

    /**
     * Returns the AWS S3 bucket to upload an S3Object to.
     *
     * @param S3Object the document to upload to AWS S3.
     *
     * @return string
     */
    public function getBucket(S3Object $object);

    /**
     * Whether the object should store its document on S3 using server-side encryption.
     */
    public function getServerSideEncryption(S3Object $object = null);

    /**
     * Whether the object should store its document on S3 using reduced redundancy storage.
     */
    public function getReducedRedundancyStorage(S3Object $object = null);
}
