
# MediaSort

MediaSort upload package for Laravel 4 using the Flysystem.

* [Installation](#installation)
* [Quick Start](#quickstart)
* [Overview](#overview)
* [Configuration](#configuration)
* [Interpolations](#interpolations)
* [Image Processing](#image-processing)
* [Examples](#examples)
* [Fetching Remote Images](#fetching-remote-images)
* [Advanced Usage](#advanced-usage)

## Installation

To get the latest version of Hashids simply require it in your `composer.json` file.

~~~
"torann/mediasort": "0.1.*@dev"
~~~

You'll then need to run `composer install` to download it and have the autoloader updated.

Once MediaSort is installed you need to register the service provider with the application. Open up `config/app.php` and find the `providers` key.

```php
'Torann\MediaSort\MediaSortServiceProvider'
```

> There is no need to add the Facade, the package will add it for you.


### Publish the configurations

Run this on the command line from the root of your project:

~~~
$ php artisan config:publish torann/mediasort
~~~

A configuration file will be publish to `config/media items.php`.


### Setup Laravel Flysystem

Follow [Graham Campbell](https://github.com/GrahamCampbell/Laravel-Flysystem/tree/1.0) directions for setting this up.

## Quickstart

In the document root of your application (most likely the public folder), create a folder named system and 
grant your application write permissions to it.

In your model:

```php
use Torann\MediaSort\ORM\MediaSortInterface;
use Torann\MediaSort\ORM\EloquentTrait;

class User extends Eloquent implements MediaSortInterface {

    use EloquentTrait;

    public function __construct(array $attributes = array()) 
    {
        $this->hasMediaFile('avatar', array(
            'styles' => array(
                'large' => '450x450#',
                'thumb' => '50x50#'
            )
        ));
    
        parent::__construct($attributes);
    }
    
    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        static::bootMediaSort();
    }
}
```

> Make sure that the `hasMediaFile()` method is called right before `parent::__construct()` of your model.

From the command line, use the migration generator:

```php
php artisa media:fasten users avatar
php artisan migrate
```

In your new view:

```php
{{ Form::open(['url' => action('UsersController@store'), 'method' => 'POST', 'files' => true]) }}
	{{ Form::file('avatar') and
    {{ Form::submit('save') }}   
{{ Form::close() }}
```
In your controller:

```php
public function store()
{
	// Create a new user, assigning the uploaded file field ('named avatar in the form')
    // to the 'avatar' property of the user model.   
    $user = User::create(['avatar' => Input::file('avatar')]);	
}
```

In your show view:
```php
<img src="{{ $user->avatar->url() }}" >
<img src="{{ $user->avatar->url('medium') }}" >
<img src="{{ $user->avatar->url('thumb') }}" >
```

To detach (reset) a file, simply call the clear() method of the media item attribute before saving:

```php
$user->avatar->clear();
$user->save();
```

This will ensure the the corresponding media item fields in the database table record are cleared and the current file is removed from storage.  The database table record itself will not be destroyed and can be used normally (or even assigned a new file upload) as needed.

## Overview
MeidaSort works by attaching file uploads to database table records.  This is done by defining media items inside the table's corresponding model and then assigning uploaded files (from your forms) as properties (named after the media items) on the model before saving it.  In essence, this allows uploaded files to be treated just like any other property on the model; MeidaSort will abstract away all of the file processing, storage, etc so you can focus on the rest of your project without having to worry about where your files are at or how to retrieve them.  

A model can have multiple media items defined (avatar, photo, foo, etc) and in turn each media item can have multiple sizes (styles) defined.  When an image or file is uploaded, MeidaSort will handle all the file processing (moving, resizing, etc) and provide a media item object (as a model property) with methods for working with the uploaded file.  To accomplish this, four fields (named after the media item) will need to be created (via MeidaSort:fasten or manually) in the corresponding table for any model containing a file media item.  For example, for a media item named 'avatar' defined inside a model named 'User', the following fields would need to be added to the 'users' table:

*   (string) avatar_file_name
*   (integer) avatar_file_size
*   (string) avatar_content_type
*   (timestamp) avatar_updated_at

Inside your table migration file, something like this should suffice:

```php
$table->string("avatar_file_name")->nullable();
$table->integer("avatar_file_size")->nullable();
$table->string("avatar_content_type")->nullable();
$table->timestamp("avatar_updated_at")->nullable();
```

## Configuration
Configuration is available on both a per media item basis or globally through the configuration file settings.  MeidaSort is very flexible about how it processes configuration; global configuration options can be overwritten on a per media item basis so that you can easily cascade settings you would like to have on all media items while still having the freedom to customize an individual media item's configuration.  To get started, the first thing you'll probably want to do is publish the default configuration options to your app/config directory. 

 ```php
  php artisan config:publish torann/mediasort
``` 
Having done this, you should now be able to configure MeidaSort however you see fit without fear of future updates overriding your configuration files. 

### MeidaSort-Configuration
The following configuration settings apply to MeidaSort in general.

*   **image_processing**: The underlying image processing library being used.  Defaults to `\\Imagine\\Gd\\Imagine` but can also be set to `\\Imagine\\Imagick\\Imagine` or `\\Imagine\\Gmagick\\Imagine`.
*   **default_url**: The default file returned when no file upload is present for a record.
*   **styles**: An array of image sizes defined for the file media item.  MeidaSort will attempt to use to format the file upload
    into the defined style.
*   **keep_old_files**: Set this to true in order to prevent older file uploads from being deleted from the file system when a record is updated.
*   **preserve_files**: Set this to true in order to prevent a media item's file uploads from being deleted from the file system when an the media item object is destroyed (media item's are destroyed when their corresponding models are deleted/destroyed from the database).

## Interpolations
With MeidaSort, uploaded files are accessed by configuring/defining path, url, and default_url strings which point to you uploaded file assets.  This is done via string interpolations.  Currently, the following interpolations are available for use:

*   **:media** - The name of the file media as declared in the `hasMediaFile` function, e.g 'avatar'.
*   **:class**  - The classname of the model containing the file media item, e.g User.  MeidaSort can handle namespacing of classes.
*   **:basename** - The basename portion of the media file, e.g 'file' for file.jpg.
*   **:extension** - The file extension type of the media file, e.g '.jpg'
*   **:filename** - The name of the uploaded file, e.g 'some_file.jpg'
*   **:id** - The id of the corresponding database record for the uploaded file.
*   **:style** - The resizing style of the file (images only), e.g 'thumbnail' or 'original'.
*   **:url** - The url string pointing to your uploaded file.  This interpolation is actually an interpolation itself.  It can be composed of any of the above interpolations (except itself). 

## Image-Processing
MeidaSort makes use of the [Imagine Image](https://packagist.org/packages/imagine/imagine) library for all image processing.  Out of the box, the following image processing patterns/directives will be recognized when defining MeidaSort styles:

*   **width**: A style that defines a width only (landscape).  Height will be automagically selected to preserve aspect ratio.  This works well for resizing
    images for display on mobile devices, etc.
*   **xheight**: A style that defines a heigh only (portrait).  Width automagically selected to preserve aspect ratio.
*   **widthxheight#**: Resize then crop.
*   **widthxheight!**: Resize by exacty width and height.  Width and height emphatically given, original aspect ratio will be ignored.
*   **widthxheight**: Auto determine both width and height when resizing.  This will resize as close as possible to the given dimensions while still preserving the original aspect ratio.

To create styles for a media item, simply define them (you may use any style name you like: foo, bar, baz, etc) inside the media item's styles array using a combination of the directives defined above:

````php
'styles' => array(
    'thumbnail' => '50x50',
    'large' => '150x150',
    'landscape' => '150',
    'portrait' => 'portrait' => 'x150',
    'foo' => '75x75',
    'fooCropped' => '75x75#'
)
````

For more customized image processing you may also pass a [callable](http://php.net/manual/en/language.types.callable.php) type as the value for a given style definition.  MeidaSort will automatically inject in the uploaded file object instance as well as the Imagine\Image\ImagineInterface object instance for you to work with.  When you're done with your processing, simply return an instance of Imagine\Image\ImageInterface from the callable.  Using a callable for a style definition provides an incredibly amount of flexibilty when it comes to image processing. As an example of this, let's create a watermarked image using a closure (we'll do a smidge of image processing with Imagine):

 ````php
 'styles' => array(
    'watermarked' => function($file, $imagine) 
    {
        $watermark = $imagine->open('/path/to/images/watermark.png');   // Create an instance of ImageInterface for the watermark image.
        $image     = $imagine->open($file->getRealPath());              // Create an instance of ImageInterface for the uploaded image.
        $size      = $image->getSize();                                 // Get the size of the uploaded image.
        $watermarkSize = $watermark->getSize();                         // Get the size of the watermark image.
        
        // Calculate the placement of the watermark (we're aiming for the bottom right corner here).
        $bottomRight = new Imagine\Image\Point($size->getWidth() - $watermarkSize->getWidth(), $size->getHeight() - $watermarkSize->getHeight());
        
        // Paste the watermark onto the image.
        $image->paste($watermark, $bottomRight);

        // Return the Imagine\Image\ImageInterface instance.
        return $image;
    }
)
```` 

## Examples
Create a media item named 'picture', with both thumbnail (100x100) and large (300x300) styles, using custom url and default_url configurations.

```php
public function __construct(array $attributes = array()) 
{
    $this->hasMediaFile('picture', array(
        'styles' =>  array(
            'thumbnail' => '100x100',
            'large' => '300x300'
        ),
        'url' => '/system/:media/:style/:filename',
        'default_url' => '/:media/:style/missing.jpg'
    ));

    parent::__construct($attributes);
}
```

Create a media item named 'picture', with both thumbnail (100x100) and large (300x300) styles, using custom url and default_url configurations, with the keep_old_files flag set to true (so that older file uploads aren't deleted from the file system) and image cropping turned on.

```php
public function __construct(array $attributes = array()) 
{
    $this->hasMediaFile('picture',  array(
        'styles' =>  array(
            'thumbnail' => '100x100#',
            'large' => '300x300#'
        ),
        'url' => '/system/:media/:style/:filename',
        'default_url' => '/:media/:style/missing.jpg',
        'keep_old_files' => true
    ));

    parent::__construct($attributes);
}
```

MeidaSort makes it easy to manage multiple file uploads as well.  In MeidaSort, media items (and the uploaded file objects they represent) are tied directly to database records.  Because of this, processing multiple file uploads is simply a matter of defining the correct Eloquent relationships between models.  

As an example of how this works, let's assume that we have a system where users need to have multiple profile pictures (let's say 3).  Also, let's assume that users need to have the ability to upload all three of their photos from the user creation form. To do this, we'll need two tables (users and profile_pictures) and we'll need to set their relationships such that profile pictures belong to a user and a user has many profile pictures.  By doing this, uploaded images can be attached to the ProfilePicture model and instances of the User model can in turn access the uploaded files via their hasMany relationship to the ProfilePicture model.  Here's what this looks like:

In models/user.php:

```php
// A user has many profile pictures.
public function profilePictures(){
    return $this->hasMany('ProfilePicture');
}
```

In models/ProfilePicture.php:
```php
public function __construct(array $attributes = array()) 
{
    // Profile pictures have an attached file (we'll call it photo).
    $this->hasMediaFile('photo',  array(
        'styles' =>  array(
            'thumbnail' => '100x100#'
        )
    ));

    parent::__construct($attributes);
}

// A profile picture belongs to a user.
public function user(){
    return $this->belongsTo('User');
}
```

In the user create view:

```php
{{ Form::open(['url' => '/users', 'method' => 'post', 'files' => true]) }}
    {{ Form::text('first_name') }}
    {{ Form::text('last_name') }}
    {{ Form::file('photos[]') }}
    {{ Form::file('photos[]') }}
    {{ Form::file('photos[]') }}
{{ Form::close() }}
```

In controllers/UsersController.php
```php
public function store()
{
    // Create the new user
    $user = new User(Input::get());
    $user->save();

    // Loop through each of the uploaded files:
    // 1. Create a new ProfilePicture instance. 
    // 2. Attach the file to the new instance (MeidaSort will process it once it's saved).
    // 3. Attach the ProfilePicture instance to the user and save it.
    foreach(Input::file('photos') as $photo)
    {
        $profilePicture = new ProfilePicture();             // (1)
        $profilePicture->photo = $photo;                    // (2)
        $user->profilePictures()->save($profilePicture);    // (3)
    }
}
```

Displaying uploaded files is also easy.  When working with a model instance, each media item can be accessed as a property on the model.  a media item object provides methods for seamlessly accessing the properties, paths, and urls of the underlying uploaded file object.  As an example, for a media item named 'photo', the path(), url(), createdAt(), contentType(), size(), and originalFilename() methods would be available on the model to which the file was attached.  Continuing our example from above, we can loop through a user's profile pictures display each of the uploaded files like this:

```html
// Display a resized thumbnail style image belonging to a user record:
<img src="{{ asset($profilePicture->photo->url('thumbnail')) }}">

// Display the original image style (unmodified image):
<img src="{{  asset($profilePicture->photo->url('original')) }}">

// This also displays the unmodified original image (unless the :default_style interpolation has been set to a different style):
<img src="{{  asset($profilePicture->photo->url()) }}">
```

We can also retrieve the file path, size, original filename, etc of an uploaded file:
```php
$profilePicture->photo->path('thumbnail');
$profilePicture->photo->size();
$profilePicture->photo->originalFilename();
```

## Fetching Remote Images
Remote images can be fetched by assigning an absolute URL to a media item property that's defined on a model: 

```php 
$profilePicture->photo = "http://foo.com/bar.jpg"; 
```

This is very useful when working with third party API's such as Facebook, Twitter, etc.  Note that this feature requires that the CURL extension is included as part of your PHP installation.

## Advanced-Usage
When working with media items, there may come a point where you wish to do things outside of the normal workflow.  For example, suppose you wish to clear out a media item (empty the media item fields in the underlying table record and remove the uploaded file from storage) without having to destroy the record itself.  In situations where you wish to clear the uploaded file from storage without saving the record, you can use the media's destroy method:

```php
// Remove all of the media's uploaded files and empty the media attributes on the model:
$profilePicture->photo->destroy();

// For finer grained control, you can remove thumbnail files only (media attributes in the model will not be emptied).
$profilePicture->photo->destroy(['thumbnail']);
```

You may also reprocess uploaded images on a media item by calling the reprocess() command (this is very useful for adding new styles to an existing media type where records have already been uploaded).

```php
// Programmatically reprocess a media's uploaded images:
$profilePicture->photo->reprocess();
```

This may also be achieved via a call to the MeidaSort:refresh command.

Reprocess all media items for the ProfilePicture model:
php artisan MeidaSort:refresh ProfilePicture

Reprocess only the photo media on the ProfilePicture model:
php artisan MeidaSort:refresh TestPhoto --media="photo"

Reprocess a list of media items on the ProfilePicture model:
php artisan MeidaSort:refresh TestPhoto --media="foo, bar, baz, etc"
