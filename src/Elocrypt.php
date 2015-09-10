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
 *       public $encryptable = [
 *           'first_name',
 *           'last_name',
 *           'address_line_1',
 *           'postcode'
 *       ];
 *   }
 * </code>
 *
 * @see  ...
 * @link ...
 */
trait Elocrypt {

    // TRAITS CAN'T HAVE CONSTANTS! GAH!
    // const CRYPT_TAG = '__ELOCRYPT__:';

    protected $elocrypt_prefix = '__ELOCRYPT__:';


    /**
     * Determine whether an attribute should be encrypted.
     *
     * @param  string  $key
     * @return bool
     */
    protected function hasEncrypt($key)
    {
        return array_key_exists($key, $this->encryptable);
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
        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // the model, such as "json_encoding" an listing of data for storage.
        if ($this->hasSetMutator($key)) {
            $method = 'set'.Str::studly($key).'Attribute';

            return $this->{$method}($value);
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        elseif (in_array($key, $this->getDates()) && $value) {
            $value = $this->fromDateTime($value);
        }

        if ($this->isJsonCastable($key) && ! is_null($value)) {
            $value = json_encode($value);
        }

        // We now have the string version of the value stored in $value.
        // Does it need to be encrypted?  If so encrypt it, and prefix
        // the string with a tag so we know it's been encrypted.
        if ($this->hasEncrypt($key)) {
            $originalValue = $value;
            try {
                $value = $this->elocrypt_prefix . Crypt::encrypt($value);
            } catch (EncryptException $e) {
                // Reset
                $value = $originalValue;
            }
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param  string  $key
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        // This will call the base Laravel/Eloquent function to give
        // us the raw value taken from the attributes array.
        $value = parent::getAttributeFromArray($key);

        // Does it need to be decrypted?
        if (! $this->hasEncrypt($key)) {
            return $value;
        }

        // We now have the string version of the value stored in $value.
        // Decrypt it, removing the prefix that we added when we encrypted it.
        $originalValue = $value;

        if (strpos($value, $this->elocrypt_prefix) !== 0) {
            // This string has not been prefixed and so we assume that
            // it has not been encrypted.
            return $originalValue;
        }

        $value = substr($value, strlen($this->elocrypt_prefix));
        try {
            $value = $this->elocrypt_prefix . Crypt::decrypt($value);
        } catch (DecryptException $e) {
            // Reset
            $value = $originalValue;
        }

        return $value;
    }
}
