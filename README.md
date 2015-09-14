# Eloquent Encryption/Decryption for Laravel 5

Automatically encrypt and decrypt Laravel 5 Eloquent values

This is Darren Taylor's Laravel 4 "elocrypt" package, ported to Laravel 5.  I have made the
following additions/changes:

* Do the encryption at the setAttribute/getAttributeFromArray layer rather than __set and
  __get as that's more appropriate for Laravel 5 with the new casts features.  So, for example,
  you can add a field to $casts and also to $encryptable so that an array can be cast to a JSON
  string first, and then encrypted.  It should also work for Lumen.

* Prefix all encrypted values with a tag string (currently hard coded as `__ELOCRYPT__:` )
  so that plain text data can be detected and handled correctly.  The task of writing a script
  to traverse your existing database and update all plain text data to encrypted data is left
  to the reader.
  
The original Laravel 4 package is here: https://github.com/dtisgodsson/elocrypt

# Installation

This package can be installed via Composer by adding the following to your composer.json file:

```
    "require": {
        "delatbabel/elocryptfive": "dev-master"
    }
```

You must then run the following command:

```
    composer update
```

# Usage

Simply reference the Elocrypt trait in any Eloquent Model you wish to apply encryption to and 
then define an `$encryptable` array on that model containing a list of the attributes you wish
to Encrypt.

For example:

```php
    use Delatbabel\Elocrypt\Elocrypt;

    class User extends Eloquent {

        use Elocrypt;

        public $encryptable = ['first_name', 'last_name', 'address_line_1', 'postcode'];
    }
```

You can combine `$casts` and `$encryptable` to store encrypted arrays.  An array will first be
converted to JSON and then encrypted.  For example:

```php
    use Delatbabel\Elocrypt\Elocrypt;

    class User extends Eloquent {

        use Elocrypt;

        public $casts = ['extended_data' => 'array'];

        public $encryptable = ['extended_data'];
    }
```

# How it Works?

By including the Elocrypt trait, the setAttribute() and getAttributeFromArray() methods provided
by Eloquent are overridden to include an additional step. This additional step simply checks
whether the attribute being set or get is included in the "encryptable" array on the model,
and either encrypts/decrypts it accordingly.

## Summary of Methods in Illuminate\Database\Eloquent\Model

This surveys the major methods in the Laravel Model class as of
Laravel v 5.1.12 and checks to see how those models set attributes
and hence how they are affected by this trait.

* constructor -- calls fill()
* fill() -- calls setAttribute() which has been extended to encrypt the data.
* hydrate() -- TBD
* create() -- calls constructor and hence fill()
* firstOrCreate -- calls constructor
* firstOrNew -- calls constructor
* updateOrCreate -- calls fill()
* update() -- calls fill()
* toArray() -- calls attributesToArray()
* jsonSerialize() -- calls toArray()
* toJson() -- calls toArray()
* attributesToArray() -- calls getArrayableAttributes().
* getAttribute -- calls getAttributeValue()
* getAttributeValue -- calls getAttributeFromArray()
* getAttributeFromArray -- calls getArrayableAttributes()
* getArrayableAttributes -- has been extended here to decrypt the data.
* setAttribute -- has been extended here to encrypt the data.
* getAttributes -- has been extended here to decrypt the data.

# Keys and IVs

The key and encryption algorithm used are as per the Laravel Encrypter service, and defined in config/app.php
as follows:

```php
    'key' => env('APP_KEY', 'SomeRandomString'),

    'cipher' => 'AES-256-CBC',
```

I recommend generating a random 32 character string for the encryption key, and using AES-256-CBC as the cipher
for encrypting data.  If you are encrypting long data strings then AES-256-CBC-HMAC-SHA1 will be better.

The IV for encryption is randomly generated.
