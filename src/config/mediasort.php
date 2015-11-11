<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | Local Root Path
    |--------------------------------------------------------------------------
    |
    | The path option is the location where your local files will be stored
    | at on disk. This is only used for local storage. If set to null MediaSort
    | will use the "root" setting in the filesystem config.
    |
    */

    'local_root' => '{laravel_root}/public',

    /*
    |--------------------------------------------------------------------------
    | File Url
    |--------------------------------------------------------------------------
    |
    | The url (relative to your project public directory) where files will be stored.
    | It is composed of 'interpolations' that will be replaced their
    | corresponding values during runtime.  It's unique in that it functions as both
    | a configuration option and an interpolation.
    |
    */

    'url' => '/system/{class}/{media}/{id}/{style}/{filename}',

    /*
    |--------------------------------------------------------------------------
    | Prefix URL
    |--------------------------------------------------------------------------
    |
    | Prefix URL used when displaying a media item. This is helpful for
    | cloud storage or a subdomain location. If left blank the URL will be
    | the same as the requesting domain.
    |
    | e.g '//cdn.example.com' will produce
    |     '//cdn.example.com/uploads/me.jpg'.
    |
    | e.g '//foo.s3.amazonaws.com' will produce
    |     '//foo.s3.amazonaws.com/uploads/me.jpg'.
    |
    */

    'prefix_url' => '',

    /*
    |--------------------------------------------------------------------------
    | Default Url
    |--------------------------------------------------------------------------
    |
    | The url (relative to your project document root) containing a default image
    | that will be used for attachments that don't currently have an uploaded image
    | attached to them.
    |
    */

    'default_url' => '{app_url}/images/{media}/{style}/missing.png',

    /*
    |--------------------------------------------------------------------------
    | File visibility
    |--------------------------------------------------------------------------
    |
    | Here you may configure the visibility of the newly uploaded file. This
    | primarily pertains to cloud based file storage. Options are 'public'
    | or 'private'
    |
    */

    'visibility' => 'public',

    /*
    |--------------------------------------------------------------------------
    | Image Processing Library
    |--------------------------------------------------------------------------
    |
    | The default library used for image processing.  Can be one of the following:
    | \\Imagine\\Gd\\Imagine, \\Imagine\\Imagick\\Imagine,
    | or \\Imagine\\Gmagick\\Imagine.
    |
    */

    'image_processor' => '\\Imagine\\Gd\\Imagine',

    /*
    |--------------------------------------------------------------------------
    | Default Style
    |--------------------------------------------------------------------------
    |
    | The default style returned from the LatchOn file location helper methods.
    | An unaltered version of uploaded file is always stored within the 'original'
    | style, however the default_style can be set to point to any of the defined
    | syles within the styles array.
    |
    */

    'default_style' => 'original',

    /*
    |--------------------------------------------------------------------------
    | Styles
    |--------------------------------------------------------------------------
    |
    | An array of image sizes defined for the file attachment.
    | LatchOn will attempt to format the file upload into the defined style.
    |
    */

    'styles' => [],

    /*
    |--------------------------------------------------------------------------
    | Keep Old Files Flag
    |--------------------------------------------------------------------------
    |
    | Set this to true in order to prevent older file uploads from being deleted
    | from storage when a record is updated with a new upload.
    |
    */

    'keep_old_files' => false,

    /*
    |--------------------------------------------------------------------------
    | Preserve Files Flag
    |--------------------------------------------------------------------------
    |
    | Set this to true in order to prevent file uploads from being deleted
    | from the file system when an attachment is destroyed.  Essentially this
    | ensures the preservation of uploads event after their corresponding database
    | records have been removed.
    |
    */

    'preserve_files' => false,

    /*
    |--------------------------------------------------------------------------
    | Primary key for the model
    |--------------------------------------------------------------------------
    |
    | Sometimes a slug will be the primary key, but we'll not want to use it
    | in the media URL. When set to `null` it will use the model's primary key.
    |
    */

    'model_primary_key' => null,

);