<?php

class S3ObjectBehaviorObjectBuilderModifier
{
	protected $behavior, $builder;

	public function __construct($behavior)
	{
		$this->behavior = $behavior;
	}

	protected function setBuilder($builder)
	{
		$this->builder = $builder;
		$this->builder->declareClasses(
			'Aws\\S3\\S3Client',
			'Aws\\S3\\Enum\\CannedAcl',
			'Aws\\S3\\Exception\\S3Exception',
			'\\S3Object',
			'\\S3ObjectManager'
		);
	}

	public function objectFilter(&$script)
	{
		$pattern = '/abstract class (\w+) extends (\w+) implements (\w+)/i';
		$replace = 'abstract class ${1} extends ${2} implements ${3}, S3Object';
		$script = preg_replace($pattern, $replace, $script);
	}
	
	public function objectMethods($builder)
	{

		$this->setBuilder($builder);
		$script = '';

		$this->addGetServerSideEncryptionMethod($script);
		$this->addGetReducedRedundancyStorageMethod($script);

		$this->addGetPresignedUrlMethod($script);
		$this->addUploadMethod($script);
		$this->addSanitizeFilenameMEthod($script);

		return $script;
	}

	protected function addGetServerSideEncryptionMethod(&$script)
	{
		$script .= "
/**
 * Whether this object's document should be stored on S3 using server-side server-side encryption.
 */
public function getServerSideEncryption()
{
	return \$this->get" . $this->behavior->getColumnForParameter('sse_column')->getPhpName() . "();
}
";
	}

	protected function addGetReducedRedundancyStorageMethod(&$script)
	{
		$script .= "
/**
 * Whether this object's document should be stored on S3 using reduced redundancy storage.
 */
public function getReducedRedundancyStorage()
{
	return \$this->get" . $this->behavior->getColumnForParameter('rrs_column')->getPhpName() . "();
}
";
	}
	
