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

    protected function getElocryptPrefix() {
        return '__ELOCRYPT__:';
    }

    /**
     * Determine whether an attribute should be encrypted.
     *
     * @param  string  $key
     * @return bool
     */
    protected function hasEncrypt($key)
    {
        return in_array($key, $this->encryptable);
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
                $value = $this->getElocryptPrefix() . Crypt::encrypt($value);
            } catch (EncryptException $e) {
                // Reset
                $value = $originalValue;
            }
        }

        $this->attributes[$key] = $value;
    }

    /**
     * Decrypt an attribute if required
     *
     * @param string $key
     * @param mixed $value
     * @return mixed
     */
    protected function doDecryptAttribute($key, $value)
    {
        // Does it need to be decrypted?
        if (! $this->hasEncrypt($key)) {
            return $value;
        }

        // We now have the string version of the value stored in $value.
        // Decrypt it, removing the prefix that we added when we encrypted it.
        $originalValue = $value;
        $elocrypt_prefix = $this->getElocryptPrefix();

        if (strpos($value, $elocrypt_prefix) !== 0) {
            // This string has not been prefixed and so we assume that
            // it has not been encrypted.
            return $originalValue;
        }

        $value = substr($value, strlen($elocrypt_prefix));
        try {
            $value = Crypt::decrypt($value);
        } catch (DecryptException $e) {
            // Reset
            $value = $originalValue;
        }

        return $value;
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
        return $this->doDecryptAttribute($key, $value);
    }

    /**
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        $attributes = parent::getArrayableItems($this->attributes);

        // Decrypt them all as required
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->doDecryptAttribute($key, $value);
        }

        return $attributes;
    }

    /**
     * Get all of the current attributes on the model.
     *
     * @return array
     */
    public function getAttributes()
    {
        $attributes = parent::getAttributes();

        // Decrypt them all as required
        foreach ($attributes as $key => $value) {
            $attributes[$key] = $this->doDecryptAttribute($key, $value);
        }

        return $attributes;
    }
}
