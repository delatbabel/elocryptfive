<?php
/**
 * Class DummyModel
 *
 * @author del
 */

use Illuminate\Encryption\Encrypter;

/**
 * Class DummyModel
 *
 * Dummy model class to be used for testing.
 */
class DummyModel extends BaseModel
{
    use \Delatbabel\Elocrypt\Elocrypt;

    /** @var array list of attributes to encrypt */
    protected $encrypts = ['encrypt_me'];

    /** @var  Encrypter */
    protected $encrypter;

    public function __construct(array $attributes)
    {
        $this->encrypter = new Encrypter('088409730f085dd15e8e3a7d429dd185', 'AES-256-CBC');
        parent::__construct($attributes);
    }

    /**
     * Return the encrypted value of an attribute's value.
     *
     * This has been exposed as a public method because it is of some
     * use when searching.
     *
     * @param string $value
     * @return string
     */
    public function encryptedAttribute($value)
    {
        return self::$ELOCRYPT_PREFIX . $this->encrypter->encrypt($value);
    }

    /**
     * Return the decrypted value of an attribute's encrypted value.
     *
     * This has been exposed as a public method because it is of some
     * use when searching.
     *
     * @param string $value
     * @return string
     */
    public function decryptedAttribute($value)
    {
        return $this->encrypter->decrypt(str_replace(self::$ELOCRYPT_PREFIX, '', $value));
    }
}
