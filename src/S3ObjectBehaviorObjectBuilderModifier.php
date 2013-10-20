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

    public function objectAttributes($builder)
    {
        $objectClassname = $builder->getStubObjectBuilder()->getClassname();

        return "
/**
 * Path to upload file
 * @var        string
 */
protected \$pathname;

/**
 * S3ObjectManager instance associated with this object
 * @var        S3ObjectManager
 */
protected \$s3object_manager;

";
    }

    public function objectMethods($builder)
    {
        $this->setBuilder($builder);
        $script = '';

        $this->addGetServerSideEncryptionMethod($script);
        $this->addGetReducedRedundancyStorageMethod($script);

        $this->addGetPresignedUrlMethod($script);
        $this->addUploadMethod($script);
        $this->addDeleteFileMethod($script);
        $this->addGetS3ObjectManagerMethod($script);
        $this->addSetS3ObjectManagerMethod($script);
        $this->addGetPathnameMethod($script);
        $this->addSetPathnameMethod($script);
        $this->addFileExistsMethod($script);
        $this->addGenerateKeyMethod($script);

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

    protected function addGenerateKeyMethod(&$script)
    {
        $script .= "
/**
 * Returns a key for the associated file on AWS S3.
 *
 * @param S3ObjectManager a S3 object manager
 *
 * @return string
 * @throws InvalidArgumentException if the request is not associated with this client object
 */
public function generateKey(\\S3ObjectManager \$manager = null)
{
    if (\$manager == null) {
        \$manager = \$this->getS3ObjectManager();
    }

    if (!\$manager) {
        throw new \\RuntimeException('No S3ObjectManager instance found.');
    }

    return \$manager->generateKey(\$this);
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
public function getPresignedUrl(\$expires = \"+5 minutes\", S3ObjectManager \$manager = null)
{
    if (\$manager == null) {
        \$manager = \$this->getS3ObjectManager();
    }

    if (!\$manager) {
        throw new \\RuntimeException('No S3ObjectManager instance found.');
    }

    return \$manager->getPresignedUrl(\$this, \$expires);
}
";
    }

    protected function addUploadMethod(&$script)
    {
        $script .= "
/**
 * Uploads a file to S3.
 *
 * @param string|stream|Guzzle\Http\EntityBody the path to the file to upload; accepts any valid argument for the 'Body' parameter passed to the S3Client::putObject method.
 * @param S3ObjectManager an S3ObjectManager instance
 *
 * @return Guzzle\Service\Resource\Model reponse from S3Client request via Guzzle
 * @throws S3Exception if the request fails
 */
public function upload(\$file, \\S3ObjectManager \$manager = null)
{
    if (\$manager == null) {
        \$manager = \$this->getS3ObjectManager();
    }

    if (!\$manager) {
        throw new \\RuntimeException('No S3ObjectManager instance found.');
    }

    return \$manager->uploadFile(\$this, \$file);
}
";
    }

    protected function addDeleteFileMethod(&$script)
    {
        $script .= "
/**
 * Deletes the associated file on S3.
 *
 * @param S3ObjectManager an S3ObjectManager instance
 *
 * @return Guzzle\Service\Resource\Model reponse from S3Client request via Guzzle
 * @throws S3Exception if the request fails
 */
public function deleteFile(\\S3ObjectManager \$manager = null)
{
    if (\$manager == null) {
        \$manager = \$this->getS3ObjectManager();
    }

    if (!\$manager) {
        throw new \\RuntimeException('No S3ObjectManager instance found.');
    }

    return \$manager->deleteFile(\$this);
}
";
    }

    protected function addGetS3ObjectManagerMethod(&$script)
    {
        $script .= "
/**
 * Returns the S3ObjectManager instance associated with this object, if there is one.
 *
 * @return Guzzle\Service\Resource\Model reponse from S3Client request via Guzzle
 */
public function getS3ObjectManager()
{
    return \$this->s3object_manager;
}
";
    }

    protected function addSetS3ObjectManagerMethod(&$script)
    {
        $script .= "
/**
 * Sets the S3ObjectManager instance associated with this object.
 *
 */
public function setS3ObjectManager(S3ObjectManager \$manager)
{
    \$this->s3object_manager = \$manager;
}
";
    }

    protected function addGetPathnameMethod(&$script)
    {
        $script .= "
/**
 * Returns the local path to the file associated with this object, if there is one.
 *
 */
public function getPathname()
{
    return \$this->pathname;
}
";
    }

    protected function addSetPathnameMethod(&$script)
    {
        $script .= "
/**
 * Sets the local path to the file associated with this object.
 *
 */
public function setPathname(\$pathname)
{
    \$this->pathname = \$pathname;
}
";
    }

    public function preSave($builder)
    {
        $peerClassname = $builder->getStubPeerBuilder()->getClassname();

        return "\$generated_key = \$this->generateKey();

if (\$generated_key != \$this->getKey() && \$this->getS3ObjectManager()) {
    \$this->deleteFile();
    \$this->setKey(\$generated_key);
}
";
    }

    public function postSave($builder)
    {
        $peerClassname = $builder->getStubPeerBuilder()->getClassname();

        return "if (\$this->getPathname() && \$this->getS3ObjectManager()) {
    \$this->upload(\$this->getPathname());
}
";
    }

    public function postDelete($builder)
    {
        $peerClassname = $builder->getStubPeerBuilder()->getClassname();

        return "if (\$this->getS3ObjectManager()) {
    \$this->deleteFile();
}
";
    }

    protected function addFileExistsMethod(&$script)
    {
        $script .= "
/**
 * Checks whether the file associated with this document exists on AWS S3.
 *
 * @param S3ObjectManager an S3ObjectManager instance
 */
 public function fileExists(\\S3ObjectManager \$manager = null)
 {
    if (\$manager == null) {
        \$manager = \$this->getS3ObjectManager();
    }

    if (!\$manager) {
        throw new \\RuntimeException('No S3ObjectManager instance found.');
    }

    return \$manager->fileExists(\$this);

}";
    }
}
