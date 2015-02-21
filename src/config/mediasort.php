<?php

return array(

    /*
    |--------------------------------------------------------------------------
    | File Url
    |--------------------------------------------------------------------------
    |
    | The url (relative to your project document root) where files will be stored.
    | It is composed of 'interpolations' that will be replaced their
    | corresponding values during runtime.  It's unique in that it functions as both
    | a configuration option and an interpolation.
    |
    */

    'url' => '/system/:class/:media/:id/:style/:filename',

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
    | Default Url
    |--------------------------------------------------------------------------
    |
    | The url (relative to your project document root) containing a default image
    | that will be used for attachments that don't currently have an uploaded image
    | attached to them.
    |
    */

    'default_url' => '/images/:media/:style/missing.png',

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

    'styles' => array(),

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

    'preserve_files' => false

);