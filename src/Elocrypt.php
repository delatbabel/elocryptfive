<?php
/**
 * Trait Elocrypt
 */
namespace Delatbabel\Elocrypt;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Encryption\EncryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * Trait Elocrypt
 *
 * Automatically encrypt and decrypt Laravel 5 Eloquent values
 *
 * ### Example
 *
 * <code>
 *   use Delatbabel\Elocrypt\Elocrypt;
 *
 *   class User extends Eloquent {
 *
 *       use Elocrypt;
 *
 *       protected $encrypts = [
 *           'address_line_1', 'first_name', 'last_name', 'postcode'
 *       ];
 *   }
 * </code>
 *
 * ### Summary of Methods in Illuminate\Database\Eloquent\Model
 *
 * This surveys the major methods in the Laravel Model class as of
 * Laravel v 5.1.12 and checks to see how those models set attributes
 * and hence how they are affected by this trait.
 *
 * * __construct -- calls fill()
 * * fill() -- calls setAttribute() which has been overridden.
 * * hydrate() -- TBD
 * * create() -- calls constructor and hence fill()
 * * firstOrCreate -- calls constructor
 * * firstOrNew -- calls constructor
 * * updateOrCreate -- calls fill()
 * * update() -- calls fill()
 * * toArray() -- calls attributesToArray()
 * * jsonSerialize() -- calls toArray()
 * * toJson() -- calls toArray()
 * * attributesToArray() -- has been over-ridden here.
 * * getAttribute -- calls getAttributeValue()
 * * getAttributeValue -- calls getAttributeFromArray()
 * * getAttributeFromArray -- calls getArrayableAttributes
 * * getArrayableAttributes -- has been over-ridden here.
 * * setAttribute -- has been over-ridden here.
 * * getAttributes -- has been over-ridden here.
 *
 * @see Illuminate\Support\Facades\Crypt
 * @see Illuminate\Contracts\Encryption\Encrypter
 * @see Illuminate\Encryption\Encrypter
 * @link http://laravel.com/docs/5.1/eloquent
 */
trait Elocrypt
{
    /**
     * The prefix used to determine if a string is encrypted.
     *
     * @var string
     */
    private static $ELOCRYPT_PREFIX = '__ELOCRYPT__:';

    /**
     * Determine whether an attribute should be encrypted.
     *
     * @param  string  $key
     * @return bool
     */
    protected function shouldEncrypt($key)
    {
        $encrypt = isset($this->encrypts) ? $this->encrypts : $this->encryptable;

        return in_array($key, $encrypt);
    }

    /**
     * Determine whether a string has already been encrypted.
     *
     * @param  mixed  $value
     * @return bool
     */
    protected function isEncrypted($value)
    {
        return strpos((string)$value, self::$ELOCRYPT_PREFIX) === 0;
    }

    /**
     * Set a given attribute on the model.
     *
     * @param  string  $key
     * @param  mixed   $value
     * @return void
     */
    public function setAttribute($key, $value)
    {
        parent::setAttribute($key, $value);

        $this->doEncryptAttribute($key);
    }

    /**
     * Encrypt a stored attribute.
     *
     * @param  string $key
     * @return void
     */
    protected function doEncryptAttribute($key)
    {
        if ($this->shouldEncrypt($key) && ! $this->isEncrypted($this->attributes[$key])) {
            try {
                $this->attributes[$key] = self::$ELOCRYPT_PREFIX . Crypt::encrypt($this->attributes[$key]);
            } catch (EncryptException $e) {}
        }
    }

    /**
     * Decrypt an attribute if required.
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function doDecryptAttribute($key, $value)
    {
        if ($this->shouldEncrypt($key) && $this->isEncrypted($value)) {
            try {
                return Crypt::decrypt(str_replace(self::$ELOCRYPT_PREFIX, '', $value));
            } catch (DecryptException $e) {}
        }

        return $value;
    }

    /**
     * Decrypt each attribute in the array as required.
     *
     * @param  array $attributes
     * @return array
     */
    public function doDecryptAttributes($attributes)
    {
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->doDecryptAttribute($key, $value);
        }

        return $attributes;
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        return $this->doDecryptAttribute($key, parent::getAttributeFromArray($key));
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        return $this->doDecryptAttributes(parent::getArrayableAttributes());
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->doDecryptAttributes(parent::getAttributes());
    }
}
