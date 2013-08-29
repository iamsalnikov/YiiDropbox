### YiiDropbox
> This Extension based on [Dropbox client library for PHP](http://www.dropbox-php.com/)

### Install
Download YiiDropbox.php.
Create in your extensions folder YiiDropbox folder.
Move downloaded YiiDropbox.php file to YiiDropbox extension folder.

In app config:
```php
'components'=>array(
    ...

    'dropbox' => array(
        'class' => 'ext.YiiDropbox.YiiDropbox',
        'appKey' => 'YOUR APP KEY',
        'appSecret' => 'YOUR SECRET KEY',
        'root' => 'dropbox' //or 'sandbox'
    ),
    
    ...
);
```

### Usage
```php
  $dropbox = Yii::app()->dropbox;

  //First step. Connect to dropbox
  $request = $dropbox->getRequestToken();
  Yii::app()->session->add('request', $request); //Save this tokens
  $link = $dropbox->getAuthorizeLink('path/to/callback'); //Show this link to user

  /**
   * This code from callback function
   */
  $dropbox->setToken(Yii::app()->session->get('request')); // Set request tokens
  $tokens = $dropbox->getAccessToken(); // get Access tokens
  Yii::app()->session->add('dropbox', $tokens); //save request tokens. It's tokens we can save in db and use

  /**
   * if we get access tokens from database or other storage, we must set tokens by:   * 
   */
  $dropbox->setToken($tokens);

  /**
   * Now we can use API methods
   */
  $dropbox->getAccountInfo();
  $dropbox->getFile('path/to/file');
  $dropbox->putFile('path/to/file', 'path/to/file/on/server');
  ...
```

