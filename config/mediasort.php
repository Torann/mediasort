<?php

return [

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
    | Waiting Url
    |--------------------------------------------------------------------------
    |
    | The url (relative to your project document root) containing a waiting
    | image that will be used for attachments that are flagged as waiting to be
    | processed.
    */

    'waiting_url' => '{app_url}/images/{media}/{style}/waiting.gif',

    /*
    |--------------------------------------------------------------------------
    | Loading Url
    |--------------------------------------------------------------------------
    |
    | The url (relative to your project document root) containing a loading image
    | that will be used for attachments that are flagged as being in processed.
    */

    'loading_url' => '{app_url}/images/{media}/{style}/loading.gif',

    /*
    |--------------------------------------------------------------------------
    | Failed Url
    |--------------------------------------------------------------------------
    |
    | The url (relative to your project document root) containing a failed
    | image that will be used for attachments that failed processing.
    */

    'failed_url' => '{app_url}/images/{media}/{style}/failed.gif',

    /*
    |--------------------------------------------------------------------------
    | Queueable
    |--------------------------------------------------------------------------
    |
    | This indicates if the attachement is queueable. If queuable the model
    | needs to include the column to indicate this, the format for such column
    | would be `<style>_queue_state`. The state values can be found as constants
    | on \Torann\MediaSort\Manager
    |
    | Queue States:
    |
    | - Manager::QUEUE_DONE = the attachment has been processed
    | - Manager::QUEUE_WAITING = the attachment in queue for processing
    | - Manager::QUEUE_WORKING = the attachment is being processed
    | - Manager::QUEUE_FAILED = the attachment failed processing
    |
    */

    'queueable' => false,

    /*
    |--------------------------------------------------------------------------
    | File Queue Path
    |--------------------------------------------------------------------------
    |
    | Used to temporarily store large files for queuing. It is composed of
    | 'interpolations' that will be replaced their corresponding values
    | during runtime.
    |
    */

    'queue_path' => '{laravel_root}/uploads/queue',

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
    | \Imagine\Gd\Imagine, \Imagine\Imagick\Imagine,
    | or \Imagine\Gmagick\Imagine.
    |
    */

    'image_processor' => \Imagine\Gd\Imagine::class,

    /*
    |--------------------------------------------------------------------------
    | Image Quality
    |--------------------------------------------------------------------------
    |
    | Define optionally the quality of the image. It is normalized for all
    | file types to a range from 0 (poor quality, small file) to 100 (best
    | quality, big file). Quality is only applied if you're encoding JPG
    | format since PNG compression is lossless and does not affect image
    | quality. The default value is 90.
    |
    */

    'image_quality' => 90,

    /*
    |--------------------------------------------------------------------------
    | Automatically Orient
    |--------------------------------------------------------------------------
    |
    | Automatically orient images that are positioned incorrectly. This is
    | helpful for mobile uploads.
    |
    */

    'auto_orient' => false,

    /*
    |--------------------------------------------------------------------------
    | Image Color Palette
    |--------------------------------------------------------------------------
    |
    | Set a palette for the image. Useful to change colorspace. Value must be
    | a class that implements the \Imagine\Image\Palette\PaletteInterface
    |
    */

    'color_palette' => null,

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

    /*
    |--------------------------------------------------------------------------
    | Interpolation
    |--------------------------------------------------------------------------
    |
    | Use this to override or provide custom variables for use during the URL
    | interpolation.
    |
    */

    'interpolate' => [],

];