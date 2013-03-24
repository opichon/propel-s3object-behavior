<?php 

interface S3Object
{
	public function getRegion();
	public function getBucket();
	public function getKey();
}