	protected function addGetPresignedUrlMethod(&$script)
	{
		$script .= "
/**
 * Returns a pre-signed url to the document on AWS S3.
 *
 * @param S3ObjectManager a S3 object manager
 * @param int|string \$expires The Unix timestamp to expire at or a string that can be evaluated by strtotime
 *
 * @return string
 * @throws InvalidArgumentException if the request is not associated with this client object  
 */
public function getPresignedUrl(S3ObjectManager \$manager, \$expires = \"+5 minutes\")
{
	\$s3 = \$manager->getS3Client(\$this);

	if (\$region = \$this->getRegion()) {
		\$s3->setRegion(\$region);
	}

	\$url = sprintf(
		'%s/%s?response-content-disposition=attachment; filename=\"%s\"',
		\$manager->getBucket(\$this),
		\$this->getKey(),
		\$this->getOriginalFilename()
	);
	\$request = \$s3->get(\$url);
	\$signed = \$s3->createPresignedUrl(\$request, \$expires);

	return \$signed;
}
";
	}

	protected function addUploadMethod(&$script)
	{
		$script .= "
/**
 * Uploads a file to S3.
 *
 * @param S3ObjectManager an S3ObjectManager instance
 * @param string|stream|Guzzle\Http\EntityBody the path to the file to upload; accepts any valid argument for the 'Body' parameter passed to the S3Client::putObject method.
 * @param string the AWS S3 bucket to upload this file to. If unset, this instance's 'bucket' property will be used.
 *
 * @return Guzzle\Service\Resource\Model reponse from S3Client request via Guzzle
 * @throws S3Exception if the request fails
 */
public function upload(\\S3ObjectManager \$manager, \$file)
{
	if (!\$file) {
		return; 
	}

	\$s3 = \$manager->getS3Client(\$this);

	if (\$region = \$this->getRegion()) {
		\$s3->setRegion(\$region);
	}

	\$response = \$s3->putObject(array(
		'Bucket' => \$manager->getBucket(\$this),
		'Key'    => \$this->getKey(),
		'Body'   => \$file,
		'ACL'    => CannedAcl::PRIVATE_ACCESS,
		'ServerSideEncryption' => 'AES256'
	));

	return \$response;
}
";
	}

	protected function addSanitizeFilenameMethod(&$script)
	{
		$script .= "
/**
 * Convenience method to sanitize a file for use as a key in AWS S3.
 */
 public function sanitize(\$filename) {
	\$s = trim(\$filename);
	\$s = preg_replace('/^[.]*/', '', \$s); // remove leading periods
	\$s = preg_replace('/[.]*\$/', '', \$s); // remove trailing periods
	\$s = preg_replace('/\.[.]+/', '.', \$s); // remove any consecutive periods

	// replace dodhy characters
	\$dodgychars = '[^0-9a-zA-Z\\.()_-]'; // allow only alphanumeric, underscore, parentheses, hyphen and period
	\$s = preg_replace('/' . \$dodgychars . '/', '_', \$s); // replace dodgy characters

	// replace accented characters
	\$a = array('À', 'Á', 'Â', 'Ã', 'Ä', 'Å', 'Æ', 'Ç', 'È', 'É', 'Ê', 'Ë', 'Ì', 'Í', 'Î', 'Ï', 'Ð', 'Ñ', 'Ò', 'Ó', 'Ô', 'Õ', 'Ö', 'Ø', 'Ù', 'Ú', 'Û', 'Ü', 'Ý', 'ß', 'à', 'á', 'â', 'ã', 'ä', 'å', 'æ', 'ç', 'è', 'é', 'ê', 'ë', 'ì', 'í', 'î', 'ï', 'ñ', 'ò', 'ó', 'ô', 'õ', 'ö', 'ø', 'ù', 'ú', 'û', 'ü', 'ý', 'ÿ', 'Ā', 'ā', 'Ă', 'ă', 'Ą', 'ą', 'Ć', 'ć', 'Ĉ', 'ĉ', 'Ċ', 'ċ', 'Č', 'č', 'Ď', 'ď', 'Đ', 'đ', 'Ē', 'ē', 'Ĕ', 'ĕ', 'Ė', 'ė', 'Ę', 'ę', 'Ě', 'ě', 'Ĝ', 'ĝ', 'Ğ', 'ğ', 'Ġ', 'ġ', 'Ģ', 'ģ', 'Ĥ', 'ĥ', 'Ħ', 'ħ', 'Ĩ', 'ĩ', 'Ī', 'ī', 'Ĭ', 'ĭ', 'Į', 'į', 'İ', 'ı', 'Ĳ', 'ĳ', 'Ĵ', 'ĵ', 'Ķ', 'ķ', 'Ĺ', 'ĺ', 'Ļ', 'ļ', 'Ľ', 'ľ', 'Ŀ', 'ŀ', 'Ł', 'ł', 'Ń', 'ń', 'Ņ', 'ņ', 'Ň', 'ň', 'ŉ', 'Ō', 'ō', 'Ŏ', 'ŏ', 'Ő', 'ő', 'Œ', 'œ', 'Ŕ', 'ŕ', 'Ŗ', 'ŗ', 'Ř', 'ř', 'Ś', 'ś', 'Ŝ', 'ŝ', 'Ş', 'ş', 'Š', 'š', 'Ţ', 'ţ', 'Ť', 'ť', 'Ŧ', 'ŧ', 'Ũ', 'ũ', 'Ū', 'ū', 'Ŭ', 'ŭ', 'Ů', 'ů', 'Ű', 'ű', 'Ų', 'ų', 'Ŵ', 'ŵ', 'Ŷ', 'ŷ', 'Ÿ', 'Ź', 'ź', 'Ż', 'ż', 'Ž', 'ž', 'ſ', 'ƒ', 'Ơ', 'ơ', 'Ư', 'ư', 'Ǎ', 'ǎ', 'Ǐ', 'ǐ', 'Ǒ', 'ǒ', 'Ǔ', 'ǔ', 'Ǖ', 'ǖ', 'Ǘ', 'ǘ', 'Ǚ', 'ǚ', 'Ǜ', 'ǜ', 'Ǻ', 'ǻ', 'Ǽ', 'ǽ', 'Ǿ', 'ǿ');
	\$b = array('A', 'A', 'A', 'A', 'A', 'A', 'AE', 'C', 'E', 'E', 'E', 'E', 'I', 'I', 'I', 'I', 'D', 'N', 'O', 'O', 'O', 'O', 'O', 'O', 'U', 'U', 'U', 'U', 'Y', 's', 'a', 'a', 'a', 'a', 'a', 'a', 'ae', 'c', 'e', 'e', 'e', 'e', 'i', 'i', 'i', 'i', 'n', 'o', 'o', 'o', 'o', 'o', 'o', 'u', 'u', 'u', 'u', 'y', 'y', 'A', 'a', 'A', 'a', 'A', 'a', 'C', 'c', 'C', 'c', 'C', 'c', 'C', 'c', 'D', 'd', 'D', 'd', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'E', 'e', 'G', 'g', 'G', 'g', 'G', 'g', 'G', 'g', 'H', 'h', 'H', 'h', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'I', 'i', 'IJ', 'ij', 'J', 'j', 'K', 'k', 'L', 'l', 'L', 'l', 'L', 'l', 'L', 'l', 'l', 'l', 'N', 'n', 'N', 'n', 'N', 'n', 'n', 'O', 'o', 'O', 'o', 'O', 'o', 'OE', 'oe', 'R', 'r', 'R', 'r', 'R', 'r', 'S', 's', 'S', 's', 'S', 's', 'S', 's', 'T', 't', 'T', 't', 'T', 't', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'W', 'w', 'Y', 'y', 'Y', 'Z', 'z', 'Z', 'z', 'Z', 'z', 's', 'f', 'O', 'o', 'U', 'u', 'A', 'a', 'I', 'i', 'O', 'o', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'U', 'u', 'A', 'a', 'AE', 'ae', 'O', 'o');
	\$s = str_replace(\$a, \$b, \$s);

	return \$s;
}
";
	}
}