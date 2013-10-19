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

/**
 * Key for previous version of file, to be deleted
 * @var        string
 */
protected \$key_marked_for_deletion;

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

        $this->addSanitizeFilenameMethod($script);

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
 * @param S3ObjectManager an S3ObjectManager instance
 * @param string|stream|Guzzle\Http\EntityBody the path to the file to upload; accepts any valid argument for the 'Body' parameter passed to the S3Client::putObject method.
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

    protected function addSanitizeFilenameMethod(&$script)
    {
        $script .= "
/**
 * Convenience method to sanitize a file for use as a key in AWS S3.
 */
 public function sanitize(\$filename)
 {
    \$s = trim(\$filename);
    \$s = preg_replace('/^[.]*/', '', \$s); // remove leading periods
    \$s = preg_replace('/[.]*\$/', '', \$s); // remove trailing periods
    \$s = preg_replace('/\.[.]+/', '.', \$s); // remove any consecutive periods

    // replace dodgy characters
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
